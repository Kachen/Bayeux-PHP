<?php

namespace Bayeux\Api\Client;

use Bayeux\Api\Client\ClientSessionChannel\MessageListener;
use Bayeux\Api\Client\ClientSessionChannel\ClientSessionChannelListener;
use Bayeux\Api\Channel;
use Bayeux\Api\Session;

/**
 * <p>A client side channel representation.</p>
 * <p>A {@link ClientSessionChannel} is scoped to a particular {@link ClientSession}
 * that is obtained by a call to {@link ClientSession#getChannel(String)}.</p>
 * <p>Typical usage examples are:</p>
 * <pre>
 *     clientSession.getChannel("/foo/bar").subscribe(mySubscriptionListener);
 *     clientSession.getChannel("/foo/bar").publish("Hello");
 *     clientSession.getChannel("/meta/*").addListener(myMetaChannelListener);
 * <pre>
 *
 * @version $Revision$ $Date$
 */
interface ClientSessionChannel extends Channel
{
    /**
     * @param listener the listener to add
     */
    public function addListener(ClientSessionChannelListener $listener);

    /**
     * @param listener the listener to remove
     */
    public function removeListener(ClientSessionChannelListener $listener);

    /**
     * @return the client session associated with this channel
     */
    public function getSession();

    /**
     * Equivalent to {@link #publish(Object, Object) publish(data, null)}.
     * @param data the data to publish
     */
    /**
     * Publishes the given {@code data} to this channel,
     * optionally specifying the {@code messageId} to set on the
     * publish message.
     * @param data the data to publish
     * @param messageId the message id to set on the message, or null to let the
     * implementation choose the message id.
     * @see Message#getId()
     */
    public function publish($data, $messageId = null);

    public function subscribe(MessageListener $listener);

    public function unsubscribe(MessageListener $listener = null);
}