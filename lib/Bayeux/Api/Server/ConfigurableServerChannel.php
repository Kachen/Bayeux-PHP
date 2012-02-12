<?php

namespace Bayeux\Api\Server;

use Bayeux\Api\Server\ServerChannel\ServerChannelListener;
use Bayeux\Api\Channel;

/**
 * <p>A {@link ConfigurableServerChannel} offers an API that can be used to
 * configure {@link ServerChannel}s at creation time.</p>
 * <p>{@link ServerChannel}s may be created concurrently via
 * {@link BayeuxServer#createIfAbsent(String, Initializer...)} and it is
 * important that the creation of a channel is atomic so that its
 * configuration is executed only once, and so that it is guaranteed that
 * it happens before any message can be published or received by the channel.</p>
 *
 * @version $Revision: 1483 $ $Date: 2009-03-04 14:56:47 +0100 (Wed, 04 Mar 2009) $
 */
interface ConfigurableServerChannel extends Channel
{
    /**
     * @param listener the listener to add
     * @see #removeListener(ServerChannelListener)
     */
    public function addListener(ServerChannelListener $listener);

    /**
     * @param listener the listener to remove
     * @see #addListener(ServerChannelListener)
     */
    public function removeListener(ServerChannelListener $listener);

    /**
     * @return an immutable list of listeners
     * @see #addListener(ServerChannelListener)
     */
    //List<ServerChannelListener> getListeners();
    public function getListeners();

    /**
     * @return whether the channel is lazy
     * @see #setLazy(boolean)
     */
    public function isLazy();

    /**
     * A lazy channel marks all messages published to it as lazy.
     * @param lazy whether the channel is lazy
     * @see #isLazy()
     */
    public function setLazy($lazy);

    /**
     * @return whether the channel is persistent
     * @see #setPersistent(boolean)
     */
    public function isPersistent();

    /**
     * A persistent channel is not removed when the last subscription is removed
     * @param persistent whether the channel is persistent
     * @see #isPersistent()
     */
    public function setPersistent($persistent);

    /**
     * <p>Adds the given {@link Authorizer} that grants or denies operations on this channel.</p>
     * <p>Operations must be granted by at least one Authorizer and must not be denied by any.</p>
     *
     * @param authorizer the Authorizer to add
     * @see #removeAuthorizer(Authorizer)
     * @see Authorizer
     */
    public function addAuthorizer(Authorizer $authorizer);

    /**
     * <p>Removes the given {@link Authorizer}.</p>
     * @param authorizer the Authorizer to remove
     * @see #addAuthorizer(Authorizer)
     */
    public function removeAuthorizer(Authorizer $authorizer);

    /**
     * @return an immutable list of authorizers for this channel
     */
    //public List<Authorizer> getAuthorizers();
    public function getAuthorizers();
}
