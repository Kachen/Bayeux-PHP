<?php

namespace Bayeux\Api\Server;

/**
 * <p>The Bayeux Context provides information about the current context of a Bayeux message.</p>
 * <p>This information may be from an associated HTTP request, or a HTTP request used to
 * originally establish the connection (for example in a websocket handshake).</p>
 */
interface BayeuxContext
{
    /**
     * @return The user Principal (if any)
     */
    public function getUserPrincipal();

    /**
     * @param role the role to check whether the user belongs to
     * @return true if there is a known user and they are in the given role.
     */
    public function isUserInRole($role);

    /**
     * @return the remote socket address
     */
    public function getRemoteAddress();

    /**
     * @return the local socket address
     */
    public function getLocalAddress();

    /**
     * Get a transport header.<p>
     * Get a header for any current transport mechanism (eg HTTP request).
     * For transports like websocket, the header may be from the initial handshake.
     * @param name The name of the header
     * @return The header value or null if no current transport mechanism or no such header.
     */
    public function getHeader($name);

    /**
     * Get a multi valued transport header.<p>
     * Get a header for any current transport mechanism (eg HTTP request).
     * For transports like websocket, the header may be from the initial handshake.
     * @param name The name of the header
     * @return The header value or null if no current transport mechanism or no such header.
     */
    public function getHeaderValues($name);

    /**
     * Get a transport parameter.<p>
     * Get a parameter for any current transport mechanism (eg HTTP request).
     * For transports like websocket, the parameter may be from the initial handshake.
     * @param name The name of the parameter
     * @return The parameter value or null if no current transport mechanism or no such parameter.
     */
    public function getParameter($name);

    /**
     * Get a multi valued transport parameter.<p>
     * Get a parameter for any current transport mechanism (eg HTTP request).
     * For transports like websocket, the parameter may be from the initial handshake.
     * @param name The name of the parameter
     * @return The parameter value or null if no current transport mechanism or no such parameter.
     */
    public function getParameterValues($name);

    /**
     * Get a transport cookie.<p>
     * Get a cookie for any current transport mechanism (eg HTTP request).
     * For transports like websocket, the cookie may be from the initial handshake.
     * @param name The name of the cookie
     * @return The cookie value or null if no current transport mechanism or no such cookie.
     */
    public function getCookie($name);

    /**
     * Access the HTTP Session (if any) ID.
     * The {@link ServerSession#getId()} should be used in preference to the HTTP Session.
     * @return HTTP session ID or null
     */
    public function getHttpSessionId();

    /**
     * Access the HTTP Session (if any) attributes.
     * The {@link ServerSession#getAttribute(String)} should be used in preference to the HTTP Session.
     * @param name the attribute name
     * @return The attribute value
     */
    public function getHttpSessionAttribute($name);

    /**
     * Access the HTTP Session (if any) attributes.
     * The {@link ServerSession#setAttribute(String, Object)} should be used in preference to the HTTP Session.
     * @param name the attribute name
     * @param value the attribute value
     */
    public function setHttpSessionAttribute($name, $value);

    /**
     * Invalidate the HTTP Session.
     * The {@link ServerSession#getId()} should be used in preference to the HTTP Session.
     */
    public function invalidateHttpSession();

    /**
     * Access the Request (if any) attributes.
     * @param name the attribute name
     * @return The attribute value
     */
    public function getRequestAttribute($name);

    /**
     * Access the ServletContext (if any) attributes.
     * @param name the attribute name
     * @return The attribute value
     */
    public function getContextAttribute($name);

    /**
     * Access the ServletContext (if any) init parameter.
     * @param name the init parameter name
     * @return The attribute value
     */
    public function getContextInitParameter($name);

    /**
     * @return the full request URI complete with query string if present.
     */
    public function getURL();
}
