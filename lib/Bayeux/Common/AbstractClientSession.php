<?php

namespace Bayeux\Common;

use Bayeux\Api\Bayeux\Client\ClientSession;

/**
 * <p>Partial implementation of {@link ClientSession}.</p>
 * <p>It handles extensions and batching, and provides utility methods to be used by subclasses.</p>
 */
abstract class AbstractClientSession implements ClientSession
{
    private $_extensions = array();
    private $_attributes = array();
    private $_channels = array();
    private $_batch;
    private $_idGen;

    /* ------------------------------------------------------------ */
    protected function __construct()
    {
        $this->_batch = new AtomicInteger();
        $this->_idGen = new AtomicInteger(0);
    }

    /* ------------------------------------------------------------ */
    protected function newMessageId()
    {
        return $this->_idGen->incrementAndGet();
    }

    /* ------------------------------------------------------------ */
    public function addExtension(Extension $extension)
    {
        $this->_extensions.add(extension);
    }

    /* ------------------------------------------------------------ */
    public function removeExtension(Extension $extension)
    {
        $this->_extensions.remove(extension);
    }

    /* ------------------------------------------------------------ */
    protected function extendSend(Message\Mutable $message)
    {
        if ($message->isMeta())
        {
            foreach ($this->_extensions as $extension) {
                if (!$extension->sendMeta($this, $message)) {
                    return false;
                }
            }
        }
        else
        {
            foreach ($this->_extensions as $extension) {
                if (!$extension->send($this, $message)) {
                    return false;
                }
            }
        }
        return true;
    }

    /* ------------------------------------------------------------ */
    protected function extendRcv(Message\Mutable $message)
    {
        if ($message->isMeta())
        {
            foreach ($this->_extensions as $extension) {
                if (! $extension->rcvMeta($this, $message)) {
                    return false;
                }
            }
        }
        else
        {
            foreach ($this->_extensions as $extension) {
                if (!$extension->rcv($this, $message)) {
                    return false;
                }
            }
        }
        return true;
    }

    /* ------------------------------------------------------------ */
    protected abstract function newChannelId($channelId);

    /* ------------------------------------------------------------ */
    protected abstract function newChannel(ChannelId $channelId);

    /* ------------------------------------------------------------ */
    public function getChannel($channelId)
    {
        $channel = $this->_channels[$channelId];
        if ($channel==null)
        {
            $id = $this->newChannelId($channelId);
            $new_channel=$this->newChannel($id);
            $channel=$this->_channels->putIfAbsent($channelId, $new_channel);
            if ($channel==null) {
                $channel=$new_channel;
            }
        }
        return $channel;
    }

    /* ------------------------------------------------------------ */
    protected function getChannels()
    {
        return $this->_channels;
    }

    /* ------------------------------------------------------------ */
    public function startBatch()
    {
        $this->_batch->ncrementAndGet();
    }

    /* ------------------------------------------------------------ */
    protected abstract function sendBatch();

    /* ------------------------------------------------------------ */
    public function endBatch()
    {
        if ($this->_batch->decrementAndGet()==0)
        {
            $this->sendBatch();
            return true;
        }
        return false;
    }

    /* ------------------------------------------------------------ */
    public function batch(Runnable $batch)
    {
        $this->startBatch();
        try
        {
            $batch->run();
        }
        catch(\Exception $e)
        {
            $this->endBatch();
        }
    }

    /* ------------------------------------------------------------ */
    protected function isBatching()
    {
        return $this->_batch.get() > 0;
    }

    /* ------------------------------------------------------------ */
    public function getAttribute($name)
    {
        return $this->_attributes[$name];
    }

    /* ------------------------------------------------------------ */
    public function getAttributeNames()
    {
        return array_keys($this->_attributes);
    }

    /* ------------------------------------------------------------ */
    public function removeAttribute($name)
    {
        $old = $this->_attributes[$name];
        unset($this->_attributes[$name]);
        return $old;
    }

    /* ------------------------------------------------------------ */
    public function setAttribute($name, $value)
    {
        $this->_attributes[$name] = $value;
    }

    /* ------------------------------------------------------------ */
    protected function resetSubscriptions()
    {
        foreach ($this->_channels as $ch) {
            $ch->resetSubscriptions();
        }
    }

    /* ------------------------------------------------------------ */
    /**
     * <p>Receives a message (from the server) and process it.</p>
     * <p>Processing the message involves calling the receive {@link Extension extensions}
     * and the channel {@link ClientSessionChannel.ClientSessionChannelListener listeners}.</p>
     * @param message the message received.
     * @param mutable the mutable version of the message received
     */
    public function receive(Message\Mutable $message)
    {
        $id = $message->getChannel();
        if ($id == null) {
            throw new IllegalArgumentException("Bayeux messages must have a channel, " . $message);
        }

        if (!$this->extendRcv($message)) {
            return;
        }

        $channel = $this->getChannel($id);
        $channelId = $channel->getChannelId();

        $channel->notifyMessageListeners($message);

        foreach ($channelId->getWilds() as $channelPattern)
        {
            $channelIdPattern = $this->newChannelId($channelPattern);
            if ($channelIdPattern->matches($channelId))
            {
                $wildChannel = $this->getChannel($channelPattern);
                $wildChannel->notifyMessageListeners($message);
            }
        }
    }

    /* ------------------------------------------------------------ */
    public function dump($b, $indent)
    {
        $b .= $this->toString();
        $b .= '\n';

        $leaves = $this->_channels.size();
        $i=0;
        foreach ($this->_channels as $child)
        {
            $b .= $indent;
            $b = " +-";
            $b .= $child->dump($b, $indent . ((++$i==$leaves)?"   ":" | "));
        }
        return $b;
    }
}

/* ------------------------------------------------------------ */
/**
 * <p>A channel scoped to a {@link ClientSession}.</p>
 */
abstract class AbstractSessionChannel implements ClientSessionChannel
{
    protected $logger;
    private $_id;
    private $_attributes = array();
    private $_subscriptions = array();
    private $_subscriptionCount;
    private $_listeners = array();

    /* ------------------------------------------------------------ */
    protected function __construct(ChannelId $id)
    {
        $this->_subscriptionCount = new AtomicInteger();
        $this->logger = Log::getLogger($this->getClass()->getName());
        $this->_subscriptionCount = new AtomicInteger();
        $this->_id=$id;
    }

    /* ------------------------------------------------------------ */
    public function getChannelId()
    {
        return $this->_id;
    }

    /* ------------------------------------------------------------ */
    public function addListener(ClientSessionChannelListener $listener)
    {
        $this->_listeners[] = $listener;
    }

    /* ------------------------------------------------------------ */
    public function removeListener(ClientSessionChannelListener $listener)
    {
        $key = array_search($listener, $this->_listeners);
        if ($key !== false) {
            unset($this->_listeners[$key]);
        }
    }

    /* ------------------------------------------------------------ */
    protected abstract function sendSubscribe();

    /* ------------------------------------------------------------ */
    protected abstract function sendUnSubscribe();

    /* ------------------------------------------------------------ */
    public function subscribe(MessageListener $listener)
    {
        $added = $this->_subscriptions[] = $listener;
        if ($added)
        {
            $count = $this->_subscriptionCount->incrementAndGet();
            if ($count == 1) {
                $this->sendSubscribe();
            }
        }
    }

    /* ------------------------------------------------------------ */
    public function unsubscribe(MessageListener $listener = null)
    {
        if ($listener === null) {
            foreach ($this->_subscriptions as $listener) {
                $this->unsubscribe($listener);
            }
        } else {
            $removed = $this->_subscriptions.remove(listener);
            if ($removed)
            {
                $count = $this->_subscriptionCount->decrementAndGet();
                if (count == 0) {
                    $this->sendUnSubscribe();
                }
            }
        }
    }

    /* ------------------------------------------------------------ */
    protected function resetSubscriptions()
    {
        for (MessageListener l : _subscriptions)
        {
            if (_subscriptions.remove(l))
            _subscriptionCount.decrementAndGet();
        }
    }

    /* ------------------------------------------------------------ */
    public function getId()
    {
        return _id.toString();
    }

    /* ------------------------------------------------------------ */
    public function isDeepWild()
    {
        return _id.isDeepWild();
    }

    /* ------------------------------------------------------------ */
    public function isMeta()
    {
        return _id.isMeta();
    }

    /* ------------------------------------------------------------ */
    public function isService()
    {
        return _id.isService();
    }

    /* ------------------------------------------------------------ */
    public function isWild()
    {
        return _id.isWild();
    }

    protected function notifyMessageListeners(Message message)
    {
        for (ClientSessionChannelListener listener : _listeners)
        {
            if (listener instanceof ClientSessionChannel.MessageListener)
            {
                try
                {
                    ((MessageListener)listener).onMessage(this, message);
                }
                catch (Exception x)
                {
                    logger.info(x);
                }
            }
        }
        for (ClientSessionChannelListener listener : _subscriptions)
        {
            if (listener instanceof ClientSessionChannel.MessageListener)
            {
                if (message.getData() != null)
                {
                    try
                    {
                        ((MessageListener)listener).onMessage(this, message);
                    }
                    catch (Exception x)
                    {
                        logger.info(x);
                    }
                }
            }
        }
    }

    public function setAttribute(String name, Object value)
    {
        _attributes.setAttribute(name, value);
    }

    public function getAttribute(String name)
    {
        return _attributes.getAttribute(name);
    }

    public function getAttributeNames()
    {
        return _attributes.keySet();
    }

    public function removeAttribute(String name)
    {
        Object old = getAttribute(name);
        _attributes.removeAttribute(name);
        return old;
    }

    protected function dump(StringBuilder b,String indent)
    {
        b.append(toString());
        b.append('\n');

        for (ClientSessionChannelListener child : _listeners)
        {
            b.append(indent);
            b.append(" +-");
            b.append(child);
            b.append('\n');
        }
        for (MessageListener child : _subscriptions)
        {
            b.append(indent);
            b.append(" +-");
            b.append(child);
            b.append('\n');
        }
    }

    /* ------------------------------------------------------------ */
    //@Override
    public function toString()
    {
        return _id.toString();
    }
}
