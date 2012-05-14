<?php

namespace Bayeux\Common;

/*
 * Copyright (c) 2011 the original author or authors.
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
*
*     http://www.apache.org/licenses/LICENSE-2.0
*
* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS,
* WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
* See the License for the specific language governing permissions and
* limitations under the License.
*/


abstract class PHPJSONContext
{
    private $_jsonParser;
    private $_messageParser;
    private $_messagesParser;

    public function __construct()
    {
        //private $_jsonParser = new FieldJSON();
        //private $_messageParser = new MessageJSON();
        //private $_messagesParser = new MessagesJSON();
    }

    public function getJSON()
    {
        return $this->_jsonParser;
    }

    protected abstract function newRoot(array $result);

    public function parse($json) {
        $object = \json_decode($json, true);
        if ($object) {
            return $this->adapt($object);
        }

        throw new ParseException();
        //throw new ParseException($json, -1)->initCause($x);
    }

    private function adapt($objects)
    {
        if ($objects == null) {
            return null;
        }

        $messages = array();
        if (is_array($objects)) {
            foreach ($objects as $object) {
                $messages[] = $this->newRoot((array) $object);
            }

            return $messages;
        }

        return array($this->newRoot((array) $objects));
    }

    public function generate($message) {
        return \json_encode((array) $message);
    }
}

class FieldJSON {
    // Allows for optimizations
}

class MessageJSON extends FieldJSON {
    protected function newMap()
    {
        return newRoot();
    }

    protected function contextFor($field)
    {
        return getJSON();
    }
}

class MessagesJSON extends FieldJSON {

    protected function newMap() {
        return newRoot();
    }

    protected function newArray($size) {
        return newRootArray(size);
    }

    protected function contextFor($field) {
        return getJSON();
    }

    protected function contextForArray() {
        return $this->_messageParser;
    }
}