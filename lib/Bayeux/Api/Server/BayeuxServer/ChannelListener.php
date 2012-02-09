<?php

namespace Bayeux\Api\Server\BayeuxServer;


use Bayeux\Api\Server\ServerChannel;

/**
 * <p>Specialized listener for {@link ServerChannel} events.</p>
 * <p>The {@link ServerChannel.Initializer#configureChannel(ConfigurableServerChannel)}
 * method is called atomically during Channel creation so that the channel may be configured
 * before use. It is guaranteed that in case of concurrent channel creation, the
 * {@link ServerChannel.Initializer#configureChannel(ConfigurableServerChannel)} is
 * invoked exactly once.</p>
 * <p>The other methods are called asynchronously when a channel is added to or removed
 * from a {@link BayeuxServer}, and there is no guarantee that these methods will be called
 * before any other {@link ServerChannel.ServerChannelListener server channel listeners}
 * that may be added during channel configuration.</p>
 */
interface ChannelListener extends BayeuxServerListener, ConfigurableServerChannel\Initializer
{
    /**
     * Callback invoked when a {@link ServerChannel} has been added to a {@link BayeuxServer} object.
     * @param channel the channel that has been added
     */
    public function channelAdded(ServerChannel $channel);

    /**
     * Callback invoked when a {@link ServerChannel} has been removed from a {@link BayeuxServer} object.
     * @param channelId the channel identifier of the channel that has been removed.
     */
    public function channelRemoved($channelId);
}