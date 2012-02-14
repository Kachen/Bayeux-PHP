<?php

namespace Bayeux\Server\LocalSessionImpl;

use Bayeux\Api\ChannelId;
use Bayeux\Server\BayeuxServerImpl;
use Bayeux\Server\LocalSessionImpl;
use Bayeux\Api\Message;
use Bayeux\Api\Channel;
use Bayeux\Common\AbstractClientSession\AbstractSessionChannel;

/**
 * A channel scoped to this local session
 */
class LocalChannel extends AbstractSessionChannel
{

    private $_localSession;
    private $_bayeux;
    private $_session;

    /* ------------------------------------------------------------ */
    public function __construct(ChannelId $id)
    {
        parent::__construct($id);
    }

    public function init(LocalSessionImpl $localSession, BayeuxServerImpl $bayeux, &$session) {
        $this->_localSession = $localSession;
        //$localSession->setSession($this, $session);

        $this->_bayeux = $bayeux;
        $this->_session = $session;
    }

    /* ------------------------------------------------------------ */
    public function getSession()
    {
        return $this->_localSession;
    }


    /* ------------------------------------------------------------ */
    public function publish($data, $messageId = null)
    {
        if ($this->_session == null) {
            throw new \Exception("!handshake");
        }

        $message = $this->_bayeux->newMessage();
        $message->setChannel($this->getId());
        $message->setData($data);
        if ($messageId != null) {
            $message->setId($messageId);
        }

        $this->_localSession->send($this->_session, $message);
        $message->setAssociated(null);
    }

    /* ------------------------------------------------------------ */
//     @Override
    public function toString()
    {
        return parent::toString() . "@" . LocalSessionImpl.this.toString();
    }

//     @Override
    protected function sendSubscribe()
    {
        $message = $this->_bayeux->newMessage();
        $message->setChannel(Channel::META_SUBSCRIBE);
        $message[Message::SUBSCRIPTION_FIELD] =  $this->getId();
        $message->setClientId($this->_localSession->getId());
        $message->setId($this->_localSession->newMessageId());

        $this->_localSession->send($this->_session, $message);
        $message->setAssociated(null);
    }

//     @Override
    protected function sendUnSubscribe()
    {
        $message = $this->_bayeux->newMessage();
        $message->setChannel(Channel::META_UNSUBSCRIBE);
        $message->put(Message::SUBSCRIPTION_FIELD, $this->getId());
        $message->setId($this->_localSession->newMessageId());

        $this->_localSession->send($this->_session, $message);
        $message->setAssociated(null);
    }
}
