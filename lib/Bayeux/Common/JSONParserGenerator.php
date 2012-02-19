<?php

namespace Bayeux\Common;

use Bayeux\Api\Message;

interface JSONParserGenerator
{
    /**
     * @param string $json
     * @return Message\Mutable
     */
    public function parse($json); // throws ParseException;

    /**
     * @param array|Message\Mutable $message
     */
    public function generate($message);
}
