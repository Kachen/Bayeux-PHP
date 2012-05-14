<?php

namespace Bayeux\Server\Transport;

use Bayeux\Http\Response;
use Bayeux\Http\Request;
use Bayeux\Server\BayeuxServerImpl;
use Bayeux\Api\Server\ServerMessage;

class JSONPTransport extends LongPollingTransport
{
    const PREFIX="long-polling.jsonp";
    const NAME="callback-polling";
    const MIME_TYPE_OPTION="mimeType";
    const CALLBACK_PARAMETER_OPTION="callbackParameter";

    private $_mimeType="text/javascript;charset=UTF-8";
    private $_callbackParam="jsonp";

    public function __construct(BayeuxServerImpl $bayeux)
    {
        parent::__construct($bayeux, self::NAME);
        $this->setOptionPrefix(self::PREFIX);
    }

    /**
     * @see org.cometd.server.transport.LongPollingTransport#isAlwaysFlushingAfterHandle()
     */
    protected function isAlwaysFlushingAfterHandle()
    {
        return true;
    }

    /**
     * @see org.cometd.server.transport.JSONTransport#init()
     */
    public function init()
    {
        parent::init();
        $this->_callbackParam = $this->getOption(self::CALLBACK_PARAMETER_OPTION, $this->_callbackParam);
        $this->_mimeType = $this->getOption(self::MIME_TYPE_OPTION, $this->_mimeType);
        // This transport must deliver only via /meta/connect
        $this->setMetaConnectDeliveryOnly(true);
    }

    public function accept(Request $request) {
        return $request->isGet() && $request->getParameter($this->getCallbackParameter()) != null;
    }

    protected function parseMessages($request) {
        if (! ($request instanceof Request)) {
            throw new \InvalidArgumentException();
        }
        return parent::parseMessages($request->getParameterValues(self::MESSAGE_PARAM));
    }

    public function getCallbackParameter() {
        return $this->_callbackParam;
    }

    protected function send(Request $request, Response $response, $writer, ServerMessage $message) //throws IOException
    {
        if ($writer==null) {
            $response.setContentType(_mimeType);

            $callback=request.getParameter(_callbackParam);
            $writer = $response->getWriter();
            $writer.append(callback);
            $writer.append("([");
        } else {
            $writer.append(',');
        }
        $writer.append($message->getJSON());
        return $writer;
    }

    protected function complete($writer) {
        return "{$writer}])\r\n";
    }
}
