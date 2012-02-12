<?php

namespace Bayeux\Server\Transport;

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


    /* ------------------------------------------------------------ */
    /**
     * @see org.cometd.server.transport.LongPollingTransport#isAlwaysFlushingAfterHandle()
     */
//     @Override
    protected function isAlwaysFlushingAfterHandle()
    {
        return true;
    }

    /* ------------------------------------------------------------ */
    /**
     * @see org.cometd.server.transport.JSONTransport#init()
     */
//     @Override
    public function init()
    {
        parent::init();
        $this->_callbackParam=$this->getOption(self::CALLBACK_PARAMETER_OPTION, $this->_callbackParam);
        $this->_mimeType=$this->getOption(self::MIME_TYPE_OPTION, $this->_mimeType);
        // This transport must deliver only via /meta/connect
        $this->setMetaConnectDeliveryOnly(true);
    }

    /* ------------------------------------------------------------ */
//     @Override
    public function accept(HttpServletRequest $request)
    {
        return "GET" == $request.getMethod() && $request->getParameter($this->getCallbackParameter())!=null;
    }

    /* ------------------------------------------------------------ */
    public function getCallbackParameter()
    {
        return $this->_callbackParam;
    }

    /* ------------------------------------------------------------ */
//     @Override
    protected function send(HttpServletRequest $request, HttpServletResponse $response, PrintWriter $writer, ServerMessage $message) //throws IOException
    {
        if ($writer==null)
        {
            $response.setContentType(_mimeType);

            $callback=request.getParameter(_callbackParam);
            $writer = response.getWriter();
            $writer.append(callback);
            $writer.append("([");
        } else {
            $writer.append(',');
        }
        $writer.append(message.getJSON());
        return writer;
    }

    /* ------------------------------------------------------------ */
//     @Override
    protected function complete(PrintWriter $writer) //throws IOException
    {
        $writer.append("])\r\n");
        $writer.close();
    }
}
