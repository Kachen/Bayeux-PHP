<?php

namespace Bayeux\Server\Transport\HttpTransport;

use Bayeux\Server\AbstractServerTransport;;

class LongPollScheduler implements AbstractServerTransport\OneTimeScheduler, ContinuationListener
{
    private $_session;
    private $_continuation;
    private $_reply;
    private $_browserId;

    public function __construct(ServerSessionImpl $session, Continuation $continuation, ServerMessage\Mutable $reply, $browserId)
    {
        $this->_session = $session;
        $this->_continuation = $continuation;
        $this->_continuation->addContinuationListener(this);
        $this->_reply = $reply;
        $this->_browserId = $browserId;
    }

    public function cancel()
    {
        if ($this->_continuation != null && $this->_continuation->isSuspended() && !$this->_continuation->isExpired())
        {
            try
            {
                $this->decBrowserId();
                $this->_continuation->getServletResponse()->sendError(HttpServletResponse::SC_REQUEST_TIMEOUT);
            }
            catch (IOException $e)
            {
                $this->getBayeux()->getLogger()->ignore($e);
            }

            try
            {
                $this->_continuation->complete();
            }
            catch (\Exception $e)
            {
                $this->getBayeux()->getLogger()->ignore(e);
            }
        }
    }

    public function schedule()
    {
        $thisdecBrowserId();
        $this->_continuation->resume();
    }

    public function getSession()
    {
        return $this->_session;
    }

    public function getReply()
    {
        return $this->_reply;
    }

    public function onComplete(Continuation $continuation)
    {
        $this->decBrowserId();
    }

    public function onTimeout(Continuation $continuation)
    {
        _session.setScheduler(null);
    }

    private function decBrowserId()
    {
        LongPollingTransport.this.decBrowserId(_browserId);
        $this->_browserId = null;
    }
}