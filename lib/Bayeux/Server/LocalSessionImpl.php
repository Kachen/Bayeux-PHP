<?php

namespace Bayeux\Server;

use Bayeux\Common\IllegalStateException;

use Bayeux\Server\LocalSessionImpl\LocalChannel;
use Bayeux\Api\Server\LocalSession;
use Bayeux\Common\AbstractClientSession;
use Bayeux\Api\Server\ServerMessage;
use Bayeux\Api\ChannelId;
use Bayeux\Api\Channel;
use Bayeux\Api\Message;

/** A LocalSession implementation.
 * <p>
 * This session is local to the {@link BayeuxServer} instance and
 * communicates with the server without any serialization.
 * The normal Bayeux meta messages are exchanged between the LocalSession
 * and the ServerSession.
 */
class LocalSessionImpl extends AbstractClientSession implements LocalSession
{
    private $_queue;
    private $_bayeux;
    private $_idHint;

    private $_session;

    public function __construct(BayeuxServerImpl $bayeux, $idHint)
    {
        $this->_queue = new \SplQueue();
        $this->_bayeux = $bayeux;
        $this->_idHint = $idHint;
    }

    public function receive(Message\Mutable $message) {
        parent::receive($message);
        if (Channel::META_DISCONNECT == $message->getChannel() && $message->isSuccessful()) {
            $this->_session = null;
        }
    }

    /**
     * @see org.cometd.common.AbstractClientSession#newChannel(org.cometd.bayeux.ChannelId)
     */
    public function newChannel(ChannelId $channelId = null)
    {
        $localChannel = new LocalChannel($channelId);
        $localChannel->init($this, $this->_bayeux, $this->_session);
        return $localChannel;
    }

    /**
     * @see org.cometd.common.AbstractClientSession#newChannelId(java.lang.String)
     */
    public function newChannelId($channelId) {
        return $this->_bayeux->newChannelId($channelId);
    }

    /**
     * @see org.cometd.common.AbstractClientSession#sendBatch()
     */
    protected function sendBatch() {
        while(! $this->_queue->isEmpty())
        {
            $message = $this->_queue->dequeue();
            $this->doSend($this->_session, $message);
        }
    }

    public function getServerSession() {
        if ($this->_session == null) {
            throw new IllegalStateException();
        }
        return $this->_session;
    }

    public function handshake($template = null) {
        if ($this->_session != null) {
            throw new \IllegalStateException();
        }

        $message = $this->_bayeux->newMessage();

        if ($template != null) {
            $this->message[] = $template;
        }
        $message->setChannel(Channel::META_HANDSHAKE);
        $message->setId($this->newMessageId());

        $session = new ServerSessionImpl($this->_bayeux, $this, $this->_idHint);

        $this->doSend($session, $message);

        $reply = $message->getAssociated();
        if ($reply != null && $reply->isSuccessful())
        {
            $this->_session = $session;

            $message = $this->_bayeux->newMessage();
            $message->setChannel(Channel::META_CONNECT);
            $message->setClientId($this->_session->getId());

            $a = $message->getAdvice(true);
            $a['asf'] = '2';
            $advice = $message->getAdvice(true);
            $advice[Message::ADVICE_FIELD] = -1;
            $message->setId($this->newMessageId());

            $this->doSend($session, $message);
            $reply = $message->getAssociated();
            if (!$reply->isSuccessful()) {
                $this->_session = null;
            }
        }

        $message->setAssociated(null);
    }

    public function disconnect() {
        if ($this->_session!=null)
        {
            $message = $this->_bayeux->newMessage();
            $message->setChannel(Channel::META_DISCONNECT);
            $message->setId($this->newMessageId());
            $this->send($this->_session, $message);
            while ($this->isBatching()) {
                $this->endBatch();
            }
        }
    }

    public function getId() {
        if ($this->_session == null) {
            throw new \Exception("!handshake");
        }
        return $this->_session->getId();
    }

    public function isConnected() {
        return $this->_session != null && $this->_session->isConnected();
    }

    public function isHandshook() {
        return $this->_session != null && $this->_session->isHandshook();
    }

    public function toString() {
        return 'L:' . ($this->_session == null ? $this->_idHint . '?' : $this->_session->getId());
    }

    /** Send a message (to the server).
     * <p>
     * This method will either batch the message or call {@link #doSend(ServerSessionImpl, org.cometd.bayeux.server.ServerMessage.Mutable)}
     * @param session The ServerSession to send as. This normally the current server session, but during handshake it is a proposed server session.
     * @param message The message to send.
     */
    public function send(ServerSessionImpl $session, ServerMessage\Mutable $message)
    {
        if ($this->isBatching()) {
            $this->_queue->enqueue($message);
        } else {
            $this->doSend($session, $message);
        }
    }

    /** Send a message (to the server).
     * <p>
     * Extends and sends the message without batching.
     * @param from The ServerSession to send as. This normally the current server session, but during handshake it is a proposed server session.
     * @param message The message to send.
     */
    protected function doSend(ServerSessionImpl $from, ServerMessage\Mutable $message)
    {
        if (! $this->extendSend($message)) {
            return;
        }

        if ($this->_session != null) {
            $message->setClientId($this->_session->getId());
        }

        $reply = $this->_bayeux->handle($from, $message);
        if ($reply != null)
        {
            $reply = $this->_bayeux->extendReply($from, ($this->_session != null && $this->_session->isHandshook()) ? $this->_session : null, $reply);
            if ($reply != null) {
                $this->receive($reply);
            }
        }
    }
}