<?php

namespace Monitor\App\Task\Domain\Interfaces;

use Monitor\App\Task\Domain\Task;
use Monitor\App\TaskResult\Domain\TaskResult;

interface ICollector
{
    public function setConfig(array $config): void;
    public function connect(): void;
    public function isConnected(): void;
    public function invalidate(): void;
    public function run(string $id, string $command): TaskResult;
    public function setLimit(int $limit): void;
    public function cleanLimit(): void;
}
