<?php

namespace Bayeux\Common;

use Bayeux\Api\Transport;

/**
 * <p>Partial implementation of {@link ClientSession}.</p>
 * <p>It handles extensions and batching, and provides utility methods to be used by subclasses.</p>
 */
class AbstractTransport implements Transport
{
    private $_name;
    private $_options;
    private $_prefix = array();
    private $_optionPrefix = '';

    protected function __construct($name, \ArrayObject $options) {

        if (!is_string($name)) {
            throw new \InvalidArgumentException();
        }

        $this->_name = $name;
        $this->_options = $options;
    }

    public function getName(){
        return $this->_name;
    }

    public function getOption($name, $dftValue = null)
    {
        if (isset($this->_options[$name])) {
            $value = $this->_options[$name];
        } else {
            $value = null;
        }

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

    public function getOptionPrefix() {
        return $this->_optionPrefix;
    }

    /** Set the option name prefix segment.
     * <p> Normally this is called by the super class constructors to establish
     * a naming hierarchy for options and iteracts with the {@link #setOption(String, Object)}
     * method to create a naming hierarchy for options.
     * For example the following sequence of calls:<pre>
     *   setOption("foo","x");
     *   setOption("bar","y");
     *   setOptionPrefix("long-polling");
     *   setOption("foo","z");
     *   setOption("whiz","p");
     *   setOptionPrefix("long-polling.jsonp");
     *   setOption("bang","q");
     *   setOption("bar","r");
     * </pre>
     * will establish the following option names and values:<pre>
     *   foo: x
     *   bar: y
     *   long-polling.foo: z
     *   long-polling.whiz: p
     *   long-polling.jsonp.bang: q
     *   long-polling.jsonp.bar: r
     * </pre>
     * The various {@link #getOption(String)} methods will search this
     * name tree for the most specific match.
     *
     * @param segment name
     * @throws IllegalArgumentException if the new prefix is not prefixed by the old prefix.
     */
    public function setOptionPrefix($prefix)
    {
        if ( !empty($this->_optionPrefix) && strpos($prefix, $this->_optionPrefix) !== 0) {
            throw new \InvalidArgumentException($this->_optionPrefix . " not prefix of " . $prefix);
        }

        $this->_optionPrefix = $prefix;
        $this->_prefix = explode(".", $prefix);
    }

    public function getOptionNames()
    {
        $names = array();
        foreach ($this->_options as $name => $valuekeySet) {
            $names = rtrim($name, '.');
        }
        return $names;
    }
}