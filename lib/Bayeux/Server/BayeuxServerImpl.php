<?php

namespace Bayeux\Server;

use Bayeux\Api\Server\ConfigurableServerChannel;

use Bayeux\Api\Server\BayeuxServer\ChannelListener;
use Bayeux\Api\Server\Authorizer;
use Bayeux\Server\BayeuxServerImpl\HandlerListener;
use Bayeux\Server\BayeuxServerImpl\DisconnectHandler;
use Bayeux\Server\BayeuxServerImpl\UnsubscribeHandler;
use Bayeux\Server\BayeuxServerImpl\SubscribeHandler;
use Bayeux\Server\BayeuxServerImpl\ConnectHandler;
use Bayeux\Server\BayeuxServerImpl\HandshakeHandler;
use Bayeux\Api\Client\MessageListener;
use Bayeux\Api\Server\ServerSession;
use Bayeux\Api\Server\ServerChannel;
use Bayeux\Api\ChannelId;
use Bayeux\Api\Server\ServerTransport;
use Bayeux\Server\Transport\JSONPTransport;
use Bayeux\Server\Transport\JSONTransport;
use Bayeux\Api\Message;
use Bayeux\Api\Server\ServerMessage;
use Bayeux\Api\Server\SecurityPolicy;
use Bayeux\Api\Server\BayeuxServer\BayeuxServerListener;
use Bayeux\Api\Server\BayeuxServer\Extension;
use Bayeux\Api\Server\BayeuxServer;
use Bayeux\Api\Server\ServerChannel\ServerChannelListener;
use Bayeux\Api\Channel;


/**
 *
 * Options to configure the server are: <dl>
 * <tt>tickIntervalMs</tt><td>The time in milliseconds between ticks to check for timeouts etc</td>
 * <tt>sweepIntervalMs</tt><td>The time in milliseconds between sweeps of channels to remove
 * invalid subscribers and non-persistent channels</td>
 * </dl>
 */
class BayeuxServerImpl implements BayeuxServer
{
    const LOG_LEVEL = "logLevel";
    const OFF_LOG_LEVEL = 0;
    const CONFIG_LOG_LEVEL = 1;
    const INFO_LOG_LEVEL = 2;
    const DEBUG_LOG_LEVEL = 3;
    const JSON_CONTEXT = "jsonContext";

    private $_logger;
    private $_random = array();
    private $_listeners = array();
    private $_extensions = array();
    private $_sessions = array();
    private $_channels = array();
    private $_transports = array();
    private $_allowedTransports = array();
    private $_currentTransport = null;
    private $_options = array();
    private $_timeout;
    private $_handshakeAdvice = array();
    private $_policy;
    private $_logLevel = self::OFF_LOG_LEVEL;
    private $_jsonContext;
    private $_timer;



    public function __construct(array $transports = array())
    {
        $this->addTransport(new JSONTransport($this));
        $this->addTransport(new JSONPTransport($this));

        $this->_handshakeAdvice[Message::RECONNECT_FIELD] = Message::RECONNECT_HANDSHAKE_VALUE;
        $this->_handshakeAdvice[Message::INTERVAL_FIELD] = 0;
    }

    public function get(HandlerListener $handler, $name) {
        return $this->{$name};
    }


    public function getLogger()
    {
        return $this->_logger;
    }

    private function debug($message)
    {
        $args = func_get_args();
        array_shift($args);
/*         if ($this->_logLevel >= self::DEBUG_LOG_LEVEL) {
            $this->_logger->info($message, $args);
        } else {
            $this->_logger->debug($message, $args);
        } */
    }

    public function getLogLevel()
    {
        return $this->_logLevel;
    }


    /**
     */
    //@Override
    public function start() //throws Exception
    {
        $this->_logLevel = self::OFF_LOG_LEVEL;

        $logLevelValue = $this->getOption(self::LOG_LEVEL);
        if ($logLevelValue != null)
        {
            $this->_logLevel = intval($logLevelValue);
        }

        if ($this->_logLevel >= self::CONFIG_LOG_LEVEL)
        {
            throw new \Exception('debug');
            //for (Map.Entry<String, Object> entry : getOptions().entrySet())
            //    getLogger().info(entry.getKey() + "=" + entry.getValue());
        }

        $this->initializeMetaChannels();
        $this->initializeJSONContext();
        $this->initializeDefaultTransports();
        $allowedTransportNames = $this->getAllowedTransports();

        if (empty($allowedTransportNames)) {
            throw new \Exception("No allowed transport names are configured, there must be at least one");
        }

        foreach ($allowedTransportNames as $allowedTransportName)
        {
            $allowedTransport = $this->getTransport($allowedTransportName);
            if ($allowedTransport instanceof AbstractServerTransport) {
                $allowedTransport->init();
            }
        }

        //$this->_timer = new Timer("BayeuxServer@" . $this->hashCode(), true);

        $tick_interval = $this->getOption("tickIntervalMs", 97);
        if ($tick_interval > 0)
        {
            /*$this->_timer->schedule(new TimerTask()
            {
                @Override
                public void run()
                {
                    _timeout.tick(System.currentTimeMillis());
                }
            }, tick_interval, tick_interval);*/
        }

        $sweep_interval = $this->getOption("sweepIntervalMs", 997);
        if ($sweep_interval > 0)
        {
            /*$this->_timer.schedule(new TimerTask()
            {
                @Override
                public void run()
                {
                    doSweep();

                    final long now = System.currentTimeMillis();
                    for (ServerSessionImpl session : _sessions.values())
                    session.sweep(now);
                }
            }, sweep_interval, sweep_interval);*/
        }
    }


    /**
     * @see org.eclipse.jetty.util.component.AbstractLifeCycle#doStop()
     */
    //@Override
    public function stop() //throws Exception
    {
        //parent::doStop();

        $this->_listeners = array();
        $this->_extensions = array();
        $this->_sessions = array();
        $this->_channels = array();
        $this->_transports = array();
        $this->_allowedTransports = array();
        $this->_options = array();
        //$this->_timer
    }

    protected function initializeMetaChannels()
    {
        $this->createIfAbsent(Channel::META_HANDSHAKE);
        $this->createIfAbsent(Channel::META_CONNECT);
        $this->createIfAbsent(Channel::META_SUBSCRIBE);
        $this->createIfAbsent(Channel::META_UNSUBSCRIBE);
        $this->createIfAbsent(Channel::META_DISCONNECT);


        HandlerListener::setBayeuxServerImpl($this);
        $this->getChannel(Channel::META_HANDSHAKE)->addListener(new HandshakeHandler());
        $this->getChannel(Channel::META_CONNECT)->addListener(new ConnectHandler());
        $this->getChannel(Channel::META_SUBSCRIBE)->addListener(new SubscribeHandler());
        $this->getChannel(Channel::META_UNSUBSCRIBE)->addListener(new UnsubscribeHandler());
        $this->getChannel(Channel::META_DISCONNECT)->addListener(new DisconnectHandler());
    }

    protected function initializeJSONContext()
    {
        $option = $this->getOption(self::JSON_CONTEXT);
        if ($option == null)
        {
            $this->_jsonContext = new PHPJSONContextServer();
        }
        else
        {
            /*
            if ($option instanceof String)
            {
                Class<?> jsonContextClass = Thread.currentThread().getContextClassLoader().loadClass((String)option);
                if (JSONContext.Server.class.isAssignableFrom(jsonContextClass))
                {
                    _jsonContext = (JSONContext.Server)jsonContextClass.newInstance();
                }
                else
                {
                    throw new IllegalArgumentException("Invalid " + JSONContext.Server.class.getName() + " implementation class");
                }
            }
            else if (option instanceof JSONContext.Server)
            {
                _jsonContext = (JSONContext.Server)option;
            }
            else
            {
                throw new IllegalArgumentException("Invalid " + JSONContext.Server.class.getName() + " implementation class");
            }
            */
        }
        $this->_options[self::JSON_CONTEXT] = $this->_jsonContext;
    }


    /** Initialize the default transports.
     * <p>This method creates  a {@link JSONTransport} and a {@link JSONPTransport}.
     * If no allowed transport have been set then adds all known transports as allowed transports.
     */
    protected function initializeDefaultTransports()
    {
        if (empty($this->_allowedTransports))
        {
            foreach ($this->_transports as $t) {
                $this->_allowedTransports[] = $t->getName();
            }
        }
        //$this->_logger->info("Allowed Transports: " . $this->_allowedTransports);
    }


    public function startTimeout(Timeout\Task $task, $interval)
    {
        $this->_timeout->schedule($task, $interval);
    }


    public function cancelTimeout(Timeout\Task $task)
    {
        $task->cancel();
    }


    public function newChannelId($id)
    {
        if (isset($this->_channels[$id])) {
            return $this->_channels[$id]->getChannelId();
        } else {
            return new ChannelId($id);
        }
    }


    public function getOptions()
    {
        return $this->_options;
    }




    /** Get an option value as a long
     * @param name
     * @param dft The default value
     * @return long value
     */
    public function getOption($name, $dft = null)
    {
        if (! isset($this->_options[$name])) {
            return $dft;
        }

        return $this->_options[$name];
    }


    /**
     * @see org.cometd.bayeux.Bayeux#getOptionNames()
     */
    public function getOptionNames()
    {
        return array_keys($this->_options);
    }


    /**
     * @see org.cometd.bayeux.Bayeux#setOption(java.lang.String, java.lang.Object)
     */
    public function setOption($qualifiedName, $value)
    {
        $this->_options[$qualifiedName] = $value;
    }

    public function setOptions(array $options)
    {
        $this->_options = $options;
    }


    public function randomLong()
    {
        return uniqid();
    }


    public function setCurrentTransport(AbstractServerTransport $transport)
    {
        $this->_currentTransport = $transport;
    }


    public function getCurrentTransport()
    {
        return $this->_currentTransport;
    }


    public function getContext()
    {
        $transport = $this->_currentTransport;
        return $transport == null ? null : $transport->getContext();
    }


    public function getSecurityPolicy()
    {
        return $this->_policy;
    }

    public function createIfAbsent($channelName)
    {
        $initializers = func_get_args();
        array_shift($initializers);
        $this->createChannelIfAbsent($channelName, $initializers);
        return true;
    }

    private function createChannelIfAbsent($channelName, array $initializers = array())
    {
        $initialized = false;
        if (empty($this-> _channels[$channelName])) {
            $channel = null;
        } else {
            $channel = $this-> _channels[$channelName];
        }

        if ($channel === null)
        {
            $channelId = new ChannelId($channelName);
            // Be sure the parent is there
            $parentChannel = null;
            if ($channelId->depth() > 1)
            {
                $parentName = $channelId->getParent();
                // If the parent needs to be re-created, we are missing its initializers,
                // but there is nothing we can do: in this case, the application needs
                // to make the parent persistent through an initializer.
                $parentChannel = $this->createChannelIfAbsent($parentName);
            }

            $candidate = new ServerChannelImpl($this, $channelId, $parentChannel);
            if (empty($this->_channels[$channelName])) {
                $this->_channels[$channelName] = $candidate;
                $channel = null;
            } else {
                $channel = $this->_channels[$channelName];
            }

            if ($channel === null)
            {
                // My candidate channel was added to the map, so I'd better initialize it

                $channel = $candidate;
                $this->debug("Added channel {}", $channel);

                try
                {
                    foreach ($initializers as $initializer) {
                        $this->notifyConfigureChannel($initializer, $channel);
                    }

                    foreach ($this->_listeners as $listener)
                    {
                        if ($listener instanceof ServerChannel\Initializer) {
                            $this->notifyConfigureChannel($listener, $channel);
                        }
                    }
                }
                catch (\Exception $e)
                {
                    throw $e;
                    $channel->initialized();
                }

                foreach ($this->_listeners as $listener)
                {
                    if ($listener instanceof BayeuxServer\ChannelListener) {
                        $this->notifyChannelAdded($listener, $channel);
                    }
                }

                $initialized = true;
            }
        }
        else
        {
            $channel->resetSweeperPasses();
            // Double check if the sweeper removed this channel between the check at the top and here.
            // This is not 100% fool proof (e.g. this thread is preempted long enough for the sweeper
            // to remove the channel, but the alternative is to have a global lock)
            if (empty($this->_channels[$channelName])) {
                $this->_channels[$channelName] = $channel;
            }
        }
        // Another thread may add this channel concurrently, so wait until it is initialized
        $channel->waitForInitialized();
        return $channel;
    }

    private function notifyConfigureChannel(ConfigurableServerChannel\Initializer $listener, ServerChannel $channel)
    {
        try
        {
            $listener->configureChannel($channel);
        }
        catch (\Exception $x)
        {
            throw $x;
            _logger.info("Exception while invoking listener " + listener, x);
        }
    }

    private function notifyChannelAdded(ChannelListener $listener, ServerChannel $channel)
    {
        try
        {
            $listener->channelAdded($channel);
        }
        catch (\Exception $x)
        {
            _logger.info("Exception while invoking listener " + listener, x);
        }
    }


    public function getSessions()
    {
        return $this->_sessions;
    }


    public function getSession($clientId)
    {
        if ($clientId == null) {
            return null;
        }
        if (empty($this->_sessions[$clientId])) {
            return null;
        }
        return $this->_sessions[$clientId];
    }


    public function addServerSession(ServerSessionImpl $session)
    {
        $this->_sessions[$session->getId()] = $session;
        foreach ($this->_listeners as $listener)
        {
            if ($listener instanceof BayeuxServer\SessionListener) {
                $listener->sessionAdded($session);
            }
        }
    }


    /**
     * @param session
     * @param timedout
     * @return true if the session was removed and was connected
     */
    public function removeServerSession(ServerSession $session, $timedout)
    {
        //if ($this->_logger->isDebugEnabled()) {
        //    $this->_logger->debug("remove " . $session . ($timedout ? " timedout" : ""));
        //}

        $removed = null;
        if (!empty($this->_sessions[$session->getId()])) {
            $removed = $this->_sessions[$session->getId()];
            unset($this->_sessions[$session->getId()]);
        }

        if ($removed == $session)
        {
            foreach ($this->_listeners as $listener)
            {
                if ($listener instanceof BayeuxServer\SessionListener) {
                    $this->notifySessionRemoved($listener, $session, $timedout);
                }
            }

            return $session->removed($timedout);
        } else {
            return false;
        }
    }

    private function notifySessionRemoved(SessionListener $listener, ServerSession $session, $timedout)
    {
        try
        {
            $listener->sessionRemoved($session, $timedout);
        }
        catch (\Exception $x)
        {
            _logger.info("Exception while invoking listener " + listener, x);
        }
    }

    /**
     *
     * Enter description here ...
     * @param LocalSessionImpl $local
     * @param unknown_type $idHint
     * @return \Bayeux\Server\ServerSessionImpl
     */
    public function newServerSession(LocalSessionImpl $local = null, $idHint = null)
    {
        return new ServerSessionImpl($this, $local, $idHint);
    }


    public function newLocalSession($idHint)
    {
        return new LocalSessionImpl($this, $idHint);
    }


    /**
     * (non-PHPdoc)
     * @see Bayeux\Api\Server.BayeuxServer::newMessage()
     * return Message
     */
    public function newMessage(ServerMessage $tocopy = null)
    {
        if ($tocopy === null) {
            return new ServerMessageImpl();
        }

        $mutable = $this->newMessage();
        foreach ($tocopy as $key => $value) {
            $mutable[$key] = $value; //$tocopy[$key]);
        }
        return $mutable;
    }


    public function setSecurityPolicy(SecurityPolicy $securityPolicy)
    {
        $this->_policy=$securityPolicy;
    }


    public function addExtension(Extension $extension)
    {
        $this->_extensions[] = $extension;
    }


    public function removeExtension(Extension $extension)
    {
        $key = array_search(extension, $this->_extensions);
        if ($key !== false) {
            unset($this->_extensions[$key]);
        }
    }


    public function addListener(BayeuxServerListener $listener)
    {
        $this->_listeners[] = $listener;
    }


    /**
     * (non-PHPdoc)
     * @see Bayeux\Api\Server.BayeuxServer::getChannel()
     */
    public function getChannel($channelId)
    {
        if (isset($this->_channels[$channelId])) {
            return $this->_channels[$channelId];
        }

        return null;
    }


    public function getChannels()
    {
        return $this->_channels;
    }


    public function getChannelChildren(ChannelId $id)
    {
        $children = array();
        foreach ($this->_channels as $channel)
        {
            if ($id->isParentOf($channel->getChannelId())) {
                $children[] = $channel;
            }
        }
        return $children;
    }


    public function removeListener(BayeuxServerListener $listener)
    {
        $key = array_search($listener, $this->_listeners);
        if ($key !== false) {
            unset($this->_listeners[$key]);
        }
    }


    /** Extend and handle in incoming message.
     * @param session The session if known
     * @param message The message.
     * @return An unextended reply message
     */
    public function handle(ServerSessionImpl $session, ServerMessage\Mutable $message)
    {
        /* if ($this->_logger->isDebugEnabled()) {
            $this->_logger.debug(">  " + message + " " + session);
        } */

        $reply = null;
        if (! $this->extendRecv($session, $message) || $session != null && ! $session->extendRecv($message))
        {
            $reply = $this->createReply($message);
            $this->error($reply, "404::message deleted");
        }
        else
        {
            /* if ($this->_logger->isDebugEnabled()) {
                $this->_logger->debug(">> " + message);
            } */

            $channelName = $message->getChannel();

            $channel;
            if ($channelName == null)
            {
                $reply = $this->createReply($message);
                $this->error($reply, "400::channel missing");
            }
            else
            {
                $channel = $this->getChannel($channelName);
                if ($channel == null)
                {
                    $creationResult = $this->isCreationAuthorized($session, $message, $channelName);
                    if ($creationResult instanceof Authorizer\Result\Denied)
                    {
                        $reply = $this->createReply($message);
                        $denyReason = $creationResult->getReason();
                        $this->error($reply, "403:" . $denyReason . ":create denied");
                    }
                    else
                    {
                        $this->createIfAbsent($channelName);
                        $channel = $this->getChannel($channelName);
                    }
                }

                if ($channel != null)
                {
                    if ($channel->isMeta())
                    {
                        if ($session == null && ! (Channel::META_HANDSHAKE == $channelName))
                        {
                            $reply = $this->createReply($message);
                            $this->unknownSession($reply);
                        }
                        else
                        {
                            $this->doPublish($session, $channel, $message);
                            $reply = $message->getAssociated();
                        }
                    }
                    else
                    {
                        if ($session == null)
                        {
                            $reply = $this->createReply($message);
                            $this->unknownSession($reply);
                        }
                        else
                        {
                            $publishResult = $this->isPublishAuthorized($channel, $session, $message);
                            if ($publishResult instanceof Authorizer\Result\Denied)
                            {
                                $reply = $this->createReply($message);
                                $denyReason = $publishResult->getReason();
                                $this->error($reply, "403:" . $denyReason . ":publish denied");
                            }
                            else
                            {
                                $channel->publish($session, $message);
                                $reply = $this->createReply($message);
                                $reply->setSuccessful(true);
                            }
                        }
                    }
                }
            }
        }

        // Here the reply may be null if this instance is stopped concurrently

/*        if ($this->_logger->isDebugEnabled()) {
            $this->_logger->debug("<< " . $reply);
        }*/
        return $reply;
    }

    public function isPublishAuthorized(ServerChannel $channel, ServerSession $session, ServerMessage $message)
    {
        if ($this->_policy != null && !$this->_policy->canPublish($this, $session, $channel, $message))
        {
            //$this->_logger->warn("{} denied Publish@{} by {}", $session, $channel->getId(), $this->_policy);
            return Authorizer\Result::deny("denied_by_security_policy");
        }
        return $this->isOperationAuthorized(Authorizer\Operation::PUBLISH, $session, $message, $channel->getChannelId());
    }

    public function isSubscribeAuthorized(ServerChannel $channel, ServerSession $session, ServerMessage $message)
    {
        if ($this->_policy != null && !$this->_policy->canSubscribe($this, $session, $channel, $message))
        {
            //$this->_logger->warn("{} denied Publish@{} by {}", $session, $channel, $this->_policy);
            return Authorizer\Result::deny("denied_by_security_policy");
        }
        return $this->isOperationAuthorized(Authorizer\Operation::SUBSCRIBE, $session, $message, $channel->getChannelId());
    }

    public function isCreationAuthorized(ServerSession $session, ServerMessage $message, $channel)
    {
        if ($this->_policy != null && !$this->_policy->canCreate($this, $session, $channel, $message))
        {
            //$this->_logger->warn("{} denied Create@{} by {}", $session, $message->getChannel(), $this->_policy);
            return Authorizer\Result::deny("denied_by_security_policy");
        }
        return $this->isOperationAuthorized(Authorizer\Operation::CREATE, $session, $message, new ChannelId($channel));
    }

    private function isOperationAuthorized(/*Authorizer\Operation*/ $operation, ServerSession $session, ServerMessage $message, ChannelId $channelId)
    {
        $channels = array();
        foreach ($channelId->getWilds() as $wildName)
        {
            if (!empty($this->_channels[$wildName])) {
                $channels[] = $this->_channels[$wildName];
            }
        }

        if (!empty($this->_channels[$channelId->toString()])) {
            $channels[] = $this->_channels[$channelId->toString()];
        }

        $called = false;
        $result = Authorizer\Result::ignore();
        foreach ($channels as $channel)
        {
            foreach ($channel->getAuthorizers() as $authorizer)
            {
                $called = true;
                $authorization = $authorizer->authorize($operation, $channelId, $session, $message);
                $this->_logger->debug("Authorizer {} on channel {} {} {} for channel {}", $authorizer, $channel, $authorization, $operation, $channelId);
                if ($authorization instanceof Authorizer\Result\Denied)
                {
                    $result = $authorization;
                    break;
                }
                else if ($authorization instanceof Authorizer\Result\Granted)
                {
                    $result = $authorization;
                }
            }
        }

        if (!$called)
        {
            $result = Authorizer\Result::grant();
            //$this->_logger->debug("No authorizers, {} for channel {} {}", $operation, $channelId, $result);
        }
        else
        {
            if ($result instanceof Authorizer\Result\Ignored)
            {
                $result = Authorizer\Result::deny("denied_by_not_granting");
                //$this->_logger->debug("No authorizer granted {} for channel {}, authorization {}", operation, channelId, result);
            }
            else if ($result instanceof Authorizer\Result\Granted)
            {
                //$this->_logger->debug("No authorizer denied {} for channel {}, authorization {}", $operation, $channelId, $result);
            }
        }

        // We need to make sure that this method returns a boolean result (granted or denied)
        // but if it's denied, we need to return the object in order to access the deny reason
        //assert !(result instanceof Authorizer.Result.Ignored); //FIXME: assert
        return $result;
    }


    public function doPublish(ServerSessionImpl $from, ServerChannelImpl $to, ServerMessage\Mutable $mutable)
    {
        // check the parent channels
        $parent = $to->getChannelId()->getParent();
        while ($parent != null)
        {
            if (! isset($this->_channels[$parent])) {
                return; // remove in progress
            }

            $c = $this->_channels[$parent];
            if ($c->isLazy()) {
                $mutable->setLazy(true);
            }
            $parent = $c->getChannelId()->getParent();
        }

        // Get the array of listening channels
        $wildIds = $to->getChannelId()->getWilds();
        $wild_channels = array();
        for ( $i = count($wildIds); $i-- > 0; ) {
            if (isset($this->_channels[$wildIds[$i]])) {
                $wild_channels[$i] = $this->_channels[$wildIds[$i]];
            } else {
                $wild_channels[$i] = null;
            }
        }

        // Call the wild listeners
        foreach ($wild_channels as $channel)
        {
            if ($channel == null) {
                continue;
            }

            if ($channel->isLazy()) {
                $mutable->setLazy(true);
            }
            foreach ($channel->getListeners() as $listener) {
                if ($listener instanceof ServerChannel\MessageListener) {
                    if (! $this->notifyOnMessage($listener, $from, $to, $mutable)) {
                        return;
                    }
                }
            }
        }

        // Call the leaf listeners
        if ($to->isLazy()) {
            $mutable->setLazy(true);
        }

        foreach ($to->getListeners() as $listener) {
            if ($listener instanceof ServerChannel\MessageListener) {
                if (! $this->notifyOnMessage($listener, $from, $to, $mutable)) {
                    return;
                }
            }
        }

        // Exactly at this point, we convert the message to JSON and therefore
        // any further modification will be lost.
        // This is an optimization so that if the message is sent to a million
        // subscribers, we generate the JSON only once.
        // From now on, user code is passed a ServerMessage reference (and not
        // ServerMessage.Mutable), and we attempt to return immutable data
        // structures, even if it is not possible to guard against all cases.
        // For example, it is impossible to prevent things like
        // ((CustomObject)serverMessage.getData()).change() or
        // ((Map)serverMessage.getExt().get("map")).put().
        $this->freeze($mutable);

        // Call the wild subscribers
        $wild_subscribers=null;
        if (ChannelId::staticIsBroadcast($mutable->getChannel())) {
            foreach ($wild_channels as $channel)
            {
                if ($channel == null) {
                    continue;
                }

                foreach ($channel->getSubscribers() as $session )
                {
                    if ($wild_subscribers==null) {
                        $wild_subscribers = array();
                    }

                    if (! in_array($session->getId(), $wild_subscribers)) {
                        $wild_subscribers[] = $session->getId();
                        $session->doDeliver($from, $mutable);
                    }
                }
            }
        }

        // Call the leaf subscribers
        foreach ($to->getSubscribers() as $session)
        {
            if ($wild_subscribers == null || ! in_array($session->getId(), $wild_subscribers)) {
                $session->doDeliver($from, $mutable);
            }
        }

        // Meta handlers
        if ($to->isMeta())
        {
            foreach ($to->getListeners() as $listener) {

                if ($listener instanceof BayeuxServerImpl\HandlerListener) {
                    $listener->onMessage($from, $mutable);
                }
            }
        }
    }

    public function freeze(ServerMessage\Mutable $message)
    {
        if ($message->isFrozen()) {
            return;
        }
        $json = $this->_jsonContext->generate($message);
        $message->freeze($json);
    }

    private function notifyOnMessage(ServerChannel\MessageListener $listener, ServerSession $from, ServerChannel $to, ServerMessage\Mutable $mutable)
    {
        try
        {
            return $listener->onMessage($from, $to, $mutable);
        }
        catch (\Exception $x)
        {
            echo "Exception while invoking listener " . $listener . $x;
            //_logger.info("Exception while invoking listener " + listener, x);
            return true;
        }
    }



    public function extendReply(ServerSessionImpl $from, ServerSessionImpl $to = null, ServerMessage\Mutable $reply)
    {
        if (!$this->extendSend($from, $to, $reply)) {
            return null;
        }

        if ($to != null)
        {
            if ($reply->isMeta())
            {
                if(!$to->extendSendMeta($reply)) {
                    return null;
                }
            }
            else
            {
                $newReply = $to->extendSendMessage($reply);
                if ($newReply == null)
                {
                    $reply = null;
                }
                else if ($newReply != $reply)
                {
                    if ($newReply instanceof ServerMessage\Mutable) {
                        $reply = $newReply;
                    } else {
                        $reply = $this->newMessage($newReply);
                    }
                }
            }
        }

        return $reply;
    }


    protected function extendRecv(ServerSessionImpl $from, ServerMessage\Mutable $message)
    {
        if ($message->isMeta())
        {
            foreach ($this->_extensions as $ext) {
                if (!$ext->rcvMeta($from, $message)) {
                    return false;
                }
            }
        }
        else
        {
            foreach ($this->_extensions as $ext) {
                if (!$ext->rcv($from, $message)) {
                    return false;
                }
            }
        }
        return true;
    }


    public function extendSend(ServerSessionImpl $from, ServerSessionImpl $to = null, ServerMessage\Mutable $message)
    {
        if ($message->isMeta())
        {
            $i = new \ArrayIterator(array_reverse($this->_extensions, true));
            while($i->valid())
            {
                if (! $i->current()->sendMeta($to, $message))
                {
                    /*if ($this->_logger->isDebugEnabled()) {
                        $this->_logger->debug("!  " . $message);
                    }*/
                    return false;
                }
                $i->next();
            }
        }
        else
        {
            $i = new \ArrayIterator(array_reverse($this->_extensions, true));
            while($i->valid())
            {
                if (! $i->current()->send($from, $to, $message))
                {
                    /*if ($this->_logger->isDebugEnabled()) {
                        $this->_logger->debug("!  " . $message);
                    }*/
                    return false;
                }

                $i->next();
            }
        }

        /*if ($this->_logger->isDebugEnabled()) {
            $this->_logger->debug("<  " . message);
        }*/
        return true;
    }


    public function removeServerChannel(ServerChannelImpl $channel)
    {
        if (empty($this->_channels[$channel->getId()])) {
            return false;
        }

        if ($this->_channels[$channel->getId()] !== $channel) {
            return false;
        }

        unset($this->_channels[$channel->getId()]);
        //if($this->_channels->remove($channel->getId(), $channel))
        if (empty($this->_channels[$channel->getId()]))
        {
            //$this->_logger->debug("removed {}", $channel);
            foreach ($this->_listeners as $listener)
            {
                if ($listener instanceof BayeuxServer\ChannelListener) {
                    $listener->channelRemoved($channel->getId());
                }
            }
            return true;
        }
        return false;
    }


    public function getListeners()
    {
        return $this->_listeners;
    }


    public function getKnownTransportNames()
    {
        return array_keys($this->_transports);
    }


    public function getTransport($transport)
    {
        return $this->_transports[$transport];
    }




    public function addTransport(ServerTransport $transport)
    {
        $this->_transports[$transport->getName()] = $transport;
    }


    public function setTransports(array $transports)
    {
        $this->_transports = array();
        foreach ($transports as $transport) {
            $this->addTransport($transport);
        }
    }


    public function getAllowedTransports()
    {
        return $this->_allowedTransports;
    }


    public function setAllowedTransports(array $allowed)
    {
        $this->_allowedTransports = array();
        foreach ($allowed as $transport)
        {
            if (!empty($this->_transports[$transport])) {
                $this->_allowedTransports[] = $transport;
            }
        }
    }


    protected function unknownSession(Servermessage\Mutable $reply)
    {
        $this->error($reply,"402::Unknown client");
        if (Channel::META_HANDSHAKE == $reply->getChannel() || Channel::META_CONNECT == $reply->getChannel()) {
            $reply[Message::ADVICE_FIELD] =  $this->_handshakeAdvice;
        }
    }


    protected function error(ServerMessage\Mutable $reply, $error)
    {
        $reply[Message::ERROR_FIELD] = $error;
        $reply->setSuccessful(false);
    }


    public function createReply(ServerMessage\Mutable $message)
    {
        $reply = $this->newMessage();
        $message->setAssociated($reply);
        $reply->setAssociated($message);

        $reply->setChannel($message->getChannel());
        $id = $message->getId();
        if ($id != null) {
            $reply->setId($id);
        }
        return $reply;
    }


    public function sweep()
    {
        foreach ($this->_channels as $channel) {
            $channel->sweep();
        }

        foreach ($this->_transports as $transport)
        {
            if ($transport instanceof AbstractServerTransport) {
                $transport->sweep();
            }
        }

        $now = microtime(true);
        foreach ($this->_sessions as $session) {
            $session->sweep($now);
        }
    }


    public function dump()
    {
        $b = '';

        $children = array();
        if ($this->_policy!=null) {
            $children[] = $this->_policy;
        }

        foreach ($this->_channels as $channel)
        {
            if ($channel.getChannelId()->depth()==1) {
                $children[] = $channel;
            }
        }

        $leaves=count($children);
        $i=0;
        foreach ($children as $child)
        {
            $b .= " +-";
            if ($child instanceof ServerChannelImpl) {
                throw new \Exception("Verificar o return");
                $b .= $child->dump((++$i == $leaves ) ? "   ":" | ");
            } else {
                $b = $child->toString() . "\n";
            }
        }

        return b.toString();
    }


}


