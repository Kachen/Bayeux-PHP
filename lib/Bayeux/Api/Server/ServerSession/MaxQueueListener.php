<?php

namespace Bayeux\Api\Server\ServerSession;

use Bayeux\Api\Message;
use Bayeux\Api\Session;
use Bayeux\Api\Server\ServerSession;

/**
 * <p>Listeners objects that implement this interface will be notified when the session queue is full.</p>
 */
interface MaxQueueListener extends ServerSessionListener
{
    /**
     * <p>Callback invoked to notify when the message queue is exceeding the value
     * configured for the transport with the option "maxQueue".</p>
     *
     * @param session the session that will receive the message
     * @param from the session that is sending the messages
     * @param message the message that exceeded the max queue capacity
     * @return true if the message should be added to the session queue
     */
    public function queueMaxed(ServerSession $session, Session $from, Message $message);
}