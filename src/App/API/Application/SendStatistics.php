<?php

namespace Monitor\App\API\Application;

use Monitor\App\API\Domain\Interfaces\Repository;

class SendStatistics
{
    public Repository $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    public function execute(SendStatisticsDto $data): void
    {
         $this->repository->sendStatistics($data->api, $data->stats);
    }
}
