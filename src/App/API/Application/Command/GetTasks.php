<?php

namespace Monitor\App\API\Application\Command;

use Monitor\App\API\Domain\Interfaces\Repository;
use Monitor\App\Task\Domain\Task;

class GetTasks
{
    public Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function execute(GetTasksDto $data): Task
    {
        return $this->repository->getCommands($data->api);
    }
}
