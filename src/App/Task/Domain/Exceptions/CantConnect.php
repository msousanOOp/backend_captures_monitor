<?php

namespace Monitor\App\Task\Domain\Exceptions;


class CantConnect extends \DomainException
{
    public function __construct(string $connector, int $code)
    {
        parent::__construct("Could not establish connection with technology ($connector)", $code);
    }
}
