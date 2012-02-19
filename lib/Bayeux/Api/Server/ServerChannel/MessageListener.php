<?php

namespace Bayeux\Api\Server\ServerChannel;

use Bayeux\Api\Server\ServerChannel\ServerChannelListener;
use Bayeux\Api\Server\ServerSession;
use Bayeux\Api\Server\ServerChannel;
use Bayeux\Api\Server\ServerMessage;
use Bayeux\Api\Bayeux\Bayeux;

/**
 * <p>Listeners objects that implement this interface will be notified of message publish.</p>
 */
interface MessageListener extends ServerChannelListener
{
    /**
     * <p>Callback invoked when a message is being published.</p>
     * <p>Implementors can decide to return false to signal that the message should not be
     * published.</p>
     * @param from the session that publishes the message
     * @param channel the channel the message is published to
     * @param message the message to be published
     * @return whether the message should be published or not
     */
    public function onMessage(ServerSession $from, ServerChannel $channel, ServerMessage\Mutable $message);
}