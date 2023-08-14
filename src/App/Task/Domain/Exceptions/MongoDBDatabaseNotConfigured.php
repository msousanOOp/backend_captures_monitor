<?php

namespace Monitor\App\Task\Domain\Exceptions;


class MongoDBDatabaseNotConfigured extends \DomainException
{
    public function __construct()
    {
        parent::__construct("Database is not configured");
    }
}
