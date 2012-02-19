<?php

namespace Bayeux\Api\Server\BayeuxServer;


use Bayeux\Api\Server\ServerSession;
use Bayeux\Api\Server\ServerMessage;

/**
 * <p>Extension API for {@link BayeuxServer}.</p>
 * <p>Implementations of this interface allow to modify incoming and outgoing messages
 * respectively just before and just after they are handled by the implementation,
 * either on client side or server side.</p>
 * <p>Extensions are be registered in order and one extension may allow subsequent
 * extensions to process the message by returning true from the callback method, or
 * forbid further processing by returning false.</p>
 *
 * @see BayeuxServer#addExtension(Extension)
 */
interface Extension
{
    /**
     * Callback method invoked every time a normal message is incoming.
     * @param from the session that sent the message
     * @param message the incoming message
     * @return true if message processing should continue, false if it should stop
     */
    public function rcv(ServerSession $from, ServerMessage\Mutable $message);

    /**
     * Callback method invoked every time a meta message is incoming.
     * @param from the session that sent the message
     * @param message the incoming meta message
     * @return true if message processing should continue, false if it should stop
     */
    public function rcvMeta(ServerSession $from, ServerMessage\Mutable $message);

    /**
     * Callback method invoked every time a normal message is outgoing.
     * @param from the session that sent the message or null
     * @param to the session the message is sent to, or null for a publish.
     * @param message the outgoing message
     * @return true if message processing should continue, false if it should stop
     */
    public function send(ServerSession $from, ServerSession $to = null, ServerMessage\Mutable $message);

    /**
     * Callback method invoked every time a meta message is outgoing.
     * @param to the session the message is sent to, or null for a publish.
     * @param message the outgoing meta message
     * @return true if message processing should continue, false if it should stop
     */
    public function sendMeta(ServerSession $to = null, ServerMessage\Mutable $message);
}