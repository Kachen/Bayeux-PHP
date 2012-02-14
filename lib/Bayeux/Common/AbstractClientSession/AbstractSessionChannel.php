<?php

namespace Bayeux\Common\AbstractClientSession;

use Bayeux\Api\Client\ClientSessionChannel\ClientSessionChannelListener;
use Bayeux\Api\Message;
use Bayeux\Api\Client\ClientSessionChannel\MessageListener;
use Bayeux\Api\ChannelId;
use Bayeux\Api\Client\ClientSessionChannel;

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
        $this->_subscriptionCount;// = new AtomicInteger();
        //$this->logger = Log::getLogger($this->getClass()->getName());
        $this->_subscriptionCount;// = new AtomicInteger();
        $this->_id = $id;
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
            $count = ++$this->_subscriptionCount; //->incrementAndGet(); FIXME: ATOMIC
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
            $key = array_search($listener, $this->_subscriptions);
            if ($key !== false)
            {
                unset($this->_subscriptions[$key]);
                $count = --$this->_subscriptionCount;
                if ($count == 0) {
                    $this->sendUnSubscribe();
                }
            }
        }
    }

    /* ------------------------------------------------------------ */
    protected function resetSubscriptions()
    {
        foreach ($this->_subscriptions as $key => $l)
        {
            unset($this->_subscriptions[$key]); //FIXME: verificar esse logica e mudar
            $this->_subscriptionCount--;
        }
    }

    /* ------------------------------------------------------------ */
    public function getId()
    {
        return $this->_id->toString();
    }

    /* ------------------------------------------------------------ */
    public function isDeepWild()
    {
        return $this->_id->isDeepWild();
    }

    /* ------------------------------------------------------------ */
    public function isMeta()
    {
        return $this->_id->isMeta();
    }

    /* ------------------------------------------------------------ */
    public function isService()
    {
        return $this->_id->isService();
    }

    /* ------------------------------------------------------------ */
    public function isWild()
    {
        return $this->_id->isWild();
    }

    public function notifyMessageListeners(Message $message)
    {
        foreach ($this->_listeners as $listener)
        {
            if (listener instanceof ClientSessionChannel\MessageListener)
            {
                try
                {
                    $listener->onMessage($this, $message);
                }
                catch (\Exception $x)
                {
                    $this->logger->info($x);
                }
            }
        }
        foreach ($this->_subscriptions as $listener)
        {
            if ($listener instanceof ClientSessionChannel\MessageListener)
            {
                if ($message->getData() != null)
                {
                    try
                    {
                        $listener->onMessage($this, $message);
                    }
                    catch (\Exception $x)
                    {
                        $this->logger->info($x);
                    }
                }
            }
        }
    }

    public function setAttribute($name, $value)
    {
        $this->_attributes->setAttribute($name, $value);
    }

    public function getAttribute($name)
    {
        return $this->_attributes->getAttribute($name);
    }

    public function getAttributeNames()
    {
        return array_keys($this->_attributes);
    }

    public function removeAttribute($name)
    {
        $old = $this->getAttribute($name);
        unset($this->_attributes[$name]);
        return $old;
    }

    protected function dump($b, $indent)
    {
        $b .= $this->toString();
        $b .= '\n';

        foreach ($this->_listeners as $child)
        {
            $b .= $indent;
            $b .= " +-";
            $b .= $child;
            $b .= '\n';
        }
        foreach ($this->_subscriptions as $child)
        {
            $b .= $indent;
            $b .= " +-";
            $b .= $child;
            $b .= '\n';
        }
        return $b;
    }

    /* ------------------------------------------------------------ */
    //@Override
    public function toString()
    {
        return $this->_id->toString();
    }
}