<?php

namespace Bayeux\Server;

use Bayeux\Api\Server\ServerSession;

class DisconnectTest extends AbstractBayeuxClientServerTest {

    public static $latch = 0;

    public function testDisconnect() {
        $handshake = $this->newBayeuxExchange("[{" .
                    "\"channel\": \"/meta/handshake\"," .
                    "\"version\": \"1.0\"," .
                    "\"minimumVersion\": \"1.0\"," .
                    "\"supportedConnectionTypes\": [\"long-polling\"]" .
                    "}]");

        $clientId = $this->extractClientId($handshake);

        $connect = $this->newBayeuxExchange("[{" .
                    "\"channel\": \"/meta/connect\"," .
                    "\"clientId\": \"{$clientId}\"," .
                    "\"connectionType\": \"long-polling\"" .
                    "}]");
        $this->assertEquals(200, $connect->getStatusCode());

        $serverSession = $this->bayeux->getSession($clientId);
        $this->assertNotNull($serverSession);
        //$latch = new CountDownLatch(1);
        DisconnectTest::$latch = 1;
        $serverSession->addListener(new ServerSessionRemoveListenerTest());

        $disconnect = $this->newBayeuxExchange("[{" .
                    "\"channel\": \"/meta/disconnect\"," .
                    "\"clientId\": \"{$clientId}\"" .
                    "}]");

        $this->assertEquals(0, DisconnectTest::$latch);
    }
}

class ServerSessionRemoveListenerTest implements ServerSession\RemoveListener {
    public function removed(ServerSession $session, $timeout) {
        DisconnectTest::$latch--;
    }
}