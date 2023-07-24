<?php

namespace Monitor\App\Log\Domain\Exceptions;


class LevelNotSupported extends \DomainException
{
    public function __construct()
    {
        parent::__construct("Invalid Log Level");
    }
}
