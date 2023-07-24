<?php

namespace Monitor\App\API\Application\Tasks;

use Monitor\App\API\Domain\Interfaces\Repository;
use Monitor\App\Shared\TasksServerHash;

class GetTasks
{
    public Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return TasksServerHash[]
     */
    public function execute(GetTasksDto $data): array
    {
        return $this->repository->getServerHashs($data->api);
    }
}
