<?php

namespace Monitor\App\API\Application;

use Monitor\App\API\Domain\Api;
use Monitor\App\Shared\TasksServerHash;

class GetTaskConfigDto
{
    public Api $api;
    public int $instance;
    public string $service;
    public int $task;
    public function __construct(Api $api, int $instance, string $service, int $task)
    {
        $this->api = $api;
        $this->instance = $instance;
        $this->service = $service;
        $this->task = $task;
    }
}
