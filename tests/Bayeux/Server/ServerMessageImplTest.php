<?php
namespace Bayeux\Server;

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


use Bayeux\Api\Message;

use Bayeux\Common\UnsupportedOperationException;

class ServerMessageImplTest extends \PHPUnit_Framework_TestCase
{
    protected $backupGlobals = true;

    /*public final TestWatchman testName = new TestWatchman()
     {
    public void starting(FrameworkMethod method)
    {
    super.starting(method);
    System.err.printf("Running %s.%s%n", method.getMethod().getDeclaringClass().getName(), method.getName());
    }
    };*/

    public function testSimpleContent() //throws Exception
    {
        $message = new ServerMessageImpl();
        $message["channel"] =  "/foo/bar";
        $iterator = new \ArrayIterator($message);

        $this->assertEquals(1, count($message));
        $this->assertEquals("/foo/bar", $message->getChannel());
        $this->assertEquals("channel", $iterator->key());
        $this->assertEquals("/foo/bar", $iterator->current());
    }

    public function testFrozenBehavior()
    {
$originalJSON = <<<JSON
{
	"id":"12345",
    "clientId":"jva73siaj92jdafa",
	"data": {
		"dataName":"dataValue",
		"innerData":{}
	},
	"ext":{"extName":"extValue"}
}
JSON;

        $jsonContext = new PHPJSONContextServer();
        $messages = $jsonContext->parse($originalJSON);
        $message = $messages[0];

        $json = $jsonContext->generate($message);
        $this->assertTrue(strpos($json, '"ext":{"extName":"extValue"}') !== false);
        $this->assertTrue(strpos($json, '"clientId":"jva73siaj92jdafa"') !== false);
        $this->assertTrue(strpos($json, '"dataName":"dataValue"') !== false);
        $this->assertTrue(strpos($json, '"id":"12345"') !== false);
        $this->assertEquals("12345", $message->getId());

        // Modify the message
        $message["id"] = "54321";
        $this->assertEquals("54321", $message->getId());

        // Be sure the JSON reflects the modifications
        $json = $jsonContext->generate($message);
        $this->assertTrue(strpos($json, '"id":"54321"') !== false);

        // Freeze the message
        $message->freeze($json);

        try
        {
            $message["id"] = "666";
            $this->fail();
        }
        catch (UnsupportedOperationException $expected)
        {
        }

        $this->assertEquals("54321", $message->getId());
        $this->assertEquals("54321", $message[Message::ID_FIELD]);



        try
        {
            $data = $message->getDataAsMap();
            $data["x"] = "9";
            $this->fail();
        }
        catch (UnsupportedOperationException $expected)
        {
        }

        try
        {
            $data = $message->getData();
            $data["x"] = "9";
            $this->fail();
        }
        catch (UnsupportedOperationException $expected)
        {
        }

        try
        {
            $data = $message->getExt();
            $data["x"] = "9";

            $this->fail();
        }
        catch (UnsupportedOperationException $expected)
        {
        }

        // Deep modifications after the message is frozen are discarded
        //$innerData = $message->getDataAsMap()->get("innerData");
        //$innerData["newContent"] = true;

        //$json2 = $message->getJSON();
        //$this->assertEquals($json, $json2);
    }

    public function testSerialization()
    {
        $message = new ServerMessageImpl();
        $message->setChannel("/channel");
        $message->setClientId("clientId");
        $message->setId("id");
        $message->setSuccessful(true);
        $message->getDataAsMap(true)->offsetSet("data1", "dataValue1");
        $message->getExt(true)->offsetSet("ext1", "extValue1");
        $message->setLazy(true);
        $associated = new ServerMessageImpl();
        $associated["associated"] = true;
        $message->setAssociated($associated);

        $json = new PHPJSONContextServer();
        $json = $json->generate($message);
        $message->freeze($json);

        $deserialized = serialize($message);

        $deserialized =  unserialize($deserialized);

        $this->assertEquals($message, $deserialized);
        $this->assertTrue($deserialized->isLazy());
        //$this->assertNull($deserialized->getAssociated());

        // Make sure the message is still frozen
        try
        {
            $deserialized["a"] = "b";
            $this->fail();
        }
        catch (UnsupportedOperationException $expected)
        {
        }
    }

    public function testModificationViaEntrySet()
    {
        $message = new ServerMessageImpl();
        $message->setChannel("/channel");

        foreach ($message as $key => $value)
        {
            if (Message::CHANNEL_FIELD == $key)
            {
                $message[$key] = "/foo";
                break;
            }
        }

        $json = new PHPJSONContextServer();
        $json = $json->generate($message);
        $message->freeze($json);

        foreach ($message as $key => $value)
        {
            if (Message::CHANNEL_FIELD == $key)
            {
                try
                {
                    $message[$key]  = "/foo";
                    $this->fail();
                }
                catch (UnsupportedOperationException $expected)
                {
                    break;
                }
            }
        }
    }

    public function testNullValue()
    {
$originalJSON = <<<JSON
{
	"id":"12345",
	"clientId":"jva73siaj92jdafa",
	"data":{
		"bar":5,
		"nullData":null
	}
}
JSON;

        $jsonContext = new PHPJSONContextServer();
        $messages = $jsonContext->parse($originalJSON);
        $message = $messages[0];
        $data = $message->getDataAsMap();
        $this->assertNull($data["nullData"]);
        $this->assertTrue(isset($data["nullData"]));
        $this->assertEquals(2, count($data));
    }
}
