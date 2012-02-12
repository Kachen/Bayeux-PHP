<?php

namespace Bayeux\Server;

class ServerSessionImpl implements ServerSession
{
    private static $_idCount; //=new AtomicLong();

    private $_bayeux;
    private $_logger;
    private $_id;
    private $_listeners = array();
    private $_extensions = array();
    private $_queue= array(); // QUEUES
    private $_localSession;
    private $_attributes = array();
    private $_connected;// = new AtomicBoolean();
    private $_handshook;// = new AtomicBoolean();
    private $_subscribedTo; //array();

    private $_scheduler;
    private $_advisedTransport;

    private $_maxQueue=-1;
    private $_transientTimeout=-1;
    private $_transientInterval=-1;
    private $_timeout=-1;
    private $_interval=-1;
    private $_maxInterval;
    private $_maxLazy=-1;
    private $_metaConnectDelivery;
    private $_batch;
    private $_userAgent;
    private $_connectTimestamp=-1;
    private $_intervalTimestamp;
    private $_lastInterval;
    private $_lastConnect;
    private $_lazyDispatch;

    private $_lazyTask;

    /* ------------------------------------------------------------ */
    protected function __construct(BayeuxServerImpl $bayeux, LocalSessionImpl $localSession = null, $idHint = null)
    {
        $this->_subscribedTo = new \ArrayObject();

        $this->_bayeux=$bayeux;
        $this->_logger=$bayeux->getLogger();
        $this->_localSession=$localSession;

        $id = '';
        $len = 20;
        if ($idHint!=null)
        {
            $len += strlen($idHint)+1;
            $id .= $idHint;
            $id .= '_';
        }
        $index= strlen($id);

        while ($index<$len)
        {
            $random=$this->_bayeux->randomLong();
            $id .= $random < 0 ? -$random:$random; //fimxe veririfacar esse trecho com o original
        }

        $id->insert(index,Long.toString(_idCount.incrementAndGet(),36));

        $this->_id=$id;

        $transport = $this->_bayeux->getCurrentTransport();
        if ($transport!=null) {
            $this->_intervalTimestamp=System.currentTimeMillis()+transport.getMaxInterval();
        }
    }

    /* ------------------------------------------------------------ */
    /** Get the userAgent.
     * @return the userAgent
     */
    public function getUserAgent()
    {
        return $this->_userAgent;
    }

    /* ------------------------------------------------------------ */
    /** Set the userAgent.
     * @param userAgent the userAgent to set
     */
    public function setUserAgent($userAgent)
    {
        $this->_userAgent = $userAgent;
    }

    /* ------------------------------------------------------------ */
    protected function sweep($now)
    {
        if ($this->_intervalTimestamp!=0 && now>_intervalTimestamp)
        {
            if ($this->_logger.isDebugEnabled())
            $this->_logger.debug("Expired interval "+ServerSessionImpl.this);
                if ($this->_scheduler!=null)
                $this->_scheduler.cancel();
            $this->_bayeux.removeServerSession(ServerSessionImpl.this,true);
        }
    }

    /* ------------------------------------------------------------ */
    protected function getExtensions()
    {
        return $this->_extensions;
    }

    /* ------------------------------------------------------------ */
    public function addExtension(Extension $extension)
    {
        $this->_extensions[] = $extension;
    }

    public function removeExtension(Extension $extension)
    {
        $this->_extensions.remove(extension);
    }

    /* ------------------------------------------------------------ */
    public function batch(Runnable $batch)
    {
        $this->startBatch();
        try
        {
            $batch.run();
        } catch (\Exception $e)
        {
            $this->endBatch();
        }
    }

    /* ------------------------------------------------------------ */
    public function deliver(Session $from, Mutable $immutable)
    {
        if (!$this->_bayeux->extendSend($from, $this, $immutable)) {
            return;
        }

        if (from instanceof LocalSession) {
            $this->doDeliver($from->getServerSession(), $immutable);
        } else {
            $this->doDeliver($from, $immutable);
        }
    }

    /* ------------------------------------------------------------ */
    public function deliver(Session $from, $channelId, $data, $id)
    {
        $mutable = $this->_bayeux->newMessage();
        $mutable.setChannel($channelId);
        $mutable.setData($data);
        $mutable.setId($id);
        $this->deliver($from, $mutable);
    }

    /* ------------------------------------------------------------ */
    protected function doDeliver(ServerSession $from, ServerMessage\Mutable $mutable)
    {
        $message = null;
        if ($mutable->isMeta())
        {
            if (!$this->extendSendMeta($mutable)) {
                return;
            }
        }
        else
        {
            $message = $this->extendSendMessage($mutable);
        }

        if (message==null)
        return;

        foreach ($this->_listeners as $listener)
        {
            try
            {
                if ($listener instanceof MaxQueueListener && $this->_maxQueue >=0 && count($this->_queue) >= $this->_maxQueue)
                {
                    if (!$listener->queueMaxed($this, $from, $message))
                    return;
                }
                if ($listener instanceof MessageListener)
                {
                    if (!$listener->onMessage($this,$from,$message)) {
                        return;
                    }
                }
            }
            catch(\Exception $e)
            {
                $this->_bayeux->getLogger()->warn("Exception while invoking listener " . $listener, $e);
            }
        }

        $this->_queue[] = $message;
        $wakeup = $this->_batch == 0;

        if ($wakeup)
        {
            if ($message->isLazy()) {
                $this->flushLazy();
            } else {
                $this->flush();
            }
        }
    }

    /* ------------------------------------------------------------ */
    protected function handshake()
    {
        _handshook.set(true);
    }

    /* ------------------------------------------------------------ */
    protected function connect()
    {
            $this->_connected = true;

            if ($this->_connectTimestamp==-1)
            {
                $transport = $this->_bayeux->getCurrentTransport();

                if ($transport!=null)
                {
                    $this->_maxQueue=transport.getOption("maxQueue",-1);

                    $this->_maxInterval=_interval>=0?(_interval+transport.getMaxInterval()-transport.getInterval()):transport.getMaxInterval();
                    $this->_maxLazy=transport.getMaxLazyTimeout();

                    if ($this->_maxLazy>0)
                    {
                        /* $this->_lazyTask=new Timeout.Task()
                        {
                            @Override
                            public void expired()
                            {
                                _lazyDispatch=false;
                                flush();
                            }

                            @Override
                            public String toString()
                            {
                                return "LazyTask@"+getId();
                            }
                        }; */
                    }
                }
            }
            $this->cancelIntervalTimeout();
    }

    /* ------------------------------------------------------------ */
    public function disconnect()
    {
        $connected=$this->_bayeux.removeServerSession(this,false);
        if ($connected)
        {
            $message = $this->_bayeux->newMessage();
            $message.setClientId(getId());
            $message.setChannel(Channel.META_DISCONNECT);
            $message.setSuccessful(true);
            $this->deliver(this,message);
            if (count($this->_queue)>0) {
                $this->flush();
            }
        }
    }

    /* ------------------------------------------------------------ */
    public function endBatch()
    {
        if (--$this->_batch==0 && count($this->_queue)>0)
        {
            $this->flush();
            return true;
        }
        return false;
    }

    /* ------------------------------------------------------------ */
    public function getLocalSession()
    {
        return _localSession;
    }

    /* ------------------------------------------------------------ */
    public function isLocalSession()
    {
        return _localSession!=null;
    }

    /* ------------------------------------------------------------ */
    public function startBatch()
    {
            $this->_batch++;
    }

    /* ------------------------------------------------------------ */
    public function addListener(ServerSessionListener $listener)
    {
        $this->_listeners[] = $listener;
    }

    /* ------------------------------------------------------------ */
    public function getId()
    {
        return $this->_id;
    }

    /* ------------------------------------------------------------ */
    public function getLock()
    {
        return $this->_queue;
    }

    /* ------------------------------------------------------------ */
    public function getQueue()
    {
        return $this->_queue;
    }

    /* ------------------------------------------------------------ */
    public function isQueueEmpty()
    {
        return count($this->_queue)==0;
    }

    /* ------------------------------------------------------------ */
    public function addQueue(ServerMessage $message)
    {
        $this->_queue->add($message);
    }

    /* ------------------------------------------------------------ */
    public function replaceQueue(array $queue)
    {
            $this->_queue.clear();
            $this->_queue.addAll($queue);
    }

    /* ------------------------------------------------------------ */
    public function takeQueue()
    {
        $copy = array();
            if (!$this->_queue->isEmpty())
            {
                foreach ($this->_listeners as $listener)
                {
                    if (listener instanceof DeQueueListener) {
                        $listener->deQueue($this, $this->_queue);
                    }
                }
                $copy->addAll($this->_queue);
                $this->_queue->clear();
            }
        return $copy;
    }

    /* ------------------------------------------------------------ */
    public function removeListener(ServerSessionListener $listener)
    {
        $this->_listeners.remove(listener);
    }

    /* ------------------------------------------------------------ */
    public function setScheduler(AbstractServerTransport\Scheduler $scheduler)
    {
            if ($scheduler == null)
            {
                if ($this->_scheduler!=null)
                {
                    $this->_scheduler->cancel();
                    $this->_scheduler = null;
                }
            }
            else
            {
                if ($this->_scheduler!=null && $this->_scheduler!=$scheduler)
                {
                    $this->_scheduler->cancel();
                }

                $this->_scheduler=$scheduler;

                if ($this->_queue.size()>0 && $this->_batch==0)
                {
                    $this->_scheduler.schedule();
                    if ($this->_scheduler instanceof OneTimeScheduler) {
                        $this->_scheduler=null;
                    }
                }
            }
    }

    /* ------------------------------------------------------------ */
    public function flush()
    {
        if ($this->_lazyDispatch && _lazyTask!=null) {
            $this->_bayeux.cancelTimeout(_lazyTask);
        }

        $scheduler=$this->_scheduler;
        if ($scheduler!=null)
        {
            if ($this->_scheduler instanceof OneTimeScheduler) {
                $this->_scheduler=null;
            }
            $scheduler.schedule();
            return;
        }

        // do local delivery
        if  ($this->_localSession!=null && $this->_queue.size()>0)
        {
            foreach ($this->takeQueue() as $msg )
            {
                if ($msg instanceof Message\Mutable) {
                    $this->_localSession->receive($msg);
                } else {
                    $this->_localSession->receive(new HashMapMessage($msg));
                }
            }
        }
    }

    /* ------------------------------------------------------------ */
    public function flushLazy()
    {
            if ($this->_maxLazy==0) {
                $this->flush();
            } else if ($this->_maxLazy>0 && !$this->_lazyDispatch)
            {
                $this->_lazyDispatch=true;
                $this->_bayeux.startTimeout(_lazyTask,_connectTimestamp%_maxLazy);
            }
    }

    /* ------------------------------------------------------------ */
    public function cancelSchedule()
    {
            $scheduler=$this->_scheduler;
            if ($scheduler!=null)
            {
                $this->_scheduler=null;
                $scheduler->cancel();
            }
    }

    /* ------------------------------------------------------------ */
    public function cancelIntervalTimeout()
    {
            $now = System.currentTimeMillis();
            if ($this->_intervalTimestamp>0) {
                $this->_lastInterval=$now-($this->_intervalTimestamp-$this->_maxInterval);
            }
            $this->_connectTimestamp=$now;
            $this->_intervalTimestamp=0;
    }

    /* ------------------------------------------------------------ */
    public function startIntervalTimeout()
    {
            $now = System.currentTimeMillis();
            $this->_lastConnect=$now-$this->_connectTimestamp;
            $this->_intervalTimestamp=$now+_maxInterval;
    }

    /* ------------------------------------------------------------ */
    public function getAttribute($name)
    {
        return $this->_attributes[$name];
    }

    /* ------------------------------------------------------------ */
    public function getAttributeNames()
    {
        return array_keys($this->_attributes);
    }

    /* ------------------------------------------------------------ */
    public function removeAttribute($name)
    {
        $old = $this->getAttribute($name);
        $this->_attributes->removeAttribute($name);
        return $old;
    }

    /* ------------------------------------------------------------ */
    public function setAttribute($name, $value)
    {
        $this->_attributes[$name] = $value;
    }

    /* ------------------------------------------------------------ */
    public function isConnected()
    {
        return $this->_connected;
    }

    /* ------------------------------------------------------------ */
    public function isHandshook()
    {
        return _handshook.get();
    }

    /* ------------------------------------------------------------ */
    protected function extendRecv(ServerMessage\Mutable $message)
    {
        if ($message->isMeta())
        {
            foreach ($this->_extensions as $ext ) {
                if (!$ext->rcvMeta($this, $message)) {
                    return false;
                }
            }
        }
        else
        {
            foreach ($this->_extensions as $ext) {
                if (!$ext->rcv($this, $message)) {
                    return false;
                }
            }
        }
        return true;
    }

    /* ------------------------------------------------------------ */
    protected function extendSendMeta(ServerMessage\Mutable $message)
    {
        if (!$message->isMeta()) {
            throw new IllegalStateException();
        }

        foreach ($this->_extensions as $ext) {
            if (!$ext->sendMeta($this,message)) {
                return false;
            }
        }
        return true;
    }

    /* ------------------------------------------------------------ */
    protected function extendSendMessage(ServerMessage $message)
    {
        if ($message->isMeta()) {
            throw new IllegalStateException();
        }

        foreach ($this->_extensions as $ext)
        {
            $message=$ext->send($this, $message);
            if ($message==null) {
                return null;
            }
        }
        return $message;
    }

    /* ------------------------------------------------------------ */
    public function getAdvice()
    {
        $transport = $this->_bayeux->getCurrentTransport();
        if ($transport==null) {
            return null;
        }

        $timeout = $this->getTimeout() < 0 ? $transport->getTimeout() : $this->getTimeout();
        $interval = $this->getInterval() < 0 ? $transport->getInterval() : $this->getInterval();

        return new JSON.Literal("{\"reconnect\":\"retry\"," +
                "\"interval\":" + interval + "," +
                "\"timeout\":" + timeout + "}");
    }

    /* ------------------------------------------------------------ */
    public function reAdvise()
    {
        $this->_advisedTransport=null;
    }

    /* ------------------------------------------------------------ */
    public function takeAdvice()
    {
        $transport = $this->_bayeux.getCurrentTransport();

        if (transport!=null && $transport!=$this->_advisedTransport)
        {
            $this->_advisedTransport=transport;
            return $this->getAdvice();
        }

        // advice has not changed, so return null.
        return null;
    }

    /* ------------------------------------------------------------ */
    public function getTimeout()
    {
        return $this->_timeout;
    }

    /* ------------------------------------------------------------ */
    public function getInterval()
    {
        return $this->_interval;
    }

    /* ------------------------------------------------------------ */
    public function setTimeout($timeoutMS)
    {
        $this->_timeout=$timeoutMS;
        $this->_advisedTransport=null;
    }

    /* ------------------------------------------------------------ */
    public function setInterval($intervalMS)
    {
        $this->_interval=$intervalMS;
        $this->_advisedTransport=null;
    }

    /* ------------------------------------------------------------ */
    /**
     * @param timedout
     * @return True if the session was connected.
     */
    protected function removed($timedout)
    {
        $connected = $this->_connected.getAndSet(false);
        $handshook = $this->_handshook.getAndSet(false);
        if (connected || handshook)
        {
            foreach ($this->_subscribedTo as $channel => $value )
            {
                $channel->unsubscribe($this);
            }

            foreach ($this->_listeners as $listener)
            {
                if ($listener instanceof ServerSession\RemoveListener) {
                    $listener->removed($this, $timedout);
                }
            }
        }
        return $connected;
    }

    /* ------------------------------------------------------------ */
    public function setMetaConnectDeliveryOnly($meta)
    {
        $this->_metaConnectDelivery=$meta;
    }

    /* ------------------------------------------------------------ */
    public function isMetaConnectDeliveryOnly()
    {
        return $this->_metaConnectDelivery;
    }

    /* ------------------------------------------------------------ */
    protected function subscribedTo(ServerChannelImpl $channel)
    {
        $this->_subscribedTo[$channel] = true;
    }

    /* ------------------------------------------------------------ */
    protected function unsubscribedTo(ServerChannelImpl $channel)
    {
        $this->_subscribedTo.remove(channel);
    }

    /* ------------------------------------------------------------ */
    protected function dump($b, $indent)
    {
        $b .= $this->toString();
        $b .= '\n';

        foreach ($this->_listeners as $child)
        {
            $b .= $indent;
            $b .= " +-";
            $b .= $child;
            $b .= '\n';
        }

        if ($this->isLocalSession())
        {
            $b .= $indent;
            $b .= " +-";
            $b .= $this->_localSession->dump($b, $indent . "   ");
        }
    }

    /* ------------------------------------------------------------ */
    public function toDetailString()
    {
        return $this->_id+",lc=".$this->_lastConnect+",li=".$this->_lastInterval;
    }

    /* ------------------------------------------------------------ */
    //@Override
    public function toString()
    {
        return $this->_id;
    }

    public function calculateTimeout($defaultTimeout)
    {
        if ($this->_transientTimeout >= 0) {
            return $this->_transientTimeout;
        }

        if ($this->_timeout >= 0) {
            return $this->_timeout;
        }

        return $defaultTimeout;
    }

    public function calculateInterval($defaultInterval)
    {
        if ($this->_transientInterval >= 0) {
            return $this->_transientInterval;
        }

        if ($this->_interval >= 0) {
            return $this->_interval;
        }

        return $defaultInterval;
    }

    /**
     * Updates the transient timeout with the given value.
     * @param timeout the value to update the timeout to
     * @see #updateTransientInterval(long)
     */
    public function updateTransientTimeout($timeout)
    {
        $this->_transientTimeout = $timeout;
    }

    /**
     * Updates the transient timeout with the given value.
     * @param interval the value to update the interval to
     * @see #updateTransientTimeout(long)
     */
    public function updateTransientInterval($interval)
    {
        $this->_transientInterval = $interval;
    }
}
