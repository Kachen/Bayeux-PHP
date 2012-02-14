<?php

namespace Bayeux\Common;

use Bayeux\Api\ChannelId;

use Bayeux\Api\Message;

class HashMapMessage extends \ArrayObject implements Message\Mutable
{

    public function addJSON(&$buffer)
    {
        buffer.append($this->getJSON());
    }



    public function getChannel()
    {
        return $this[self::CHANNEL_FIELD];
    }

    public function getChannelId()
    {
        return new ChannelId(getChannel());
    }

    public function getClientId()
    {
        return $this[self::CLIENT_ID_FIELD];
    }

    public function getData()
    {
        if (isset($this[self::DATA_FIELD])) {
            return $this[self::DATA_FIELD];
        }
        return null;
    }

    public function getId()
    {
        // Support also old-style ids of type long
        if (isset($this[self::ID_FIELD])) {
            return $this[self::ID_FIELD];
        }

        return null;
    }

    public function getJSON()
    {
        return json_encode($this->getArrayCopy());
    }

    public function getAdvice($create = null)
    {
        if ($create === null) {
            if (isset($this[self::ADVICE_FIELD])) {
                $advice = $this[self::ADVICE_FIELD];
            } else {
                $advice = null;
            }

            if ($advice instanceof JSON\Literal)
            {
                $advice = $this->jsonParser->parse($advice->toString());
                $this[self::ADVICE_FIELD] = $advice;
            }

            //return $advice;
        }

        if ($create && $advice == null)
        {
            $advice = array();
            $this[self::ADVICE_FIELD] = $advice;
        }
        return $advice;
    }

    public function getDataAsMap($create = null)
    {
        if ($create === null) {
            if (isset($this[self::DATA_FIELD])) {
                $data = $this[self::DATA_FIELD];
            } else {
                $data = null;
            }

            if ($data instanceof JSON.Literal)
            {
                $data = $jsonParser.parse(data.toString());
                $this->put(DATA_FIELD, data);
            }
            //return $data;
        }

        if ($create && $data == null)
        {
            $data = array();
            $this[self::DATA_FIELD] = &$data;
        }
        return $this[self::DATA_FIELD];
    }

    public function getExt($create = null)
    {
        if ($create === null) {
            $ext = $this[self::EXT_FIELD];
            if ($ext instanceof JSON\Literal)
            {
                $ext = jsonParser.parse($ext.toString());
                $this[self::EXT_FIELD] = $ext;
            }
            //return $ext;
        }

        if ($create && $ext == null) {
            $ext = array();
            $this[self::EXT_FIELD] = $ext;
        }
        return $ext;
    }

    public function isMeta()
    {
        return ChannelId::staticIsMeta($this->getChannel());
    }

    public function isSuccessful()
    {
        $value = $this[Message::SUCCESSFUL_FIELD];
        return $value != null && $value;
    }

    public function toString()
    {
        return $this->getJSON();
    }

    public function setChannel($channel)
    {
        if ($channel == null) {
            if (isset($this[self::CHANNEL_FIELD])) {
                unset($this[self::CHANNEL_FIELD]);
            }
        } else {
            $this[self::CHANNEL_FIELD] = $channel;
        }
    }

    public function setClientId($clientId)
    {
        if ($clientId == null) {
            unset($this[self::CLIENT_ID_FIELD]);
        } else {
            $this[self::CLIENT_ID_FIELD] = $clientId;
        }
    }

    public function setData($data = null)
    {
        if ($data == null) {
            unset($this[self::DATA_FIELD]);
        } else {
            $this[self::DATA_FIELD] = $data;
        }
    }

    public function setId($id)
    {
        if ($id == null && isset($this[self::ID_FIELD])) {
            unset($this[self::ID_FIELD]);
        } else {
            $this[self::ID_FIELD] = $id;
        }
    }

    public function setSuccessful($successful)
    {
        $this[self::SUCCESSFUL_FIELD] = $successful;
    }

    public static function parseMessages($content)
    {
        $object = $messagesParser->parse(new JSON.StringSource(content));
        if ($object instanceof Message.Mutable) {
            return Collections.singletonList($object);
        }
        return $object;
    }
/*
    protected static $jsonParser = new JSON();
    private static function _messageParser = new JSON()
    {
        @Override
        protected Map<String, Object> newMap()
        {
            return new HashMapMessage();
        }

        @Override
        protected JSON contextFor(String field)
        {
            return jsonParser;
        }
    };
    private static JSON messagesParser = new JSON()
    {
        @Override
        protected Map<String, Object> newMap()
        {
            return new HashMapMessage();
        }

        @Override
        protected Object[] newArray(int size)
        {
            return new Message.Mutable[size];
        }

        @Override
        protected JSON contextFor(String field)
        {
            return jsonParser;
        }

        @Override
        protected JSON contextForArray()
        {
            return _messageParser;
        }
    }; */
}
