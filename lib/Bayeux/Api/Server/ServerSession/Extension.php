<?php

namespace Bayeux\Api\Server\ServerSession;

use Bayeux\Api\Server\ServerMessage;
use Bayeux\Api\Server\ServerSession;

/**
 * <p>Listeners objects that implement this interface will be notified when the session queue
 * is being drained to actually deliver the messages.</p>
 */
interface DeQueueListener extends ServerSessionListener
{
    /**
     * <p>Callback invoked to notify that the queue of messages is about to be sent to the
     * remote client.</p>
     * <p>This is the last chance to process the queue and remove duplicates or merge messages.</p>
     * @param session the session whose messages are being sent
     * @param queue the queue of messages to send
     */
    public function deQueue(ServerSession $session, /*Queue<ServerMessage>*/ $queue);
}