<?php

namespace Bayeux\Server;

class BayeuxServerTest extends \PHPUnit_Framework_TestCase
{
    private $_events;
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
        $this->_events->clear();
    }


    private function newServerSession()
    {
        $session = _bayeux.newServerSession();
        $this->_bayeux->addServerSession($session);
        $session->handshake();
        $session->connect();
        return $session;
    }

    //@Test
    public function testListeners() //throws Exception
    {

        var_dump("sfd");
        exit;


        $this->_bayeux->addListener(new SubListener());
        $this->_bayeux->addListener(new SessListener());
        $this->_bayeux->addListener(new CListener());

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

        $this->assertEquals("channelAdded", $this->_events.poll());
        $this->assertEquals(_bayeux.getChannel("/foo"),_events.poll());
        $this->assertEquals("channelAdded", $this->_events.poll());
        $this->assertEquals(foobar,_events.poll());
        $this->assertEquals("channelAdded", $this->_events.poll());
        $this->assertEquals(foostar,_events.poll());
        $this->assertEquals("channelAdded", $this->_events.poll());
        $this->assertEquals(starstar,_events.poll());
        $this->assertEquals("channelAdded", $this->_events.poll());
        $this->assertEquals(foobob,_events.poll());
        $this->assertEquals("channelAdded", $this->_events.poll());
        $this->assertEquals(wibble,_events.poll());

        wibble.remove();
        assertEquals("channelRemoved",_events.poll());
        assertEquals(wibble.getId(),_events.poll());

        $session0 = $this->newServerSession();
        $session1 = $this->newServerSession();
        $session2 = $this->newServerSession();

        $this->assertEquals("sessionAdded",_events.poll());
        $this->assertEquals(session0,_events.poll());
        $this->assertEquals("sessionAdded",_events.poll());
        $this->assertEquals(session1,_events.poll());
        $this->assertEquals("sessionAdded",_events.poll());
        $this->assertEquals(session2,_events.poll());

        $foobar.subscribe(session0);
        $foobar.unsubscribe(session0);

        $this->assertEquals("subscribed",_events.poll());
        $this->assertEquals(session0,_events.poll());
        $this->assertEquals(foobar,_events.poll());
        $this->assertEquals("unsubscribed",_events.poll());
        $this->assertEquals(session0,_events.poll());
        $this->assertEquals(foobar,_events.poll());
    }

    public function testSessionAttributes() //throws Exception
    {
        $local = $this->_bayeux->newLocalSession("s0");
        $local->handshake();
        $session = $local->getServerSession();

        $local.setAttribute("foo","bar");
        $this->assertEquals("bar",local.getAttribute("foo"));
        $this->assertEquals(null,session.getAttribute("foo"));

        $session.setAttribute("bar","foo");
        $this->assertEquals(null,local.getAttribute("bar"));
        $this->assertEquals("foo",session.getAttribute("bar"));

        $this->assertTrue($local->getAttributeNames().contains("foo"));
        $this->assertFalse($local->getAttributeNames().contains("bar"));
        $this->assertFalse($session->getAttributeNames().contains("foo"));
        $this->assertTrue($session->getAttributeNames().contains("bar"));

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

class CListener implements BayeuxServer.ChannelListener
{
    public void configureChannel(ConfigurableServerChannel channel)
    {
    }

    public void channelAdded(ServerChannel channel)
    {
        _events.add("channelAdded");
        _events.add(channel);
    }

    public void channelRemoved(String channelId)
    {
        _events.add("channelRemoved");
        _events.add(channelId);
    }

}

class SessListener implements BayeuxServer\SessionListener
{
    public function sessionAdded(ServerSession session)
    {
        _events.add("sessionAdded");
        _events.add(session);
    }

    public void sessionRemoved(ServerSession session, boolean timedout)
    {
        _events.add("sessionRemoved");
        _events.add(session);
        _events.add(timedout);
    }
}

class SubListener implements BayeuxServer.SubscriptionListener
{
    public void subscribed(ServerSession session, ServerChannel channel)
    {
        _events.add("subscribed");
        _events.add(session);
        _events.add(channel);
    }

    public void unsubscribed(ServerSession session, ServerChannel channel)
    {
        _events.add("unsubscribed");
        _events.add(session);
        _events.add(channel);
    }

}
*/