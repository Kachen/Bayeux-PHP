<?php
/*
 * Copyright (c) 2010 the original author or authors.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Bayeux\Server;

use Bayeux\Api\Server\ServerSession;
use Bayeux\Api\Message;
use Bayeux\Api\Channel;

class BayeuxServiceMetaNotificationsTest extends AbstractBayeuxClientServerTest {

    public static $handshakeLatch = 1;
    public static $connectLatch = 1;
    public static $subscribeLatch = 1;
    public static $unsubscribeLatch = 1;
    public static $disconnectLatch = 1;

    public function testMetaNotifications() {

        $abstractService = new AbstractServiceTest($this->bayeux, "test");
        $abstractService->addService(Channel::META_HANDSHAKE, "metaHandshake");
        $abstractService->addService(Channel::META_CONNECT, "metaConnect");
        $abstractService->addService(Channel::META_SUBSCRIBE, "metaSubscribe");
        $abstractService->addService(Channel::META_UNSUBSCRIBE, "metaUnsubscribe");
        $abstractService->addService(Channel::META_DISCONNECT, "metaDisconnect");

        $handshake = $this->newBayeuxExchange("[{" .
                "\"channel\": \"/meta/handshake\"," .
                "\"version\": \"1.0\"," .
                "\"minimumVersion\": \"1.0\"," .
                "\"supportedConnectionTypes\": [\"long-polling\"]" .
                "}]");

        $this->assertEquals(0, BayeuxServiceMetaNotificationsTest::$handshakeLatch);
        //$this->assertEquals(HttpExchange.STATUS_COMPLETED, handshake.waitForDone());
        $this->assertEquals(200, $handshake->getStatusCode());

        $clientId = $this->extractClientId($handshake);
        //$bayeuxCookie = $this->extractBayeuxCookie(handshake);

        $connect = $this->newBayeuxExchange("[{" .
                "\"channel\": \"/meta/connect\"," .
                "\"clientId\": \"{$clientId}\"," .
                "\"connectionType\": \"long-polling\"" .
                "}]");
        //$connect->setRequestHeader(HttpHeaders.COOKIE, bayeuxCookie);
        //httpClient.send(connect);
        $this->assertEquals(0, BayeuxServiceMetaNotificationsTest::$connectLatch);
        //$this->assertEquals(HttpExchange.STATUS_COMPLETED, connect.waitForDone());
        $this->assertEquals(200, $connect->getStatusCode());

        $channel = "/foo";
        $subscribe = $this->newBayeuxExchange("[{" .
                "\"channel\": \"/meta/subscribe\"," .
                "\"clientId\": \"{$clientId}\"," .
                "\"subscription\": \"{$channel}\"" .
                "}]");
        //httpClient.send(subscribe);
        $this->assertEquals(0, BayeuxServiceMetaNotificationsTest::$subscribeLatch);
        //$this->assertEquals(HttpExchange.STATUS_COMPLETED, subscribe.waitForDone());
        $this->assertEquals(200, $subscribe->getStatusCode());

        $unsubscribe = $this->newBayeuxExchange("[{" .
                "\"channel\": \"/meta/unsubscribe\"," .
                "\"clientId\": \"{$clientId}\"," .
                "\"subscription\": \"{$channel}\"" .
                "}]");
        //httpClient.send(unsubscribe);
        $this->assertEquals(0, BayeuxServiceMetaNotificationsTest::$unsubscribeLatch);
        //$this->assertEquals(HttpExchange.STATUS_COMPLETED, unsubscribe.waitForDone());
        $this->assertEquals(200, $unsubscribe->getStatusCode());

        $disconnect = $this->newBayeuxExchange("[{" .
                "\"channel\": \"/meta/disconnect\"," .
                "\"clientId\": \"{$clientId}\"" .
                "}]");
        $this->assertEquals(0, BayeuxServiceMetaNotificationsTest::$disconnectLatch);
        //$this->assertEquals(HttpExchange.STATUS_COMPLETED, disconnect.waitForDone());
        $this->assertEquals(200, $disconnect->getStatusCode());
    }
}

class AbstractServiceTest extends AbstractService {

    public function metaHandshake(ServerSession $remote = null, Message $message) {
        BayeuxServiceMetaNotificationsTest::$handshakeLatch--;
    }

    public function metaConnect(ServerSession $remote, Message $message) {
        BayeuxServiceMetaNotificationsTest::$connectLatch--;
    }

    public function metaSubscribe(ServerSession $remote, Message $message) {
        BayeuxServiceMetaNotificationsTest::$subscribeLatch--;
    }

    public function metaUnsubscribe(ServerSession $remote, Message $message) {
        BayeuxServiceMetaNotificationsTest::$unsubscribeLatch--;
    }

    public function metaDisconnect(ServerSession $remote, Message $message) {
        BayeuxServiceMetaNotificationsTest::$disconnectLatch--;
    }
}