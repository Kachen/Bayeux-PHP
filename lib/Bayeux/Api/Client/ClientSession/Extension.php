<?php

namespace Bayeux\Api\Client\ClientSession;

use Bayeux\Api\Message;
use Bayeux\Api\Client\ClientSession;

/**
 * <p>Extension API for client session.</p>
 * <p>An extension allows user code to interact with the Bayeux protocol as late
 * as messages are sent or as soon as messages are received.</p>
 * <p>Messages may be modified, or state held, so that the extension adds a
 * specific behavior simply by observing the flow of Bayeux messages.</p>
 *
 * @see ClientSession#addExtension(Extension)
 */
interface Extension
{
    /**
     * Callback method invoked every time a normal message is received.
     * @param session the session object that is receiving the message
     * @param message the message received
     * @return true if message processing should continue, false if it should stop
     */
    public function rcv(ClientSession $session, Message\Mutable $message);

    /**
     * Callback method invoked every time a meta message is received.
     * @param session the session object that is receiving the meta message
     * @param message the meta message received
     * @return true if message processing should continue, false if it should stop
     */
    public function rcvMeta(ClientSession $session, Message\Mutable $message);

    /**
     * Callback method invoked every time a normal message is being sent.
     * @param session the session object that is sending the message
     * @param message the message being sent
     * @return true if message processing should continue, false if it should stop
     */
    public function send(ClientSession $session, Message\Mutable $message);

    /**
     * Callback method invoked every time a meta message is being sent.
     * @param session the session object that is sending the message
     * @param message the meta message being sent
     * @return true if message processing should continue, false if it should stop
     */
    public function sendMeta(ClientSession $session, Message\Mutable $message);
}