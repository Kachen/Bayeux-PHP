<?php

namespace Bayeux\Api\Bayeux\Client\ClientSessionChannel;

use Bayeux\Api\Bayeux\Client\ClientSessionChannel;
use Bayeux\Api\Bayeux\Message;

/**
 * A listener for messages on a {@link ClientSessionChannel}.
 */
interface MessageListener extends ClientSessionChannelListener
{
    /**
     * Callback invoked when a message is received on the given {@code channel}.
     * @param channel the channel that received the message
     * @param message the message received
     */
    public function onMessage(ClientSessionChannel $channel, Message $message);
}