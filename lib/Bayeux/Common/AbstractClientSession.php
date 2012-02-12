<?php

namespace Bayeux\Common;

use Bayeux\Api\Client\ClientSession;
use Bayeux\Api\ChannelId;
use Bayeux\Api\Message;
use Bayeux\Api\Cliente\ClientSession\Extension;

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
        $this->_extensions[] = $extension;
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
    protected abstract function newChannel(ChannelId $channelId = null);

    /* ------------------------------------------------------------ */
    public function getChannel($channelId)
    {
        $channel = $this->_channels[$channelId];
        if ($channel==null)
        {
            $id = $this->newChannelId($channelId);
            $new_channel=$this->newChannel($id);
            $channel=$this->_channels->putIfAbsent($channelId, $new_channel);
            if ($channel == null) {
                $channel = $new_channel;
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
        if ($this->_batch->decrementAndGet() == 0)
        {
            $this->sendBatch();
            return true;
        }
        return false;
    }

    /* ------------------------------------------------------------ */
    public function batch($batch)
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
            throw new \Exception("Bayeux messages must have a channel, " . $message);
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

