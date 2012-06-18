<?php

namespace Bayeux\Server\Transport;

use Bayeux\Server\Transport\HttpTransport\HttpContext;
use Bayeux\Http\Response;
use Bayeux\Http\Request;
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
    const JSON_DEBUG_OPTION = "jsonDebug";
    const MESSAGE_PARAM = "message";

    private $_currentRequest;

    public function __construct(BayeuxServerImpl $bayeux, $name) {
        parent::__construct($bayeux, $name);
    }

    public abstract function accept(Request $request);

    public abstract function handle(Request $request, Response $response); //throws IOException, ServletException;

    public function setCurrentRequest(Request $request = null) {
        $this->_currentRequest = $request;
    }

    public function getCurrentRequest() {
        return $this->_currentRequest;
    }

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