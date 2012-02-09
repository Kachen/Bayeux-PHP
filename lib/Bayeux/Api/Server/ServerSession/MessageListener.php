<?php

namespace Bayeux\Api\Server\ServerSession;

use Bayeux\Api\Server\ServerMessage;
use Bayeux\Api\Server\ServerSession;

/**
 * <p>Listeners objects that implement this interface will be notified of message arrival.</p>
 */
interface MessageListener extends ServerSessionListener
{
    /**
     * <p>Callback invoked when a message is received.</p>
     * <p>Implementors can decide to return false to signal that the message should not be
     * processed, meaning that other listeners will not be notified and that the message
     * will be discarded.</p>
     * @param to the session that received the message
     * @param from the session that sent the message
     * @param message the message sent
     * @return whether the processing of the message should continue
     */
    public function onMessage(ServerSession $to, ServerSession $from, ServerMessage $message);
}