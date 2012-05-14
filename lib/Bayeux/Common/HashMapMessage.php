<?php

namespace Bayeux\Common;

use Bayeux\Api\Message;
use Bayeux\Api\ChannelId;

class HashMapMessage extends \ArrayObject implements Message\Mutable
{

    public function getChannel()
    {
        if (isset($this[self::CHANNEL_FIELD])) {
            return $this[self::CHANNEL_FIELD];
        }
        return null;
    }

    public function getChannelId()
    {
        return new ChannelId($this->getChannel());
    }

    public function getClientId() {
        if (empty($this[self::CLIENT_ID_FIELD])) {
            return null;
        }
        return $this[self::CLIENT_ID_FIELD];
    }

    public function getId()
    {
        // Support also old-style ids of type long
        if (isset($this[self::ID_FIELD])) {
            return $this[self::ID_FIELD];
        }

        return null;
    }

    public function getData()
    {
        if (isset($this[self::DATA_FIELD])) {
            if (is_array($this[self::DATA_FIELD])) {
                return new \ArrayObject((array) $this[self::DATA_FIELD]);
            }
            return $this[self::DATA_FIELD];
        }
        return null;
    }

    public function getJSON()
    {
        //FIXME: colocaro jsoncontext como estatico no constructor e utilizar ele aqui
        return df_json_encode($this->getArrayCopy());
    }

    public function getAdvice($create = null)
    {
        if (isset($this[self::ADVICE_FIELD])) {
            $advice = $this[self::ADVICE_FIELD];
        } else {
            $advice = null;
        }

        if ($create === null) {
            return $advice;
        }

        if ($create && $advice === null)
        {
            $this[self::ADVICE_FIELD] = $advice = new \ArrayObject();
        }
        return $advice;
    }

    public function getDataAsMap($create = null)
    {
        if (isset($this[self::DATA_FIELD])) {
            if (! ($this[self::DATA_FIELD] instanceof \ArrayObject)) {
                $data = new \ArrayObject((array) $this[self::DATA_FIELD]);
            }
        } else {
            $data = null;
        }

        if ($create === null) {
            return $data;
        }

        if ($create && $data == null)
        {
            $this[self::DATA_FIELD] = $data = new \ArrayObject();
        }
        return $data;
    }

    public function getExt($create = null)
    {
        if (isset($this[self::EXT_FIELD])) {
            $ext = $this[self::EXT_FIELD];
        } else {
            $ext = null;
        }

        if ($create === null) {
            return $ext;
        }

        if ($create && $ext == null) {
            $this[self::EXT_FIELD] = $ext = new \ArrayObject();
        }

        return $ext;
    }

    public function isMeta()
    {
        return ChannelId::staticIsMeta($this->getChannel());
    }

    public function isSuccessful()
    {
        if (isset($this[Message::SUCCESSFUL_FIELD])) {
            $value = (bool) $this[Message::SUCCESSFUL_FIELD];
        } else {
            $value = null;
        }

        return $value != null && $value;
    }

    public function setChannel($channel = null)
    {
        if ($channel == null) {
            if (isset($this[self::CHANNEL_FIELD])) {
                unset($this[self::CHANNEL_FIELD]);
            }
        } else {
            $this[self::CHANNEL_FIELD] = $channel;
        }
    }

    public function setClientId($clientId = null)
    {
        if ($clientId === null) {
            if (isset($this[self::CLIENT_ID_FIELD])) {
                unset($this[self::CLIENT_ID_FIELD]);
            }
        } else {
            $this[self::CLIENT_ID_FIELD] = $clientId;
        }
    }

    public function setData($data = null)
    {
        if ($data === null) {
            if (isset($this[self::DATA_FIELD])) {
                unset($this[self::DATA_FIELD]);
            }
        } else {
            $this[self::DATA_FIELD] = $data;
        }
    }

    public function setId($id = null)
    {
        if ($id === null) {
            if (isset($this[self::ID_FIELD])) {
                unset($this[self::ID_FIELD]);
            }
        } else {
            $this[self::ID_FIELD] = $id;
        }
    }

    public function setSuccessful($successful)
    {
        if (! is_bool($successful)) {
            throw new \InvalidArgumentException();
        }
        $this[self::SUCCESSFUL_FIELD] = $successful;
    }
}
