<?php

namespace Monitor\App\API\Application;

use Monitor\App\API\Domain\Interfaces\Repository;

class GetTaskConfig
{
    public Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function execute(GetTaskConfigDto $data): array
    {
        return $this->repository->getTaskConfig($data->api, $data->instance, $data->service, $data->task);
    }
}
