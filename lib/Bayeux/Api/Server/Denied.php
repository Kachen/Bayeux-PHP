<?php

namespace Bayeux\Api\Server;


final class Denied extends Result
{
    private $reason;

    // FIXME: tem valor padrão null
    private function __construct($reason)
    {
        if (reason == null) {
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