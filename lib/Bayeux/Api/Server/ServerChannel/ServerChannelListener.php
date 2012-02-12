<?php

namespace Bayeux\Api\Server\ServerChannel;

use Bayeux\Api\Bayeux;

/**
 * <p>Common interface for {@link ServerChannel} listeners.</p>
 * <p>Specific sub-interfaces define what kind of event listeners will be notified.</p>
 */
interface ServerChannelListener extends Bayeux\BayeuxListener
{
}