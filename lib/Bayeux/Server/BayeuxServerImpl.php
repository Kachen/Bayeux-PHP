<?php

namespace Bayeux\Server;

use Bayeux\Api\Message;

use Bayeux\Api\Server\ServerMessage;


use Bayeux\Api\Server\SecurityPolicy;
use Bayeux\Api\Server\BayeuxServer\BayeuxServerListener;
use Bayeux\Api\Server\BayeuxServer\Extension;
use Bayeux\Api\Server\BayeuxServer;
use Bayeux\Api\Server\ServerChannel\ServerChannelListener;
use Bayeux\Api\Channel;

/* ------------------------------------------------------------ */
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

    private $_logger;
    private $_listeners = array();
    private $_extensions = array();
    private $_sessions = array();
    private $_channels = array();
    private $_transports = array();
    private $_allowedTransports = array();
    private $_currentTransport = array();
    private $_options = array();
    private $_timeout;

    private $_timer;
    private $_handshakeAdvice;
    private $_policy;

    /* ------------------------------------------------------------ */
    public function __construct(array $transports = array())
    {
        //$this->_logger = Log::getLogger(getClass().getName() + "@" + System.identityHashCode(this));
        //$this->_timeout = new Timeout();

        //$this->_timer = new Timer();
        $this->_handshakeAdvice = '{"reconnect":"handshake","interval":500}';
        $this->_policy=new DefaultSecurityPolicy();

        if (empty($transports)) {
            $this->addTransport(new JSONTransport($this));
            $this->addTransport(new JSONPTransport($this));
        } else {
            $this->setTransports(transports);
        }
    }

    /* ------------------------------------------------------------ */
    public function getLogger()
    {
        return $this->_logger;
    }

    /* ------------------------------------------------------------ */
    /**
     * @see org.eclipse.jetty.util.component.AbstractLifeCycle#doStart()
     */
    //@Override
    protected function doStart() //throws Exception
    {
        parent::doStart();

        $logLevel = self::OFF_LOG_LEVEL;
        $logLevelValue = $this->getOption(self::LOG_LEVEL);
        if ($logLevelValue != null)
        {
            $this->getLogger()->setDebugEnabled($logLevel > self::INFO_LOG_LEVEL);
        }

        if ($logLevel >= self::CONFIG_LOG_LEVEL)
        {
            throw new \Ecception('deboug');
            //for (Map.Entry<String, Object> entry : getOptions().entrySet())
            //    getLogger().info(entry.getKey() + "=" + entry.getValue());
        }

        $this->initializeMetaChannels();

        $this->initializeDefaultTransports();

        $allowedTransportNames = $this->getAllowedTransports();
        if ($this->allowedTransportNames->isEmpty()) {
            throw new IllegalStateException("No allowed transport names are configured, there must be at least one");
        }

        foreach ($this->allowedTransportNames as $allowedTransportName)
        {
            $allowedTransport = $this->getTransport($allowedTransportName);
            if (allowedTransport instanceof AbstractServerTransport) {
                $allowedTransport->init();
            }
        }

        $this->_timer = new Timer("BayeuxServer@" . $this->hashCode(), true);
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

    /* ------------------------------------------------------------ */
    /**
     * @see org.eclipse.jetty.util.component.AbstractLifeCycle#doStop()
     */
    //@Override
    protected function doStop() //throws Exception
    {
        parent::doStop();

        $this->_listeners.clear();
        $this->_extensions.clear();
        $this->_sessions.clear();
        $this->_channels.clear();
        $this->_transports.clear();
        $this->_allowedTransports.clear();
        $this->_options.clear();
        $this->_timer.cancel();
    }

    protected function initializeMetaChannels()
    {
        $this->createIfAbsent(Channel::META_HANDSHAKE);
        $this->createIfAbsent(Channel::META_CONNECT);
        $this->createIfAbsent(Channel::META_SUBSCRIBE);
        $this->createIfAbsent(Channel::META_UNSUBSCRIBE);
        $this->createIfAbsent(Channel::META_DISCONNECT);
        $this->getChannel(Channel::META_HANDSHAKE)->addListener(new HandshakeHandler());
        $this->getChannel(Channel::META_CONNECT)->addListener(new ConnectHandler());
        $this->getChannel(Channel::META_SUBSCRIBE)->addListener(new SubscribeHandler());
        $this->getChannel(Channel::META_UNSUBSCRIBE)->addListener(new UnsubscribeHandler());
        $this->getChannel(Channel::META_DISCONNECT)->addListener(new DisconnectHandler());
    }

    /* ------------------------------------------------------------ */
    /** Initialize the default transports.
     * <p>This method creates  a {@link JSONTransport} and a {@link JSONPTransport}.
     * If no allowed transport have been set then adds all known transports as allowed transports.
     */
    protected function initializeDefaultTransports()
    {
        if ($this->_allowedTransports.size()==0)
        {
            foreach ($this->_transports as $t) {
                $this->_allowedTransports->add($t->getName());
            }
        }
        $this->_logger->info("Allowed Transports:" . $this->_allowedTransports);
    }

    /* ------------------------------------------------------------ */
    public function startTimeout(Timeout\Task $task, $interval)
    {
        $this->_timeout->schedule($task, $interval);
    }

    /* ------------------------------------------------------------ */
    public function cancelTimeout(Timeout\Task $task)
    {
        $task->cancel();
    }

    /* ------------------------------------------------------------ */
    public function newChannelId($id)
    {
        $channel = $this->_channels[$id];
        if ($channel != null) {
            return $channel->getChannelId();
        }
        return new ChannelId($id);
    }

    /* ------------------------------------------------------------ */
    public function getOptions()
    {
        return $this->_options;
    }

    /* ------------------------------------------------------------ */

    /* ------------------------------------------------------------ */
    /** Get an option value as a long
     * @param name
     * @param dft The default value
     * @return long value
     */
    public function getOption($name, $dft = null)
    {
        $val = $this->getOption(name);
        if ($val == null) {
            return dft;
        }
        //if ($val instanceof Number)
        //return ((Number)val).longValue();
        //return Long.parseLong(val.toString());
        return $val;
    }

    /* ------------------------------------------------------------ */
    /**
     * @see org.cometd.bayeux.Bayeux#getOptionNames()
     */
    public function getOptionNames()
    {
        return array_keys($this->_options);
    }

    /* ------------------------------------------------------------ */
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

    /* ------------------------------------------------------------ */
    public function randomLong()
    {
        return uniqid();
    }

    /* ------------------------------------------------------------ */
    public function setCurrentTransport(AbstractServerTransport $transport)
    {
        $this->_currentTransport[] = $transport;
    }

    /* ------------------------------------------------------------ */
    public function getCurrentTransport()
    {
        return $this->_currentTransport;
    }

    /* ------------------------------------------------------------ */
    public function getContext()
    {
        $transport=$this->_currentTransport;
        return $transport == null ? null : $transport->getContext();
    }

    /* ------------------------------------------------------------ */
    public function getSecurityPolicy()
    {
        return $this->_policy;
    }

    /* ------------------------------------------------------------ */
    public function createIfAbsent($channelId /*ServerChannel\Initializer... initializers*/)
    {
        if (empty($this->_channels[$channelId])) {
            return false;
        }

        $id = new ChannelId($channelId);
        if ($id->depth()>1) {
            $this->createIfAbsent(id.getParent());
        }

        $proposed = new ServerChannelImpl($this, $id);
        $channel = $this->_channels.putIfAbsent($channelId, $proposed);
        if ($channel==null)
        {
            // My proposed channel was added to the map, so I'd better initialize it!
            $channel=$proposed;
            $this->_logger->debug("added {}", $channel);
            try
            {
                foreach ($this->initializers as $initializer) {
                    $initializer->configureChannel($channel);
                }
                foreach ($this->_listeners as $listener)
                {
                    if ($listener instanceof ServerChannel\Initializer) {
                        $listener->configureChannel($channel);
                    }
                }
            } catch(\Exception $e) {
                $channel->initialized();
            }

            foreach ($this->_listeners as $listener)
            {
                if ($listener instanceof BayeuxServer\ChannelListener) {
                    $listener->channelAdded($channel);
                }
            }

            return true;
        }

        // somebody else added it before me, so wait until it is initialized
        $channel->waitForInitialized();
        return false;
    }

    /* ------------------------------------------------------------ */
    public function getSessions()
    {
        return $this->_sessions;
    }

    /* ------------------------------------------------------------ */
    public function getSession($clientId)
    {
        if ($clientId==null) {
            return null;
        }
        if (empty($this->_sessions[$clientId])) {
            return null;
        }
        return $this->_sessions[$clientId];
    }

    /* ------------------------------------------------------------ */
    protected function addServerSession(ServerSessionImpl $session)
    {
        $this->_sessions[$session->getId()] = $session;
        foreach ($this->_listeners as $listener)
        {
            if ($istener instanceof BayeuxServer\SessionListener) {
                $listener->sessionAdded($session);
            }
        }
    }

    /* ------------------------------------------------------------ */
    /**
     * @param session
     * @param timedout
     * @return true if the session was removed and was connected
     */
    public function removeServerSession(ServerSession $session, $timedout)
    {
        if ($this->_logger->isDebugEnabled()) {
            $this->_logger->debug("remove " . $session . ($timedout ? " timedout" : ""));
        }

        $removed = $this->_sessions->remove($session->getId());

        if($removed==$session)
        {
            $connected = $session->removed($timedout);

            foreach ($this->_listeners as $listener)
            {
                if ($listener instanceof BayeuxServer\SessionListener) {
                    $listener->sessionRemoved($session, $timedout);
                }
            }

            return $connected;
        } else {
            return false;
        }
    }

    /* ------------------------------------------------------------ */
    protected function newServerSession(LocalSessionImpl $local = null, $idHint = null)
    {
        return new ServerSessionImpl($this, $local, $idHint);
    }

    /* ------------------------------------------------------------ */
    public function newLocalSession($idHint)
    {
        return new LocalSessionImpl($this, $idHint);
    }

    /* ------------------------------------------------------------ */
    public function newMessage(ServerMessage $tocopy = null)
    {
        if ($tocopy === null) {
            return new ServerMessageImpl();
        }

        $mutable = new Message();
        foreach ($tocopy as $key => $value) {
            $mutable[$key] = $value; //$tocopy[$key]);
        }
        return $mutable;
    }

    /* ------------------------------------------------------------ */
    public function setSecurityPolicy(SecurityPolicy $securityPolicy)
    {
        $this->_policy=$securityPolicy;
    }

    /* ------------------------------------------------------------ */
    public function addExtension(Extension $extension)
    {
        $this->_extensions[$extension];
    }

    /* ------------------------------------------------------------ */
    public function removeExtension(Extension $extension)
    {
        $key = array_search(extension, $this->_extensions);
        if ($key !== false) {
            unset($this->_extensions[$key]);
        }
    }

    /* ------------------------------------------------------------ */
    public function addListener(BayeuxServerListener $listener)
    {
        if ($listener == null) {
            throw new NullPointerException();
        }
        $this->_listeners[] = $listener;
    }

    /* ------------------------------------------------------------ */
    public function getChannel($channelId)
    {
        return $this->_channels[$channelId];
    }

    /* ------------------------------------------------------------ */
    public function getChannels()
    {
        return $this->_channels;
    }

    /* ------------------------------------------------------------ */
    public function getChannelChildren(ChannelId $id)
    {
        $children = array();
        foreach ($this->_channels as $value )
        {
            if ($id->isParentOf($channel->getChannelId())) {
                $children[] = $channel;
            }
        }
        return $children;
    }

    /* ------------------------------------------------------------ */
    public function removeListener(BayeuxServerListener $listener)
    {
        $key = array_search($listener, $this->_listeners);
        if ($key !== false) {
            unset($this->_listeners[$key]);
        }
    }

    /* ------------------------------------------------------------ */
    /** Extend and handle in incoming message.
     * @param session The session if known
     * @param message The message.
     * @return An unextended reply message
     */
    public function handle(ServerSessionImpl $session, ServerMessage\Mutable $message)
    {
        if ($this->_logger->isDebugEnabled()) {
            $this->_logger.debug(">  " + message + " " + session);
        }

        $reply = null;
        if (!$this->extendRecv($session, $message) || $session != null && !$session->extendRecv($message))
        {
            $reply = $this->createReply(message);
            $this->error($reply, "404::message deleted");
        }
        else
        {
            if ($this->_logger->isDebugEnabled()) {
                $this->_logger->debug(">> " + message);
            }

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
                        $reply = createReply(message);
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
                        if ($session == null && !Channel::META_HANDSHAKE == $channelName)
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
                            $this->unknownSession(reply);
                        }
                        else
                        {
                            $publishResult = $this->isPublishAuthorized($channel, $session, $message);
                            if ($publishResult instanceof Authorizer\Result\Denied)
                            {
                                $reply = $this->createReply(message);
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

        if ($this->_logger->isDebugEnabled()) {
            $this->_logger->debug("<< " . $reply);
        }
        return $reply;
    }

    private function isPublishAuthorized(ServerChannel $channel, ServerSession $session, ServerMessage $message)
    {
        if ($this->_policy != null && !$this->_policy->canPublish($this, $session, $channel, $message))
        {
            $this->_logger->warn("{} denied Publish@{} by {}", $session, $channel->getId(), $this->_policy);
            return Authorizer\Result\deny("denied_by_security_policy");
        }
        return $this->isOperationAuthorized(Authorizer\Operation::PUBLISH, $session, $message, $channel->getChannelId());
    }

    private function isSubscribeAuthorized(ServerChannel $channel, ServerSession $session, ServerMessage $message)
    {
        if ($this->_policy != null && !$this->_policy->canSubscribe($this, $session, $channel, $message))
        {
            $this->_logger->warn("{} denied Publish@{} by {}", $session, $channel, $this->_policy);
            return Authorizer\Result\deny("denied_by_security_policy");
        }
        return $this->isOperationAuthorized(Authorizer\Operation::SUBSCRIBE, $session, $message, $channel->getChannelId());
    }

    private function isCreationAuthorized(ServerSession $session, ServerMessage $message, $channel)
    {
        if ($this->_policy != null && !$this->_policy->canCreate(this, $session, $channel, $message))
        {
            $this->_logger->warn("{} denied Create@{} by {}", $session, $message->getChannel(), $this->_policy);
            return Authorizer\Result\deny("denied_by_security_policy");
        }
        return $this->isOperationAuthorized(\Authorizer\Operation::CREATE, $session, $message, new ChannelId($channel));
    }

    private function isOperationAuthorized(Authorizer\Operation $operation, ServerSession $session, ServerMessage $message, ChannelId $channelId)
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
        $result = Authorizer\Result\ignore();
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
            $this->_logger->debug("No authorizers, {} for channel {} {}", $operation, $channelId, $result);
        }
        else
        {
            if ($result instanceof Authorizer\Result\Ignored)
            {
                $result = Authorizer\Result\deny("denied_by_not_granting");
                $this->_logger->debug("No authorizer granted {} for channel {}, authorization {}", operation, channelId, result);
            }
            else if ($result instanceof Authorizer\Result\Granted)
            {
                $this->_logger->debug("No authorizer denied {} for channel {}, authorization {}", $operation, $channelId, $result);
            }
        }

        // We need to make sure that this method returns a boolean result (granted or denied)
        // but if it's denied, we need to return the object in order to access the deny reason
        //assert !(result instanceof Authorizer.Result.Ignored); //FIXME: assert
        return $result;
    }

    /* ------------------------------------------------------------ */
    protected function doPublish(ServerSessionImpl $from, ServerChannelImpl $to, ServerMessage\Mutable $mutable)
    {
        // check the parent channels
        $parent = $to->getChannelId()->getParent();
        while ($parent != null)
        {
            if (empty($this->_channels[$parent])) {
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
        for ( $i=count($wildIds); $i-- >0; ) {
            $wild_channels[$i]=$this->_channels->get($wildIds[$i]);
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
            foreach ($channel->getListeners() as $listener)
            if ($listener instanceof MessageListener) {
                if (!$listener->onMessage($from, $to, $mutable)) {
                    return;
                }
            }
        }

        // Call the leaf listeners
        if ($to->isLazy()) {
            $mutable->setLazy(true);
        }
        foreach ($to->getListeners() as $listener) {
            if ($listener instanceof MessageListener) {
                if (!$listener->onMessage($from, $to, $mutable)) {
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
        $mutable->freeze();

        // Call the wild subscribers
        $wild_subscribers=null;
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

                if (in_array($session->getId(), $wild_subscribers)) {
                    $wild_subscribers[] = $session->getId();
                    $session->doDeliver($from, $mutable);
                }
            }
        }

        // Call the leaf subscribers
        foreach ($to->getSubscribers() as $session)
        {
            if ($wild_subscribers==null || !in_array($session->getId(), $wild_subscribers)) {
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


    /* ------------------------------------------------------------ */
    public function extendReply(ServerSessionImpl $from, ServerSessionImpl $to, ServerMessage\Mutable $reply)
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

    /* ------------------------------------------------------------ */
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

    /* ------------------------------------------------------------ */
    protected function extendSend(ServerSessionImpl $from, ServerSessionImpl $to, ServerMessage\Mutable $message)
    {
        if ($message->isMeta())
        {
            $i = $this->_extensions->listIterator($this->_extensions.size());
            while($i->hasPrevious())
            {
                if (!$i->previous()->sendMeta($to, $message))
                {
                    if ($this->_logger->isDebugEnabled()) {
                        $this->_logger->debug("!  " . $message);
                    }
                    return false;
                }
            }
        }
        else
        {
            $i = $this->_extensions->listIterator($this->_extensions.size());
            while($i->hasPrevious())
            {
                if (!$i->previous()->send($from, $to, $message))
                {
                    if ($this->_logger->isDebugEnabled()) {
                        $this->_logger->debug("!  " . $message);
                    }
                    return false;
                }
            }
        }

        if ($this->_logger->isDebugEnabled()) {
            $this->_logger->debug("<  " . message);
        }
        return true;
    }

    /* ------------------------------------------------------------ */
    public function removeServerChannel(ServerChannelImpl $channel)
    {
        if($this->_channels->remove($channel->getId(), $channel))
        {
            $this->_logger->debug("removed {}", $channel);
            foreach ($this->_listeners as $listener )
            {
                if ($listener instanceof BayeuxServer\ChannelListener) {
                    $listener->channelRemoved($channel->getId());
                }
            }
            return true;
        }
        return false;
    }

    /* ------------------------------------------------------------ */
    public function getListeners()
    {
        return $this->_listeners;
    }

    /* ------------------------------------------------------------ */
    public function getKnownTransportNames()
    {
        return array_keys($this->_transports);
    }

    /* ------------------------------------------------------------ */
    public function getTransport($transport)
    {
        return $this->_transports[$transport];
    }

    /* ------------------------------------------------------------ */

    /* ------------------------------------------------------------ */
    public function addTransport(ServerTransport $transport)
    {
        $this->_transports[$transport->getName()] = $transport;
    }

    /* ------------------------------------------------------------ */
    public function setTransports(array $transports)
    {
        $this->_transports = array();
        foreach ($transports as $transport) {
            $this->addTransport($transport);
        }
    }

    /* ------------------------------------------------------------ */
    public function getAllowedTransports()
    {
        return $this->_allowedTransports;
    }

    /* ------------------------------------------------------------ */
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

    /* ------------------------------------------------------------ */
    protected function unknownSession(Servermessage\Mutable $reply)
    {
        $this->error($reply,"402::Unknown client");
        if (Channel::META_HANDSHAKE == $reply->getChannel() || Channel::META_CONNECT == $reply->getChannel()) {
            $reply[Message::ADVICE_FIELD] =  $this->_handshakeAdvice;
        }
    }

    /* ------------------------------------------------------------ */
    protected function error(ServerMessage\Mutable $reply, $error)
    {
        $reply[Message::ERROR_FIELD] = $error;
        $reply->setSuccessful(false);
    }

    /* ------------------------------------------------------------ */
    protected function createReply(ServerMessage\Mutable $message)
    {
        $reply=$this->newMessage();
        $message->setAssociated($reply);
        $reply->setAssociated($message);

        $reply->setChannel($message->getChannel());
        $id=$message->getId();
        if ($id != null) {
            $reply->setId($id);
        }
        return $reply;
    }

    /* ------------------------------------------------------------ */
    public function doSweep()
    {
        foreach ($this->_channels as $channel) {
            $channel->doSweep();
        }

        foreach ($this->_transports as $transport)
        {
            if ($transport instanceof AbstractServerTransport) {
                $transport->doSweep();
            }
        }
    }

    /* ------------------------------------------------------------ */
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


/* ------------------------------------------------------------ */
/* ------------------------------------------------------------ */
abstract class HandlerListener implements ServerChannelListener
{
    protected function isSessionUnknown(ServerSession $session)
    {
        return $session == null || $this->getSession($session->getId()) == null;
    }

    public abstract function onMessage(ServerSessionImpl $from, ServerMessage\Mutable $message);
}

class HandshakeHandler extends HandlerListener
{
    public function onMessage(ServerSessionImpl $session, ServerMessage\Mutable $message)
    {
        if (session==null) {
            $session = $this->newServerSession();
        }

        $reply=$this->createReply($message);

        if ($this->_policy != null && !$this->_policy->canHandshake(BayeuxServerImplThis, $session, $message))
        {
            $this->error($reply,"403::Handshake denied");
            // The user's SecurityPolicy may have customized the response's advice
            $advice = $reply->getAdvice(true);
            if (!$advice[Message::RECONNECT_FIELD]) {
                $advice[Message::RECONNECT_FIELD] = Message::RECONNECT_NONE_VALUE;
            }
            return;
        }

        $session->handshake();
        $this->addServerSession($session);

        $reply->setSuccessful(true);
        $reply[Message::CLIENT_ID_FIELD] = session.getId();
        $reply[Message::VERSION_FIELD] = "1.0";
        $reply[Message::MIN_VERSION_FIELD] = "1.0";
        $reply[Message::SUPPORTED_CONNECTION_TYPES_FIELD]  = $this->getAllowedTransports();
    }
}

class ConnectHandler extends HandlerListener
{
    public function onMessage(ServerSessionImpl $session, ServerMessage\Mutable $message)
    {
        $reply=$this->createReply($message);

        if ($this->isSessionUnknown($session))
        {
            $this->unknownSession($reply);
            return;
        }

        $session->connect();

        // Handle incoming advice
        $adviceIn = $message->getAdvice();
        if ($adviceIn != null)
        {
            $timeout = $adviceIn["timeout"];
            $session->updateTransientTimeout($timeout==null?-1:$timeout);
            $interval = $adviceIn["interval"];
            $session->updateTransientInterval($interval==null?-1:$interval);
            // Force the server to send the advice, as the client may
            // have forgotten it (for example because of a reload)
            $session->reAdvise();
        }
        else
        {
            $session->updateTransientTimeout(-1);
            $session->updateTransientInterval(-1);
        }

        // Send advice
        $adviceOut = $session->takeAdvice();
        if ($adviceOut!=null) {
            $reply[Message::ADVICE_FIELD] = $adviceOut;
        }

        $reply->setSuccessful(true);
    }
}

class SubscribeHandler extends HandlerListener
{
    public function onMessage(ServerSessionImpl $from, ServerMessage\Mutable $message)
    {
        $reply = $this->createReply($message);
        if ($this->isSessionUnknown(from))
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

class UnsubscribeHandler extends HandlerListener
{
    public function onMessage(ServerSessionImpl $from, ServerMessage\Mutable $message)
    {
        $reply=$this->createReply(message);
        if ($this->isSessionUnknown($from))
        {
            $this->unknownSession($reply);
            return;
        }

        $subscribe_id= $message[Message::SUBSCRIPTION_FIELD];
        $reply[Message::SUBSCRIPTION_FIELD] =  $subscribe_id;
        if ($subscribe_id==null)
            $this->error(reply,"400::channel missing");
        else
        {
            $reply[Message::SUBSCRIPTION_FIELD] = $subscribe_id;

            $channel = $this->getChannel($subscribe_id);
            if (channel==null)
            error(reply,"400::channel missing");
            else
            {
                if (from.isLocalSession() || !channel.isMeta() && !channel.isService())
                channel.unsubscribe(from);
                reply.setSuccessful(true);
            }
        }
    }
}

class DisconnectHandler extends HandlerListener
{
    public function onMessage(ServerSessionImpl $session, ServerMessage\Mutable $message)
    {
        $reply = $this->createReply($message);
        if ($this->isSessionUnknown($session))
        {
            $this->unknownSession($reply);
            return;
        }

        $this->removeServerSession($session,false);
        $this->session->flush();

        $reply->setSuccessful(true);
    }
}