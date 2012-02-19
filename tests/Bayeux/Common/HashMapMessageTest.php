<?php

namespace Bayeux\Common;



class HashMapMessageTest extends \PHPUnit_Framework_TestCase
{
    public function testSerialization()
    {
        $message = new HashMapMessage();
        $message->setChannel("/channel");
        $message->setClientId("clientId");
        $message->setId("id");
        $message->setSuccessful(true);

        $data = $message->getDataAsMap(true);
        $data["data1"] = "dataValue1";

        $ext = $message->getExt(true);
        $ext['ext1'] = "extValue1";

        $deserialized = unserialize(serialize($message));

        $this->assertEquals($message, $deserialized);
    }
}
