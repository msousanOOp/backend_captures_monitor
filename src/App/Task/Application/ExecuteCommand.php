<?php

namespace Monitor\App\Task\Application;

use Monitor\App\Task\Domain\Interfaces\ICollector;
use Monitor\App\TaskResult\Domain\TaskResult;

class ExecuteCommand
{
    public ICollector $repository;

    public function __construct(ICollector $repository)
    {
        $this->repository = $repository;
    }

    public function execute(ExecuteCommandDto $data): TaskResult
    {
        return $this->repository->run($data->task_id, $data->command);
    }
}
