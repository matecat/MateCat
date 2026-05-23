<?php

namespace Model\DataAccess;

use DomainException;

class UnknownPropertyException extends DomainException
{
    public function __construct(string $name, ?string $class = null)
    {
        $message = $class
            ? "Unknown property $name in $class"
            : "Unknown property $name";
        parent::__construct($message);
    }
}
