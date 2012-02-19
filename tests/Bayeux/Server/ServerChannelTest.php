<?php

namespace Bayeux\Server;

use Bayeux\Server\Authorizer\GrantAuthorizer;
use Bayeux\Api\Server\ServerMessage\Mutable;
use Bayeux\Api\Server\BayeuxServer;
use Bayeux\Api\Server\ServerChannel\MessageListener;
use Bayeux\Api\Server\ServerSession;
use Bayeux\Api\Server\ServerChannel;
use Bayeux\Api\Server\ConfigurableServerChannel;

class ServerChannelTest extends \PHPUnit_Framework_TestCase
{
    private $_bayeuxChannelListener;
    private $_bayeuxSubscriptionListener;
    private $_bayeux;

    public function setUp()
    {
        $this->_bayeuxChannelListener = new BayeuxChannelListener();
        $this->_bayeuxSubscriptionListener = new BayeuxSubscriptionListener();
        $this->_bayeux = new BayeuxServerImpl();
        $this->_bayeux->start();
        $this->_bayeux->addListener($this->_bayeuxChannelListener);
        $this->_bayeux->addListener($this->_bayeuxSubscriptionListener);
    }

    public function tearDown()
    {
        $this->_bayeux->removeListener($this->_bayeuxSubscriptionListener);
        $this->_bayeux->removeListener($this->_bayeuxChannelListener);
        $this->_bayeux->stop();
    }

    public function testChannelCreate()
    {
        $this->assertNull($this->_bayeux->getChannel("/foo"));
        $this->assertNull($this->_bayeux->getChannel("/foo/bar"));
        $this->_bayeux->createIfAbsent("/foo/bar");
        $this->_bayeux->getChannel("/foo/bar");

        $this->assertNotNull($this->_bayeux->getChannel("/foo"));
        $this->assertNotNull($this->_bayeux->getChannel("/foo/bar"));

        $this->assertEquals(4, $this->_bayeuxChannelListener->_calls);
        $this->assertEquals("initadded", $this->_bayeuxChannelListener->_method);


        $this->assertEquals("/foo/bar", $this->_bayeuxChannelListener->_channel);
        $this->_bayeux->createIfAbsent("/foo/bob");
        $this->assertTrue($this->_bayeux->getChannel("/foo/bob") != null);
        $this->assertEquals(6, $this->_bayeuxChannelListener->_calls);
        $this->assertEquals("initadded", $this->_bayeuxChannelListener->_method);
        $this->assertEquals("/foo/bob", $this->_bayeuxChannelListener->_channel);
    }

    public function testSubscribe()
    {
        $this->_bayeux->createIfAbsent("/foo/bar");
        $fooBar = $this->_bayeux->getChannel("/foo/bar");

        $csubl = new ChannelSubscriptionListener();
        $fooBar->addListener($csubl);
        $session0 = $this->newServerSession();

        $fooBar->subscribe($session0);
        $this->assertEquals(1, count($fooBar->getSubscribers()));
        $this->assertTrue(in_array($session0, $fooBar->getSubscribers()));
        $this->assertEquals("subscribed", $this->_bayeuxSubscriptionListener->_method);
        $this->assertEquals($fooBar, $this->_bayeuxSubscriptionListener->_channel);
        $this->assertEquals($session0, $this->_bayeuxSubscriptionListener->_session);

        $this->assertEquals("subscribed", $csubl->_method);
        $this->assertEquals($fooBar, $csubl->_channel);
        $this->assertEquals($session0, $csubl->_session);

        // config+add for /foo and config+add for /foo/bar
        $this->assertEquals(4, $this->_bayeuxChannelListener->_calls);
        $session1 = $this->newServerSession();
        $this->_bayeux->createIfAbsent("/foo/*");
        $this->_bayeux->getChannel("/foo/*")->subscribe($session1);

        $this->assertEquals("subscribed", $this->_bayeuxSubscriptionListener->_method);
        $this->assertEquals("/foo/*", $this->_bayeuxSubscriptionListener->_channel->getId());
        $this->assertEquals($session1, $this->_bayeuxSubscriptionListener->_session);

        // config+add for /foo/*
        $this->assertEquals(6, $this->_bayeuxChannelListener->_calls);

        $session2 = $this->newServerSession();
        $this->_bayeux->createIfAbsent("/**");
        $this->_bayeux->getChannel("/**")->subscribe($session2);

        $this->assertEquals("subscribed", $this->_bayeuxSubscriptionListener->_method);
        $this->assertEquals("/**", $this->_bayeuxSubscriptionListener->_channel->getId());
        $this->assertEquals($session2, $this->_bayeuxSubscriptionListener->_session);

        // config+add for /**
        $this->assertEquals(8, $this->_bayeuxChannelListener->_calls);

        $fooBar->unsubscribe($session0);
        $this->assertEquals(0, count($fooBar->getSubscribers()));
        $this->assertFalse(in_array($session0, $fooBar->getSubscribers(), true));
        $this->assertEquals("unsubscribed", $this->_bayeuxSubscriptionListener->_method);
        $this->assertEquals($fooBar, $this->_bayeuxSubscriptionListener->_channel);
        $this->assertEquals($session0, $this->_bayeuxSubscriptionListener->_session);

        $this->assertEquals("unsubscribed", $csubl->_method);
        $this->assertEquals($fooBar, $csubl->_channel);
        $this->assertEquals($session0, $csubl->_session);


        // Remove also the listener, then sweep: /foo/bar should be gone
        $fooBar->removeListener($csubl);
        $this->sweep();


        // remove for /foo/bar
        $this->assertEquals(9, $this->_bayeuxChannelListener->_calls);
        $this->assertEquals("/foo/bar", $this->_bayeuxChannelListener->_channel);
        $this->assertEquals("removed", $this->_bayeuxChannelListener->_method);

        $this->_bayeux->createIfAbsent("/foo/bob");
        $fooBob = $this->_bayeux->getChannel("/foo/bob");
        $fooBob->subscribe($session0);
        $foo = $this->_bayeux->getChannel("/foo");
        $foo->subscribe($session0);
        $foo->addListener(new ChannelSubscriptionListener());

        // config+add for /foo/bob
        $this->assertEquals(11, $this->_bayeuxChannelListener->_calls);

        $foo->remove();

        // removed for /foo/bob, /foo/* and /foo
        $this->assertEquals(14, $this->_bayeuxChannelListener->_calls);
        $this->assertEquals("/foo", $this->_bayeuxChannelListener->_channel);
        $this->assertEquals("removed", $this->_bayeuxChannelListener->_method);

        $this->assertEquals(0, count($foo->getSubscribers()));
        $this->assertEquals(0, count($foo->getListeners()));
        $this->assertEquals(0, count($fooBob->getSubscribers()));
    }


    public function testUnSubscribeAll()
    {
        $this->_bayeux->createIfAbsent("/foo/bar");
        $channel = $this->_bayeux->getChannel("/foo/bar");
        $session0 = $this->newServerSession();

        $channel->subscribe($session0);
        $this->assertEquals(1, count($channel->getSubscribers()));
        $this->assertTrue(in_array($session0, $channel->getSubscribers()));

        $this->_bayeux->removeServerSession($session0, false);

        $this->assertEquals(0, count($channel->getSubscribers()));
        $this->assertFalse(in_array($session0, $channel->getSubscribers()));
    }


    public function testPublish()
    {
        $this->_bayeux->createIfAbsent("/foo/bar");
        $this->_bayeux->createIfAbsent("/foo/*");
        $this->_bayeux->createIfAbsent("/**");
        $this->_bayeux->createIfAbsent("/foo/bob");
        $this->_bayeux->createIfAbsent("/wibble");

        $foobar = $this->_bayeux->getChannel("/foo/bar");
        $foostar = $this->_bayeux->getChannel("/foo/*");
        $starstar = $this->_bayeux->getChannel("/**");
        $foobob = $this->_bayeux->getChannel("/foo/bob");
        $wibble = $this->_bayeux->getChannel("/wibble");

        $foobar->addListener(new TestMessageListener1());
        $foostar->addListener(new TestMessageListener2());
        $starstar->addListener(new TestMessageListener3());

        $session0 = $this->newServerSession();

        // this is a private API - not a normal subscribe!!
        $foobar->subscribe($session0);

        $session1 = $this->newServerSession();
        $foostar->subscribe($session1);
        $session2 = $this->newServerSession();
        $starstar->subscribe($session2);

        $msg = $this->_bayeux->newMessage();
        $msg->setData("Hello World");

        $foobar->publish($session0, $msg);
        $this->assertEquals(1, count($session0->getQueue()));
        $this->assertEquals(1, count($session1->getQueue()));
        $this->assertEquals(1, count($session2->getQueue()));

        $foobob->publish($session0, $this->_bayeux->newMessage($msg));
        $this->assertEquals(1, count($session0->getQueue()));
        $this->assertEquals(2, count($session1->getQueue()));
        $this->assertEquals(2, count($session2->getQueue()));

        $wibble->publish($session0, $this->_bayeux->newMessage($msg));
        $this->assertEquals(1, count($session0->getQueue()));
        $this->assertEquals(2, count($session1->getQueue()));
        $this->assertEquals(3, count($session2->getQueue()));

        $msg = $this->_bayeux->newMessage();
        $msg->setData("ignore");
        $foobar->publish($session0, $msg);
        $this->assertEquals(1, count($session0->getQueue()));
        $this->assertEquals(2, count($session1->getQueue()));
        $this->assertEquals(3, count($session2->getQueue()));

        $msg = $this->_bayeux->newMessage();
        $msg->setData("foostar");
        $msg->setLazy(true);
        $foobar->publish($session0, $msg);
        $this->assertEquals(2, count($session0->getQueue()));
        $this->assertEquals(3, count($session1->getQueue()));
        $this->assertEquals(4, count($session2->getQueue()));

        $msg = $this->_bayeux->newMessage();
        $msg->setData("starstar");
        $msg->setLazy(true);
        $foobar->publish($session0, $msg);
        $this->assertEquals(3, count($session0->getQueue()));
        $this->assertEquals(4, count($session1->getQueue()));
        $this->assertEquals(5, count($session2->getQueue()));

        $this->assertEquals("Hello World", $session0->getQueue()->dequeue()->getData());
        $this->assertEquals("FooStar", $session0->getQueue()->dequeue()->getData());
        $this->assertEquals("StarStar", $session0->getQueue()->dequeue()->getData());
    }

    public function testPublishFromSweptChannelSucceeds()
    {
        $this->_bayeux->createIfAbsent("/foo/**");
        $fooStarStar = $this->_bayeux->getChannel("/foo/**");

        $session1 = $this->newServerSession();
        $fooStarStar->subscribe($session1);

        $this->_bayeux->createIfAbsent("/foo/bar");
        $fooBar = $this->_bayeux->getChannel("/foo/bar");

        $this->sweep();

        $this->assertNull($this->_bayeux->getChannel($fooBar->getId()));

        $session0 = $this->newServerSession();
        $message = $this->_bayeux->newMessage();
        $message->setData("test");
        $fooBar->publish($session0, $message);

        $this->assertEquals(1, $session1->getQueue()->count());
    }

    public function testPersistentChannelIsNotSwept()
    {
        $channelName = "/foo/bar";
        $this->_bayeux->createIfAbsent($channelName);
        $foobar = $this->_bayeux->getChannel($channelName);
        $foobar->setPersistent(true);

        $this->sweep();
        $this->assertNotNull($this->_bayeux->getChannel($channelName));
    }



    public function testChannelWithSubscriberIsNotSwept()
    {
        $this->_bayeux->createIfAbsent("/foo/bar");
        $foobar = $this->_bayeux->getChannel("/foo/bar");
        $this->assertEquals($foobar, $this->_bayeux->getChannel("/foo/bar"));

        // First sweep does not remove the channel yet
        $this->_bayeux->sweep();
        $this->assertEquals($foobar, $this->_bayeux->getChannel("/foo/bar"));
        // Nor a second sweep
        $this->_bayeux->sweep();
        $this->assertEquals($foobar, $this->_bayeux->getChannel("/foo/bar"));
        // Third sweep removes it
        $this->_bayeux->sweep();
        $this->assertNull($this->_bayeux->getChannel("/foo/bar"));

        $this->_bayeux->createIfAbsent("/foo/bar/baz");
        $this->_bayeux->getChannel("/foo/bar/baz")->remove();
        $this->assertNull($this->_bayeux->getChannel("/foo/bar/baz"));
        $this->assertNotNull($this->_bayeux->getChannel("/foo/bar"));
        $this->assertNotNull($this->_bayeux->getChannel("/foo"));

        $this->sweep();
        $this->assertNull($this->_bayeux->getChannel("/foo/bar"));
        $this->assertNull($this->_bayeux->getChannel("/foo"));

        $this->_bayeux->createIfAbsent("/foo/bar");
        $foobar = $this->_bayeux->getChannel("/foo/bar");
        $this->assertEquals($foobar, $this->_bayeux->getChannel("/foo/bar"));

        $this->_bayeux->createIfAbsent("/foo/bar/baz");
        $foobarbaz = $this->_bayeux->getChannel("/foo/bar/baz");
        $session0 = $this->newServerSession();
        $foobarbaz->subscribe($session0);
        $this->_bayeux->getChannel("/foo")->subscribe($session0);

        $this->sweep();
        $this->assertNotNull($this->_bayeux->getChannel("/foo/bar/baz"));
        $this->assertNotNull($this->_bayeux->getChannel("/foo/bar"));
        $this->assertNotNull($this->_bayeux->getChannel("/foo"));

        $foobarbaz->unsubscribe($session0);

        $this->sweep();
        $this->assertNull($this->_bayeux->getChannel("/foo/bar/baz"));
        $this->assertNull($this->_bayeux->getChannel("/foo/bar"));
        $this->assertNotNull($this->_bayeux->getChannel("/foo"));

        $this->_bayeux->getChannel("/foo")->unsubscribe($session0);

        $this->sweep();
        $this->assertNull($this->_bayeux->getChannel("/foo"));
    }

    public function testChannelWithListenersIsNotSwept()
    {
        $channelName = "/test";
        $this->_bayeux->createIfAbsent($channelName);
        $channel = $this->_bayeux->getChannel($channelName);
        $channel->addListener(new TestMessageListener4());

        $this->sweep();

        $this->assertNotNull($this->_bayeux->getChannel($channelName));
    }

    public function testChannelsWithAutorizersSweeping()
    {
        $listener = new TestMessageListener5();
        $initializer = new TestConfigurableServerChannelInitializer1();

        $channelName1 = "/a/b/c";
        $this->_bayeux->createIfAbsent($channelName1);
        $channel1 = $this->_bayeux->getChannel($channelName1);
        $channel1->addListener($listener);

        $wildName1 = "/a/b/*";
        $this->_bayeux->createIfAbsent($wildName1, $initializer);

        $wildName2 = "/a/**";
        $this->_bayeux->createIfAbsent($wildName2, $initializer);

        $this->sweep();

        // Channel with authorizers but no listeners or subscriber must not be swept
        $this->assertNotNull($this->_bayeux->getChannel($channelName1));
        $this->assertNotNull($this->_bayeux->getChannel($wildName1));
        $this->assertNotNull($this->_bayeux->getChannel($wildName2));

        // Remove the authorizer from a wild parent must sweep the wild parent
        $this->_bayeux->getChannel($wildName2)->removeAuthorizer(GrantAuthorizer::GRANT_ALL());

        $this->sweep();

        $this->assertNotNull($this->_bayeux->getChannel($channelName1));
        $this->assertNotNull($this->_bayeux->getChannel($wildName1));
        $this->assertNull($this->_bayeux->getChannel($wildName2));

        // Remove the listener from a channel must not sweep the wild parent with authorizer
        // since other channels may be added later that will match the wild channel
        $this->_bayeux->getChannel($channelName1)->removeListener($listener);

        $this->sweep();

        $this->assertNull($this->_bayeux->getChannel($channelName1));
        $this->assertNotNull($this->_bayeux->getChannel($wildName1));
        $this->assertNull($this->_bayeux->getChannel($wildName2));
    }

    private function sweep()
    {
        // 12 is a big enough number that will make sure channel will be swept
        for ($i = 0; $i < 12; ++$i) {
            $this->_bayeux->sweep();
        }
    }

    /**
     * @return @return \Bayeux\Server\ServerSessionImpl
     */
    private function newServerSession()
    {
        $session = $this->_bayeux->newServerSession();
        $this->_bayeux->addServerSession($session);
        $session->handshake();
        $session->connect();
        return $session;
    }
}



class TestMessageListener1 implements MessageListener {
    public function onMessage(ServerSession $from, ServerChannel $channel, Mutable $message) {
        return ! ("ignore" == $message->getData());
    }
}


class TestMessageListener2 implements MessageListener {
    public function onMessage(ServerSession $from, ServerChannel $channel, Mutable $message)
    {
        if ("foostar" == $message->getData()) {
            $message->setData("FooStar");
        }
        return true;
    }
}


class TestMessageListener3 implements MessageListener {
    public function onMessage(ServerSession $from, ServerChannel $channel, Mutable $message)
    {
        if ("starstar" == $message->getData()) {
            $message->setData("StarStar");
        }
        return true;
    }
}

class TestMessageListener4 implements MessageListener
{
    public function onMessage(ServerSession $from, ServerChannel $channel, Mutable $message)
    {
        return true;
    }
}

class TestMessageListener5 implements MessageListener
{
    public function onMessage(ServerSession $from, ServerChannel $channel, Mutable $message)
    {
        return true;
    }
}


class TestConfigurableServerChannelInitializer1 implements ConfigurableServerChannel\Initializer {

    public function configureChannel(ConfigurableServerChannel $channel)
    {
        $channel->addAuthorizer(GrantAuthorizer::GRANT_ALL());
    }
}

class BayeuxSubscriptionListener implements BayeuxServer\SubscriptionListener
{
    public $_method;
    public $_session;
    public $_channel;

    public function reset()
    {
        $this->_method = null;
        $this->_session = null;
        $this->_channel = null;
    }

    public function subscribed(ServerSession $session, ServerChannel $channel)
    {
        $this->_method = "subscribed";
        $this->_session = $session;
        $this->_channel = $channel;
    }

    public function unsubscribed(ServerSession $session, ServerChannel $channel)
    {
        $this->_method = "unsubscribed";
        $this->_session = $session;
        $this->_channel = $channel;
    }
}

class ChannelSubscriptionListener implements ServerChannel\SubscriptionListener
{
    public $_method;
    public $_session;
    public $_channel;

    public function reset()
    {
        $this->_method = null;
        $this->_session = null;
        $this->_channel = null;
    }

    public function subscribed(ServerSession $session, ServerChannel $channel)
    {
        $this->_method = "subscribed";
        $this->_session = $session;
        $this->_channel = $channel;
    }

    public function unsubscribed(ServerSession $session, ServerChannel $channel)
    {
        $this->_method = "unsubscribed";
        $this->_session = $session;
        $this->_channel = $channel;
    }
}

class BayeuxChannelListener implements BayeuxServer\ChannelListener
{
    public $_calls;
    public $_method;
    public $_channel;

    public function reset()
    {
        $this->_calls = 0;
        $this->_method = null;
        $this->_channel = null;
    }

    public function configureChannel(ConfigurableServerChannel $channel)
    {
        $this->_calls++;
        $this->_method = "init";
    }

    public function channelAdded(ServerChannel $channel)
    {
        $this->_calls++;
        $this->_method .= "added";
        $this->_channel = $channel->getId();
    }

    public function channelRemoved($channelId)
    {
        $this->_calls++;
        $this->_method = "removed";
        $this->_channel = $channelId;
    }
}