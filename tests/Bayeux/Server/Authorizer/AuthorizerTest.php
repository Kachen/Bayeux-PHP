<?php

namespace Bayeux\Server\Authorizer;

use Bayeux\Api\Server\ConfigurableServerChannel;
use Bayeux\Api\Server\Authorizer;
/*
class AuthorizerTest extends AbstractBayeuxClientServerTest
{
    private $bayeux;

    protected function customizeBayeux(BayeuxServerImpl $bayeux)
    {
        $this->bayeux = $bayeux;
        //$bayeux.getLogger().setDebugEnabled(true);
    }

    public function testAuthorizersOnSlashStarStar()
    {
        $this->bayeux->createIfAbsent("/**", new ConfigurableServerChannelTest1());

        $handshake = $this->newBayeuxExchange("[{" +
                "\"channel\": \"/meta/handshake\"," +
                "\"version\": \"1.0\"," +
                "\"minimumVersion\": \"1.0\"," +
                "\"supportedConnectionTypes\": [\"long-polling\"]" +
                "}]");
        $httpClient->send($handshake);
        $this->assertEquals(HttpExchange::STATUS_COMPLETED, $handshake->waitForDone());
        $this->assertEquals(200, $handshake->getResponseStatus());

        $clientId = $this->extractClientId($handshake);

        $publish = $this->newBayeuxExchange("[{" +
                "\"channel\": \"/foo\"," +
                "\"clientId\": \"" + clientId + "\"," +
                "\"data\": {}" +
                "}]");
        httpClient.send(publish);
        $this->assertEquals(HttpExchange.STATUS_COMPLETED, publish.waitForDone());
        $this->assertEquals(200, publish.getResponseStatus());

        $messages = HashMapMessage.parseMessages(publish.getResponseContent());
        $this->assertEquals(1, messages.size());
        $message = messages.get(0);
        $this->assertFalse(message.isSuccessful());

        $publish = newBayeuxExchange("[{" .
                "\"channel\": \"/service/foo\"," .
                "\"clientId\": \"" . $clientId . "\"," .
                "\"data\": {}" .
                "}]");
        $httpClient->send($publish);
        $this->assertEquals(HttpExchange.STATUS_COMPLETED, $publish.waitForDone());
        $this->assertEquals(200, $publish->getResponseStatus());

        $messages = HashMapMessage.parseMessages(publish.getResponseContent());
        $this->assertEquals(1, $messages.size());
        $message = $messages.get(0);
        $this->assertTrue($message->isSuccessful());
    }

    public function testIgnoringAuthorizerDenies()
    {
        String channelName = "/test";
        bayeux.createIfAbsent(channelName, new ConfigurableServerChannel.Initializer()
        {
            public void configureChannel(ConfigurableServerChannel channel)
            {
                channel.addAuthorizer(new Authorizer()
                {
                    public Result authorize(Operation operation, ChannelId channel, ServerSession session, ServerMessage message)
                    {
                        return Result.ignore();
                    }
                });
            }
        });

        $handshake = $this->newBayeuxExchange("[{" .
                "\"channel\": \"/meta/handshake\"," .
                "\"version\": \"1.0\"," .
                "\"minimumVersion\": \"1.0\"," .
                "\"supportedConnectionTypes\": [\"long-polling\"]" .
                "}]");
        $httpClient->send($handshake);
        $this->assertEquals(HttpExchange::STATUS_COMPLETED, $handshake->waitForDone());
        $this->assertEquals(200, $handshake->getResponseStatus());

        $clientId = $this->extractClientId($handshake);

        ContentExchange publish = $this->newBayeuxExchange("[{" .
                "\"channel\": \"" . $channelName . "\"," +
                "\"clientId\": \"" . $clientId . "\"," +
                "\"data\": {}" .
                "}]");
        httpClient.send(publish);
        $this->assertEquals(HttpExchange.STATUS_COMPLETED, $publish->waitForDone());
        $this->assertEquals(200, $publish->getResponseStatus());

        $messages = HashMapMessage.parseMessages($publish->getResponseContent());
        $this->assertEquals(1, $messages.size());
        $message = $messages.get(0);
        $this->assertFalse($message->isSuccessful());

        // Check that publishing to another channel does not involve authorizers
        $grantedPublish = newBayeuxExchange("[{" +
                "\"channel\": \"/foo\"," +
                "\"clientId\": \"" + clientId + "\"," +
                "\"data\": {}" +
                "}]");
        $httpClient.send(grantedPublish);
        $this->assertEquals(HttpExchange.STATUS_COMPLETED, $grantedPublish->waitForDone());
        $this->assertEquals(200, $grantedPublish->getResponseStatus());

        $messages = HashMapMessage.parseMessages($grantedPublish->getResponseContent());
        $this->assertEquals(1, $messages.size());
        $message = $messages.get(0);
        $this->assertTrue($message->isSuccessful());
    }

    public function testNoAuthorizersGrant() throws Exception
    {
        ContentExchange handshake = newBayeuxExchange("[{" +
                "\"channel\": \"/meta/handshake\"," +
                "\"version\": \"1.0\"," +
                "\"minimumVersion\": \"1.0\"," +
                "\"supportedConnectionTypes\": [\"long-polling\"]" +
                "}]");
        httpClient.send(handshake);
        assertEquals(HttpExchange.STATUS_COMPLETED, handshake.waitForDone());
        assertEquals(200, handshake.getResponseStatus());

        String clientId = extractClientId(handshake);

        ContentExchange publish = newBayeuxExchange("[{" +
                "\"channel\": \"/test\"," +
                "\"clientId\": \"" + clientId + "\"," +
                "\"data\": {}" +
                "}]");
        httpClient.send(publish);
        assertEquals(HttpExchange.STATUS_COMPLETED, publish.waitForDone());
        assertEquals(200, publish.getResponseStatus());

        List<Message.Mutable> messages = HashMapMessage.parseMessages(publish.getResponseContent());
        assertEquals(1, messages.size());
        Message message = messages.get(0);
        assertTrue(message.isSuccessful());
    }

    public function testDenyAuthorizerDenies() throws Exception
    {
        bayeux.createIfAbsent("/test/*", new ConfigurableServerChannel.Initializer()
        {
            public void configureChannel(ConfigurableServerChannel channel)
            {
                channel.addAuthorizer(GrantAuthorizer.GRANT_ALL);
            }
        });
        String channelName = "/test/denied";
        bayeux.createIfAbsent(channelName, new ConfigurableServerChannel.Initializer()
        {
            public void configureChannel(ConfigurableServerChannel channel)
            {
                channel.addAuthorizer(new Authorizer()
                {
                    public Result authorize(Operation operation, ChannelId channel, ServerSession session, ServerMessage message)
                    {
                        return Result.deny("test");
                    }
                });
            }
        });

        ContentExchange handshake = newBayeuxExchange("[{" +
                "\"channel\": \"/meta/handshake\"," +
                "\"version\": \"1.0\"," +
                "\"minimumVersion\": \"1.0\"," +
                "\"supportedConnectionTypes\": [\"long-polling\"]" +
                "}]");
        httpClient.send(handshake);
        assertEquals(HttpExchange.STATUS_COMPLETED, handshake.waitForDone());
        assertEquals(200, handshake.getResponseStatus());

        String clientId = extractClientId(handshake);

        ContentExchange publish = newBayeuxExchange("[{" +
                "\"channel\": \"" + channelName + "\"," +
                "\"clientId\": \"" + clientId + "\"," +
                "\"data\": {}" +
                "}]");
        httpClient.send(publish);
        assertEquals(HttpExchange.STATUS_COMPLETED, publish.waitForDone());
        assertEquals(200, publish.getResponseStatus());

        List<Message.Mutable> messages = HashMapMessage.parseMessages(publish.getResponseContent());
        assertEquals(1, messages.size());
        Message message = messages.get(0);
        assertFalse(message.isSuccessful());

        // Check that publishing to another channel does not involve authorizers
        ContentExchange grantedPublish = newBayeuxExchange("[{" +
                "\"channel\": \"/foo\"," +
                "\"clientId\": \"" + clientId + "\"," +
                "\"data\": {}" +
                "}]");
        httpClient.send(grantedPublish);
        assertEquals(HttpExchange.STATUS_COMPLETED, grantedPublish.waitForDone());
        assertEquals(200, grantedPublish.getResponseStatus());

        messages = HashMapMessage.parseMessages(grantedPublish.getResponseContent());
        assertEquals(1, messages.size());
        message = messages.get(0);
        assertTrue(message.isSuccessful());
    }

    public function testAddRemoveAuthorizer() throws Exception
    {
        bayeux.createIfAbsent("/test/*", new ConfigurableServerChannel.Initializer()
        {
            public void configureChannel(ConfigurableServerChannel channel)
            {
                channel.addAuthorizer(GrantAuthorizer.GRANT_NONE);
            }
        });
        String channelName = "/test/granted";
        bayeux.createIfAbsent(channelName, new ConfigurableServerChannel.Initializer()
        {
            public void configureChannel(final ConfigurableServerChannel channel)
            {
                channel.addAuthorizer(new Authorizer()
                {
                    public Result authorize(Operation operation, ChannelId channelId, ServerSession session, ServerMessage message)
                    {
                        channel.removeAuthorizer(this);
                        return Result.grant();
                    }
                });
            }
        });

        ContentExchange handshake = newBayeuxExchange("[{" +
                "\"channel\": \"/meta/handshake\"," +
                "\"version\": \"1.0\"," +
                "\"minimumVersion\": \"1.0\"," +
                "\"supportedConnectionTypes\": [\"long-polling\"]" +
                "}]");
        httpClient.send(handshake);
        assertEquals(HttpExchange.STATUS_COMPLETED, handshake.waitForDone());
        assertEquals(200, handshake.getResponseStatus());

        String clientId = extractClientId(handshake);

        ContentExchange publish = newBayeuxExchange("[{" +
                "\"channel\": \"" + channelName + "\"," +
                "\"clientId\": \"" + clientId + "\"," +
                "\"data\": {}" +
                "}]");
        httpClient.send(publish);
        assertEquals(HttpExchange.STATUS_COMPLETED, publish.waitForDone());
        assertEquals(200, publish.getResponseStatus());

        $messages = HashMapMessage.parseMessages(publish.getResponseContent());
        assertEquals(1, messages.size());
        Message message = messages.get(0);
        assertTrue(message.isSuccessful());

        // Check that publishing again fails (the authorizer has been removed)
        ContentExchange grantedPublish = newBayeuxExchange("[{" +
                "\"channel\": \"" + channelName + "\"," +
                "\"clientId\": \"" + clientId + "\"," +
                "\"data\": {}" +
                "}]");
        httpClient.send(grantedPublish);
        assertEquals(HttpExchange.STATUS_COMPLETED, grantedPublish.waitForDone());
        assertEquals(200, grantedPublish.getResponseStatus());

        messages = HashMapMessage.parseMessages(grantedPublish.getResponseContent());
        assertEquals(1, messages.size());
        message = messages.get(0);
        assertFalse(message.isSuccessful());
    }
}


class ConfigurableServerChannelTest1 implements ConfigurableServerChannel\Initializer
{
    public function configureChannel(ConfigurableServerChannel $channel)
    {
        // Grant create and subscribe to all and publishes only to service channels
        $channel->addAuthorizer(GrantAuthorizer::GRANT_CREATE_SUBSCRIBE);
        $channel->addAuthorizer(new AuthorizerTest1());
    }
}


class AuthorizerTest1 implements Authorizer
{
    public function authorize(Operation $operation, ChannelId $channel, ServerSession $session, ServerMessage $message)
    {
        if ($operation == Operation::PUBLISH && $channel->isService()) {
            return Result.grant();
        }
        return Result::ignore();
    }
}
*/