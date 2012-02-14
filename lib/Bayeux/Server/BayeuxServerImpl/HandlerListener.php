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
    protected static $_bayeux;

    protected function isSessionUnknown(ServerSession $session)
    {
        return $session == null || $this->getSession($session->getId()) == null;
    }

    public static function setBayeuxServerImpl(BayeuxServerImpl $bayeux) {
        self::$_bayeux = $bayeux;
    }


    public function __get($name) {
        return self::$_bayeux->get($this, $name);
    }

    public function __call($name, $param_arr) {
        return call_user_func_array(array(self::$_bayeux, $name), $param_arr);
    }


    public abstract function onMessage(ServerSessionImpl $from, ServerMessage\Mutable $message);
}