<?php

namespace Bayeux\Server\Transport;

use Bayeux\Api\Server\ServerMessage;

use Bayeux\Server\BayeuxServerImpl;

class JSONTransport //extends LongPollingTransport
{
    const PREFIX = "long-polling.json";
    const NAME = "long-polling";
    const MIME_TYPE_OPTION = "mimeType";

    private $_mimeType = "application/json;charset=UTF-8";

    public function __contruct(BayeuxServerImpl $bayeux)
    {
        //parent::__construct($bayeux, self::NAME);
        $this->setOptionPrefix(self::PREFIX);
    }

//    @Override
    protected function isAlwaysFlushingAfterHandle()
    {
        return false;
    }

//     @Override
    protected function init()
    {
        parent::init();
        $this->_mimeType = $this->getOption(self::MIME_TYPE_OPTION, $this->_mimeType);
    }

//     @Override
    public function accept(HttpServletRequest $request)
    {
        return "POST" == $request->getMethod();
    }

//     @Override
    protected function send(HttpServletRequest $request, HttpServletResponse $response, PrintWriter $writer, ServerMessage $message) //throws IOException
    {
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
        $writer.append(message.getJSON());
        return $writer;
    }

//     @Override
    protected function complete(PrintWriter $writer)// throws IOException
    {
        $writer.append("]\n");
        $writer.close();
    }
}
