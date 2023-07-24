<?php

namespace Monitor\Events;

use DomainException;
use Exception;
use Monitor\App\Workers\TaskWorker\Application\CreateWorkerDto;
use Monitor\App\API\Application\Command\GetTasks;
use Monitor\App\API\Application\Command\GetTasksDto;
use Monitor\App\API\Application\SendTaskResult;
use Monitor\App\API\Application\SendTaskResultDto;
use Monitor\App\API\Domain\Api;
use Monitor\App\API\Infrastructure\Client;
use Monitor\App\Log\Application\SaveEventStatistics;
use Monitor\App\Log\Application\SaveEventStatisticsDto;
use Monitor\App\Log\Domain\Log;
use Monitor\App\Task\Application\ExecuteCommand;
use Monitor\App\Task\Application\ExecuteCommandDto;
use Monitor\App\Workers\TaskWorker\Application\CreateWorker;
use Sohris\Event\Annotations\Time;
use Sohris\Event\Annotations\StartRunning;
use Sohris\Event\Event\EventControl;
use Throwable;

/**
 * @Time(
 *  type="Interval",
 *  time="5"
 * )
 * @StartRunning
 */
class Command extends EventControl
{
    private static Api $api;
    private static GetTasks $get_tasks;
    private static SaveEventStatistics $save_stats;
    private static int $start;
    private static array $last_error = [];

    public static function run()
    {
        \Monitor\App\Log\Domain\Log::debug("Getting Commands", "COMMAND");
        try {

            $get_tasks_dto = new GetTasksDto(self::$api);
            $task = self::$get_tasks->execute($get_tasks_dto);
            $connector = $task->connector();

            \Monitor\App\Log\Domain\Log::debug("Execute Command", "COMMAND");
            $execute_command_dto = new ExecuteCommandDto($task->id(), $task->command());
            $execute_command = new ExecuteCommand($connector);
            $result = $execute_command->execute($execute_command_dto);

            $result->setInstance($task->instance());
            $result->setService($task->service());
            $result->setType($task->type());

            //Send Result
            $send_result_dto = new SendTaskResultDto($result, self::$api);
            $send_result = new SendTaskResult(new Client);
            $send_result->execute($send_result_dto);
        } catch (DomainException $e) {
            \Monitor\App\Log\Domain\Log::debug($e->getMessage(), "COMMAND");
        } catch (Exception $e) {
            \Monitor\App\Log\Domain\Log::debug($e->getMessage(), "COMMAND");
            self::$last_error = [
                'timestamp' => time(),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => is_array($e->getTrace()) ? array_slice($e->getTrace(), 0, 3) : $e->getTrace()
            ];
        } catch (Throwable $e) {
            \Monitor\App\Log\Domain\Log::debug($e->getMessage(), "COMMAND");
            self::$last_error = [
                'timestamp' => time(),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => is_array($e->getTrace()) ? array_slice($e->getTrace(), 0, 3) : $e->getTrace()
            ];
        }

        $dto = new SaveEventStatisticsDto("command", self::$start, time(), memory_get_peak_usage(true), self::$last_error);
        self::$save_stats->execute($dto);
    }

    public static function firstRun()
    {
        \Monitor\App\Log\Domain\Log::debug("Starting", "COMMAND");
        self::$start = time();
        self::$api = new Api;
        $api_repository = new Client;
        self::$get_tasks = new GetTasks($api_repository);
        self::$save_stats = new SaveEventStatistics;
    }
}
