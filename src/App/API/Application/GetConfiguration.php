<?php

namespace Monitor\App\API\Application;

use Monitor\App\API\Domain\Interfaces\Repository;

class GetConfiguration
{
    public Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function execute(GetConfigurationDto $data): array
    {
        return $this->repository->getTasksConfiguration($data->api, $data->hash);
    }
}
