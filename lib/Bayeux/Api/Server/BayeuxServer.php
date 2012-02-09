<?php

namespace Bayeux\Api\Server;

use Bayeux\Api\Bayeux\Bayeux;

/* ------------------------------------------------------------ */
/**
 * <p>The server-side Bayeux interface.</p>
 * <p>An instance of the {@link BayeuxServer} interface is available to
 * web applications via the "{@value #ATTRIBUTE}" attribute
 * of the {@link javax.servlet.ServletContext servlet context}.</p>
 * <p>The {@link BayeuxServer} APIs give access to the
 * {@link ServerSession}s via the {@link #getSession(String)}
 * method.  It also allows new {@link LocalSession} to be
 * created within the server using the {@link #newLocalSession(String)}
 * method.</p>
 * <p>{@link ServerChannel} instances may be accessed via the
 * {@link #getChannel(String)} method, but the server has
 * no direct relationship with {@link ClientSessionChannel}s or
 * {@link ClientSession}.</p>
 * <p>If subscription semantics is required, then
 * the {@link #newLocalSession(String)} method should be used to
 * create a {@link LocalSession} that can subscribe and publish
 * like a client-side Bayeux session.</p>
 *
 * @version $Revision: 1483 $ $Date: 2009-03-04 14:56:47 +0100 (Wed, 04 Mar 2009) $
 */
interface BayeuxServer extends Bayeux
{
    /** ServletContext attribute name used to obtain the Bayeux object */
    const ATTRIBUTE ="org.cometd.bayeux";

    /**
     * Adds the given extension to this Bayeux object.
     * @param extension the extension to add
     * @see #removeExtension(Extension)
     */
    public function addExtension(Extension $extension);

    /**
     * Removes the given extension from this Bayeux object
     * @param extension the extension to remove
     * @see #addExtension(Extension)
     */
    public function removeExtension(Extension $extension);

    /**
     * Adds a listener to this Bayeux object.
     * @param listener the listener to add
     * @see #removeListener(BayeuxServerListener)
     */
    public function addListener(BayeuxServerListener $listener);

    /**
     * Removes a listener from this Bayeux object.
     * @param listener the listener to remove
     * @see #addListener(BayeuxServerListener)
     */
    public function removeListener(BayeuxServerListener $listener);

    /**
     * @param channelId the channel identifier
     * @return a {@link ServerChannel} with the given {@code channelId},
     * or null if no such channel exists
     * @see #createIfAbsent(String, org.cometd.bayeux.server.ConfigurableServerChannel.Initializer...)
     */
    public function getChannel($channelId);

    /**
     * @return the list of channels known to this BayeuxServer object
     */
    //List<ServerChannel> getChannels();
    public function getChannels();

    /**
     * <p>Creates a {@link ServerChannel} and initializes it atomically.</p>
     * <p>This method can be used instead of adding a {@link ChannelListener}
     * to atomically initialize a channel. The initializer will be called before
     * any other thread can access the new channel instance.</p>
     * <p>The createIfAbsent method should be used when a channel needs to be
     * intialized (e.g. by adding listeners) before any publish or subscribes
     * can occur on the channel, or before any other thread may concurrently
     * create the same channel.
     *
     * @param channelId the channel identifier
     * @param initializers the initializers invoked to configure the channel
     * @return true if the channel was initialized, false otherwise
     */
    public function createIfAbsent($channelId /*ConfigurableServerChannel.Initializer... initializers*/);

    /**
     * @param clientId the {@link ServerSession} identifier
     * @return the {@link ServerSession} with the given {@code clientId}
     * or null if no such valid session exists.
     */
    public function getSession($clientId);

    /**
     * @return the list of {@link ServerSession}s known to this BayeuxServer object
     */
    //List<ServerSession> getSessions();
    public function getSessions();

    /**
     * <p>Creates a new {@link LocalSession}.</p>
     * <p>A {@link LocalSession} is a server-side ClientSession that allows
     * server-side code to have special clients (resident within the same JVM)
     * that can be used to publish and subscribe like a client-side session
     * would do.</p>
     *
     * @param idHint a hint to be included in the unique clientId of the session.
     * @return a new {@link LocalSession}
     */
    public function newLocalSession($idHint);

    /**
     * @return a new or recycled mutable message instance.
     */
    public function newMessage();

    /**
     * @return the {@link SecurityPolicy} associated with this session
     * @see #setSecurityPolicy(SecurityPolicy)
     */
    public function getSecurityPolicy();

    /**
     * @param securityPolicy the {@link SecurityPolicy} associated with this session
     * @see #getSecurityPolicy()
     */
    public function setSecurityPolicy(SecurityPolicy $securityPolicy);

    /**
     * @return the current transport instance of the current thread
     */
    public function getCurrentTransport();

    /**
     * @return the current Context, is equivalent to ((ServerTransport){@link #getCurrentTransport()}).{@link ServerTransport#getContext()}
     */
    public function getContext();
}
