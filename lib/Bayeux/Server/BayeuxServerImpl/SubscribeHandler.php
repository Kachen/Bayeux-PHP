<?php

namespace Bayeux\Server\BayeuxServerImpl;

use Bayeux\Api\Message;
use Bayeux\Api\Server\ServerMessage;
use Bayeux\Server\ServerSessionImpl;

class SubscribeHandler extends HandlerListener
{
    public function onMessage(ServerSessionImpl $from, ServerMessage\Mutable $message)
    {
        $reply = $message->getAssociated();
        if ($this->isSessionUnknown($from))
        {
            $this->unknownSession($reply);
            return;
        }

        $subscription = $message[Message::SUBSCRIPTION_FIELD];
        $reply[Message::SUBSCRIPTION_FIELD] = $subscription;

        if ($subscription == null)
        {
            $this->error($reply, "403::subscription missing");
        }
        else
        {
            $channel = $this->getChannel($subscription);
            if ($channel == null)
            {
                $creationResult = $this->isCreationAuthorized($from, $message, $subscription);
                if ($creationResult instanceof Authorizer\Result\Denied)
                {
                    $denyReason = $creationResult->getReason();
                    $this->error($reply, "403:" . $denyReason . ":create denied");
                }
                else
                {
                    $this->createIfAbsent($subscription);
                    $channel = $this->getChannel($subscription);
                }
            }

            if ($channel != null)
            {
                $subscribeResult = $this->isSubscribeAuthorized($channel, $from, $message);
                if ($subscribeResult instanceof Authorizer\Result\Denied)
                {
                    $denyReason = $subscribeResult->getReason();
                    $this->error($reply, "403:" . $denyReason . ":subscribe denied");
                }
                else
                {
                    // Reduces the window of time where a server-side expiration
                    // or a concurrent disconnect causes the invalid client to be
                    // registered as subscriber and hence being kept alive by the
                    // fact that the channel references it.
                    if (!$this->isSessionUnknown($from))
                    {
                        if ($from->isLocalSession() || !$channel->isMeta() && !$channel->isService())
                        {
                            if ($channel->subscribe($from)) {
                                $reply->setSuccessful(true);
                            } else {
                                $this->error($reply, "403::subscribe failed");
                            }
                        }
                        else
                        {
                            $reply->setSuccessful(true);
                        }
                    }
                    else
                    {
                        $this->unknownSession($reply);
                    }
                }
            }
        }
    }
}