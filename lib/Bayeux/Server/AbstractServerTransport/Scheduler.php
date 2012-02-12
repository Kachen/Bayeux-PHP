<?php

namespace Bayeux\Server\AbstractServerTransport;


/* ------------------------------------------------------------ */
interface Scheduler
{
    public function cancel();
    public function schedule();
}

