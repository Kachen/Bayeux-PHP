<?php

namespace Bayeux\Api\Server\ServerChannel;

use Bayeux\Api\Server\ServerSession;
use Bayeux\Api\Server\ServerChannel;

/**
 * <p>Listeners object that implement this interface will be notified of subscription events.</p>
 */
interface SubscriptionListener extends ServerChannelListener
{
    /**
     * Callback invoked when the given {@link ServerSession} subscribes to the given {@link ServerChannel}.
     * @param session the session that subscribes
     * @param channel the channel the session subscribes to
     */
    public function subscribed(ServerSession $session, ServerChannel $channel);

    /**
     * Callback invoked when the given {@link ServerSession} unsubscribes from the given {@link ServerChannel}.
     * @param session the session that unsubscribes
     * @param channel the channel the session unsubscribes from
     */
    public function unsubscribed(ServerSession $session, ServerChannel $channel);
}