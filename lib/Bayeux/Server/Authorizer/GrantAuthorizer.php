<?php

namespace Bayeux\Server\Authorizer;

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
    public static $GRANT_CREATE = new GrantAuthorizer(EnumSet.of(Operation.CREATE));

    /**
     * Grants {@link Operation#SUBSCRIBE} authorization
     */
    public static $GRANT_SUBSCRIBE = new GrantAuthorizer(EnumSet.of(Operation.SUBSCRIBE));

    /**
     * Grants {@link Operation#PUBLISH} authorization
     */
    public static $GRANT_PUBLISH = new GrantAuthorizer(EnumSet.of(Operation.PUBLISH));

    /**
     * Grants {@link Operation#CREATE} and {@link Operation#SUBSCRIBE} authorization
     */
    public static $GRANT_CREATE_SUBSCRIBE = new GrantAuthorizer(EnumSet.of(Operation.CREATE, Operation.SUBSCRIBE));

    /**
     * Grants {@link Operation#SUBSCRIBE} and {@link Operation#PUBLISH} authorization
     */
    public static $GRANT_SUBSCRIBE_PUBLISH = new GrantAuthorizer(EnumSet.of(Operation.SUBSCRIBE, Operation.PUBLISH));

    /**
     * Grants {@link Operation#CREATE}, {@link Operation#SUBSCRIBE} and {@link Operation#PUBLISH} authorization
     */
    public static $GRANT_ALL = new GrantAuthorizer(EnumSet.allOf(Operation.class));

    /**
     * Grants no authorization, the authorization request is ignored
     */
    public static $GRANT_NONE = new GrantAuthorizer(EnumSet.noneOf(Operation.class));

    private $_operations;

    private function __construct(array $operations)
    {
        $this->_operations = $operations;
    }

    public function authorize(Operation $operation, ChannelId $channel, ServerSession $session, ServerMessage $message)
    {
        if ($this->_operations->contains($operation)) {
            return Result::grant();
        }
        return Result::ignore();
    }

    public function toString()
    {
        return $this->getClass()->getSimpleName() + $this->_operations;
    }
}
