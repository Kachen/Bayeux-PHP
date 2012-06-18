<?php

namespace Bayeux\Server\BayeuxServerImpl;

use Bayeux\Api\Message;

use Bayeux\Api\Server\ServerMessage;
use Bayeux\Server\ServerSessionImpl;

class ConnectHandler extends HandlerListener
{
    public function onMessage(ServerSessionImpl $session, ServerMessage\Mutable $message)
    {
        $reply = $message->getAssociated();
        if ($this->isSessionUnknown($session))
        {
            $this->unknownSession($reply);
            return;
        }

        $session->connect();

        // Handle incoming advice
        $adviceIn = $message->getAdvice();
        if ($adviceIn != null)
        {
            if (isset($adviceIn["timeout"])) {
                $timeout = $adviceIn["timeout"];
            } else {
                $timeout = -1;
            }

            if (isset($adviceIn["interval"])) {
                $interval = $adviceIn["interval"];
            } else {
                $interval = -1;
            }

            $session->updateTransientTimeout($timeout);
            $session->updateTransientInterval($interval);
            // Force the server to send the advice, as the client may
            // have forgotten it (for example because of a reload)
            $session->reAdvise();
        }
        else
        {
            $session->updateTransientTimeout(-1);
            $session->updateTransientInterval(-1);
        }

        // Send advice
        $adviceOut = $session->takeAdvice();
        if ($adviceOut != null) {
            $reply[Message::ADVICE_FIELD] = $adviceOut;
        }

        $reply->setSuccessful(true);
    }
}
