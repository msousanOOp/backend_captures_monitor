<?php

namespace Monitor\App\Task\Infrastructure;

use Monitor\App\TaskResult\Domain\TaskResult;
use Exception;
use Monitor\App\Shared\Utils;
use Monitor\App\Task\Domain\Collector;
use Monitor\App\Task\Domain\Exceptions\CantConnect;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use Throwable;

class Local extends Collector
{

    const CONNECTOR_NAME = "local";

    public function setConfig(array $config): void
    {}

    public function connect(): void
    {}

    public function isConnected(): void
    {}

    public function invalidate(): void
    {}

    public function run(string $task_id, string $command): TaskResult
    {
        $task_result = new TaskResult($task_id, self::CONNECTOR_NAME);
        try {
            $task_result->startTimer("connection_time_" . self::CONNECTOR_NAME);
            $this->connect();
            $task_result->finishTimer("connection_time_" . self::CONNECTOR_NAME);

            $task_result->startTimer("task_$task_id");
            $stm = "";
            exec($command, $stm);
            $task_result->finishTimer("task_$task_id");
            $task_result->setResult(Utils::convertTextArray($stm));
            $task_result->setStatus("successfully");
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            $this->log("ERROR", $e->getMessage());
            $task_result->setStatus("failed");
            $task_result->log($task_id, "Error", $e->getCode(), $e->getMessage());
        } catch (\Throwable $e) {
            echo $e->getMessage() . PHP_EOL;
            $this->log("ERROR", $e->getMessage());
            $task_result->setStatus("failed");
            $task_result->log($task_id, "Error", $e->getCode(), $e->getMessage());
        }
        $task_result->finish();
        return $task_result;
    }
}
