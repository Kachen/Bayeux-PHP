<?php

namespace Bayeux\Api\Server\ServerSession;

use Bayeux\Api\Server\ServerMessage; // FIXME: vai conflitar o nome da classe e do namespace para o mutable
use Bayeux\Api\Server\ServerSession;

/**
 * <p>Extension API for {@link ServerSession}.</p>
 * <p>Implementations of this interface allow to modify incoming and outgoing messages
 * respectively just before and just after they are handled by the implementation,
 * either on client side or server side.</p>
 * <p>Extensions are be registered in order and one extension may allow subsequent
 * extensions to process the message by returning true from the callback method, or
 * forbid further processing by returning false.</p>
 *
 * @see ServerSession#addExtension(Extension)
 * @see BayeuxServer.Extension
 */
interface Extension
{
    /**
     * Callback method invoked every time a normal message is incoming.
     * @param session the session that sent the message
     * @param message the incoming message
     * @return true if message processing should continue, false if it should stop
     */
    public function rcv(ServerSession $session, ServerMessage\Mutable $message);

    /**
     * Callback method invoked every time a meta message is incoming.
     * @param session the session that is sent the message
     * @param message the incoming meta message
     * @return true if message processing should continue, false if it should stop
     */
    public function rcvMeta(ServerSession $session, ServerMessage\Mutable $message);

    /**
     * Callback method invoked every time a normal message is outgoing.
     * @param to the session receiving the message, or null for a publish
     * @param message the outgoing message
     * @return The message to send or null to not send the message
     */
    public function send(ServerSession $to, ServerMessage $message);

    /**
     * Callback method invoked every time a meta message is outgoing.
     * @param session the session receiving the message
     * @param message the outgoing meta message
     * @return true if message processing should continue, false if it should stop
     */
    public function sendMeta(ServerSession $session = null, ServerMessage\Mutable $message);
}