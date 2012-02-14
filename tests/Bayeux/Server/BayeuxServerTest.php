<?php

namespace Bayeux\Server;

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
        $this->_events = array();
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
}
/*

    public function testLocalSessions() //throws Exception
    {
        $session0 = $this->_bayeux.newLocalSession("s0");
        $this->assertTrue($session0->toString().indexOf("s0?")>=0);
        $session0.handshake();
        $this->assertTrue($session0->toString().indexOf("s0_")>=0);

        $session1 = $this->_bayeux->newLocalSession("s1");
        $session1->handshake();
        $session2 = $this->_bayeux->newLocalSession("s2");
        $session2->handshake();

        $events = new \SplQueue();

        ClientSessionChannel.MessageListener listener = new ClientSessionChannel.MessageListener()
        {
            public void onMessage(ClientSessionChannel channel, Message message)
            {
                events.add(channel.getSession().getId());
                events.add(message.getData().toString());
            }
        };

        $session0.getChannel("/foo/bar").subscribe(listener);
        $session0.getChannel("/foo/bar").subscribe(listener);
        $session1.getChannel("/foo/bar").subscribe(listener);
        $session2.getChannel("/foo/bar").subscribe(listener);

        System.err.println(_bayeux.dump());

        $this->assertEquals(3,_bayeux.getChannel("/foo/bar").getSubscribers().size());

        $session0.getChannel("/foo/bar").unsubscribe(listener);
        $this->assertEquals(3,_bayeux.getChannel("/foo/bar").getSubscribers().size());
        $session0.getChannel("/foo/bar").unsubscribe(listener);
        $this->assertEquals(2,_bayeux.getChannel("/foo/bar").getSubscribers().size());

        $foobar0=session0.getChannel("/foo/bar");
        $foobar0.subscribe(listener);
        $foobar0.subscribe(listener);

        $foostar0=session0.getChannel("/foo/*");
        $foostar0.subscribe(listener);

        $this->assertEquals(3,_bayeux.getChannel("/foo/bar").getSubscribers().size());
        $this->assertEquals(session0,foobar0.getSession());
        $this->assertEquals("/foo/bar",foobar0.getId());
        $this->assertEquals(false,foobar0.isDeepWild());
        $this->assertEquals(false,foobar0.isWild());
        $this->assertEquals(false,foobar0.isMeta());
        $this->assertEquals(false,foobar0.isService());

        $foobar0.publish("hello");

        $this->assertEquals(session0.getId(),events.poll());
        $this->assertEquals("hello",events.poll());
        $this->assertEquals(session0.getId(),events.poll());
        $this->assertEquals("hello",events.poll());
        $this->assertEquals(session0.getId(),events.poll());
        $this->assertEquals("hello",events.poll());
        $this->assertEquals(session1.getId(),events.poll());
        $this->assertEquals("hello",events.poll());
        $this->assertEquals(session2.getId(),events.poll());
        $this->assertEquals("hello",events.poll());
        $foostar0.unsubscribe(listener);

        session1.batch(new Runnable()
        {
            public void run()
            {
                ClientSessionChannel foobar1=session1.getChannel("/foo/bar");
                foobar1.publish("part1");
                assertEquals(null,events.poll());
                foobar1.publish("part2");
            }
        });

        $this->assertEquals(session1.getId(),events.poll());
        $this->assertEquals("part1",events.poll());
        $this->assertEquals(session2.getId(),events.poll());
        $this->assertEquals("part1",events.poll());
        $this->assertEquals(session0.getId(),events.poll());
        $this->assertEquals("part1",events.poll());
        $this->assertEquals(session0.getId(),events.poll());
        $this->assertEquals("part1",events.poll());
        $this->assertEquals(session1.getId(),events.poll());
        $this->assertEquals("part2",events.poll());
        $this->assertEquals(session2.getId(),events.poll());
        $this->assertEquals("part2",events.poll());
        $this->assertEquals(session0.getId(),events.poll());
        $this->assertEquals("part2",events.poll());
        $this->assertEquals(session0.getId(),events.poll());
        $this->assertEquals("part2",events.poll());


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
        final Queue<String> events = new ConcurrentLinkedQueue<String>();
        _bayeux.addExtension(new BayeuxServer.Extension()
        {
            public boolean sendMeta(ServerSession to, Mutable message)
            {
                return true;
            }

            public boolean send(ServerSession from, ServerSession to, Mutable message)
            {
                if ("three".equals(message.getData()))
                    message.setData("four");
                return !"ignoreSend".equals(message.getData());
            }

            public boolean rcvMeta(ServerSession from, Mutable message)
            {
                return true;
            }

            public boolean rcv(ServerSession from, Mutable message)
            {
                if ("one".equals(message.getData()))
                    message.setData("two");
                return !"ignoreRcv".equals(message.getData());
            }
        });

        final LocalSession session0 = _bayeux.newLocalSession("s0");
        session0.handshake();
        //final LocalSession session1 = _bayeux.newLocalSession("s1");
        //session1.handshake();

        session0.addExtension(new ClientSession.Extension()
        {
            public boolean sendMeta(ClientSession session, org.cometd.bayeux.Message.Mutable message)
            {
                return true;
            }

            public boolean send(ClientSession session, org.cometd.bayeux.Message.Mutable message)
            {
                if ("zero".equals(message.getData()))
                    message.setData("one");
                return true;
            }

            public boolean rcvMeta(ClientSession session, org.cometd.bayeux.Message.Mutable message)
            {
                return true;
            }

            public boolean rcv(ClientSession session, org.cometd.bayeux.Message.Mutable message)
            {
                if ("five".equals(message.getData()))
                    message.setData("six");
                return true;
            }
        });


        session0.getServerSession().addExtension(new ServerSession.Extension()
        {
            public boolean rcv(ServerSession from, Mutable message)
            {
                if ("two".equals(message.getData()))
                    message.setData("three");
                return true;
            }

            public boolean rcvMeta(ServerSession from, Mutable message)
            {
                return true;
            }

            public ServerMessage send(ServerSession to, ServerMessage message)
            {
                if (message.isMeta())
                    new Throwable().printStackTrace();
                if ("four".equals(message.getData()))
                {
                    ServerMessage.Mutable cloned=_bayeux.newMessage(message);
                    cloned.setData("five");
                    return cloned;
                }
                return message;
            }

            public boolean sendMeta(ServerSession to, Mutable message)
            {
                return true;
            }
        });

        ClientSessionChannel.MessageListener listener = new ClientSessionChannel.MessageListener()
        {
            public void onMessage(ClientSessionChannel channel, Message message)
            {
                events.add(channel.getSession().getId());
                events.add(message.getData().toString());
            }
        };

        $session0.getChannel("/foo/bar").subscribe(listener);
        // session1.getChannel("/foo/bar").subscribe(listener);

        $session0.getChannel("/foo/bar").publish("zero");
        $session0.getChannel("/foo/bar").publish("ignoreSend");
        $session0.getChannel("/foo/bar").publish("ignoreRcv");

        sleep(100);
        System.err.println(events);

        $this->assertEquals(session0.getId(),events.poll());
        $this->assertEquals("six",events.poll());


        //assertEquals(session1.getId(),events.poll());
        //assertEquals("four",events.poll());
        //assertEquals(null,events.poll());


    }
}
*/

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