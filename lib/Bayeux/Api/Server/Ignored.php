<?php

namespace Bayeux\Api\Server;


final class Ignored extends Result
{
    private static $IGNORED = null;

    public static function getInstance() {
        if (self::$IGNORED === null) {
            self::setInstance(new Ignored());
        }

        return self::$IGNORED;
    }

    private static function setInstance(Ignored $ignored) {
        self::$IGNORED = $ignored;
    }

    private function __construct()
    {
    }
}
