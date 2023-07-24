<?php

namespace Monitor\App\API\Domain\Exceptions;


class EmptyCommands extends \DomainException
{
    public function __construct()
    {
        parent::__construct("Empty Commands");
    }
}
