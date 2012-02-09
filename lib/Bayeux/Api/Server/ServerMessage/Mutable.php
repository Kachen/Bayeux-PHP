<?php

namespace Bayeux\Api\Server\ServerMessage;

use Bayeux\Api\Bayeux\Message;
use Bayeux\Api\Server\ServerMessage;

/**
 * The mutable version of a {@link ServerMessage}
 */
interface Mutable extends ServerMessage, Message\Mutable
{
    /**
     * @param message the message associated with this message
     */
    public function setAssociated(Mutable $message);

    /**
     * A lazy message does not provoke immediately delivery to the client
     * but it will be delivered at first occasion or after a timeout expires
     * @param lazy whether the message is lazy
     */
    public function setLazy($lazy);
}