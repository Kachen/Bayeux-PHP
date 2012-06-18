<?php

namespace Bayeux\Server\LocalSessionImpl;

use Bayeux\Common\AbstractClientSession;

use Bayeux\Api\Client\ClientSessionChannel\MessageListener;

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

    public function __construct(ChannelId $id)
    {
        parent::__construct($id);
    }

    public function getSession()
    {
        return $this->_localSession;
    }

    public function publish($data, MessageListener $listener = null)
    {
        $this->throwIfReleased();
        $message = $this->_bayeux->newMessage();
        $message->setChannel($this->getId());
        $message->setData($data);
        $message->setClientId($this->_localSession->getId());
        if ($listener != null) {
            $message[AbstractClientSession::PUBLISH_CALLBACK_KEY] = $listener;
        }
        $this->_localSession->send($this->_session, $message);
    }

    protected function sendSubscribe()
    {
        $message = $this->_bayeux->newMessage();
        $message->setChannel(Channel::META_SUBSCRIBE);
        $message[Message::SUBSCRIPTION_FIELD] =  $this->getId();
        $message->setClientId($this->_localSession->getId());
        $this->_localSession->send($this->_session, $message);
    }

    protected function sendUnSubscribe()
    {
        $message = $this->_bayeux->newMessage();
        $message->setChannel(Channel::META_UNSUBSCRIBE);
        $message[Message::SUBSCRIPTION_FIELD] = $this->getId();
        $message->setClientId($this->_localSession->getId());
        $this->_localSession->send($this->_session, $message);
    }

    public function toString()
    {
        return parent::toString() . "@" . $this->_localSession->toString();
    }
}
