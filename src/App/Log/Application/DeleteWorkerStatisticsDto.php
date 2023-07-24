<?php

namespace Monitor\App\Log\Application;

class DeleteWorkerStatisticsDto
{

    public string $sub_worker_id;

    public function __construct(
        string $sub_worker_id

    ) {
        $this->sub_worker_id = $sub_worker_id;
    }
}
