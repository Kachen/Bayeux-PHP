<?php

namespace Bayeux\Api\Server;


/**
 * <p>The result of an authentication request.</p>
 */
abstract class Result
{
    /**
     * @param reason the reason for which the authorization is denied
     * @return a result that denies the authorization
     */
    public static function deny($reason)
    {
        return new Denied($reason);
    }

    /**
     * @return a result that grants the authorization
     */
    public static function grant()
    {
        return Granted::getInstance();
    }

    /**
     * @return a result that ignores the authorization, leaving the decision to other {@link Authorizer}s.
     */
    public static function ignore()
    {
        return Ignored::getInstance();
    }

    //@Override
    public function toString()
    {
        return strtolower(get_class($this));
    }

    public function __toString() {
        $this->toString();
    }
}