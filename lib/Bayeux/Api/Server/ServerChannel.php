<?php

namespace Bayeux\Api\Server;

use Bayeux\Api\Server\ServerMessage;
use Bayeux\Api\Session;

/**
 * <p>Server side representation of a Bayeux channel.</p>
 * <p>{@link ServerChannel} is the entity that holds a set of
 * {@link ServerSession}s that are subscribed to the channel itself.</p>
 * <p>A message published to a {@link ServerChannel} will be delivered to
 * all the {@link ServerSession}'s subscribed to the channel.</p>
 * <p>Contrary to their client side counterpart ({@link ClientSessionChannel})
 * a {@link ServerChannel} is not scoped with a session.</p>
 *
 * @version $Revision: 1483 $ $Date: 2009-03-04 14:56:47 +0100 (Wed, 04 Mar 2009) $
 */
interface ServerChannel extends ConfigurableServerChannel
{
    /**
     * @return a snapshot of the set of subscribers of this channel
     */
    //Set<ServerSession> getSubscribers();
    public function getSubscribers();

    /**
     * A broadcasting channel is a channel that is neither a meta channel
     * not a service channel.
     * @return whether the channel is a broadcasting channel
     */
    public function isBroadcast();

    /**
     * <p>Publishes the given message to this channel, delivering
     * the message to all the {@link ServerSession}s subscribed to
     * this channel.</p>
     *
     * @param from the session from which the message originates
     * @param message the message to publish
     * @see #publish(Session, Object, String)
     */
    /**
     * <p>Publishes the given information to this channel.</p>
     * @param from the session from which the message originates
     * @param data the data of the message
     * @param id the id of the message
     * @see #publish(Session, ServerMessage)
     */
    public function publish(Session $from = null, $arg1, $id = null);

    /**
     * <p>Removes this channel, and all the children channels.</p>
     * <p>If channel "/foo", "/foo/bar" and "/foo/blip" exist,
     * removing channel "/foo" will remove also "/foo/bar" and
     * "/foo/blip".</p>
     * <p>The removal will notify {@link BayeuxServer.ChannelListener}
     * listeners.
     */
    public function remove();

}
