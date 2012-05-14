<?php

namespace Bayeux\Server;

use Bayeux\Http\Request;

abstract class AbstractBayeuxServerTest extends \PHPUnit_Framework_TestCase {

    protected $timeout = 5000;
    protected $cometd;
    protected $uri = 'http://localhost/cometd/cometd.php';

    protected function setUp()
    {
        $this->cometd = $cometd = new Cometd();
        // Setup comet servlet
        $options = array("timeout" => $this->timeout);
        foreach ($options as $key => $value) {
            $cometd->setOption($key, $value);
        }

        $cometd->start();
    }

    protected function loop(Request $request) {
        return $this->cometd->service($request);
    }
}