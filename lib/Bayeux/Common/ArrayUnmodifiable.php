<?php

namespace Bayeux\Common;


class ArrayUnmodifiable extends \ArrayObject {

    public function offsetSet($index, $newval) {
        throw new UnsupportedOperationException();
    }

    public function offsetUnset($index) {
        throw new UnsupportedOperationException();
    }
}