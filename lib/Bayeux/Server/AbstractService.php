<?php
// ========================================================================
// Copyright 2008 Mort Bay Consulting Pty. Ltd.
// ------------------------------------------------------------------------
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
// http://www.apache.org/licenses/LICENSE-2.0
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.
//========================================================================

namespace Bayeux\Server;

use Bayeux\Api\Server\ServerMessage\Mutable;
use Bayeux\Api\Server\ServerSession;
use Bayeux\Api\Server\ServerChannel;

/**
 * Abstract Bayeux Service
 * <p>
 * This class provides convenience methods to assist with the
 * creation of a Bayeux Services typically used to provide the
 * behaviour associated with a service channel (see {@link Channel#isService()}).
 * Specifically it provides: <ul>
 * <li>Mapping of channel subscriptions to method invocation on the derived service
 * class.
 * <li>Optional use of a thread pool used for method invocation if handling can take
 * considerable time and it is desired not to hold up the delivering thread
 * (typically a HTTP request handling thread).
 * <li>The objects returned from method invocation are delivered back to the
 * calling client in a private message.
 * </ul>
 *
 * @see {@link BayeuxServer#getSession(String)} as an alternative to AbstractService.
 */
use Bayeux\Api\Server\BayeuxServer;

abstract class AbstractService
{
    private $_name;
    private $_bayeux;
    private $_session;

    private $_threadPool;
    public $_seeOwn = false;
    private $_logger;

    /**
     * Instantiate the service. Typically the derived constructor will call @
     * #subscribe(String, String)} to map subscriptions to methods.
     *
     * @param bayeux
     *            The bayeux instance.
     * @param name
     *            The name of the service (used as client ID prefix).
     * @param maxThreads
     *            The size of a ThreadPool to create to handle messages.
     */
    public function __construct(BayeuxServer $bayeux, $name, $maxThreads = 0)
    {
        if ($maxThreads > 0) {
            setThreadPool(new QueuedThreadPool(maxThreads));
        }
        $this->_name = $name;
        $this->_bayeux = $bayeux;
        $this->_session = $bayeux->newLocalSession($name);
        $this->_session->handshake();
        $this->_logger = $bayeux->getLogger();
    }

    public function getBayeux()
    {
        return $this->_bayeux;
    }

    public function getLocalSession()
    {
        return $this->_session;
    }

    public function getServerSession()
    {
        return $this->_session->getServerSession();
    }

    public function getThreadPool()
    {
        return $this->_threadPool;
    }

    /**
     * Set the threadpool. If the {@link ThreadPool} is a {@link LifeCycle},
     * then it is started by this method.
     *
     * @param pool
     */
    public function setThreadPool(ThreadPool $pool)
    {
        try
        {
            if ($pool instanceof LifeCycle) {
                if (!$pool->isStarted()) {
                    $pool->start();
                }
            }
        }
        catch(\Exception $e)
        {
            throw new IllegalStateException($e);
        }
        $this->_threadPool = $pool;
    }

    public function isSeeOwnPublishes()
    {
        return _seeOwn;
    }

    public function setSeeOwnPublishes($own)
    {
        $this->_seeOwn=$own;
    }

    /**
     * Add a service.
     * <p>Listen to a channel and map a method to handle
     * received messages. The method must have a unique name and one of the
     * following signatures:
     * <ul>
     * <li><code>myMethod(ServerSession from,Object data)</code></li>
     * <li><code>myMethod(ServerSession from,Object data,String|Object id)</code></li>
     * <li><code>myMethod(ServerSession from,String channel,Object data,String|Object id)</code>
     * </li>
     * </li>
     *
     * The data parameter can be typed if the type of the data object published
     * by the client is known (typically Map<String,Object>). If the type of the
     * data parameter is {@link Message} then the message object itself is
     * passed rather than just the data.
     * <p>
     * Typically a service will be used to a channel in the "/service/**"
     * space which is not a broadcast channel. Messages published to these
     * channels are only delivered to server side clients like this service.
     * <p>
     * Any object returned by a mapped subscription method is delivered to the
     * calling client and not broadcast. If the method returns void or null,
     * then no response is sent. A mapped subscription method may also call
     * {@link #send(ServerSession, String, Object, String)} to deliver a response
     * message(s) to different clients and/or channels. It may also publish
     * methods via the normal {@link Bayeux} API.
     * <p>
     *
     *
     * @param channelId
     *            The channel to subscribe to
     * @param methodName
     *            The name of the method on this object to call when messages
     *            are received.
     */
    public function addService($channelId, $methodName)
    {
        /*if ($this->_logger->isDebugEnabled()) {
            $this->_logger->debug("subscribe " . $this->_name . "#" . $methodName . " to " . $channelId);
        }*/


        $this->_bayeux->createIfAbsent($channelId);
        $channel=$this->_bayeux->getChannel($channelId);

        $channel->addListener(new MessageListenerService($this, $methodName));
    }

    /**
     * Send data to a individual client. The data passed is sent to the client
     * as the "data" member of a message with the given channel and id. The
     * message is not published on the channel and is thus not broadcast to all
     * channel subscribers. However to the target client, the message appears as
     * if it was broadcast.
     * <p>
     * Typically this method is only required if a service method sends
     * response(s) to channels other than the subscribed channel. If the
     * response is to be sent to the subscribed channel, then the data can
     * simply be returned from the subscription method.
     *
     * @param toClient
     *            The target client
     * @param onChannel
     *            The channel the message is for
     * @param data
     *            The data of the message
     * @param id
     *            The id of the message (or null for a random id).
     */
    protected function send(ServerSession $toClient, $onChannel, $data, $id)
    {
        $toClient->deliver($this->_session->getServerSession(), $onChannel, $data, $id);
    }
}

class MessageListenerService implements ServerChannel\MessageListener {

    private $service;
    private $method;

    public function __construct(AbstractService $service, $method) {
        $this->service = $service;
        $this->method = $method;
    }

    public function onMessage(ServerSession $from = null, ServerChannel $channel, Mutable $message) {
        if ($this->service->_seeOwn || $from != $this->service->getServerSession()) {
            $this->service->{$this->method}($from, $message);
        }
        return true;
    }
}