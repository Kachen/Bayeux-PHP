<?php

namespace Bayeux\Api\Server;
/**
* Operations that are to be authorized on a channel
*/
final class Operation {

    /**
     * The operation to create a channel that does not exist
     */
    const CREATE = 0;
    /**
     * The operation to subscribe to a channel to receive messages published to it
     */
    const SUBSCRIBE = 1;
    /**
     * The operation to publish messages to a channel
     */
    const PUBLISH = 2;
}