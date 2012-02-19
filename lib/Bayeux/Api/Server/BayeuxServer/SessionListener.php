<?php

namespace Bayeux\Api\Server\BayeuxServer;

use Bayeux\Api\Server\ServerSession;

/**
 * <p>Specialized listener for {@link ServerSession} events.</p>
 * <p>This listener is called when a {@link ServerSession} is added
 * or removed from a {@link BayeuxServer}.</p>
 */
interface SessionListener extends BayeuxServerListener
{
    /**
     * Callback invoked when a {@link ServerSession} has been added to a {@link BayeuxServer} object.
     * @param session the session that has been added
     */
    public function sessionAdded(ServerSession $session);

    /**
     * Callback invoked when a {@link ServerSession} has been removed from a {@link BayeuxServer} object.
     * @param session the session that has been removed
     * @param timedout whether the session has been removed for a timeout or not
     */
    public function sessionRemoved(ServerSession $session, $timedout);
}