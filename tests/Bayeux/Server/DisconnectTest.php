<?php

namespace Bayeux\Server;

use Bayeux\Api\Server\ServerSession;

class DisconnectTest extends AbstractBayeuxClientServerTest {

    public function testDisconnect() {
        $request = $this->newBayeuxExchange("[{" .
                    "\"channel\": \"/meta/handshake\"," .
                    "\"version\": \"1.0\"," .
                    "\"minimumVersion\": \"1.0\"," .
                    "\"supportedConnectionTypes\": [\"long-polling\"]" .
                    "}]");

        $response = $this->loop($request);


        $clientId = $this->extractClientId($response);

        exit;

        $connect = $this->newBayeuxExchange("[{" .
                    "\"channel\": \"/meta/connect\"," .
                    "\"clientId\": \"" . $clientId + "\"," .
                    "\"connectionType\": \"long-polling\"" .
                    "}]");
        $this->assertEquals(200, connect.getResponseStatus());

        $serverSession = $this->bayeux->getSession($clientId);
        $this->assertNotNull($serverSession);

        $latch = new CountDownLatch(1);
        $serverSession->addListener(new ServerSessionRemoveListenerTest());

        $this->newBayeuxExchange("[{" +
                    "\"channel\": \"/meta/disconnect\"," +
                    "\"clientId\": \"" + clientId + "\"" +
                    "}]");
        $this->assertTrue($latch->await(5, TimeUnit.SECONDS));
    }
}

class ServerSessionRemoveListenerTest implements ServerSession\RemoveListener
{
    public function removed(ServerSession $session, $timeout)
    {
        $latch->countDown();
    }
}