<?php

namespace Monitor\App\API\Application;

use Monitor\App\API\Domain\Interfaces\Repository;

class SendTaskResult
{
    public Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function execute(SendTaskResultDto $data): void
    {
        $this->repository->sendTaskResult($data->api, $data->result);
    }
}
