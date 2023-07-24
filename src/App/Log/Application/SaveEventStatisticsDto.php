<?php

namespace Monitor\App\Log\Application;

class SaveEventStatisticsDto
{

    public string $event_name;
    public int $create_in;
    public int $last_run;
    public int $memory_usage;
    public array $last_error = [];
    public array $extra = [];

    public function __construct(
        string $event_name,
        int $create_in,
        int $last_run,
        int $memory_usage,
        array $last_error,
        array $extra = []
    ) {
        $this->event_name = $event_name;
        $this->create_in = $create_in;
        $this->last_run = $last_run;
        $this->memory_usage = $memory_usage;
        $this->last_error = $last_error;
        $this->extra = $extra;
    }

    public function toArray(): array
    {
        return [
            "event_name" => $this->event_name,
            "create_in" => $this->create_in,
            "last_run" => $this->last_run,
            "memory_usage" => $this->memory_usage,
            "last_error" => $this->last_error,
            "extra" => $this->extra
        ];
    }
}
