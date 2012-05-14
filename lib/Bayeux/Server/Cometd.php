<?php

namespace Bayeux\Server;

use Bayeux\Http\Response;
use Bayeux\Http\Request;

class Cometd {

    private $_options = array();
    private $_cometd;

    public function __construct() {
        $this->_cometd = new CometdHttp();
    }

    public function setOptions () {
        $this->_options = $options;
    }

    public function setOption($name, $value) {
        $this->_options[$name] = $value;
    }

    public function getBeayux() {
        return $this->_cometd->getBayeux();
    }

    public function start() {
        $this->_cometd->start();
    }

    public function loop() {
        while(true) {
            $response = $this->service();
            $this->outputResponse($response);
            sleep(2);
        }
    }

    private function outputResponse(Response $response) {

    }

    public function service(Request $request = null) {
        return $this->_cometd->service($request);
    }
}