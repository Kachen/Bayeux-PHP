<?php

namespace Bayeux\Server;

use Bayeux\Server\Transport\HttpTransport;

use Bayeux\Http\Response,
    Bayeux\Http\Request;

class CometdHttp {


    protected $_bayeux;
    protected $_request;
    protected $_response;

    public function __construct() {
        $this->_bayeux = new BayeuxServerImpl();
        $this->_request = new Request();
        $this->_response = new Response();
    }

    public function start() {
        $this->_bayeux->start();
    }

    public function service(Request $request = null) {
        if ($request == null) {
            $request = $this->_request;
        }
        $response = $this->_response;

        if ($request->isOptions()) {
            $this->serviceOptions($request, $response);
            return;
        }

        $transport = null;
        foreach ($this->_bayeux->getAllowedTransports() as $transportName) {
            $serverTransport = $this->_bayeux->getTransport($transportName);
            if ($serverTransport instanceof HttpTransport) {
                $t = $serverTransport;
                if ($t->accept($request))
                {
                    $transport = $t;
                    break;
                }
            }
        }

        if ($transport == null) {
            $response->sendError(Response::SC_BAD_REQUEST, "Unknown Bayeux Transport");

        } else {
            $this->_bayeux->setCurrentTransport($transport);
            $transport->setCurrentRequest($request);
            $transport->handle($request, $response);
            $this->printResponse($response);
            $transport->setCurrentRequest(null);
            $bayeux = $this->_bayeux;
            if ($bayeux != null) {
                $bayeux->setCurrentTransport(null);
            }
        }
        return $response;
    }

    private function printResponse($response) {

    }

    public function getBayeux() {
        return $this->_bayeux;
    }

    public function getRequest() {
        return $this->_request;
    }

    public function getResponse() {
        return $this->_response;
    }
}