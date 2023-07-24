<?php

namespace Monitor\App\API\Application\Tasks;

use Monitor\App\API\Domain\Api;

class GetTasksDto
{
    public Api $api;

    public function __construct(Api $api)
    {
        $this->api = $api;
    }
}
