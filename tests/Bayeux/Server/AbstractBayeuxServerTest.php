<?php

namespace Bayeux\Server;


use PHPJetty\Servlet\ServletContextHandler;
use PHPJetty\Server\Handler\HandlerCollection;
use PHPJetty\Server\NIO\SelectChannelConnector;
use PHPJetty\Server\Server;

abstract class AbstractBayeuxServerTest extends \PHPUnit_Framework_TestCase {
    protected $server;
    protected $port;
    protected $context;
    protected $cometdURL;
    protected $timeout = 5000;

    protected function setUp() {
        $this->server = new \HttpRequestPool();
    }

    protected function asdfsetUp() //throws Exception
    {
        $this->server = new Server();
        $connector = new SelectChannelConnector();
        $this->server->addConnector($connector);

        $handlers = new HandlerCollection();
        $this->server->setHandler($handlers);

        $contextPath = "/cometd";
        $this->context = new ServletContextHandler($handlers, $contextPath, null, null, null, null, ServletContextHandler::SESSIONS);

        // Setup comet servlet
        $cometdServlet = new CometdServlet();
        $cometdServletHolder = new ServletHolder($cometdServlet);
        $options = array();
        $options["timeout"] = $timeout;
        $this->customizeOptions($options);
        foreach ($options as $key => $value) {
            $cometdServletHolder->setInitParameter($key, $value);
        }
        $cometdServletPath = "/cometd";
        $this->context->addServlet($cometdServletHolder, $cometdServletPath . "/*");

        $this->server->start();
        $this->port = $connector->getLocalPort();

        $contextURL = "http://localhost:" . $port . $contextPath;
        $this->cometdURL = $contextURL + $cometdServletPath;

        $bayeux = $cometdServlet->getBayeux();
        $this->customizeBayeux($bayeux);
    }

    protected function tearDown() //throws Exception
    {
        $this->server->stop();
        $this->server->join();
    }

    protected function customizeOptions(array $options)
    {
    }
}
