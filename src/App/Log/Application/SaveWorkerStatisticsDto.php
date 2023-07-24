<?php

namespace Monitor\App\Log\Application;

class SaveWorkerStatisticsDto
{

    public string $sub_worker_id;
    public int $create_in;
    public int $last_run;
    public array $task_registers;
    public int $instance_id;
    public int $memory_usage;
    public array $tasks_runners = [];
    public array $worker_last_error = [];

    public function __construct(
        string $sub_worker_id,
        int $create_in,
        int $last_run,
        array $task_registers,
        int $instance_id,
        int $memory_usage,
        array $tasks_runners,
        array $worker_last_error

    ) {
        $this->sub_worker_id = $sub_worker_id;
        $this->create_in = $create_in;
        $this->last_run = $last_run;
        $this->task_registers = $task_registers;
        $this->instance_id = $instance_id;
        $this->memory_usage = $memory_usage;
        $this->tasks_runners = $tasks_runners;
        $this->worker_last_error = $worker_last_error;
    }

    public function toArray(): array
    {
        return [
            "sub_worker_id" => $this->sub_worker_id,
            "create_in" => $this->create_in,
            "last_run" => $this->last_run,
            "task_registers" => $this->task_registers,
            "instance_id" => $this->instance_id,
            "memory_usage" => $this->memory_usage,
            "tasks_runners" => $this->tasks_runners,
            "worker_last_error" => $this->worker_last_error
        ];
    }
}
