<?php

namespace Monitor\App\Log\Application;

class GetEventStatisticsDto
{

    public string $event_name;

    public function __construct(
        string $event_name
    ) {
        $this->event_name = $event_name;
    }

}
