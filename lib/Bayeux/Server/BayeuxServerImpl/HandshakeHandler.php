<?php

namespace Bayeux\Server\BayeuxServerImpl;


use Bayeux\Api\Message;
use Bayeux\Api\Server\ServerMessage;
use Bayeux\Server\ServerSessionImpl;

class HandshakeHandler extends HandlerListener
{
    public function onMessage(ServerSessionImpl $session = null, ServerMessage\Mutable $message)
    {
        if ($session == null) {
            $session = $this->newServerSession();
        }

        $reply = $this->createReply($message);

        if ($this->_policy != null && !$this->_policy->canHandshake($this->_bayeux, $session, $message))
        {
            $this->error($reply,"403::Handshake denied");
            // The user's SecurityPolicy may have customized the response's advice
            $advice = $reply->getAdvice(true);
            if (!$advice[Message::RECONNECT_FIELD]) {
                $advice[Message::RECONNECT_FIELD] = Message::RECONNECT_NONE_VALUE;
            }
            return;
        }

        $session->handshake();
        $this->_bayeux->addServerSession($session);

        $reply->setSuccessful(true);
        $reply[Message::CLIENT_ID_FIELD] = $session->getId();
        $reply[Message::VERSION_FIELD] = "1.0";
        $reply[Message::MIN_VERSION_FIELD] = "1.0";
        $reply[Message::SUPPORTED_CONNECTION_TYPES_FIELD]  = $this->getAllowedTransports();
    }
}