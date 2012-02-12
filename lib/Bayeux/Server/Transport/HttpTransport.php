<?php

namespace Bayeux\Server\Transport;

use Bayeux\Server\BayeuxServerImpl;
use Bayeux\Server\ServerMessageImpl;
use Bayeux\Server\AbstractServerTransport;

/**
 * HTTP Transport base class.
 *
 * Used for transports that use HTTP for a transport or to initiate a transport connection.
 *
 */
abstract class HttpTransport extends AbstractServerTransport
{
    const JSON_DEBUG_OPTION="jsonDebug";
    const MESSAGE_PARAM="message";

    private $_currentRequest;
    private $_jsonDebug = false;

    public function __construct(BayeuxServerImpl $bayeux, $name)
    {
        $this->_currentRequest = $_REQUEST;
        parent::__construct($bayeux, $name);
    }

//     @Override
    public function init()
    {
        parent::init();
        $this->_jsonDebug = $this->getOption(self::JSON_DEBUG_OPTION, $this->_jsonDebug);
    }

    public abstract function accept(HttpServletRequest $request);

    public abstract function handle(HttpServletRequest $request, HttpServletResponse $response); //throws IOException, ServletException;

    protected function parseMessages(HttpServletRequest $request) //throws IOException, ParseException
    {
        $content_type = $request->getContentType();

        // Get message batches either as JSON body or as message parameters
        if ($content_type!=null && !$content_type.startsWith("application/x-www-form-urlencoded")) {
            return ServerMessageImpl.parseServerMessages(request.getReader(), _jsonDebug);
        }

        $batches=$request->getParameterValues(self::MESSAGE_PARAM);

        if ($batches == null || $batches.length == 0) {
            return null;
        }

        if ($batches.length == 1) {
            return ServerMessageImpl::parseServerMessages($batches[0]);
        }

        $messages = array();
        foreach ($batches as $batch)
        {
            if ($batch == null) {
                continue;
            }
            $messages.addAll(Arrays.asList(ServerMessageImpl::parseServerMessages(batch)));
        }
        return $messages;
    }

    /* ------------------------------------------------------------ */
    public function setCurrentRequest(HttpServletRequest $request)
    {
        $this->_currentRequest.set($request);
    }
    /* ------------------------------------------------------------ */

    public function getCurrentRequest()
    {
        return $this->_currentRequest;
    }

    /* ------------------------------------------------------------ */
    /**
     * @see org.cometd.bayeux.server.ServerTransport#getCurrentLocalAddress()
     */
    public function getCurrentLocalAddress()
    {
        $context = $this->getContext();
        if ($context!=null) {
            return $context->getLocalAddress();
        }

        return null;
    }

    /* ------------------------------------------------------------ */
    /**
     * @see org.cometd.bayeux.server.ServerTransport#getCurrentRemoteAddress()
     */
    public function getCurrentRemoteAddress()
    {
        $context = $this->getContext();
        if ($context!=null) {
            return $context->getRemoteAddress();
        }
        return null;
    }

    /* ------------------------------------------------------------ */
    /**
     * @see org.cometd.bayeux.server.ServerTransport#getContext()
     */
    public function getContext() {
        $request = $this->getCurrentRequest();
        if ($request!=null) {
            return new HttpContext($request);
        }
        return null;
    }
}
