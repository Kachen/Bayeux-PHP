<?php

namespace Bayeux\Api\Server\ServerChannel;

use Bayeux\Api\Server\ConfigurableServerChannel;

/**
 * A listener interface by means of which listeners can atomically
 * set the initial configuration of a channel.
 */
interface Initializer extends ConfigurableServerChannel\Initializer
{
}