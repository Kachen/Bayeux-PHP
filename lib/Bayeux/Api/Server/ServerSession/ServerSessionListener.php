<?php

namespace Bayeux\Api\Server\ServerSession;

use Bayeux\Api\Bayeux;

/**
 * <p>Common interface for {@link ServerSession} listeners.</p>
 * <p>Specific sub-interfaces define what kind of event listeners will be notified.</p>
 */
interface ServerSessionListener extends Bayeux\BayeuxListener
{
}