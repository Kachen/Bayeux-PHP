<?php

namespace Bayeux\Server\Authorizer;

use Bayeux\Api\Server\Authorizer\Result;
use Bayeux\Api\Server\ServerMessage;
use Bayeux\Api\Server\ServerSession;
use Bayeux\Api\ChannelId;
use Bayeux\Api\Server\Authorizer;
use Bayeux\Api\Server\Authorizer\Operation;

/**
 * <p>This {@link Authorizer} implementation grants permission
 * for a set of operations defined at construction time.</p>
 * <p>If the operation does not match, it ignores the authorization request.</p>
 */
class GrantAuthorizer implements Authorizer
{
    /**
     * Grants {@link Operation#CREATE} authorization
     */
    private static $GRANT_CREATE = 0;

    /**
     * Grants {@link Operation#SUBSCRIBE} authorization
     */
    private static $GRANT_SUBSCRIBE;

    /**
     * Grants {@link Operation#PUBLISH} authorization
     */
    private static $GRANT_PUBLISH;

    /**
     * Grants {@link Operation#CREATE} and {@link Operation#SUBSCRIBE} authorization
     */
    private static $GRANT_CREATE_SUBSCRIBE;

    /**
     * Grants {@link Operation#SUBSCRIBE} and {@link Operation#PUBLISH} authorization
     */
    private static $GRANT_SUBSCRIBE_PUBLISH;

    /**
     * Grants {@link Operation#CREATE}, {@link Operation#SUBSCRIBE} and {@link Operation#PUBLISH} authorization
     */
    private static $GRANT_ALL;

    /**
     * Grants no authorization, the authorization request is ignored
     */
    private static $GRANT_NONE;


    public static function GRANT_CREATE() {
        if (self::$GRANT_CREATE === null) {
            self::$GRANT_CREATE = new self(Operation::CREATE);
        }
        return self::$GRANT_CREATE;
    }

    public static function GRANT_SUBSCRIBE() {
        if (self::$GRANT_SUBSCRIBE === null) {
            self::$GRANT_SUBSCRIBE = new self(Operation::SUBSCRIBE);
        }
        return self::$GRANT_SUBSCRIBE;
    }

    public static function GRANT_PUBLISH() {
        if (self::$GRANT_PUBLISH === null) {
            self::$GRANT_PUBLISH = new GrantAuthorizer(Operation::PUBLISH);
        }
        return self::$GRANT_PUBLISH;
    }

    public static function GRANT_CREATE_SUBSCRIBE() {
        throw new \Exception("validar esse tipo");
        if (self::$GRANT_CREATE_SUBSCRIBE === null) {
            self::$GRANT_CREATE_SUBSCRIBE = new GrantAuthorizer(Operation::CREATE, Operation::SUBSCRIBE);
        }
        return self::$GRANT_CREATE_SUBSCRIBE;
    }

    public static function GRANT_SUBSCRIBE_PUBLISH() {
        throw new \Exception("validar esse tipo");
        if (self::$GRANT_SUBSCRIBE_PUBLISH === null) {
            self::$GRANT_SUBSCRIBE_PUBLISH = new GrantAuthorizer(Operation::SUBSCRIBE, Operation::PUBLISH);
        }
        return self::$GRANT_SUBSCRIBE_PUBLISH;
    }

    public static function GRANT_ALL() {
        if (self::$GRANT_ALL === null) {
            self::$GRANT_ALL = new GrantAuthorizer(Operation::CREATE, Operation::PUBLISH, Operation::SUBSCRIBE);
        }
        return self::$GRANT_ALL;
    }

    public static function GRANT_NONE() {
        if (self::$GRANT_NONE === null) {
            self::$GRANT_NONE = new GrantAuthorizer();
        }
        return self::$GRANT_NONE;
    }

    private $_operations;

    private function __construct()
    {
        $this->_operations = func_get_args();
    }

    public function authorize(Operation $operation, ChannelId $channel, ServerSession $session, ServerMessage $message)
    {
        if (in_array($operation, $this->_operations)) {
            return Result::grant();
        }
        return Result::ignore();
    }

    public function toString()
    {
        return $this->getClass()->getSimpleName() + $this->_operations;
    }
}
