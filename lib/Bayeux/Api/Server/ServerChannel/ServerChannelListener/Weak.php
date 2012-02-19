<?php

namespace Bayeux\Api\Server\ServerChannel\ServerChannelListener;


use Bayeux\Api\Server\ServerChannel\ServerChannelListener;

/**
 * <p>Tag interface that marks {@link ServerChannelListener}s as "weak".</p>
 * <p>{@link ServerChannel}s that are not {@link ServerChannel#isPersistent() persistent},
 * that have no subscribers and that only have weak listeners are eligible to be
 * {@link ServerChannel#remove() removed}.</p>
 */
interface Weak extends ServerChannelListener
{
}