<?php

namespace Bayeux\Api\Server\BayeuxServer;

use Bayeux\Api\Server\ServerSession;
use Bayeux\Api\Server\ServerChannel;

/**
 * <p>Specialized listener for {@link ServerChannel} subscription events.</p>
 * <p>This listener is called when a subscribe message or an unsubscribe message
 * occurs for any channel known to the {@link BayeuxServer}.</p>
 * <p>This interface the correspondent of the {@link ServerChannel.SubscriptionListener}
 * interface, but it is invoked for any session and any channel known to the
 * {@link BayeuxServer}.</p>
 */
interface SubscriptionListener extends BayeuxServerListener
{
    /**
     * Callback invoked when a {@link ServerSession} subscribes to a {@link ServerChannel}.
     * @param session the session that subscribes
     * @param channel the channel to subscribe to
     */
    public function subscribed(ServerSession $session, ServerChannel $channel);

    /**
     * Callback invoked when a {@link ServerSession} unsubscribes from a {@link ServerChannel}.
     * @param session the session that unsubscribes
     * @param channel the channel to unsubscribe from
     */
    public function unsubscribed(ServerSession $session, ServerChannel $channel);
}