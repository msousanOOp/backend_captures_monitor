<?php

namespace Monitor\App\API\Application;

use Monitor\App\API\Domain\Api;
use Monitor\App\TaskResult\Domain\TaskResult;

class SendTaskResultDto
{
    public TaskResult $result;
    public Api $api;

    public function __construct(TaskResult $result,Api $api)
    {
        $this->api = $api;
        $this->result = $result;
    }
}
