<?php

namespace Bayeux\Server;

use Bayeux\Api\Server\ServerSession;

abstract class DisconnectTest extends AbstractBayeuxClientServerTest {

    public function testDisconnect()
    {
$json = <<<JSON

JSON;

        $handshake = $this->newBayeuxExchange("[{" .
                    "\"channel\": \"/meta/handshake\"," .
                    "\"version\": \"1.0\"," .
                    "\"minimumVersion\": \"1.0\"," .
                    "\"supportedConnectionTypes\": [\"long-polling\"]" .
                    "}]");

        $httpClient->send($handshake);
        $this->assertEquals(HttpExchange.STATUS_COMPLETED, $handshake->waitForDone());
        $this->assertEquals(200, $handshake->getResponseStatus());

        $clientId = $this->extractClientId($handshake);
        $bayeuxCookie = $this->extractBayeuxCookie($handshake);

        $connect = $this->newBayeuxExchange("[{" .
                    "\"channel\": \"/meta/connect\"," .
                    "\"clientId\": \"" . $clientId + "\"," .
                    "\"connectionType\": \"long-polling\"" .
                    "}]");
        $connect->setRequestHeader(HttpHeaders::COOKIE, $bayeuxCookie);
        $httpClient.send(connect);
        $this->assertEquals(HttpExchange.STATUS_COMPLETED, connect.waitForDone());
        $this->assertEquals(200, connect.getResponseStatus());

        $serverSession = bayeux.getSession(clientId);
        $this->assertNotNull(serverSession);

        $latch = new CountDownLatch(1);
        $serverSession->addListener(new ServerSessionRemoveListenerTest());

        $disconnect = $this->newBayeuxExchange("[{" +
                    "\"channel\": \"/meta/disconnect\"," +
                    "\"clientId\": \"" + clientId + "\"" +
                    "}]");
        $httpClient->send($disconnect);
        $this->assertEquals(HttpExchange.STATUS_COMPLETED, disconnect.waitForDone());
        $this->assertEquals(200, disconnect.getResponseStatus());

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