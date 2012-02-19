<?php

namespace Bayeux\Server;

use Bayeux\Api\Server\ServerSession\MaxQueueListener;

use Bayeux\Server\AbstractServerTransport\OneTimeScheduler;

use Bayeux\Api\Server\ServerSession\DeQueueListener;
use Bayeux\Api\Message;
use Bayeux\Common\HashMapMessage;
use Bayeux\Api\Server\ServerSession\MessageListener;
use Bayeux\Api\Session;
use Bayeux\Api\Server\ServerMessage;
use Bayeux\Api\Server\ServerSession\ServerSessionListener;
use Bayeux\Api\Server\ServerSession\Extension;
use Bayeux\Api\Server\ServerSession;
use Bayeux\Api\Server\LocalSession;
use Bayeux\Api\Channel;

class ServerSessionImpl implements ServerSession
{
    private static $_idCount; //=new AtomicLong();

    private $_logger;
    private $_bayeux;
    private $_id;
    private $_listeners = array();
    private $_extensions = array();
    private $_queue; // QUEUES
    private $_localSession;
    private $_attributes = array();
    private $_connected;// = new AtomicBoolean();
    private $_handshook;// = new AtomicBoolean();
    private $_subscribedTo; //array();

    private $_scheduler;
    private $_advisedTransport;

    private $_maxQueue = -1;
    private $_transientTimeout = -1;
    private $_transientInterval = -1;
    private $_timeout = -1;
    private $_interval = -1;
    private $_maxInterval = -1;
    private $_maxLazy = -1;
    private $_maxServerInterval = -1;
    private $_metaConnectDelivery;
    private $_batch;
    private $_userAgent;
    private $_connectTimestamp=-1;
    private $_intervalTimestamp;
    private $_lastConnect;
    private $_lazyDispatch;
    private $_lazyTask;

    /* ------------------------------------------------------------ */
    public function __construct(BayeuxServerImpl $bayeux, LocalSessionImpl $localSession = null, $idHint = null)
    {
        $this->_queue = new \SplQueue();
        $this->_subscribedTo = new \SplObjectStorage();

        $this->_bayeux = $bayeux;
        //$this->_logger=$bayeux->getLogger();
        $this->_localSession = $localSession;

        $id = '';
        $len = 20;
        if ($idHint!=null)
        {
            $len += strlen($idHint)+1;
            $id .= $idHint;
            $id .= '_';
        }
        $index = strlen($id);

        while (strlen($id) < $len)
        {
            $id .= $this->_bayeux->randomLong();
        }

        $this->_id = $id;
        $transport = $this->_bayeux->getCurrentTransport();
        if ($transport != null) {
            $this->_intervalTimestamp = microtime() + $transport->getMaxInterval();
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
    public function sweep($now)
    {
        if ($this->isLocalSession()) {
            return;
        }

        $remove = false;
        $scheduler = null;
        if ($this->_intervalTimestamp == 0)
        {
            if ($this->_maxServerInterval > 0 && $now > $this->_connectTimestamp + $this->_maxServerInterval)
            {
                //_logger.info("Emergency sweeping session {}", this);
                $remove = true;
            }
        }
        else
        {
            if ($now > $this->_intervalTimestamp)
            {
                //_logger.debug("Sweeping session {}", this);
                $remove = true;
            }
        }
        if ($remove) {
            $scheduler = $this->_scheduler;
        }
        if ($remove)
        {
            if ($scheduler != null) {
                $scheduler->cancel();
            }
            $this->_bayeux->removeServerSession($this, true);
        }
    }

    public function getSubscriptions() {
        return array_keys($this->_subscribedTo);
    }

    public function removeExtension(Extension $extension)
    {
        $this->_extensions.remove($extension);
    }

    /* ------------------------------------------------------------ */
    public function addExtension(Extension $extension)
    {
        $this->_extensions[] = $extension;
    }

    /* ------------------------------------------------------------ */
    protected function getExtensions()
    {
        return $this->_extensions;
    }

    /* ------------------------------------------------------------ */
    public function batch($batch)
    {
        $this->startBatch();
        try
        {
            $batch->run();
        } catch (\Exception $e)
        {
            $this->endBatch();
        }
    }



    /* ------------------------------------------------------------ */
    public function deliver(Session $from, $arg1, $data = null, $id = null)
    {
        if (is_string($arg1)) {
            $channelId = $arg1;
            $message = $this->_bayeux->newMessage();
            $message->setChannel($channelId);
            $message->setData($data);
            $message->setId($id);

        } else if (! ($arg1 instanceof ServerMessage\Mutable)) {
            throw new \InvalidArgumentException();

        } else {
            $message = $arg1;
        }

        if ($from instanceof ServerSession) {
            $session = $from;
        } else {
            $session = $from->getServerSession();
        }

        if (! $this->_bayeux->extendSend($session, $this, $message)) {
            return;
        }

        $this->doDeliver($session, $message);
    }

    /* ------------------------------------------------------------ */
    public  function doDeliver(ServerSession $from, ServerMessage\Mutable $mutable)
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

        if ($message == null) {
            return;
        }

        $this->_bayeux->freeze($mutable);

        $maxQueueSize = $this->_maxQueue;
        $queueSize = count($this->_queue);
        foreach ($this->_listeners as $listener)
        {
            if ($maxQueueSize > 0 && $queueSize > $maxQueueSize && $listener instanceof MaxQueueListener)
            {
                if (!$this->notifyQueueMaxed($listener, $from, $message))
                    return;
            }
            if ($listener instanceof MessageListener)
            {
                if (! $this->notifyOnMessage($listener, $from, $message))
                    return;
            }
        }

        $this->_queue->enqueue($message);
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

    private function notifyQueueMaxed($listener, ServerSession $from, ServerMessage $message)
    {
        if (! ($listener instanceof MaxQueueListener) || ! ($listener instanceof MessageListener) ) {
            throw new \InvalidArgumentException();
        }
        try
        {
            return $listener->queueMaxed($this, $from, $message);
        }
        catch (Exception $x)
        {
            echo ("Exception while invoking listener " . $listener . $x);
            return true;
        }
    }

    private function notifyOnMessage(MessageListener $listener, ServerSession $from, ServerMessage $message)
    {
        try
        {
            return $listener->onMessage($this, $from, $message);
        }
        catch (\Exception $x)
        {
            throw $x;
            //_logger.info("Exception while invoking listener " + listener, x);
            return true;
        }
    }


    /* ------------------------------------------------------------ */
    public function handshake()
    {
        $this->_handshook = true;

        $transport = $this->_bayeux->getCurrentTransport();
        if ($transport != null)
        {
            $this->_maxQueue = $transport->getOption("maxQueue", -1);
            $this->_maxInterval = $this->_interval >= 0 ? $this->_interval + $transport->getMaxInterval() : $transport->getMaxInterval();
            $this->_maxServerInterval = $transport->getOption("maxServerInterval", 10 * $this->_maxInterval);
            $this->_maxLazy = $transport->getMaxLazyTimeout();
            if ($this->_maxLazy > 0)
            {
                /*$this->_lazyTask = new Timeout.Task()
                {
                    @Override
                    public void expired()
                    {
                        flush();
                    }

                    @Override
                    public String toString()
                    {
                        return "LazyTask@" + getId();
                    }
                };*/
            }
        }
    }

    /* ------------------------------------------------------------ */
    public function connect()
    {
        $this->_connected = true;
        $this->cancelIntervalTimeout();
    }

    /* ------------------------------------------------------------ */
    public function disconnect()
    {
        $connected = $this->_bayeux->removeServerSession($this, false);
        if ($connected)
        {
            $message = $this->_bayeux->newMessage();
            $message->setClientId($this->getId());
            $message->setChannel(Channel::META_DISCONNECT);
            $message->setSuccessful(true);
            $this->deliver($this, $message);
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
        return $this->_localSession;
    }

    /* ------------------------------------------------------------ */
    public function isLocalSession()
    {
        return $this->_localSession != null;
    }

    /* ------------------------------------------------------------ */
    public function startBatch()
    {
            ++$this->_batch;
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
        $this->_queue->enqueue($message);
    }

    /* ------------------------------------------------------------ */
    public function replaceQueue(\SplQueue $queue)
    {
        $this->_queue->rewind();
        foreach ($queue as $value) {
            $this->_queue->enqueue($value);
        }
    }

    /* ------------------------------------------------------------ */
    public function takeQueue()
    {
        $copy = new \SplQueue();
        if (! $this->_queue->isEmpty())
        {
            foreach ($this->_listeners as $listener)
            {
                if ($listener instanceof DeQueueListener) {
                    $listener->deQueue($listener, $this, $this->_queue);
                }
            }

            $copy = clone $this->_queue;
            while (! $this->_queue->isEmpty()) {
                $this->_queue->dequeue();
            }
        }
        return $copy;
    }

    private function notifyDeQueue(DeQueueListener $listener, ServerSession $serverSession, $queue)
    {
        try
        {
            $listener->deQueue($serverSession, $queue);
        }
        catch (Exception $x)
        {
            echo "Exception while invoking listener " . $listener . $x;
            //_logger.info("Exception while invoking listener " + listener, x);
        }
    }

    /* ------------------------------------------------------------ */
    public function removeListener(ServerSessionListener $listener)
    {
        $this->_listeners.remove(listener);
    }

    /* ------------------------------------------------------------ */
    public function setScheduler(AbstractServerTransport\Scheduler $newScheduler = null)
    {
        if ($newScheduler == null)
        {
            $oldScheduler = $this->_scheduler;
            if ($oldScheduler != null) {
                $this->_scheduler = null;
            }
            if ($oldScheduler != null) {
                $oldScheduler->cancel();
            }
        }
        else
        {
            $oldScheduler;
            $schedule = false;
                $oldScheduler = $this->_scheduler;
                $this->_scheduler = $newScheduler;
                if ($this->_queue.size() > 0 && $this->_batch == 0)
                {
                    $schedule = true;
                    if ($newScheduler instanceof OneTimeScheduler) {
                        $this->_scheduler = null;
                    }
                }
            if ($oldScheduler != null && $oldScheduler != $newScheduler)
                $oldScheduler->cancel();
            if ($schedule)
                $newScheduler->schedule();
        }
    }

    /* ------------------------------------------------------------ */
    public function flush()
    {
        if ($this->_lazyDispatch)
        {
            $this->_lazyDispatch = false;
            if ($this->_lazyTask != null)
                $this->_bayeux->cancelTimeout($this->_lazyTask);
        }

        $scheduler = $this->_scheduler;

        if ($scheduler != null)
        {
            if ($this->_scheduler instanceof OneTimeScheduler)
                $this->_scheduler = null;
        }

        if ($scheduler != null)
        {
            $scheduler->schedule();
            // If there is a scheduler, then it's a remote session
            // and we should not perform local delivery, so we return
            return;
        }

        // do local delivery
        if ($this->_localSession != null && count($this->_queue) > 0)
        {
            foreach ($this->takeQueue() as $msg)
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
            if ($this->_maxLazy < 0) {
                $this->flush();

            } else if (! $this->_lazyDispatch)
            {
                $this->_lazyDispatch = true;
                $this->_bayeux->startTimeout($this->_lazyTask, $this->_connectTimestamp % $this->_maxLazy);
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
            $now = microtime();
            $this->_connectTimestamp = $now;
            $this->_intervalTimestamp = 0;
    }

    /* ------------------------------------------------------------ */
    public function startIntervalTimeout($defaultInterval)
    {
        $interval = $this->calculateInterval($defaultInterval);
            $now = microtime();
            $this->_lastConnect = $now - $this->_connectTimestamp;
            $this->_intervalTimestamp = $now + $interval + $this->_maxInterval;
    }

    protected function getMaxInterval()
    {
        return $this->_maxInterval;
    }

    public function getIntervalTimestamp()
    {
        return $this->_intervalTimestamp;
    }

    /* ------------------------------------------------------------ */
    public function getAttribute($name)
    {
        if (isset($this->_attributes[$name])) {
            return $this->_attributes[$name];
        }
        return null;
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
        unset($this->_attributes[$name]);
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
        return $this->_handshook;
    }

    /* ------------------------------------------------------------ */
    public function extendRecv(ServerMessage\Mutable $message)
    {
        if ($message->isMeta())
        {
            foreach ($this->_extensions as $extension ) {
                if (! $this->notifyRcvMeta($extension, $message)) {
                    return false;
                }
            }
        }
        else
        {
            foreach ($this->_extensions as $extension) {
                if (!$this->notifyRcv($extension, $message)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function notifyRcvMeta(Extension $extension, ServerMessage\Mutable $message)
    {
        try
        {
            return $extension->rcvMeta($this, $message);
        }
        catch (\Exception $x)
        {
            echo "Exception while invoking extension " . $extension . $x;
            //_logger.info("Exception while invoking extension " + extension, x);
            return true;
        }
    }

    private function notifyRcv(Extension $extension, ServerMessage\Mutable $message)
    {
        try
        {
            return $extension->rcv($this, $message);
        }
        catch (\Exception $x)
        {
            echo "Exception while invoking extension " . $extension . $x;
            //_logger.info("Exception while invoking extension " + extension, x);
            return true;
        }
    }

    /* ------------------------------------------------------------ */
    public function extendSendMeta(ServerMessage\Mutable $message)
    {
        if (!$message->isMeta()) {
            throw new \InvalidArgumentException();
        }

        foreach ($this->_extensions as $extension) {
            if (! $this->notifySendMeta($extension, $message)) {
                return false;
            }
        }
        return true;
    }

    private function notifySendMeta(Extension $extension, ServerMessage\Mutable $message)
    {
        try
        {
            return $extension->sendMeta($this, $message);
        }
        catch (\Exception $x)
        {
            echo "Exception while invoking extension " , $extension . $x;
            //_logger.info("Exception while invoking extension " + extension, x);
            return true;
        }
    }

    /* ------------------------------------------------------------ */
    public function extendSendMessage(ServerMessage $message)
    {
        if ($message->isMeta()) {
            throw new \InvalidArgumentException();
        }

        foreach ($this->_extensions as $extension)
        {
            $message = $this->notifySend($extension, $message);
            if ($message == null) {
                return null;
            }
        }
        return $message;
    }

    private function notifySend(Extension $extension, ServerMessage $message)
    {
        try
        {
            return $extension->send($this, $message);
        }
        catch (\Exception $x)
        {
            throw $x;
            echo "Exception while invoking extension " . $extension . $x;
            //_logger.info("Exception while invoking extension " + extension, x);
            return $message;
        }
    }

    public function reAdvise()
    {
        $this->_advisedTransport = null;
    }

    public function takeAdvice()
    {
        $transport = $this->_bayeux->getCurrentTransport();

        if ($transport != null && $transport != $this->_advisedTransport)
        {
            $this->_advisedTransport = $transport;

            // The timeout is calculated based on the values of the session/transport
            // because we want to send to the client the *next* timeout
            $timeout = $this->getTimeout() < 0 ? $transport->getTimeout() : $this->getTimeout();

            // The interval is calculated using also the transient value
            // because we want to send to the client the *current* interval
            $interval = $this->calculateInterval($transport->getInterval());

            $advice = array();
            $advice[Message::RECONNECT_FIELD] = Message::RECONNECT_RETRY_VALUE;
            $advice[Message::INTERVAL_FIELD] = $interval;
            $advice[Message::TIMEOUT_FIELD] = $timeout;
            return $advice;
        }

        // advice has not changed, so return null.
        return null;
    }

    public function getTimeout()
    {
        return $this->_timeout;
    }

    public function getInterval()
    {
        return $this->_interval;
    }

    public function setTimeout($timeoutMS)
    {
        $this->_timeout = $timeoutMS;
        $this->_advisedTransport = null;
    }

    public function setInterval($intervalMS)
    {
        $this->_interval = $intervalMS;
        $this->_advisedTransport = null;
    }

    /* ------------------------------------------------------------ */
    /**
     * @param timedout
     * @return True if the session was connected.
     */
    public function removed($timedout)
    {
        $connected = $this->_connected;
        $this->_connected = false;
        $handshook = $this->_handshook;
        $this->_handshoo = false;
        if ($connected || $handshook)
        {
            foreach ($this->_subscribedTo as $channel)
            {
                $channel->unsubscribe($this);
            }

            foreach ($this->_listeners as $listener)
            {
                if ($listener instanceof ServerSession\RemoveListener) {
                    $this->notifyRemoved($listener, $this, $timedout);
                }
            }
        }
        return $connected;
    }

    private function notifyRemoved(ServerSession\RemoveListener $listener, ServerSession $serverSession, $timedout)
    {
        try
        {
            $listener->removed($serverSession, $timedout);
        }
        catch (\Exception $x)
        {
            echo "Exception while invoking listener " . $listener . $x;
            //_logger.info("Exception while invoking listener " + listener, x);
        }
    }

    /* ------------------------------------------------------------ */
    public function setMetaConnectDeliveryOnly($meta)
    {
        $this->_metaConnectDelivery = $meta;
    }

    /* ------------------------------------------------------------ */
    public function isMetaConnectDeliveryOnly()
    {
        return $this->_metaConnectDelivery;
    }

    /* ------------------------------------------------------------ */
    public function subscribedTo(ServerChannelImpl $channel)
    {
        $this->_subscribedTo->attach($channel, true);
    }

    /* ------------------------------------------------------------ */
    public function unsubscribedFrom(ServerChannelImpl $channel)
    {
        $this->_subscribedTo->detach($channel);
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

    public function __toString() {
        return $this->toString();
    }

    /* ------------------------------------------------------------ */
    public function toString()
    {
        return sprintf("%s - last connect %d ms ago", $this->_id, $this->_lastConnect);
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
     * The transient timeout is the one sent by the client, that should
     * temporarily override the session/transport timeout, for example
     * when the client sends {timeout:0}
     *
     * @param timeout the value to update the timeout to
     * @see #updateTransientInterval(long)
     */
    public function updateTransientTimeout($timeout)
    {
        $this->_transientTimeout = $timeout;
    }

    /**
     * Updates the transient interval with the given value.
     * The transient interval is the one sent by the client, that should
     * temporarily override the session/transport interval, for example
     * when the client sends {timeout:0,interval:60000}
     *
     * @param interval the value to update the interval to
     * @see #updateTransientTimeout(long)
     */
    public function updateTransientInterval($interval)
    {
        $this->_transientInterval = $interval;
    }
}
