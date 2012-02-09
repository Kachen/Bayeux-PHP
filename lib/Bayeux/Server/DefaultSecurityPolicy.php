<?php

namespace Bayeux\Server;

use Bayeux\Api\Server\SecurityPolicy;

class DefaultSecurityPolicy implements SecurityPolicy
{
    public function canCreate(BayeuxServer $server, ServerSession $session, $channelId, ServerMessage $message)
    {
        return session!=null && session.isLocalSession() || !ChannelId.isMeta(channelId);
    }

    public function canHandshake(BayeuxServer $server, ServerSession $session, ServerMessage $message)
    {
        return true;
    }

    public function canPublish(BayeuxServer $server, ServerSession $session, ServerChannel $channel, ServerMessage $messsage)
    {
        return $session != null && $session->isHandshook() && !$channel->isMeta();
    }

    public function canSubscribe(BayeuxServer $server, ServerSession $session, ServerChannel $channel, ServerMessage $messsage)
    {
        return $session!=null && $session->isLocalSession() || !$channel->isMeta();
    }

}
