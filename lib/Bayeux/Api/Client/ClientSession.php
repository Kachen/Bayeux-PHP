<?php

namespace Bayeux\Api\Client;

use Bayeux\Api\Client\ClientSession\Extension;
use Bayeux\Api\Session;

/**
 * <p>This interface represents the client side Bayeux session.</p>
 * <p>In addition to the {@link Session common Bayeux session}, this
 * interface provides method to configure extension, access channels
 * and to initiate the communication with a Bayeux server(s).</p>
 *
 * @version $Revision: 1483 $ $Date: 2009-03-04 14:56:47 +0100 (Wed, 04 Mar 2009) $
 */
interface ClientSession extends Session
{
    /**
     * Adds an extension to this session.
     * @param extension the extension to add
     * @see #removeExtension(Extension)
     */
    public function addExtension(Extension $extension);

    /**
     * Removes an extension from this session.
     * @param extension the extension to remove
     * @see #addExtension(Extension)
     */
    public function removeExtension(Extension $extension);

    /**
     * <p>Initiates the bayeux protocol handshake with the server(s).</p>
     * <p>The handshake initiated by this method is asynchronous and
     * does not wait for the handshake response.</p>
     *
     * @param template additional fields to add to the handshake message.
     */
    public function handshake(/*Map<String, Object>*/ $template = null);

    /**
     * <p>Returns a client side channel scoped by this session.</p>
     * <p>The channel name may be for a specific channel (e.g. "/foo/bar")
     * or for a wild channel (e.g. "/meta/**" or "/foo/*").</p>
     * <p>This method will always return a channel, even if the
     * the channel has not been created on the server side.  The server
     * side channel is only involved once a publish or subscribe method
     * is called on the channel returned by this method.</p>
     * <p>Typical usage examples are:</p>
     * <pre>
     *     clientSession.getChannel("/foo/bar").subscribe(mySubscriptionListener);
     *     clientSession.getChannel("/foo/bar").publish("Hello");
     *     clientSession.getChannel("/meta/*").addListener(myMetaChannelListener);
     * </pre>
     * @param channelName specific or wild channel name.
     * @return a channel scoped by this session.
     */
    public function getChannel($channelName);
}
