<?php

namespace Bayeux\Http;

use \Zend\Http\PhpEnvironment\Request as PhpRequest;

class Request extends PhpRequest {

    protected $_attributes = array();

    public function setAttribute($name, $value) {
        $this->_attributes[$name] = $value;
    }

    public function getAttribute($name) {
        if (empty($this->_attributes[$name])) {
            return null;
        }

        return $this->_attributes[$name];
    }

}