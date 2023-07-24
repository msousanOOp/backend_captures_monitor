<?php

namespace Monitor\App\API\Application;

use Monitor\App\API\Domain\Api;

class SendStatisticsDto
{
    public Api $api;
    public array $stats;
    public function __construct(Api $api, array $stats)
    {
        $this->api = $api;
        $this->stats = $stats;
    }
}
