<?php

namespace Bayeux\Server;

use Bayeux\Api\Server\ServerTransport;

/* ------------------------------------------------------------ */
/** The base class of all server transports.
 * <p>
 * Each derived Transport class should declare all options that it supports
 * by calling {@link #setOption(String, Object)} for each option.
 * Then during the call the {@link #init()}, each transport should
 * call the variants of {@link #getOption(String)} to obtained the configured
 * value for the option.
 *
 */
abstract class AbstractServerTransport implements ServerTransport
{
    const TIMEOUT_OPTION="timeout";
    const INTERVAL_OPTION="interval";
    const MAX_INTERVAL_OPTION="maxInterval";
    const MAX_LAZY_OPTION="maxLazyTimeout";
    const META_CONNECT_DELIVERY_OPTION="metaConnectDeliverOnly";

    private $_bayeux;
    private $_interval=0;
    private $_maxInterval=10000;
    private $_timeout=30000;
    private $_maxLazyTimeout=5000;
    private $_metaConnectDeliveryOnly=false;
    private $_advice;

    private $_optionPrefix = '';
    private $_prefix = array();

    private $_name;
    private $_options = array();


    /* ------------------------------------------------------------ */
    /** Construct a ServerTransport.
     * </p>
     * <p>The construct is passed the {@link BayeuxServerImpl} instance for
     * the transport.  The {@link BayeuxServerImpl#getOptions()} map is
     * populated with the default options known by this transport. The options
     * are then inspected again when {@link #init()} is called, to set the
     * actual values used.  The options are arranged into a naming hierarchy
     * by derived classes adding prefix segments by calling add {@link #addPrefix(String)}.
     * Calls to {@link #getOption(String)} will use the list of prefixes
     * to search for the most specific option set.
     * </p>
     * <p>
     */
    protected function __construct(BayeuxServerImpl $bayeux, $name)
    {
        $this->_name = $name;
        $this->_options = $bayeux->getOptions();
        $this->_bayeux = $bayeux;
    }


    /* ------------------------------------------------------------ */
    public function getAdvice()
    {
        return $this->_advice;
    }

    /* ------------------------------------------------------------ */
    /** Get the interval.
     * @return the interval
     */
    public function getInterval()
    {
        return $this->_interval;
    }

    /* ------------------------------------------------------------ */
    /** Get the maxInterval.
     * @return the maxInterval
     */
    public function getMaxInterval()
    {
        return $this->_maxInterval;
    }


    /* ------------------------------------------------------------ */
    /** Get the max time before dispatching lazy message.
     * @return the max lazy timeout in MS
     */
    public function getMaxLazyTimeout()
    {
        return $this->_maxLazyTimeout;
    }

    /* ------------------------------------------------------------ */
    /**
     * @see org.cometd.bayeux.Transport#getName()
     */
    public function getName()
    {
        return $this->_name;
    }

    /* ------------------------------------------------------------ */
    /** Get an option value.
     * Get an option value by searching the option name tree.  The option
     * map obtained by calling {@link BayeuxServerImpl#getOptions()} is
     * searched for the option name with the most specific prefix.
     * If this transport was initialised with calls: <pre>
     *   addPrefix("long-polling");
     *   addPrefix("jsonp");
     * </pre>
     * then a call to getOption("foobar") will look for the
     * most specific value with names:<pre>
     *   long-polling.json.foobar
     *   long-polling.foobar
     *   foobar
     * </pre>
     */
    public function getOption($name, $dftValue = null)
    {
        if (! isset($this->_options[$name])) {
            $value = null;
        }

        $prefix = null;
        foreach ($this->_prefix as $segment) {
            $prefix = $prefix == null ? $segment : ($prefix . "." . $segment);
            $key = $prefix . "." . $name;
            if (isset($this->_options[$key])) {
                $value = $this->_options[$key];
            }
        }

        if ($value == null) {
            return $dftValue;
        }

        return $value;
    }

    /* ------------------------------------------------------------ */
    /**
     * @see org.cometd.common.AbstractTransport#getOptionNames()
     */
    public function getOptionNames()
    {
        $names = array();
        foreach (array_keys($this->_options) as $name) {
            $names[] = rtrim($name, '.');
        }
        return $names;
    }

    /* ------------------------------------------------------------ */
    public function getOptionPrefix()
    {
        return $this->_optionPrefix;
    }

    /* ------------------------------------------------------------ */
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
        if ($this->_optionPrefix !== '' &&
            strpos($this->_optionPrefix, $prefix) !== 0) {
            throw new IllegalArgumentException($this->_optionPrefix . " not prefix of " . $prefix);
        }
        $this->_optionPrefix = $prefix;
        $this->_prefix = explode('.', $prefix);
    }

    /* ------------------------------------------------------------ */
    /** Get the timeout.
     * @return the timeout
     */
    public function getTimeout()
    {
        return $this->_timeout;
    }

    /* ------------------------------------------------------------ */
    public function isMetaConnectDeliveryOnly()
    {
        return $this->_metaConnectDeliveryOnly;
    }

    /* ------------------------------------------------------------ */
    public function setMetaConnectDeliveryOnly($meta)
    {
        $this->_metaConnectDeliveryOnly = $meta;
    }

    /* ------------------------------------------------------------ */
    /**
     * @param name
     * @param value
     */
    public function setOption($name, $value)
    {
        if ($this->_optionPrefix!=null && conunt($this->_optionPrefix)>0) {
            $name = $this->_optionPrefix . "." . $name;
        }
        $this->_options[$mame] = $value;
    }

    /* ------------------------------------------------------------ */
    /** Initialise the transport.
     * Initialise the transport, resolving default and direct options.
     * After the call to init, the {@link #getMutableOptions()} set should
     * be reset to reflect only the options that can be changed on a running
     * transport.
     * This implementation clears the mutable options set.
     */
    public function init()
    {
        $this->_interval = $this->getOption(self::INTERVAL_OPTION, $this->_interval);
        $this->_maxInterval = $this->getOption(self::MAX_INTERVAL_OPTION, $this->_maxInterval);
        $this->_timeout = $this->getOption(self::TIMEOUT_OPTION, $this->_timeout);
        $this->_maxLazyTimeout = $this->getOption(self::MAX_LAZY_OPTION, $this->_maxLazyTimeout);
        $this->_metaConnectDeliveryOnly = $this->getOption(self::META_CONNECT_DELIVERY_OPTION, $this->_metaConnectDeliveryOnly);

        $this->_advice = "{\"reconnect\":\"retry\",\"interval\":{$this->_interval},\"timeout\":{$this->_timeout}}";
    }



    /* ------------------------------------------------------------ */
    /** Get the bayeux.
     * @return the bayeux
     */
    public function getBayeux()
    {
        return $this->_bayeux;
    }

    /* ------------------------------------------------------------ */
    /** Set the interval.
     * @param interval the interval to set
     */
    public function setInterval($interval)
    {
        $this->_interval = $interval;
    }

    /* ------------------------------------------------------------ */
    /** Set the maxInterval.
     * @param maxInterval the maxInterval to set
     */
    public function setMaxInterval($maxInterval)
    {
        $this->_maxInterval = $maxInterval;
    }

    /* ------------------------------------------------------------ */
    /** Set the timeout.
     * @param timeout the timeout to set
     */
    public function setTimeout($timeout)
    {
        $this->_timeout = $timeout;
    }

    /* ------------------------------------------------------------ */
    /** Set the maxLazyTimeout.
     * @param maxLazyTimeout the maxLazyTimeout to set
     */
    public function setMaxLazyTimeout($maxLazyTimeout)
    {
        $this->_maxLazyTimeout = $maxLazyTimeout;
    }

    /* ------------------------------------------------------------ */
    /** Set the advice.
     * @param advice the advice to set
     */
    public function setAdvice($advice)
    {
        $this->_advice = $advice;
    }

    /* ------------------------------------------------------------ */
    /**
     * Housekeeping sweep, called a regular intervals
     */
    protected function doSweep()
    {
    }
}

