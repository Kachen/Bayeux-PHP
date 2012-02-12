<?php

namespace Bayeux\Api\Client;

use Bayeux\Api\Bayeux\BayeuxListener;

/**
 * <p>Represents a listener on a {@link ClientSessionChannel}.</p>
 * <p>Sub-interfaces specify the exact semantic of the listener.</p>
 */
interface ClientSessionChannelListener extends BayeuxListener
{
}