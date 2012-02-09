<?php

namespace Bayeux\Api\Server\ServerSession;

use Bayeux\Api\Server\ServerSession;

/**
 * <p>Listeners objects that implement this interface will be notified of session removal.</p>
 */
interface RemoveListener extends ServerSessionListener
{
    /**
     * Callback invoked when the session is removed.
     * @param session the removed session
     * @param timeout whether the session has been removed because of a timeout
     */
    public function removed(ServerSession $session, $timeout);
}