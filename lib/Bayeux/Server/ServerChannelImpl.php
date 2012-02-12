<?php

namespace Bayeux\Server;


use Bayeux\Api\Server\BayeuxServer;
use Bayeux\Api\Server\Authorizer;
use Bayeux\Api\Server\ServerChannel\ServerChannelListener;
use Bayeux\Api\ChannelId;
use Bayeux\Api\Server\ConfigurableServerChannel;
use Bayeux\Api\Server\ServerChannel;
use Bayeux\Api\Session;

class ServerChannelImpl implements ServerChannel//, ConfigurableServerChannel
{
    private $_bayeux;
    private $_id;
    private $_attributes = array();
    private $_subscribers = array();
    private $_listeners = array();
    private $_authorizers = array();
    private $_meta;
    private $_broadcast;
    private $_service;
    private $_initialized;
    private $_lazy;
    private $_persistent;
    private $_sweeperPasses = 0;

    /* ------------------------------------------------------------ */
    public function __construct(BayeuxServerImpl $bayeux, ChannelId $id)
    {
        $this->_bayeux = $bayeux;
        $this->_id = $id;
        $this->_meta = $id->isMeta();
        $this->_service = $id->isService();
        $this->_broadcast = ! $this->isMeta() && ! $this->isService();
        $this->_initialized = 1; //FIXME: deve ser unico
        $this->setPersistent(!$this->_broadcast);
    }

    /* ------------------------------------------------------------ */
    /* wait for initialised call.
     * wait for bayeux max interval for the channel to be initialised,
    * which means waiting for addChild to finish calling bayeux.addChannel,
    * which calls all the listeners.
    *
    */
    public function waitForInitialized()
    {
        try
        {
            if (!$this->_initialized->await(5, TimeUnit.SECONDS)) {
                throw new IllegalStateException("Not Initialized: " . $this);
            }
        }
        catch(InterruptedException $e)
        {
            throw new IllegalStateException("Initizlization interrupted: "+$this);
        }
    }

    /* ------------------------------------------------------------ */
    public function initialized()
    {
        $this->_initialized->countDown();
    }

    /* ------------------------------------------------------------ */
    /**
     * @param session
     * @return true if the subscribe succeeded.
     */
    public function subscribe(ServerSessionImpl $session)
    {
        if (!$session->isHandshook()) {
            return false;
        }

        if (! in_array($session, $this->_subscribers))
        {
            $this->_subscribers[] = $session;
            $session->subscribedTo($this);
            foreach ($this->_listeners as $listener) {
                if ($listener instanceof SubscriptionListener) {
                    $listener->subscribed($session, $this);
                }
            }

            foreach ($this->_bayeux->getListeners() as $listener) {
                if ($listener instanceof BayeuxServer\SubscriptionListener) {
                    $listener->subscribed($session, $this);
                }
            }
        }

        $this->_sweeperPasses = 0;
        return true;
    }

    /* ------------------------------------------------------------ */
    public function unsubscribe(ServerSessionImpl $session)
    {
        $key = array_search($session, $this->_subscribers);
        if ($key === false) {
            return false;
        }

        unset($this->_subscribers[$key]);
        $session->unsubscribedTo($this);
        foreach ($this->_listeners as $listener) {
            if ($listener instanceof SubscriptionListener) {
                $listener->unsubscribed($session, $this);
            }
        }

        foreach ($this->_bayeux->getListeners() as $listener) {
            if ($listener instanceof BayeuxServer\SubscriptionListener) {
                $listener->unsubscribed($session, $this);
            }
        }
        return true;
    }

    /* ------------------------------------------------------------ */
    public function getSubscribers()
    {
        return $this->_subscribers;
    }

    /* ------------------------------------------------------------ */
    public function isBroadcast()
    {
        return $this->_broadcast;
    }

    /* ------------------------------------------------------------ */
    public function isDeepWild()
    {
        return $this->_id->isDeepWild();
    }

    /* ------------------------------------------------------------ */
    public function isLazy()
    {
        return $this->_lazy;
    }

    /* ------------------------------------------------------------ */
    public function isPersistent()
    {
        return $this->_persistent;
    }

    /* ------------------------------------------------------------ */
    public function isWild()
    {
        return $this->_id->isWild();
    }

    /* ------------------------------------------------------------ */
    public function setLazy($lazy)
    {
        $this->_lazy=$lazy;
    }

    /* ------------------------------------------------------------ */
    public function setPersistent($persistent)
    {
        $this->_persistent = $persistent;
    }

    /* ------------------------------------------------------------ */
    public function addListener(ServerChannelListener $listener)
    {
        $this->_listeners[] = $listener;
        $this->_sweeperPasses = 0;
    }

    /* ------------------------------------------------------------ */
    public function removeListener(ServerChannelListener $listener)
    {
        $key = arra_search($listener, $this->_listeners);
        if ($key !== false) {
            unset($this->_listeners[$key]);
            return true;
        }
        return false;
    }

    /* ------------------------------------------------------------ */
    public function getListeners()
    {
        return $this->_listeners;
    }

    /* ------------------------------------------------------------ */
    public function getChannelId()
    {
        return $this->_id;
    }

    /* ------------------------------------------------------------ */
    public function getId()
    {
        return $this->_id->toString();
    }

    /* ------------------------------------------------------------ */
    public function isMeta()
    {
        return $this->_meta;
    }

    /* ------------------------------------------------------------ */
    public function isService()
    {
        return $this->_service;
    }

    public function publish(Session $from = null, $arg1, $id = null) {
        if (! $arg1 instanceof ServerMessage\Mutable) {
            $mutable = $this->_bayeux->newMessage();
            $mutable->setChannel($this->getId());
            if($from != null) {
                $mutable->setClientId($from->getId());
            }
            $mutable->setData($arg1);
            if ($id !== null) {
                throw new \InvalidArgumentException();
            }
            $mutable->setId($id);
        }

        if ($this->isWild()) {
            throw new \Exception('Wild publish');
        }

        $session = $from instanceof ServerSessionImpl ?
            $from
            : $from instanceof LocalSession ?
                $from->getServerSession()
                : null;

        // Do not leak the clientId to other subscribers
        // as we are now "sending" this message
        $mutable->setClientId(null);

        if($this->_bayeux->extendSend($session, null, $mutable)) {
            $this->_bayeux->doPublish($session, $this, $mutable);
        }
    }

    /* ------------------------------------------------------------ */
    protected function doSweep()
    {
        foreach ($this->_subscribers as $session)
        {
            if (!session.isHandshook()) {
                $this->unsubscribe($session);
            }
        }

        if ($this->isPersistent()) {
            return;
        }

        if (count($this->_subscribers > 0) || count($this->_listeners) > 0) {
            return;
        }

        if ($this->isWild() || $this->isDeepWild())
        {
            // Wild, check if has authorizers that can match other channels
            if (count($this->_authorizers)> 0) {
                return;
            }
        }
        else
        {
            // Not wild, then check if it has children
            foreach ($this->_bayeux->getChannels() as $channel) {
                if ($this->_id->isParentOf($channel->getChannelId())) {
                    return;
                }
            }
        }

        if (++$this->_sweeperPasses < 3) {
            return;
        }

        $this->remove();
    }

    /* ------------------------------------------------------------ */
    public function remove()
    {
        foreach ($this->_bayeux->getChannelChildren($this->_id) as $child) {
            $child->remove();
        }

        if ($this->_bayeux->removeServerChannel($this))
        {
            foreach ($this->_subscribers as $subscriber) {
                $subscriber->unsubscribedTo($this);
            }
           $this->_subscribers = array();
        }

        $this->_listeners = array();
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

    /* ------------------------------------------------------------ */
    protected function dump($b, $indent)
    {
        $b .= $this->toString();
        $b .= $this->isLazy() ?" lazy":"";
        $b .= '\n';

        $children = $this->_bayeux->getChannelChildren($this->_id);
        $leaves = count($children) + count($this->_subscribers) + count($this->_listeners);
        $i=0;
        foreach ($children as $child)
        {
            $b .= $indent;
            $b .= " +-";
            $b .= $child->dump($b, $indent . ((++$i==$leaves)?"   ":" | "));
        }
        foreach ($this->_subscribers as $child)
        {
            $b .= $indent;
            $b .= " +-";
            $b .= $child->dump($b, $indent+((++$i==$leaves)?"   ":" | "));
        }
        foreach ($this->_listeners as $child)
        {
            $b .= $indent;
            $b .= " +-";
            $b .= $child;
            $b .= '\n';
        }
    }

    /* ------------------------------------------------------------ */
    public function addAuthorizer(Authorizer $authorizer)
    {
        $this->_authorizers[] = $authorizer;
    }

    /* ------------------------------------------------------------ */
    public function removeAuthorizer(Authorizer $authorizer)
    {
        $this->_authorizers[$authorizer];
    }

    /* ------------------------------------------------------------ */
    public function getAuthorizers()
    {
        return Collections.unmodifiableList(_authorizers);
    }

    /* ------------------------------------------------------------ */
    //@Override
    public function toString()
    {
        return $this->_id->toString();
    }
}