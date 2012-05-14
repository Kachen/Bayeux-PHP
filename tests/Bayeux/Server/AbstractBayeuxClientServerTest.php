<?php

namespace Bayeux\Server;


use Zend\Http\Headers;
use Bayeux\Http\Request;

/**
 * @version $Revision$ $Date$
 */
abstract class AbstractBayeuxClientServerTest extends AbstractBayeuxServerTest
{

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

    protected function newBayeuxExchange($requestBody)
    {
        $request = new Request();
        $this->configureBayeuxExchange($request, $requestBody, 'utf-8');
        return $request;
    }

    protected function configureBayeuxExchange(Request $request, $requestBody, $encoding)
    {
        $request->setRequestUri($this->uri);
        $request->setMethod(Request::METHOD_POST);
        $header = new \Bayeux\Http\Headers();
        $header->addHeaderLine("Content-Type: application/json;charset={$encoding}");
        $request->setHeaders($header);
        $request->setContent($requestBody);
    }
}
