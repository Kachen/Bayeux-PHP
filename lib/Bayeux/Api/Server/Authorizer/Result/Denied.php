<?php

namespace Bayeux\Api\Server\Authorizer\Result;

use Bayeux\Api\Server\Authorizer\Result;

final class Denied extends Result
{
    private $reason;

    // FIXME: tem valor padrão null
    public function __construct($reason)
    {
        if ($reason == null) {
            $this->reason = "";
        }
        $this->reason = $reason;
    }

    public function getReason()
    {
        return $this->reason;
    }

    //@Override
    public function toString()
    {
        return parent::toString() . " (reason='{$this->reason}')";
    }
}