<?php

namespace Bayeux\Server\BayeuxServerImpl;

use Bayeux\Api\Server\ServerMessage;
use Bayeux\Server\ServerSessionImpl;


class UnsubscribeHandler extends HandlerListener
{
    public function onMessage(ServerSessionImpl $from, ServerMessage\Mutable $message)
    {
        $reply=$this->createReply(message);
        if ($this->isSessionUnknown($from))
        {
            $this->unknownSession($reply);
            return;
        }

        $subscribe_id= $message[Message::SUBSCRIPTION_FIELD];
        $reply[Message::SUBSCRIPTION_FIELD] =  $subscribe_id;
        if ($subscribe_id==null)
        $this->error(reply,"400::channel missing");
        else
        {
            $reply[Message::SUBSCRIPTION_FIELD] = $subscribe_id;

            $channel = $this->getChannel($subscribe_id);
            if (channel==null)
            error(reply,"400::channel missing");
            else
            {
                if (from.isLocalSession() || !channel.isMeta() && !channel.isService())
                channel.unsubscribe(from);
                reply.setSuccessful(true);
            }
        }
    }
}