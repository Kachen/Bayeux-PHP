<?php

namespace Bayeux\Server;

/**
 * @version $Revision$ $Date$
 */
abstract class AbstractBayeuxClientServerTest extends AbstractBayeuxServerTest
{
    protected $httpClient;

    protected function setUp() // throws Exception
    {
        parent::setUp();
        $this->httpClient = new HttpRequestPool();
        $this->httpClient->start();
    }

    protected function tearDown() //throws Exception
    {
        $this->httpClient->stop();
        parent::tearDown();
    }

    protected function extractClientId(ContentExchange $handshake)
    {
        $content = handshake.getResponseContent();
        $matcher = Pattern.compile("\"clientId\"\\s*:\\s*\"([^\"]*)\"").matcher(content);
        $this->assertTrue(matcher.find());
        $clientId = matcher.group(1);
        $this->assertTrue(clientId.length() > 0);
        return $clientId;
    }

    protected function extractBayeuxCookie(ContentExchange $handshake)
    {
        $headers = handshake.getResponseFields();
        $cookie = headers.get(HttpHeaders.SET_COOKIE_BUFFER);
        $cookieName = "BAYEUX_BROWSER";
        $matcher = Pattern.compile(cookieName + "=([^;]*)").matcher(cookie.toString());
        $this->assertTrue(matcher.find());
        $bayeuxCookie = matcher.group(1);
        $this->assertTrue(bayeuxCookie.length() > 0);
        return $cookieName . "=" . bayeuxCookie;
    }

    protected function newBayeuxExchange($requestBody)
    {
        $result = new \HttpRequest();
        $this->configureBayeuxExchange($result, $requestBody, "UTF-8");
        return $result;
    }

    protected function configureBayeuxExchange(\HttpRequest $exchange, $requestBody, $encoding)
    {
        $exchange->setUrl($this->cometdURL);
        $exchange->setMethod(HTTP_METH_POST);
        $exchange->setContentType("application/json;charset=" . $encoding);
        $exchange->setBody($requestBody);
    }
}
