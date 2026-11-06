<?php

namespace Macroseso\Enzona\Exceptions;

use RuntimeException;

class EnzonaException extends RuntimeException
{
    private $httpStatus;

    public function __construct($message, $httpStatus = null)
    {
        parent::__construct($message, $httpStatus ?: 0);
        $this->httpStatus = $httpStatus;
    }

    public function getHttpStatus()
    {
        return $this->httpStatus;
    }
}
