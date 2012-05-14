<?php

namespace Bayeux\Server;


use Bayeux\Http\Response;

use Zend\Http\Headers;
use Bayeux\Http\Request;

/**
 * @version $Revision$ $Date$
 */
abstract class AbstractBayeuxClientServerTest extends AbstractBayeuxServerTest
{

    protected function extractClientId(Response $response)
    {
        $content = $response->getContent();
        $count = preg_match('/"clientId"\\s*:\\s*"([^"]*)"/', $content, $match);
        $this->assertEquals(1, $count);
        $clientId = $match[1];
        $this->assertTrue(strlen($clientId) > 0);
        return $clientId;
    }

    protected function extractBayeuxCookie(ContentExchange $handshake)
    {
        var_dump($_COOKIE);
        exit;
        $headers = handshake.getResponseFields();
        $cookie = headers.get(HttpHeaders.SET_COOKIE_BUFFER);
        $cookieName = "BAYEUX_BROWSER";
        $matcher = Pattern.compile(cookieName + "=([^;]*)").matcher(cookie.toString());
        $this->assertTrue(matcher.find());
        $bayeuxCookie = matcher.group(1);
        $this->assertTrue(bayeuxCookie.length() > 0);
        return $cookieName . "=" . bayeuxCookie;
    }

    /**
     * @param string
     * @return \Bayeux\Http\Response
     */
    protected function newBayeuxExchange($requestBody)
    {
        $request = new Request();
        $this->configureBayeuxExchange($request, $requestBody, 'utf-8');
        return $this->loop($request);
    }

    protected function configureBayeuxExchange(Request $request, $requestBody, $encoding)
    {
        $request->setRequestUri($this->uri);
        $request->setMethod(Request::METHOD_POST);
        $header = new \Bayeux\Http\Headers();
        $header->addHeaderLine("Content-Type: application/json;charset={$encoding}");
        $header->addHeaderLine("User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:12.0) Gecko/20100101 Firefox/12.0");
        $request->setHeaders($header);
        $request->setContent($requestBody);
    }
}
