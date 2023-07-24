<?php

namespace Monitor\App\API\Application;

use Monitor\App\API\Domain\Api;
use Monitor\App\Shared\TasksServerHash;

class GetConfigurationDto
{
    public Api $api;
    public TasksServerHash $hash;
    public function __construct(Api $api, TasksServerHash $hash)
    {
        $this->api = $api;
        $this->hash = $hash;
    }
}
