<?php

namespace Bayeux\Common;


use Bayeux\Api\ChannelId;

class ChannelIdTest extends \PHPUnit_Framework_TestCase
{

    public function testDepth()
    {
        $channel = new ChannelId("/foo");
        $this->assertEquals(1, $channel->depth());
        $channel = new ChannelId("/foo/");
        $this->assertEquals(1, $channel->depth());
        $channel = new ChannelId("/foo/bar");
        $this->assertEquals(2, $channel->depth());
        $channel = new ChannelId("/foo/bar/");
        $this->assertEquals(2, $channel->depth());
        $channel = new ChannelId("/foo/bar/*");
        $this->assertEquals(3, $channel->depth());
        $channel = new ChannelId("/foo/bar/**");
        $this->assertEquals(3, $channel->depth());
    }

    public function testSegments()
    {
        $channel = new ChannelId("/foo/bar");

        $this->assertEquals("foo", $channel->getSegment(0));
        $this->assertEquals("bar", $channel->getSegment(1));

        $this->assertNull($channel->getSegment(2));
    }

    public function testIsXxx()
    {
        $id = new ChannelId("/foo/bar");
        $this->assertFalse($id->isDeepWild());
        $this->assertFalse($id->isMeta());
        $this->assertFalse($id->isService());
        $this->assertFalse($id->isWild());

        $id = new ChannelId("/foo/*");
        $this->assertFalse($id->isDeepWild());
        $this->assertFalse($id->isMeta());
        $this->assertFalse($id->isService());
        $this->assertTrue($id->isWild());

        $id = new ChannelId("/foo/**");
        $this->assertTrue($id->isDeepWild());
        $this->assertFalse($id->isMeta());
        $this->assertFalse($id->isService());
        $this->assertTrue($id->isWild());

        $id = new ChannelId("/meta/bar");
        $this->assertFalse($id->isDeepWild());
        $this->assertTrue($id->isMeta());
        $this->assertFalse($id->isService());
        $this->assertFalse($id->isWild());

        $id = new ChannelId("/service/bar");
        $this->assertFalse($id->isDeepWild());
        $this->assertFalse($id->isMeta());
        $this->assertTrue($id->isService());
        $this->assertFalse($id->isWild());

        $id = new ChannelId("/service/**");
        $this->assertTrue($id->isDeepWild());
        $this->assertFalse($id->isMeta());
        $this->assertTrue($id->isService());
        $this->assertTrue($id->isWild());
    }

    public function testStaticIsXxx()
    {
        $this->assertTrue(ChannelId::staticIsMeta("/meta/bar"));
        $this->assertFalse(ChannelId::staticIsMeta("/foo/bar"));
        $this->assertTrue(ChannelId::staticIsService("/service/bar"));
        $this->assertFalse(ChannelId::staticIsService("/foo/bar"));
        $this->assertFalse(ChannelId::staticIsMeta("/"));
        $this->assertFalse(ChannelId::staticIsService("/"));
    }

    public function testIsParent()
    {
        $foo = new ChannelId("/foo");
        $bar = new ChannelId("/bar");
        $foobar = new ChannelId("/foo/bar");
        $foobarbaz = new ChannelId("/foo/bar/baz");

        $this->assertFalse($foo->isParentOf($foo));
        $this->assertTrue($foo->isParentOf($foobar));
        $this->assertFalse($foo->isParentOf($foobarbaz));

        $this->assertFalse($foobar->isParentOf($foo));
        $this->assertFalse($foobar->isParentOf($foobar));
        $this->assertTrue($foobar->isParentOf($foobarbaz));

        $this->assertFalse($bar->isParentOf($foo));
        $this->assertFalse($bar->isParentOf($foobar));
        $this->assertFalse($bar->isParentOf($foobarbaz));

        $this->assertFalse($foo->isAncestorOf($foo));
        $this->assertTrue($foo->isAncestorOf($foobar));
        $this->assertTrue($foo->isAncestorOf($foobarbaz));

        $this->assertFalse($foobar->isAncestorOf($foo));
        $this->assertFalse($foobar->isAncestorOf($foobar));
        $this->assertTrue($foobar->isAncestorOf($foobarbaz));

        $this->assertFalse($bar->isAncestorOf($foo));
        $this->assertFalse($bar->isAncestorOf($foobar));
        $this->assertFalse($bar->isAncestorOf($foobarbaz));

    }

    public function testEquals()
    {
        $foobar0 = new ChannelId("/foo/bar");
        $foobar1 = new ChannelId("/foo/bar");
        $foo = new ChannelId("/foo");
        $wild = new ChannelId("/foo/*");
        $deep = new ChannelId("/foo/**");

        $this->assertTrue($foobar0->equals($foobar0));
        $this->assertTrue($foobar0->equals($foobar1));

        $this->assertFalse($foobar0->equals($foo));
        $this->assertFalse($foobar0->equals($wild));
        $this->assertFalse($foobar0->equals($deep));
    }

    public function testMatches()
    {
        $foobar0 = new ChannelId("/foo/bar");
        $foobar1 = new ChannelId("/foo/bar");
        $foobarbaz = new ChannelId("/foo/bar/baz");
        $foo = new ChannelId("/foo");
        $wild = new ChannelId("/foo/*");
        $deep = new ChannelId("/foo/**");

        $this->assertTrue($foobar0->matches($foobar0));
        $this->assertTrue($foobar0->matches($foobar1));

        $this->assertFalse($foo->matches($foobar0));
        $this->assertTrue($wild->matches($foobar0));
        $this->assertTrue($deep->matches($foobar0));

        $this->assertFalse($foo->matches($foobarbaz));
        $this->assertFalse($wild->matches($foobarbaz));
        $this->assertTrue($deep->matches($foobarbaz));

    }

    public function testWilds()
    {
        $id = new ChannelId("/foo/bar/*");
        $this->assertEquals(0, count($id->getWilds()));

        $id = new ChannelId("/foo");
        $wilds = $id->getWilds();
        $this->assertEquals(2, count($wilds));
        $this->assertEquals("/*", $wilds[0]);
        $this->assertEquals("/**", $wilds[1]);

        $id = new ChannelId("/foo/bar");
        $wilds = $id->getWilds();
        $this->assertEquals(3, count($id->getWilds()));
        $this->assertEquals("/foo/*", $wilds[0]);
        $this->assertEquals("/foo/**", $wilds[1]);
        $this->assertEquals("/**", $wilds[2]);

        $id = new ChannelId("/foo/bar/bob");
        $wilds = $id->getWilds();
        $this->assertEquals(4, count($wilds));
        $this->assertEquals("/foo/bar/*", $wilds[0]);
        $this->assertEquals("/foo/bar/**", $wilds[1]);
        $this->assertEquals("/foo/**", $wilds[2]);
        $this->assertEquals("/**", $wilds[3]);
    }
}