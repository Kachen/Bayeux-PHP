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

/*         $baos = new ByteArrayOutputStream();
        $oos = new ObjectOutputStream(baos);
        $oos->writeObject($message);
        $oos->close();

        $ois = new ObjectInputStream(new ByteArrayInputStream($baos->toByteArray()));
        $deserialized = $ois->readObject();

        $this->assertEquals(message, deserialized); */
    }
}
