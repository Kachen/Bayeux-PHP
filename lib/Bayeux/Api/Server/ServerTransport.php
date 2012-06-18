<?php

namespace Bayeux\Api\Server;

use Bayeux\Api\Transport;

/**
 * <p>Server side extension of a Bayeux transport.</p>
 * <p>A {@link ServerTransport} can be configured with a {@link #getTimeout() timeout}
 * (that in the default long polling http transport is the period of time
 * the server waits before answering to a long poll), an {@link #getInterval() interval}
 * (that in the default long polling http transport is the period of time
 * that the client waits between long polls), a {@link #getMaxInterval() maximum interval}
 * (that in the default long polling http transport is the period of time
 * that must elapse before the server consider the client being lost).</p>
 * <p>Further configuration include the {@link #getMaxLazyTimeout() maximum lazy timeout}
 * used for {@link ServerMessage#isLazy() lazy messages} and the style of delivery,
 * that may happen during both responses to requests and via the "/meta/connect" channel,
 * or via the "/meta/connect" channel {@link #isMetaConnectDeliveryOnly() exclusively}.
 *
 * @version $Revision: 1483 $ $Date: 2009-03-04 14:56:47 +0100 (Wed, 04 Mar 2009) $
 */
interface ServerTransport extends Transport
{
    /**
     * <p>The advice that this transport sends to inform the client
     * about transport (re)connection.</p>
     * @return the advice object sent by the server transport
     */
    public function getAdvice();

    /**
     * @return the timeout (in milliseconds) of this transport
     */
    public function getTimeout();

    /**
     * @return the interval of time (in milliseconds) of this transport
     */
    public function getInterval();

    /**
     * @return the maximum interval of time (in milliseconds) before the server consider the client lost
     */
    public function getMaxInterval();

    /**
     * @return the maximum time (in milliseconds) before dispatching lazy messages
     */
    public function getMaxLazyTimeout();

    /**
     * @return whether the messages are delivered to clients exclusively via the "/meta/connect" channel
     */
    public function isMetaConnectDeliveryOnly();

    /**
     * @return The current transport context or null if no current context
     */
    public function getContext();
}
