<?php

namespace Bayeux\Common;

use Bayeux\Api\Bayeux\Transport;
use Bayeux\Api\Bayeux\Client\ClientSession;

/**
 * <p>Partial implementation of {@link ClientSession}.</p>
 * <p>It handles extensions and batching, and provides utility methods to be used by subclasses.</p>
 */
class AbstractTransport implements Transport
{
    private $_name;
    private $_options;
    private $_prefix = array();

    protected function __construct($name, array $options = array())
    {
        $this->_name = $name;
        $this->_options = $options;
    }

    public function getName()
    {
        return $this->_name;
    }

    public function getOption($name)
    {
        $value = $this->_options[$name];
        $prefix = null;
        foreach ($this->_prefix as $segment)
        {
            $prefix = $prefix == null ? $segment : ($prefix . "." . $segment);
            $key = $prefix . "." . $name;
            if (isset($this->_options[$key])) {
                $value = $this->_options[key];
            }
        }

        return $value == null ? $dftValue : $value;
    }

    public function setOption($name, $value)
    {
        $prefix = $this->getOptionPrefix();
        $this->_options[$prefix == null ? $name : $prefix . "." . $name] = $value;
    }

    public function getOptionPrefix()
    {
        $prefix = null;
        foreach ($this->_prefix as $segment) {
            $prefix = $prefix == null ? $segment : ($prefix . "." . $segment);
        }
        return $prefix;
    }

    public function setOptionPrefix($prefix)
    {
        $this->_prefix = explode('.', $prefix);
    }

    public function getOptionNames()
    {
        $names = array();
        foreach ($this->_options as $name => $valuekeySet())
        {
            $names = rtrim($name, '.');
        }
        return $names;
    }
}
