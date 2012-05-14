<?php

namespace Bayeux\Server\Transport;

use Bayeux\Api\Channel;

use Bayeux\Server\Transport\LongPollingTransport\LongPollScheduler;

use Bayeux\Http\Response;
use Bayeux\Http\Request;
use Bayeux\Server\BayeuxServerImpl;
use Bayeux\Api\Server\ServerMessage;

/**
 * Abstract Long Polling Transport.
 * <p/>
 * Transports based on this class can be configured with servlet init parameters:
 * <dl>
 * <dt>browserId</dt><dd>The Cookie name used to save a browser ID.</dd>
 * <dt>maxSessionsPerBrowser</dt><dd>The maximum number of long polling sessions allowed per browser.</dd>
 * <dt>multiSessionInterval</dt><dd>The polling interval to use once max session per browser is exceeded.</dd>
 * <dt>autoBatch</dt><dd>If true a batch will be automatically created to span the handling of messages received from a session.</dd>
 * <dt>allowMultiSessionsNoBrowser</dt><dd>Allows multiple sessions even when the browser identifier cannot be retrieved.</dd>
 * </dl>
 */
abstract class LongPollingTransport extends HttpTransport
{
    const PREFIX = "long-polling";
    const BROWSER_ID_OPTION = "browserId";
    const MAX_SESSIONS_PER_BROWSER_OPTION = "maxSessionsPerBrowser";
    const MULTI_SESSION_INTERVAL_OPTION = "multiSessionInterval";
    const AUTOBATCH_OPTION = "autoBatch";
    const ALLOW_MULTI_SESSIONS_NO_BROWSER_OPTION = "allowMultiSessionsNoBrowser";

    private $_browserMap = array();
    private $_browserSweep = array();

    private $_browserId = "BAYEUX_BROWSER";
    private $_maxSessionsPerBrowser = 1;
    private $_multiSessionInterval = 2000;
    private $_autoBatch = true;
    private $_allowMultiSessionsNoBrowser = false;
    private $_lastSweep = 0;

    public function __construct(BayeuxServerImpl $bayeux, $name)
    {
        parent::__construct($bayeux, $name);
        //$this->setOptionPrefix(self::$PREFIX);
    }

    public function init()
    {
        parent::init();
        $this->_browserId = $this->getOption(self::BROWSER_ID_OPTION, $this->_browserId);
        $this->_maxSessionsPerBrowser = $this->getOption(self::MAX_SESSIONS_PER_BROWSER_OPTION, $this->_maxSessionsPerBrowser);
        $this->_multiSessionInterval = $this->getOption(self::MULTI_SESSION_INTERVAL_OPTION, $this->_multiSessionInterval);
        $this->_autoBatch = $this->getOption(self::AUTOBATCH_OPTION, $this->_autoBatch);
        $this->_allowMultiSessionsNoBrowser = $this->getOption(self::ALLOW_MULTI_SESSIONS_NO_BROWSER_OPTION, $this->_allowMultiSessionsNoBrowser);
    }

    protected function findBrowserId(HttpServletRequest $request)
    {
        $cookies = $request->getCookies();
        if ($cookies != null) {
            foreach ($cookies as $cookie) {
                if ($this->_browserId == $cookie->getName()) {
                    return $cookie->getValue();
                }
            }
        }
        return null;
    }

    protected function setBrowserId(Request $request, Response $response)
    {
        $browser_id = Long.toHexString($request.getRemotePort()) +
        Long.toString($this->getBayeux()->randomLong(), 36) +
        Long.toString(System.currentTimeMillis(), 36) +
        Long.toString(request.getRemotePort(), 36);
        $cookie = new Cookie($this->_browserId, $browser_id);
        cookie.setPath("/");
        cookie.setMaxAge(-1);
        response.addCookie(cookie);
        return browser_id;
    }

    /**
     * Increment the browser ID count.
     *
     * @param browserId the browser ID to increment the count for
     * @return true if the browser ID count is below the max sessions per browser value.
     * If false is returned, the count is not incremented.
     */
    protected function incBrowserId($browserId)
    {
        if ($this->_maxSessionsPerBrowser < 0) {
            return true;
        }
        if ($this->_maxSessionsPerBrowser == 0) {
            return false;
        }

        $count = $this->_browserMap.get(browserId);
        if ($count == null)
        {
            $new_count = new AtomicInteger();
            $count = $this->_browserMap.putIfAbsent(browserId, new_count);
            if (count == null) {
                $count = $new_count;
            }
        }

        // Increment
        $sessions = $count.incrementAndGet();

        // If was zero, remove from the sweep
        if (sessions == 1) {
            $this->_browserSweep.remove(browserId);
        }

        // TODO, the maxSessionsPerBrowser should be parameterized on user-agent
        if (sessions > _maxSessionsPerBrowser)
        {
            count.decrementAndGet();
            return false;
        }

        return true;
    }

    protected function decBrowserId($browserId) {
        if ($browserId == null) {
            return;
        }

        $count = $this->_browserMap.get(browserId);
        if ($count != null && count.decrementAndGet() == 0)
        {
            $this->_browserSweep.put(browserId, new AtomicInteger(0));
        }
    }

    public function handle(Request $request, Response $response) {
        // Is this a resumed connect?
        $scheduler = $request->getAttribute(LongPollScheduler::ATTRIBUTE);
        if ($scheduler == null) {
            // No - process messages

            // Remember if we start a batch
            $batch = false;

            // Don't know the session until first message or handshake response.
            $session = null;
            $connect = false;

            try
            {
                $messages = $this->parseMessages($request);
                if ($messages == null) {
                    return;
                }

                $writer = null;
                foreach ($messages as $message)
                {
                    // Is this a connect?
                    $connect = Channel::META_CONNECT == $message->getChannel();

                    // Get the session from the message
                    $client_id = $message->getClientId();
                    if ($session == null || $client_id != null && !($client_id == $session->getId())) {
                        $session = $this->getBayeux()->getSession($client_id);
                        if ($this->_autoBatch && ! $batch && $session != null && !$connect && ! $message->isMeta()) {
                            // start a batch to group all resulting messages into a single response.
                            $batch = true;
                            $session->startBatch();
                        }

                    } else if (! $session->isHandshook()) {
                        $batch = false;
                        $session = null;
                    }

                    if ($connect && $session != null) {
                        // cancel previous scheduler to cancel any prior waiting long poll
                        // this should also dec the browser ID
                        $session->setScheduler(null);
                    }

                    $wasConnected = $session != null && $session->isConnected();

                    // Forward handling of the message.
                    // The actual reply is return from the call, but other messages may
                    // also be queued on the session.
                    $reply = $this->getBayeux()->handle($session, $message);
                    // Do we have a reply ?
                    if ($reply != null) {
                        if ($session == null) {
                            // This must be a handshake, extract a session from the reply
                            $session = $this->getBayeux()->getSession($reply->getClientId());

                            // Get the user agent while we are at it, and add the browser ID cookie
                            if ($session != null) {
                                $userAgent = $request->getHeader()->get("User-Agent");
                                var_dump($userAgent);
                                exit;
                                $session->setUserAgent($userAgent);

                                $browserId = $this->findBrowserId($request);
                                if ($browserId == null) {
                                    $this->setBrowserId($request, $response);
                                }
                            }

                        } else {
                            // If this is a connect or we can send messages with any response
                            if ($connect || !($this->isMetaConnectDeliveryOnly() || $session->isMetaConnectDeliveryOnly())) {
                                // Send the queued messages
                                $writer = sendQueue(request, response, session, writer);
                            }

                            // Special handling for connect
                            if ($connect)
                            {
                                $timeout = $session->calculateTimeout(getTimeout());

                                // If the writer is non null, we have already started sending a response, so we should not suspend
                                if ($writer == null && $reply->isSuccessful() && $session->isQueueEmpty())
                                {
                                    // Detect if we have multiple sessions from the same browser
                                    // Note that CORS requests do not send cookies, so we need to handle them specially
                                    // CORS requests always have the Origin header

                                    $browserId = $this->findBrowserId($request);
                                    $shouldSuspend;
                                    if ($browserId != null) {
                                        $shouldSuspend = $this->incBrowserId(browserId);
                                    } else {
                                        $shouldSuspend = $this->_allowMultiSessionsNoBrowser || $request->getHeader("Origin") != null;
                                    }

                                    if ($shouldSuspend) {
                                        // Support old clients that do not send advice:{timeout:0} on the first connect
                                        if ($timeout > 0 && $wasConnected) {
                                            // Suspend and wait for messages
                                            //$continuation = ContinuationSupport.getContinuation($request);
                                            //$continuation->setTimeout($timeout);
                                            //$continuation->suspend($response);
                                            $scheduler = new LongPollScheduler($session, $reply, $browserId);
                                            $session->setScheduler($scheduler);
                                            $request->setAttribute(LongPollScheduler::ATTRIBUTE, $scheduler);
                                            $reply = null;

                                        } else {
                                            $this->decBrowserId($browserId);
                                        }
                                    } else {
                                        // There are multiple sessions from the same browser
                                        $advice = $reply->getAdvice(true);

                                        if ($browserId != null) {
                                            advice.put("multiple-clients", true);
                                        }

                                        if (_multiSessionInterval > 0)
                                        {
                                            advice.put(Message.RECONNECT_FIELD, Message.RECONNECT_RETRY_VALUE);
                                            advice.put(Message.INTERVAL_FIELD, _multiSessionInterval);
                                        }
                                        else
                                        {
                                            advice.put(Message.RECONNECT_FIELD, Message.RECONNECT_NONE_VALUE);
                                            reply.setSuccessful(false);
                                        }
                                        session.reAdvise();
                                    }
                                }

                                if (reply != null && session.isConnected())
                                session.startIntervalTimeout();
                            }
                        }

                        // If the reply has not been otherwise handled, send it
                        if (reply != null)
                        {
                            $reply = getBayeux().extendReply(session, session, reply);

                            if (reply != null) {
                                $writer = send(request, response, writer, reply);
                            }
                        }
                    }

                    // Disassociate the reply
                    $message.setAssociated(null);
                }
                if (writer != null) {
                    $this->complete(writer);
                }
            }
            catch (ParseException $x)
            {
                $this->handleJSONParseException(request, response, x.getMessage(), x.getCause());
            }
            catch(\Exception $e)
            {
                throw $e;
                // If we started a batch, end it now
                if ($batch) {
                    $ended = session.endBatch();

                    // Flush session if not done by the batch, since some browser order <script> requests
                    if (!ended && isAlwaysFlushingAfterHandle())
                        $session->flush();
                }
                else if ($session != null && !$connect && $this->isAlwaysFlushingAfterHandle())
                {
                    $session->flush();
                }
            }

        } else {
            // Get the resumed session
            $session = $scheduler->getSession();
            if ($session->isConnected()) {
                $session->startIntervalTimeout();
            }

            // Send the message queue
            $writer = $this->sendQueue($request, $response, $session, null);

            // Send the connect reply
            $reply = $scheduler.getReply();
            $reply = $this->getBayeux().extendReply(session, session, reply);
            $writer = $this->send(request, response, writer, reply);

            $this->complete($writer);
        }
    }

    protected function handleJSONParseException(HttpServletRequest $request, HttpServletResponse $response, $json, Throwable $exception) // throws ServletException, IOException
    {
        getBayeux().getLogger().debug("Error parsing JSON: " + json, exception);
        response.sendError(HttpServletResponse.SC_BAD_REQUEST);
    }

    /**
     * Sweep the transport for old Browser IDs
     *
     * @see org.cometd.server.AbstractServerTransport#doSweep()
     */
    public function doSweep()
    {
        $now = microtime();
        if (0 < $this->_lastSweep && $this->_lastSweep < $now)
        {
            // Calculate the maximum sweeps that a browser ID can be 0 as the
            // maximum interval time divided by the sweep period, doubled for safety
            $maxSweeps = (int)(2 * $this->getMaxInterval() / ($now - $this->_lastSweep));

            foreach ($this->_browserSweep as $key => $entry)
            {
                $count = $entry->getValue();
                // if the ID has been in the sweep map for 3 sweeps
                if ($count != null && ++$count > $maxSweeps)
                {
                    // remove it from both browser Maps
                    $this->_browserSweep[$key] = 0;
                    if ($this->_browserSweep[$key] == $count && $this->_browserMap[$key] == 0)
                    {
                        unset($this->_browserMap[$key]);
                        //$this->getBayeux().getLogger().debug("Swept browserId {}", key);
                    }
                }
            }
        }
        $this->_lastSweep = $now;
    }

    private function sendQueue(HttpServletRequest $request, HttpServletResponse $response, ServerSessionImpl $session, PrintWriter $writer)
    {
        $queue = $session->takeQueue();
        foreach ($queue as $m) {
            $writer = $this->send($request, $response, $writer, $m);
        }
        return $writer;
    }

    protected function parseMessages($requestParameters) {
        if (is_scalar($requestParameters)) {
            return parent::parseMessages($requestParameters);
        }

        if (empty($requestParameters)) {
            throw new IOException("Missing '" . self::MESSAGE_PARAM . "' request parameter");
        }

        if (count($requestParameters) == 1) {
            return parent::parseMessages($requestParameters[0]);
        }

        $messages = array();
        foreach ($requestParameters as $batch) {
            if ($batch == null) {
                continue;
            }
            $messages = array_merge(parent::parseMessages($batch), $messages);
        }

        return $messages;
    }

    /**
     * @return true if the transport always flushes at the end of a call to {@link #handle(HttpServletRequest, HttpServletResponse)}.
     */
    abstract protected function isAlwaysFlushingAfterHandle();

    abstract protected function send(Request $request, Response $response, $writer, ServerMessage $message);// throws IOException;

    abstract protected function complete($writer);// throws IOException;
}
