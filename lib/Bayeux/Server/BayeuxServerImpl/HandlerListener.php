<?php

namespace Bayeux\Server\BayeuxServerImpl;

/* ------------------------------------------------------------ */
/* ------------------------------------------------------------ */
use Bayeux\Server\BayeuxServerImpl;
use Bayeux\Api\Server\ServerMessage;
use Bayeux\Server\ServerSessionImpl;
use Bayeux\Api\Server\ServerSession;
use Bayeux\Api\Server\ServerChannel\ServerChannelListener;

abstract class HandlerListener implements ServerChannelListener
{
    /**
     * @var Bayeux\Server\BayeuxServerImpl
     */
    protected $_bayeux;

    public function __construct(BayeuxServerImpl $bayeux) {
        $this->_bayeux = $bayeux;
    }

    protected function isSessionUnknown(ServerSession $session)
    {
        return $session == null || $this->getSession($session->getId()) == null;
    }

    public function __get($name) {
        return $this->get($this, $name);
    }

    public function __call($name, $param_arr) {
        return call_user_func_array(array($this->_bayeux, $name), $param_arr);
    }

    public abstract function onMessage(ServerSessionImpl $from = null, ServerMessage\Mutable $message);
}