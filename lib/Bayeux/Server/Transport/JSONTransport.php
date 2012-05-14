<?php

namespace Bayeux\Server\Transport;

use Bayeux\Http\Headers;

use Bayeux\Http\Header;
use Bayeux\Http\Response;
use Bayeux\Http\Request;
use Bayeux\Api\Server\ServerMessage;
use Bayeux\Server\BayeuxServerImpl;

class JSONTransport extends LongPollingTransport
{
    const PREFIX = "long-polling.json";
    const NAME = "long-polling";
    const MIME_TYPE_OPTION = "mimeType";

    private $_jsonDebug = false;
    private $_mimeType = "application/json;charset=UTF-8";

    public function __construct(BayeuxServerImpl $bayeux) {
        parent::__construct($bayeux, self::NAME);
        $this->setOptionPrefix(self::PREFIX);
    }

    protected function isAlwaysFlushingAfterHandle() {
        return false;
    }

    public function init()
    {
        parent::init();
        $this->_jsonDebug = $this->getOption(self::JSON_DEBUG_OPTION, $this->_jsonDebug);
        $this->_mimeType = $this->getOption(self::MIME_TYPE_OPTION, $this->_mimeType);
    }

    public function accept(Request $request) {
        return $request->isPost();
    }

    protected function parseMessages($request)  {
        if (! ($request instanceof $request)) {
            return parent::parseMessages($request);
        }

        $header = $request->headers();
        $charset = $header->get('contentencoding');
        if (! $charset) {
            $header->addHeaderLine('Character-Encoding: UTF-8');
        }

        $contentType = $header->get('contenttype')->getFieldValue();
        if ($contentType == null || stripos($contentType, "application/json") === 0) {
            return parent::parseMessages($request->getContent(), $this->_jsonDebug);

        } else if (stripos($contentType, "application/x-www-form-urlencoded")) {
            return parent::parseMessages($request->getParameterValues(self::MESSAGE_PARAM));

        } else {
            throw new Header\Exception("Invalid Content-Type: {$contentType}");
        }
    }

    protected function send(Request $request, Response $response, $writer, ServerMessage $message) {
        if ($writer == null)
        {
            $response->setContentType($this->_mimeType);
            $writer = $response->getWriter();
            $writer->append('[');
        }
        else
        {
            $writer.append(',');
        }
        $writer .= $message.getJSON();
        return $writer;
    }

    protected function complete($writer) {
        return $writer .= "]\n";
    }
}
