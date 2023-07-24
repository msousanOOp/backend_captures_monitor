<?php

namespace Monitor\App\API\Domain\Interfaces;

use Monitor\App\API\Domain\Api;
use Monitor\App\Shared\TasksServerHash;
use Monitor\App\Task\Domain\Task;
use Monitor\App\TaskResult\Domain\TaskResult;

interface Repository
{

    /** 
     * Get Hashes from servers to do tasks
     * @return TasksServerHash[]
     */
    public function getServerHashs(Api $api): array;

    /** 
     * Get Hashes from servers to do tasks
     * @return TasksServerHash[]
     */
    public function getServerHashsScheduler(Api $api): array;

    public function getCommands(Api $api): Task;

    public function getTaskConfig(Api $api, int $instance, string $service, int $task): array;

    public function getTasksConfiguration(Api $api, TasksServerHash $hash): array;

    public function sendTaskResult(Api $api, TaskResult $result): void;

    public function sendStatistics(Api $api, array $stats): void;

    public function sendLog(Api $api, string $id, string $level, int $timestamp, array $params): void;
}
