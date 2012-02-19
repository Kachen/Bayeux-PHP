<?php

namespace Bayeux\Server;

use Bayeux\Common\AbstractTransport;
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
abstract class AbstractServerTransport extends AbstractTransport implements ServerTransport
{
    const TIMEOUT_OPTION = "timeout";
    const INTERVAL_OPTION = "interval";
    const MAX_INTERVAL_OPTION = "maxInterval";
    const MAX_LAZY_OPTION = "maxLazyTimeout";
    const META_CONNECT_DELIVERY_OPTION = "metaConnectDeliverOnly";

    //private $_logger;
    private $_bayeux;
    private $_interval = 0;
    private $_maxInterval = 10000;
    private $_timeout = 30000;
    private $_maxLazyTimeout = 5000;
    private $_metaConnectDeliveryOnly = false;
    private $jsonContext;
    private $_advice;

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
        parent::__construct($name, $bayeux->getOptions());
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
        if (! is_bool($meta)) {
            throw new \InvalidArgumentException();
        }
        $this->_metaConnectDeliveryOnly = $meta;
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
        $this->jsonContext = $this->getOption(BayeuxServerImpl::JSON_CONTEXT);
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
    public function sweep()
    {
    }

    protected function debug($format)
    {
        throw new \Exception("não implementado");
        if ($this->_bayeux->getLogLevel() >= BayeuxServerImpl.DEBUG_LOG_LEVEL) {
            $this->_logger.info(format, args);
        } else {
            _logger.debug(format, args);
        }
    }
}

