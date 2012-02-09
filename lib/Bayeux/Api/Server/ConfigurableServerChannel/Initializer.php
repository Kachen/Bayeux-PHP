<?php

namespace Bayeux\Api\Server\ConfigurableServerChannel;

use Bayeux\Api\Server\ConfigurableServerChannel;

/**
 * A listener interface by means of which listeners can atomically
 * set the initial configuration of a channel.
 */
interface Initializer
{
    /**
     * Callback invoked when a channel is created and needs to be configured
     * @param channel the channel to configure
     */
    public function configureChannel(ConfigurableServerChannel $channel);
}