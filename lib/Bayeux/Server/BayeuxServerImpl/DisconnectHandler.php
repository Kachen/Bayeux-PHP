<?php

namespace Bayeux\Server\BayeuxServerImpl;

use Bayeux\Api\Server\ServerMessage;
use Bayeux\Server\ServerSessionImpl;

class DisconnectHandler extends HandlerListener
{
    public function onMessage(ServerSessionImpl $session, ServerMessage\Mutable $message) {
        $reply = $this->createReply($message);
        if ($this->isSessionUnknown($session))
        {
            $this->unknownSession($reply);
            return;
        }

        $this->removeServerSession($session,false);
        $session->flush();

        $reply->setSuccessful(true);
    }
}