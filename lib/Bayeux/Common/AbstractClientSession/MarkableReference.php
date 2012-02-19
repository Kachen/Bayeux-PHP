<?php

namespace Bayeux\Common\AbstractClientSession;


/**
* Non-volatile, non-atomic version of {@link AtomicMarkableReference}.
* @param <T> the reference type
*/
class MarkableReference
{
    private $reference;
    private $mark;

    public function __construct($reference, $mark)
    {
        $this->reference = $reference;
        $this->mark = $mark;
    }

    public function getReference()
    {
        return $this->reference;
    }

    public function isMarked()
    {
        return $this->mark;
    }
}