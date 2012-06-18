<?php

namespace Bayeux\Api;

/**
 * <p>The Bayeux protocol exchange information by means of messages.</p>
 * <p>This interface represents the API of a Bayeux message, and consists
 * mainly of convenience methods to access the known fields of the message map.</p>
 * <p>This interface comes in both an immutable and {@link Mutable mutable} versions.<br/>
 * Mutability may be deeply enforced by an implementation, so that it is not correct
 * to cast a passed Message, to a Message.Mutable, even if the implementation
 * allows this.</p>
 *
 * @version $Revision: 1483 $ $Date: 2009-03-04 14:56:47 +0100 (Wed, 04 Mar 2009) $
 */
interface Message //extends Map<String, Object>
{
    const CLIENT_ID_FIELD = "clientId";
    const DATA_FIELD = "data";
    const CHANNEL_FIELD = "channel";
    const ID_FIELD = "id";
    const ERROR_FIELD = "error";
    const TIMESTAMP_FIELD = "timestamp";
    const TRANSPORT_FIELD = "transport";
    const ADVICE_FIELD = "advice";
    const SUCCESSFUL_FIELD = "successful";
    const SUBSCRIPTION_FIELD = "subscription";
    const EXT_FIELD = "ext";
    const CONNECTION_TYPE_FIELD = "connectionType";
    const VERSION_FIELD = "version";
    const MIN_VERSION_FIELD = "minimumVersion";
    const SUPPORTED_CONNECTION_TYPES_FIELD = "supportedConnectionTypes";
    const RECONNECT_FIELD = "reconnect";
    const INTERVAL_FIELD = "interval";
    const TIMEOUT_FIELD = "timeout";
    const RECONNECT_RETRY_VALUE = "retry";
    const RECONNECT_HANDSHAKE_VALUE = "handshake";
    const RECONNECT_NONE_VALUE = "none";

    /**
     * Convenience method to retrieve the {@link #ADVICE_FIELD}
     * @return the advice of the message
     */
    //Map<String, Object> getAdvice();
    public function getAdvice($create = null);

    /**
     * Convenience method to retrieve the {@link #CHANNEL_FIELD}.
     * Bayeux message always have a non null channel.
     * @return the channel of the message
     */
    public function getChannel();

    /**
     * Convenience method to retrieve the {@link #CHANNEL_FIELD}.
     * Bayeux message always have a non null channel.
     * @return the channel of the message
     */
    public function getChannelId();

    /**
     * Convenience method to retrieve the {@link #CLIENT_ID_FIELD}
     * @return the client id of the message
     */
    public function getClientId();

    /**
     * Convenience method to retrieve the {@link #DATA_FIELD}
     * @return the data of the message
     * @see #getDataAsMap()
     */
    public function getData();

    /**
     * A messages that has a meta channel is dubbed a "meta message".
     * @return whether the channel's message is a meta channel
     */
    public function isMeta();

    /**
     * Publish message replies contain the "successful" field
     * @return whether this message is a publish reply (as opposed to a published message)
     */
    public function isPublishReply();

    /**
     * Convenience method to retrieve the {@link #SUCCESSFUL_FIELD}
     * @return whether the message is successful
     */
    public function isSuccessful();

    /**
     * @return the data of the message as a map
     * @see #getData()
     */
    //Map<String, Object> getDataAsMap();
    public function getDataAsMap($create = null);

    /**
     * Convenience method to retrieve the {@link #EXT_FIELD}
     * @return the ext of the message
     */
    public function getExt($create = null);

    /**
     * Convenience method to retrieve the {@link #ID_FIELD}
     * @return the id of the message
     */
    public function getId();

    /**
     * @return this message as a JSON string
     */
    public function getJSON();
}
