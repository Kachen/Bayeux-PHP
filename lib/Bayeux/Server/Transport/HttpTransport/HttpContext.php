<?php

namespace Bayeux\Server\Transport\HttpTransport;

use Bayeux\Api\Server\BayeuxContext;

/* ------------------------------------------------------------ */
/* ------------------------------------------------------------ */
class HttpContext implements BayeuxContext
{
    public $_request;

    public function __construct(HttpServletRequest $request)
    {
        $this->_request = $request;
    }

    public function getUserPrincipal()
    {
        return $this->_request->getUserPrincipal();
    }

    public function isUserInRole($role)
    {
        return $this->_request->isUserInRole($role);
    }

    public function getRemoteAddress()
    {
        return new InetSocketAddress(_request.getRemoteHost(),_request.getRemotePort());
    }

    public function getLocalAddress()
    {
        return new InetSocketAddress(_request.getLocalName(),_request.getLocalPort());
    }


    public function getHeader($name)
    {
        return $this->_request->getHeader($name);
    }

    public function getHeaderValues($name)
    {
        return $this->_request->getHeaders($name);
    }

    public function getParameter($name)
    {
        return $this->_request->getParameter($name);
    }

    public function getParameterValues($name)
    {
        return $this->_request->getParameterValues($name);
    }

    public function getCookie($name)
    {
        $cookies = $this->_request.getCookies();
        foreach ($cookies as $c)
        {
            if ($name == $c->getName()) {
                return $c->getValue();
            }
        }
        return null;
    }

    public function getHttpSessionId()
    {
        $session = $this->_request->getSession(false);
        if ($session!=null)
            return $session->getId();
        return null;
    }

    public function getHttpSessionAttribute($name)
    {
        $session = $this->_request->getSession(false);
        if ($session!=null) {
            return $session->getAttribute($name);
        }
        return null;
    }

    public function setHttpSessionAttribute($name, $value)
    {
        $session = $this->_request->getSession(false);
        if ($session!=null) {
            $session->setAttribute($name, $value);
        } else {
            throw new IllegalStateException("!session");
        }
    }

    public function invalidateHttpSession()
    {
        $session = $this->_request->getSession(false);
        if ($session!=null) {
            $session->invalidate();
        }
    }

    public function getRequestAttribute($name)
    {
        return $this->_request->getAttribute($name);
    }

    private function getServletContext()
    {
        $c = null;
        $s = $this->_request->getSession(false);
        if ($s!=null) {
            $c=$s->getServletContext();
        } else {
            $s=$this->_request->getSession(true);
            $c=$s->getServletContext();
            $s->invalidate();
        }
        return $c;
    }

    public function getContextAttribute($name)
    {
        return $this->getServletContext()->getAttribute($name);
    }

    public function getContextInitParameter($name)
    {
        return $this->getServletContext()->getInitParameter($name);
    }

    public function getURL()
    {
        $url = $this->_request->getRequestURL();
        $query = $this->_request->getQueryString();
        if ($query != null) {
            $url.append("?").append(query);
        }
        return $url;
    }
}