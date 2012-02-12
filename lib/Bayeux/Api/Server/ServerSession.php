<?php

namespace Bayeux\Api\Server;

use Bayeux\Api\Session;
use Bayeux\Api\Server\ServerSession\Extension;
use Bayeux\Api\Server\ServerSession\ServerSessionListener;

/**
 * <p>Objects implementing this interface are the server-side representation of remote Bayeux clients.</p>
 * <p>{@link ServerSession} contains the queue of messages to be delivered to the client; messages are
 * normally queued on a {@link ServerSession} by publishing them to a channel to which the session is
 * subscribed (via {@link ServerChannel#publish(Session, ServerMessage)}).</p>
 * <p>The {@link #deliver(Session, Mutable)} and {@link #deliver(Session, String, Object, String)}
 * methods may be used to directly queue messages to a session without publishing them to all subscribers
 * of a channel.</p>
 *
 * @version $Revision: 1483 $ $Date: 2009-03-04 14:56:47 +0100 (Wed, 04 Mar 2009) $
 */
interface ServerSession extends Session
{
    /**
     * Adds the given extension to this session.
     * @param extension the extension to add
     * @see #removeExtension(Extension)
     */
    public function addExtension(Extension $extension);

    /**
     * Removes the given extension from this session
     * @param extension the extension to remove
     * @see #addExtension(Extension)
     */
    public function removeExtension(Extension $extension);

    /**
     * Adds the given listener to this session.
     * @param listener the listener to add
     * @see #removeListener(ServerSessionListener)
     */
    public function addListener(ServerSessionListener $listener);

    /**
     * Removes the given listener from this session.
     * @param listener the listener to remove
     * @see #addListener(ServerSessionListener)
     */
    public function removeListener(ServerSessionListener $listener);

    /**
     * @return whether this is a session for a local client on server-side
     */
    public function isLocalSession();

    /**
     * @return the {@link LocalSession} associated with this session,
     * or null if this is a session representing a remote client.
     */
    public function getLocalSession();

    /**
     * <p>Delivers the given message to this session.</p>
     * <p>This is different from {@link ServerChannel#publish(Session, ServerMessage)}
     * as the message is delivered only to this session and
     * not to all subscribers of the channel.</p>
     * <p>The message should still have a channel id specified, so that the ClientSession
     * may identify the listeners the message should be delivered to.
     * @param from the session delivering the message
     * @param message the message to deliver
     * @see #deliver(Session, String, Object, String)
     */
    /**
     * <p>Delivers the given information to this session.</p>
     * @param from the session delivering the message
     * @param channel the channel of the message
     * @param data the data of the message
     * @param id the id of the message, or null to let the implementation choose an id
     * @see #deliver(Session, Mutable)
     */
    public function deliver(Session $from, $arg1, $data = null, $id = null);


    /* ------------------------------------------------------------ */
    /**
     * <p>Get the clients user agent</p>
     * @return The string indicating the client user agent, or null if not known
     */
    public function getUserAgent();
}
