<?php

namespace Bayeux\Common;

use Bayeux\Api\Client\ClientSession\Extension;

use Bayeux\Common\AbstractClientSession\AbstractSessionChannel;
use Bayeux\Common\AbstractClientSession\MarkableReference;
use Bayeux\Api\Client\ClientSession;
use Bayeux\Api\ChannelId;
use Bayeux\Api\Message;

/**
 * <p>Partial implementation of {@link ClientSession}.</p>
 * <p>It handles extensions and batching, and provides utility methods to be used by subclasses.</p>
 */
abstract class AbstractClientSession implements ClientSession
{
    //protected static $logger;
    private $_idGen = 0;
    private $_extensions = array();
    private $_attributes = array();
    private $_channels = array();
    private $_batch = 0;


    /* ------------------------------------------------------------ */
    protected function __construct()
    {
    }

    /* ------------------------------------------------------------ */
    public function newMessageId()
    {
        return ++$this->_idGen;
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
                if (! $extension->send($this, $message)) {
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

    protected abstract function newChannelId($channelId);

    /* ------------------------------------------------------------ */
    protected abstract function newChannel(ChannelId $channelId = null);

    /* ------------------------------------------------------------ */
    public function getChannel($channelId)
    {
        if (isset($this->_channels[$channelId])) {
            $channel = $this->_channels[$channelId];
        } else {
            $channel = null;
        }

        if ($channel === null)
        {
            $id = $this->newChannelId($channelId);
            $new_channel = $this->newChannel($id);
            if (empty($this->_channels[$channelId])) {
                $this->_channels[$channelId] = $new_channel;
            }

            if ($channel == null) {
                $channel = $new_channel;
            }
        }
        return $channel;
    }

    /* ------------------------------------------------------------ */
    public function getChannels()
    {
        $channels = &$this->_channels;
        return $channels;
    }

    public function removeChannel($id, AbstractSessionChannel $channel) {
        if (! is_string($id)) {
            throw new \InvalidArgumentException();
        }
        if (isset($this->_channels[$id])) {
            if ($this->_channels[$id] === $channel) {
                unset($this->_channels[$id]);
                return true;
            }
        }
        return false;
    }

    /* ------------------------------------------------------------ */
    public function startBatch()
    {
        ++$this->_batch;
    }

    /* ------------------------------------------------------------ */
    protected abstract function sendBatch();

    /* ------------------------------------------------------------ */
    public function endBatch()
    {
        if (--$this->_batch == 0)
        {
            $this->sendBatch();
            return true;
        }
        return false;
    }

    /* ------------------------------------------------------------ */
    public function batch($batch = true)
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
        return $this->_batch > 0;
    }

    /* ------------------------------------------------------------ */
    public function getAttribute($name)
    {
        if (isset($this->_attributes[$name])) {
            return $this->_attributes[$name];
        }
        return null;
    }

    /* ------------------------------------------------------------ */
    public function getAttributeNames()
    {
        return array_keys($this->_attributes);
    }

    /* ------------------------------------------------------------ */
    public function removeAttribute($name)
    {
        if (! isset($this->_attributes[$name])) {
            return null;
        }

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

        $channelRef = $this->getReleasableChannel($id);
        $channel = $channelRef->getReference();
        $channel->notifyMessageListeners($message);
        if ($channelRef->isMarked()) {
            $channel->release();
        }

        $channelId = $channel->getChannelId();
        foreach ($channelId->getWilds() as $wildChannelName )
        {
            $wildChannelRef = $this->getReleasableChannel($wildChannelName);
            $wildChannel = $wildChannelRef->getReference();
            $wildChannel->notifyMessageListeners($message);
            if ($wildChannelRef->isMarked()) {
                $wildChannel->release();
            }
        }
    }

    private function getReleasableChannel($id)
    {
        // Use getChannels().get(channelName) instead of getChannel(channelName)
        // to avoid to cache channels that can be released immediately.

        if (ChannelId::staticIsMeta($id)) {
            $channel = $this->getChannel($id);
        } else {
            $channels = $this->getChannels();
            if (empty($channels[$id])) {
                $channel = null;
            } else {
                $channel = $channels[$id];
            }
        }

        if ($channel != null) {
            return new MarkableReference($channel, false);
        }
        return new MarkableReference($this->newChannel($this->newChannelId($id)), true);
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

