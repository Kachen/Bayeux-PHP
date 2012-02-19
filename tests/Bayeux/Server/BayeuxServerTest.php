<?php

namespace Bayeux\Server;

use Bayeux\Api\Client\ClientSession;
use Bayeux\Api\Server\ServerMessage;
use Bayeux\Api\Message;
use Bayeux\Api\Client\ClientSessionChannel;
use Bayeux\Api\Server\ServerSession;
use Bayeux\Api\Server\ServerChannel;
use Bayeux\Api\Server\ConfigurableServerChannel;
use Bayeux\Api\Server\BayeuxServer;

class BayeuxServerTest extends \PHPUnit_Framework_TestCase
{
    public $_events;

    /**
     * @var Bayeux\Server\BayeuxServerImpl
     */
    private $_bayeux;


    //@Before
    public function setUp()// throws Exception
    {
        $this->_bayeux = new BayeuxServerImpl();
        $this->_events = new \SplQueue();
        $this->_bayeux->start();
    }

    //@After
    public function tearDown() //throws Exception
    {
        $this->_bayeux->stop();
        $this->_events = new \SplQueue();
    }


    private function newServerSession()
    {
        $session = $this->_bayeux->newServerSession();
        $this->_bayeux->addServerSession($session);
        $session->handshake();
        $session->connect();
        return $session;
    }

    //@Test
    public function testListeners() //throws Exception
    {

        $this->_bayeux->addListener(new SubListener($this));
        $this->_bayeux->addListener(new SessListener($this));
        $this->_bayeux->addListener(new CListener($this));

        $channelName = "/foo/bar";
        $this->_bayeux->createIfAbsent($channelName);
        $foobar = $this->_bayeux->getChannel($channelName);

        $channelName = "/foo/*";
        $this->_bayeux->createIfAbsent($channelName);
        $foostar = $this->_bayeux->getChannel($channelName);

        $channelName = "/**";
        $this->_bayeux->createIfAbsent($channelName);
        $starstar = $this->_bayeux->getChannel($channelName);

        $channelName = "/foo/bob";
        $this->_bayeux->createIfAbsent($channelName);
        $foobob = $this->_bayeux->getChannel($channelName);

        $channelName = "/wibble";
        $this->_bayeux->createIfAbsent($channelName);
        $wibble = $this->_bayeux->getChannel($channelName);

        $this->assertEquals("channelAdded", $this->_events->dequeue());
        $this->assertEquals($this->_bayeux->getChannel("/foo"), $this->_events->dequeue());
        $this->assertEquals("channelAdded", $this->_events->dequeue());
        $this->assertEquals($foobar, $this->_events->dequeue());
        $this->assertEquals("channelAdded", $this->_events->dequeue());
        $this->assertEquals($foostar, $this->_events->dequeue());
        $this->assertEquals("channelAdded", $this->_events->dequeue());
        $this->assertEquals($starstar, $this->_events->dequeue());
        $this->assertEquals("channelAdded", $this->_events->dequeue());
        $this->assertEquals($foobob, $this->_events->dequeue());
        $this->assertEquals("channelAdded", $this->_events->dequeue());
        $this->assertEquals($wibble, $this->_events->dequeue());


        $wibble->remove();
        $this->assertEquals("channelRemoved", $this->_events->dequeue());
        $this->assertEquals($wibble->getId(), $this->_events->dequeue());

        $session0 = $this->newServerSession();
        $session1 = $this->newServerSession();
        $session2 = $this->newServerSession();

        $this->assertEquals("sessionAdded", $this->_events->dequeue());
        $this->assertEquals($session0, $this->_events->dequeue());
        $this->assertEquals("sessionAdded", $this->_events->dequeue());
        $this->assertEquals($session1, $this->_events->dequeue());
        $this->assertEquals("sessionAdded", $this->_events->dequeue());
        $this->assertEquals($session2, $this->_events->dequeue());


        $foobar->subscribe($session0);
        $foobar->unsubscribe($session0);

        $this->assertEquals("subscribed", $this->_events->dequeue());
        $this->assertEquals($session0, $this->_events->dequeue());
        $this->assertEquals($foobar, $this->_events->dequeue());
        $this->assertEquals("unsubscribed", $this->_events->dequeue());
        $this->assertEquals($session0, $this->_events->dequeue());
        $this->assertEquals($foobar, $this->_events->dequeue());
    }


    public function testSessionAttributes() //throws Exception
    {

        $local = $this->_bayeux->newLocalSession("s0");
        $local->handshake();
        $session = $local->getServerSession();

        $local->setAttribute("foo","bar");
        $this->assertEquals("bar", $local->getAttribute("foo"));
        $this->assertEquals(null, $session->getAttribute("foo"));

        $session->setAttribute("bar","foo");
        $this->assertEquals(null, $local->getAttribute("bar"));
        $this->assertEquals("foo", $session->getAttribute("bar"));

        $this->assertTrue(in_array("foo", $local->getAttributeNames()));
        $this->assertFalse(in_array("bar", $local->getAttributeNames()));
        $this->assertFalse(in_array("foo", $session->getAttributeNames()));
        $this->assertTrue(in_array("bar", $session->getAttributeNames()));

        $this->assertEquals("bar", $local->removeAttribute("foo"));
        $this->assertEquals(null, $local->removeAttribute("foo"));
        $this->assertEquals("foo", $session->removeAttribute("bar"));
        $this->assertEquals(null, $local->removeAttribute("bar"));
    }


    public function testLocalSessions() //throws Exception
    {
        $session0 = $this->_bayeux->newLocalSession("s0");
        $this->assertEquals(strpos($session0->toString(), "s0?"), 2);
        $session0->handshake();
        $this->assertEquals(strpos($session0->toString(), "s0_"), 2);

        $session1 = $this->_bayeux->newLocalSession("s1");
        $session1->handshake();
        $session2 = $this->_bayeux->newLocalSession("s2");
        $session2->handshake();

        $events = new \SplQueue();

        $listener = new TestMessageListener($events);

        $session0->getChannel("/foo/bar")->subscribe($listener);
        $session0->getChannel("/foo/bar")->subscribe($listener);
        $session1->getChannel("/foo/bar")->subscribe($listener);
        $session2->getChannel("/foo/bar")->subscribe($listener);

        $this->assertEquals(3, count($this->_bayeux->getChannel("/foo/bar")->getSubscribers()));

        $session0->getChannel("/foo/bar")->unsubscribe($listener);
        $this->assertEquals(3, count($this->_bayeux->getChannel("/foo/bar")->getSubscribers()));
        $session0->getChannel("/foo/bar")->unsubscribe($listener);
        $this->assertEquals(2, count($this->_bayeux->getChannel("/foo/bar")->getSubscribers()));

        $foobar0 = $session0->getChannel("/foo/bar");
        $foobar0->subscribe($listener);
        $foobar0->subscribe($listener);

        $foostar0=$session0->getChannel("/foo/*");
        $foostar0->subscribe($listener);

        $this->assertEquals(3, count($this->_bayeux->getChannel("/foo/bar")->getSubscribers()));
        $this->assertEquals($session0, $foobar0->getSession());
        $this->assertEquals("/foo/bar", $foobar0->getId());
        $this->assertEquals(false, $foobar0->isDeepWild());
        $this->assertEquals(false, $foobar0->isWild());
        $this->assertEquals(false, $foobar0->isMeta());
        $this->assertEquals(false, $foobar0->isService());
        $foobar0->publish("hello");


        $this->assertEquals($session0->getId(), $events->dequeue());
        $this->assertEquals("hello", $events->dequeue());
        $this->assertEquals($session0->getId(), $events->dequeue());
        $this->assertEquals("hello", $events->dequeue());
        $this->assertEquals($session0->getId(), $events->dequeue());
        $this->assertEquals("hello", $events->dequeue());
        $this->assertEquals($session1->getId(), $events->dequeue());
        $this->assertEquals("hello", $events->dequeue());
        $this->assertEquals($session2->getId(), $events->dequeue());
        $this->assertEquals("hello", $events->dequeue());
        $foostar0->unsubscribe($listener);


        /*$session1->batch(new Runnable()
        {
            public void run()
            {*/
                $foobar1 = $session1->getChannel("/foo/bar");
                $foobar1->publish("part1");
                $this->assertEquals(null, $events->dequeue());
                $foobar1->publish("part2");
            /*}
        });*/

        $this->assertEquals($session1->getId(), $events->dequeue());
        $this->assertEquals("part1", $events->dequeue());
        $this->assertEquals($session2->getId(), $events->dequeue());
        $this->assertEquals("part1", $events.poll());
        $this->assertEquals($session0->getId(), $events->dequeue());
        $this->assertEquals("part1", $events->dequeue());
        $this->assertEquals($session0->getId(), $events->dequeue());
        $this->assertEquals("part1", $events->dequeue());
        $this->assertEquals($session1->getId(), $events->dequeue());
        $this->assertEquals("part2", $events->dequeue());
        $this->assertEquals($session2->getId(), $events->dequeue());
        $this->assertEquals("part2", $events->dequeue());
        $this->assertEquals($session0->getId(),$events->dequeue());
        $this->assertEquals("part2", $events->dequeue());
        $this->assertEquals($session0->getId(), $events->dequeue());
        $this->assertEquals("part2", $events->dequeue());
exit;

        $foobar0.unsubscribe();
        $this->assertEquals(2,_bayeux.getChannel("/foo/bar").getSubscribers().size());




        $this->assertTrue(session0.isConnected());
        $this->assertTrue(session1.isConnected());
        $this->assertTrue(session2.isConnected());
        $ss0=session0.getServerSession();
        $ss1=session1.getServerSession();
        $ss2=session2.getServerSession();
        $this->assertTrue(ss0.isConnected());
        $this->assertTrue(ss1.isConnected());
        $this->assertTrue(ss2.isConnected());

        $session0.disconnect();
        $this->assertFalse(session0.isConnected());
        $this->assertFalse(ss0.isConnected());

        $session1.getServerSession().disconnect();
        $this->assertFalse(session1.isConnected());
        $this->assertFalse(ss1.isConnected());

        $session2.getServerSession().disconnect();
        $this->assertFalse(session2.isConnected());
        $this->assertFalse(ss2.isConnected());
    }


    public function testExtensions() //throws Exception
    {
        $events = new \SplQueue();
        $this->_bayeux->addExtension(new BayeuxServerExtensionTest());

        $session0 = $this->_bayeux->newLocalSession("s0");
        $session0->handshake();
        //final LocalSession session1 = _bayeux.newLocalSession("s1");
        //session1.handshake();

        $session0->addExtension(new ClientSessionExtensionTest());
        $session0->getServerSession()->addExtension(new ServerSessionExtensionTest());
        $listener = new ClientSessionChannelMessageListenerTest();

        $session0->getChannel("/foo/bar")->subscribe($listener);
        // session1.getChannel("/foo/bar").subscribe(listener);

        $session0->getChannel("/foo/bar")->publish("zero");
        $session0->getChannel("/foo/bar")->publish("ignoreSend");
        $session0->getChannel("/foo/bar")->publish("ignoreRcv");

        usleep(100);
        //System.err->println(events);

        $this->assertEquals($session0->getId(), $events->dequeue());
        $this->assertEquals("six", $events->dequeue());


        //assertEquals(session1.getId(),events.poll());
        //assertEquals("four",events.poll());
        //assertEquals(null,events.poll());


    }
}


abstract class AListener {

    protected $_test;

    public function __construct(BayeuxServerTest $test) {
        $this->_test = $test;
    }
}

class CListener extends AListener implements BayeuxServer\ChannelListener
{
    public function configureChannel(ConfigurableServerChannel $channel)
    {
    }

    public function channelAdded(ServerChannel $channel)
    {
        $this->_test->_events->enqueue("channelAdded");
        $this->_test->_events->enqueue($channel);
    }

    public function channelRemoved($channelId)
    {
        $this->_test->_events->enqueue("channelRemoved");
        $this->_test->_events->enqueue($channelId);
    }

}


class SessListener extends AListener implements BayeuxServer\SessionListener
{
    public function sessionAdded(ServerSession $session)
    {
        $this->_test->_events->enqueue("sessionAdded");
        $this->_test->_events->enqueue($session);
    }

    public function sessionRemoved(ServerSession $session, $timedout)
    {
        $this->_test->_events->enqueue("sessionRemoved");
        $this->_test->_events->enqueue($session);
        $this->_test->_events->enqueue($timedout);
    }
}

class SubListener extends AListener implements BayeuxServer\SubscriptionListener
{
    public function subscribed(ServerSession $session, ServerChannel $channel)
    {
        $this->_test->_events->enqueue("subscribed");
        $this->_test->_events->enqueue($session);
        $this->_test->_events->enqueue($channel);
    }

    public function unsubscribed(ServerSession $session, ServerChannel $channel)
    {
        $this->_test->_events->enqueue("unsubscribed");
        $this->_test->_events->enqueue($session);
        $this->_test->_events->enqueue($channel);
    }

}

class TestMessageListener implements ClientSessionChannel\MessageListener
{

    private $events;

    public function __construct(\SplQueue $events) {
        $this->events = $events;
    }

    public function onMessage(ClientSessionChannel $channel, Message $message)
    {
        $this->events->enqueue($channel->getSession()->getId());
        $this->events->enqueue((string) $message->getData());
    }
}

class BayeuxServerExtensionTest implements BayeuxServer\Extension
{
    public function sendMeta(ServerSession $to = null, ServerMessage\Mutable $message)
    {
        return true;
    }

    public function send(ServerSession $from, ServerSession $to, ServerMessage\Mutable $message)
    {
        if ("three" == $message->getData()) {
            $message->setData("four");
        }
        return "ignoreSend" != $message->getData();
    }

    public function rcvMeta(ServerSession $from, ServerMessage\Mutable $message)
    {
        return true;
    }

    public function rcv(ServerSession $from, ServerMessage\Mutable $message)
    {
        if ("one" == $message->getData()) {
            $message->setData("two");
        }
        return "ignoreRcv" != $message->getData();
    }
}

class ClientSessionExtensionTest implements ClientSession\Extension
{
    public function sendMeta(ClientSession $session, Message\Mutable $message)
    {
        return true;
    }

    public function send(ClientSession $session, Message\Mutable $message)
    {
        if ("zero" == $message->getData()) {
            $message->setData("one");
        }
        return true;
    }

    public function rcvMeta(ClientSession $session, Message\Mutable $message)
    {
        return true;
    }

    public function rcv(ClientSession $session, Message\Mutable $message)
    {
        if ("five" == $message->getData()) {
            $message->setData("six");
        }
        return true;
    }
}

class ServerSessionExtensionTest implements ServerSession\Extension
{
    public function rcv(ServerSession $from, ServerMessage\Mutable $message)
    {
        if ("two" == $message->getData()) {
            $message->setData("three");
        }
        return true;
    }

    public function rcvMeta(ServerSession $from, ServerMessage\Mutable $message)
    {
        return true;
    }

    public function send(ServerSession $to, ServerMessage $message)
    {
        if ($message->isMeta()) {
            new Throwable().printStackTrace();
        }
        if ("four" == $message->getData())
        {
            $cloned = $this->_bayeux->newMessage($message);
            $cloned->setData("five");
            return $cloned;
        }
        return $message;
    }

    public function sendMeta(ServerSession $to = null, ServerMessage\Mutable $message)
    {
        return true;
    }
}

class ClientSessionChannelMessageListenerTest implements ClientSessionChannel\MessageListener
{
    public function onMessage(ClientSessionChannel $channel, Message $message)
    {
        $this->_events->enqueue($channel->getSession()->getId());
        $this->_events->enqueue((string) $message->getData());
    }
}