<?php

namespace Bayeux\Common\AbstractClientSession;

use Bayeux\Common\IllegalStateException;

use Bayeux\Server\BayeuxServerImpl;

use Bayeux\Common\AbstractClientSession;

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
    private $_id;
    private $_attributes = array();
    private $_subscriptions = array();
    private $_subscriptionCount = 0;
    private $_listeners = array();
    private $_released;

    protected $_localSession;
    protected $_bayeux;
    protected $_session;


    /* ------------------------------------------------------------ */
    protected function __construct(ChannelId $id)
    {
        $this->_id = $id;
    }

    public function init(AbstractClientSession $localSession, BayeuxServerImpl $bayeux, &$session) {
        $this->_localSession = $localSession;
        //$localSession->setSession($this, $session);

        $this->_bayeux = $bayeux;
        $this->_session = $session;
    }

    /* ------------------------------------------------------------ */
    public function getChannelId()
    {
        return $this->_id;
    }

    /* ------------------------------------------------------------ */
    public function addListener(ClientSessionChannelListener $listener)
    {
        $this->throwIfReleased();
        $this->_listeners[] = $listener;
    }

    /* ------------------------------------------------------------ */
    public function removeListener(ClientSessionChannelListener $listener)
    {
        $this->throwIfReleased();
        $key = array_search($listener, $this->_listeners);
        if ($key !== false) {
            unset($this->_listeners[$key]);
        }
    }

    public function getListeners()
    {
        return $this->_listeners;
    }

    /* ------------------------------------------------------------ */
    protected abstract function sendSubscribe();

    /* ------------------------------------------------------------ */
    protected abstract function sendUnSubscribe();

    /* ------------------------------------------------------------ */
    public function subscribe(MessageListener $listener)
    {
        $this->throwIfReleased();
      //  if (! in_array($listener, $this->_subscriptions, true))
      //  {
         //   echo '---------------------- ';
            $this->_subscriptions[] = $listener;
            $count = ++$this->_subscriptionCount; //->incrementAndGet(); FIXME: ATOMIC
            if ($count == 1) {
                $this->sendSubscribe();
            }
       // }
    }

    /* ------------------------------------------------------------ */
    public function unsubscribe(MessageListener $listener = null)
    {
        $this->throwIfReleased();
        if ($listener === null) {
            foreach ($this->_subscriptions as $listener) {
                $this->unsubscribe($listener);
            }
        } else {
            $key = array_search($listener, $this->_subscriptions, true);
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

    public function getSubscribers()
    {
        return $this->_subscriptions;
    }


    public function release()
    {
        if ($this->_released) {
            return false;
        }

        if (empty($this->_subscriptions) && empty($this->_listeners))
        {
            $removed = $this->_localSession->removeChannel($this->getId(), $this);
            $this->_released = $removed;
            return $removed;
        }
        return false;
    }

    public function isReleased()
    {
        return $this->_released;
    }

    /* ------------------------------------------------------------ */
    protected function resetSubscriptions()
    {
        $this->throwIfReleased();
        foreach ($this->_subscriptions as $key => $l)
        {
            unset($this->_subscriptions[$key]); //FIXME: verificar esse logica e mudar
            --$this->_subscriptionCount;
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

    public function isBroadCast() {
        return ! $this->isMeta() && ! $this->isService();
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
            if (listener instanceof ClientSessionChannel\MessageListener) {
                $this->notifyOnMessage($listener, $message);
            }
        }

        foreach ($this->_subscriptions as $listener)
        {
            if ($listener instanceof ClientSessionChannel\MessageListener)
            {
                if ($message->getData() != null) {
                    $this->notifyOnMessage($listener, $message);
                }
            }
        }
    }

    private function notifyOnMessage(MessageListener $listener, Message $message)
    {
        $this->throwIfReleased();
        try
        {
            $listener->onMessage($this, $message);
        }
        catch (\Exception $x)
        {
            throw $x;
            echo "Exception while invoking listener " . $listener . $x;
            //logger.info("Exception while invoking listener " + listener, x);
        }
    }

    public function setAttribute($name, $value)
    {
        $this->throwIfReleased();
        $this->_attributes->setAttribute($name, $value);
    }

    public function getAttribute($name)
    {
        $this->throwIfReleased();
        return $this->_attributes->getAttribute($name);
    }

    public function getAttributeNames()
    {
        $this->throwIfReleased();
        return array_keys($this->_attributes);
    }

    public function removeAttribute($name)
    {
        $this->throwIfReleased();
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

    protected function throwIfReleased()
    {
        if ($this->isReleased()) {
            throw new IllegalStateException("Channel " . $this . " has been released");
        }
    }

    /* ------------------------------------------------------------ */
    //@Override
    public function toString()
    {
        return $this->_id->toString();
    }
}