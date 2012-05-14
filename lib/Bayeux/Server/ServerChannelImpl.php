<?php

namespace Bayeux\Server;


use Bayeux\Api\Server\ServerMessage;

use Bayeux\Api\Server\ServerSession;

use Bayeux\Api\Server\BayeuxServer\SubscriptionListener;

use Bayeux\Api\Server\LocalSession;
use Bayeux\Api\Server\BayeuxServer;
use Bayeux\Api\Server\Authorizer;
use Bayeux\Api\Server\ServerChannel\ServerChannelListener;
use Bayeux\Api\ChannelId;
use Bayeux\Api\Server\ConfigurableServerChannel;
use Bayeux\Api\Server\ServerChannel;
use Bayeux\Api\Session;

class ServerChannelImpl implements ServerChannel {

    //private $_logger;
    private $_bayeux;
    private $_id;
    private $_attributes = array();
    private $_subscribers = array();
    private $_listeners = array();
    private $_authorizers = array();
    private $_initialized = 1;
    private $_sweeperPasses = 0;
    private $_children = array();
    private $_parent;
    private $_lazy;
    private $_persistent;

    public function __construct(BayeuxServerImpl $bayeux, ChannelId $id, ServerChannelImpl $parent = null)
    {
        $this->_bayeux = $bayeux;
        $this->_id = $id;
        $this->_parent = $parent;
        if ($parent != null) {
            $parent->addChild($this);
        }
        $this->_initialized = 1; //FIXME: deve ser unico
        $this->setPersistent(!$this->isBroadcast());
    }

    /**
     * wait for initialised call.
     * wait for bayeux max interval for the channel to be initialised,
     * which means waiting for addChild to finish calling bayeux.addChannel,
     * which calls all the listeners.
     *
     */
    public function waitForInitialized()
    {
        try
        {
            //if (! $this->_initialized->await(5, TimeUnit.SECONDS)) {
            //    throw new IllegalStateException("Not Initialized: " . $this);
            //}
        }
        catch(InterruptedException $e)
        {
            //throw new IllegalStateException("Initizlization interrupted: "+$this);
        }
    }

    public function initialized()
    {
        $this->resetSweeperPasses();
        $this->_initialized = 1;
    }

    public function resetSweeperPasses()
    {
        $this->_sweeperPasses = 0;
    }

    /**
     * @param session
     * @return true if the subscribe succeeded.
     */
    public function subscribe(ServerSessionImpl $session)
    {
        if (! $session->isHandshook()) {
            return false;
        }

        if ($this->isService()) {
            return true;
        }

        if ($this->isMeta()) {
            return false;
        }


        $this->resetSweeperPasses();
        if (! in_array($session, $this->_subscribers))
        {
            $this->_subscribers[] = $session;
            $session->subscribedTo($this);
            foreach ($this->_listeners as $listener) {
                if ($listener instanceof ServerChannel\SubscriptionListener) {
                    $listener->subscribed($session, $this);
                }
            }

            foreach ($this->_bayeux->getListeners() as $listener) {
                if ($listener instanceof BayeuxServer\SubscriptionListener) {
                    $listener->subscribed($session, $this);
                }
            }
        }

        return true;
    }

    private function notifySubscribed(SubscriptionListener $listener, ServerSession $session, ServerChannel $channel) {
        if (! ($listener instanceof SubscriptionListener) || ! ($listener instanceof BayeuxServer\SubscriptionListener)) {
            throw new \InvalidArgumentException();
        }

        try {
            $listener->subscribed($session, $channel);
        } catch (\Exception $x) {
            echo "Exception while invoking listener ";
            //_logger.info("Exception while invoking listener " + listener, x);
        }
    }

    public function unsubscribe(ServerSession $session) {
        if (! $session instanceof ServerSessionImpl) {
            if ($this->isService()) {
                return true;
            }

            if ($this->isMeta()) {
                return false;
            }
        }

        $key = array_search($session, $this->_subscribers);
        if ($key === false) {
            return false;
        }

        unset($this->_subscribers[$key]);
        $session->unsubscribedFrom($this);
        foreach ($this->_listeners as $listener) {
            if ($listener instanceof ServerChannel\SubscriptionListener) {
                $this->notifyUnsubscribed($listener, $session, $this);
            }
        }

        foreach ($this->_bayeux->getListeners() as $listener) {
            if ($listener instanceof BayeuxServer\SubscriptionListener) {
                $this->notifyUnsubscribed($listener, $session, $this);
            }
        }
        return true;
    }

    private function notifyUnsubscribed($listener, ServerSession $session, ServerChannel $channel) {
        //if (! ($listener instanceof SubscriptionListener) && ! ($listener instanceof BayeuxServer\SubscriptionListener)) {
        //    throw new \InvalidArgumentException();
        //}

        try {
            $listener->unsubscribed($session, $channel);

        } catch (\Exception $x) {
            echo ("Exception while invoking listener " . $listener . $x);
        }
    }

    public function getSubscribers() {
        return $this->_subscribers;
    }

    public function isBroadcast() {
        return !$this->isMeta() && !$this->isService();
    }

    public function isDeepWild() {
        return $this->_id->isDeepWild();
    }

    public function isLazy() {
        return $this->_lazy;
    }

    public function isPersistent() {
        return $this->_persistent;
    }

    public function isWild() {
        return $this->_id->isWild();
    }

    public function setLazy($lazy) {
        $this->_lazy = $lazy;
    }

    public function setPersistent($persistent) {
        $this->resetSweeperPasses();
        $this->_persistent = $persistent;
    }

    public function addListener(ServerChannelListener $listener) {
        $this->resetSweeperPasses();
        $this->_listeners[] = $listener;
    }

    public function removeListener(ServerChannelListener $listener) {
        $key = array_search($listener, $this->_listeners);
        if ($key !== false) {
            unset($this->_listeners[$key]);
        }
    }

    public function getListeners() {
        return $this->_listeners;
    }

    public function getChannelId() {
        return $this->_id;
    }

    public function getId() {
        return $this->_id->toString();
    }

    public function isMeta() {
        return $this->_id->isMeta();
    }

    public function isService() {
        return $this->_id->isService();
    }

    public function publish(Session $from = null, $arg1, $id = null) {
        if (! ($arg1 instanceof ServerMessage\Mutable) ) {
            $mutable = $this->_bayeux->newMessage();
            $mutable->setChannel($this->getId());
            if($from != null) {
                $mutable->setClientId($from->getId());
            }
            $mutable->setData($arg1);
            $mutable->setId($id);
        } else {
            $mutable = $arg1;
        }

        if ($this->isWild()) {
            throw new \Exception('Wild publish');
        }

        if ($from instanceof ServerSessionImpl) {
            $session = $from;

        } else if ($from instanceof LocalSession ) {
            $session = $from->getServerSession();

        } else {
            $session = null;
        }

//        $session = $from instanceof ServerSessionImpl ? $from :$from instanceof LocalSession ? $from->getServerSession()
  //              : null;

        // Do not leak the clientId to other subscribers
        // as we are now "sending" this message
        $mutable->setClientId(null);

        if($this->_bayeux->extendSend($session, null, $mutable)) {
            $this->_bayeux->doPublish($session, $this, $mutable);
        }
    }

    public function sweep() {
        foreach ($this->_subscribers as $session)
        {
            if (! $session->isHandshook()) {
                $this->unsubscribe($session);
            }
        }

        if ($this->isPersistent()) {
            return;
        }


        if (count($this->_subscribers) > 0) {
            return;
        }

        if (count($this->_authorizers) > 0) {
            return;
        }

        if (! $this->isWild())
        {
            // Wild, check if has authorizers that can match other channels
            if (count($this->_children) > 0) {
                return;
            }
        }

        foreach ($this->_listeners as $listener ) {
            if (! ($listener instanceof ServerChannelListener\Weak)) {
                return;
            }
        }

        if (++$this->_sweeperPasses < 3) {
            return;
        }

        $this->remove();
    }

    public function remove() {
        if ($this->_parent != null) {
            $this->_parent->removeChild($this);
        }

        foreach ($this->_children as $child) {
            $child->remove();
        }

        if ($this->_bayeux->removeServerChannel($this))
        {
            foreach ($this->_subscribers as $subscriber) {
                $subscriber->unsubscribedFrom($this);
            }
            $this->_subscribers = array();
        }

        $this->_listeners = array();
    }

    public function setAttribute($name, $value) {
        $this->_attributes->setAttribute($name, $value);
    }

    public function getAttribute($name) {
        return $this->_attributes->getAttribute($name);
    }

    public function getAttributeNames() {
        return array_keys($this->_attributes);
    }

    public function removeAttribute($name) {
        $old = $this->getAttribute($name);
        unset($this->_attributes[$name]);
        return $old;
    }

    private function addChild(ServerChannelImpl $child) {
        $this->_children[] = $child;
    }

    private function removeChild(ServerChannelImpl $child) {
        $key = array_search($child, $this->_children);
        if ($key !== false) {
            unset($this->_children[$key]);
        }
    }

    protected function dump($b, $indent) {
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

    public function addAuthorizer(Authorizer $authorizer) {
        $this->_authorizers[] = $authorizer;
    }

    public function removeAuthorizer(Authorizer $authorizer) {
        $key = array_search($authorizer, $this->_authorizers);
        if ($key !== false)  {
            unset($this->_authorizers[$key]);
        }
    }

    public function getAuthorizers() {
        return $this->_authorizers;
    }

    public function toString() {
        return $this->_id->toString();
    }
}