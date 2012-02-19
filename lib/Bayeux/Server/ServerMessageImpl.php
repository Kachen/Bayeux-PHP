<?php

namespace Bayeux\Server;

use Bayeux\Common\ArrayUnmodifiable;

use Bayeux\Common\UnsupportedOperationException;
use Bayeux\Common\HashMapMessage;
use Bayeux\Api\Server\ServerMessage;

class ServerMessageImpl extends HashMapMessage implements ServerMessage\Mutable
{
    private $_associated = null;
    private $_lazy = false;
    private $_json = null;

    public function __sleep() {
        $this->_associated = null;
        return (array) $this;
    }

    public function __wakeup() {
        $this->_json = json_encode((array) $this);
    }

    public function getAssociated()
    {
        return $this->_associated;
    }

    public function setAssociated(ServerMessage\Mutable $associated = null)
    {
        $this->_associated = $associated;
    }

    public function isLazy()
    {
        return $this->_lazy;
    }

    public function setLazy($lazy)
    {
        $this->_lazy = $lazy;
    }

    public function freeze($json)
    {
        if ($this->_json != null) {
            throw new \Exception();
        }
        $this->_json = $json;
    }

    public function isFrozen()
    {
        return $this->_json != null;
    }

    public function getData()
    {
        $data = parent::getData();
        if ($this->isFrozen() && (is_array($data) || $data instanceof \stdClass)) {
            return new ArrayUnmodifiable($data);
        }
        return $data;
    }

    public function offsetSet($index, $newval) {
        if ($this->isFrozen()) {
            throw new UnsupportedOperationException();
        }
        return parent::offsetSet($index, $newval);
    }

    //@Override
    public function getDataAsMap($create = null)
    {
        $data = parent::getDataAsMap($create);
        if ($this->isFrozen() && $data != null) {
            return new ArrayUnmodifiable((array) $data);
        }

        return $data;
    }

    //@Override
    public function getExt($create = null)
    {
        $ext = parent::getExt($create);
        if ($this->isFrozen() && $ext != null) {
            return new ArrayUnmodifiable((array) $ext);
        }
        return $ext;
    }

    //@Override
    public function getAdvice($create = null)
    {
        $advice = parent::getAdvice($create);
        if ($this->isFrozen() && $advice != null) {
            return new ArrayUnmodifiable((array) $advice);
        }
        return $advice;
    }
}