<?php

namespace src\transformer\repos\exceptions;

use Exception;

class TypeNotFound extends Exception {

    protected $debug = [];

    public function addDebugInfo(array $debug) {
        $this->debug = array_merge($this->debug, $debug);
    }

    /**
     * @return string
     */
    public function __toString() {
        $previous = '';
        if ($this->getPrevious()) {
            $previous = (string) $previous . " ";
        }
        return sprintf(
            "%sTransformer repos Exception: Type %s not found Debug: %s Trace: %s",
            $previous, $this->message, json_encode($this->debug), $this->getTraceAsString()
    );

    }
}