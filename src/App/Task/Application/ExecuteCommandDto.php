<?php

namespace Monitor\App\Task\Application;

class ExecuteCommandDto
{

    public string $task_id;
    public string $command;

    public function __construct(string $task_id, string $command)
    {
        $this->task_id = $task_id;
        $this->command= $command;
    }
}
