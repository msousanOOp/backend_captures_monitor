<?php

namespace Monitor\App\Log\Application;

use Monitor\App\API\Domain\Api;
use Monitor\App\API\Domain\Interfaces\Repository;

class SendLog
{
    public Repository $repository;
    public Api $api;

    public function __construct(Repository $repository, Api $api)
    {
        $this->repository = $repository;
        $this->api = $api;
    }

    public function execute(SendLogDto $dto): void
    {
        $this->repository->sendLog($this->api, $dto->ref_id, $dto->level, $dto->timestamp, $dto->params);
    }
}
