<?php

namespace Bayeux\Server;


class SweepTest extends \PHPUnit_Framework_TestCase
{
    public function testChannelsSweepPerformance()
    {
        $bayeuxServer = new BayeuxServerImpl();

        $builder = '';
        $count = 5000;
        $children = 5;
        for ($i = 0; $i < $count; ++$i)
        {
            for ($j = 0; $j < $children; ++$j)
            {
                $letter = 'a' . $j;
                $name = $builder . "/" . $letter. $i;
                $bayeuxServer->createIfAbsent($name);
            }
        }

        //$start = $nanotime = system('date +%s%N');
        $start = microtime();
        $bayeuxServer->sweep();
        $end = microtime();

        $elapsedMicros = $end - $start;

        $microsPerSweepPerChannel = 100;
        $expectedMicros = $count * $children * $microsPerSweepPerChannel;
        $this->assertTrue($elapsedMicros < $expectedMicros, "elapsed micros " . $elapsedMicros . ", expecting < " . $expectedMicros);
    }

    public function testChannelsAreSwept()
    {
        $bayeuxServer = new BayeuxServerImpl();
        $builder = '';
        $count = 100;
        $children = 5;
        for ($i = 0; $i < $count; ++$i)
        {
            for ($j = 0; $j < $children; ++$j)
            {
                $letter = 'a' . $j;
                $name = $builder . "/" . $letter . $i;
                $bayeuxServer->createIfAbsent($name);
            }
        }

        $sweepPasses = 3;
        $maxIterations = $sweepPasses * $children * 2;
        $iterations = 0;
        while (count($bayeuxServer->getChannels()) > 0)
        {
            $bayeuxServer->sweep();
            ++$iterations;
            if ($iterations > $maxIterations) {
                break;
            }
        }

        $this->assertEquals(0, count($bayeuxServer->getChannels()));
    }

    public function testSessionsSweepPerformance()
    {
        $bayeuxServer = new BayeuxServerImpl();

        $count = 25000;
        for ($i = 0; $i < $count; ++$i)
        {
            $bayeuxServer->addServerSession($bayeuxServer->newServerSession());
        }
        $start = microtime();
        $bayeuxServer->sweep();
        $end = microtime();

        $elapsedMicros = $end - $start;
        $microsPerSweepPerChannel = 100;
        $expectedMicros = $count * $microsPerSweepPerChannel;
        $this->assertTrue($elapsedMicros < $expectedMicros, "elapsed micros " . $elapsedMicros . ", expecting < " . $expectedMicros);
    }

    public function testLocalSessionIsNotSwept()// throws Exception
    {
        $bayeuxServer = new BayeuxServerImpl();
        $bayeuxServer->setOption("sweepIntervalMs", -1);
        $maxInterval = 1000;
        $bayeuxServer->setOption("maxInterval", $maxInterval);
        $bayeuxServer->setOption("maxServerInterval", $maxInterval);
        $bayeuxServer->start();
        $serverTransport = $bayeuxServer->getTransport("long-polling");
        $bayeuxServer->setCurrentTransport($serverTransport);

        // LocalSessions do not perform heartbeat so we should not sweep them until disconnected
        $localSession = $bayeuxServer->newLocalSession("test_sweep");
        $localSession->handshake();

        $bayeuxServer->sweep();

        $this->assertNotNull($bayeuxServer->getSession($localSession->getId()));

        usleep($maxInterval * 2);

        $bayeuxServer->sweep();

        $this->assertNotNull($bayeuxServer->getSession($localSession->getId()));

        $localSession->disconnect();
    }
}
