<?php

namespace Monitor\Events;

use Exception;
use Monitor\App\Workers\TaskWorker\Application\CreateWorkerDto;
use Monitor\App\API\Application\Scheduler\GetTasks;
use Monitor\App\API\Application\Scheduler\GetTasksDto;
use Monitor\App\API\Domain\Api;
use Monitor\App\API\Infrastructure\Client;
use Monitor\App\Log\Application\SaveEventStatistics;
use Monitor\App\Log\Application\SaveEventStatisticsDto;
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
class Scheduler
{

    private static $workers = [];

    private static Api $api;
    private static GetTasks $get_tasks;
    private static CreateWorker $create_worker;
    private static SaveEventStatistics $save_stats;
    private static int $start;
    private static array $last_error = [];


    public static function run()
    {
        try {

            \Monitor\App\Log\Domain\Log::debug("Updating Schedulers", "SCHEDULER");
            $get_tasks_dto = new GetTasksDto(self::$api);
            $tasks = self::$get_tasks->execute($get_tasks_dto);

            $keys = array_keys(self::$workers);

            $delete = array_diff($keys, $tasks);
            $create = array_diff($tasks, $keys);

            if (!empty($delete)) {
                \Monitor\App\Log\Domain\Log::debug("Deleting older schedulers", "SCHEDULER");
                foreach ($delete as  $hash) {
                    self::$workers[$hash]->stop();
                    unset(self::$workers[$hash]);
                }
            }

            if (!empty($create)) {
                \Monitor\App\Log\Domain\Log::debug("Creating new schedulers", "SCHEDULER");
                foreach ($create as $hash) {
                    $create_worker_dto = new CreateWorkerDto($hash, "scheduler");
                    self::$workers[$hash->hash()] = self::$create_worker->execute($create_worker_dto);
                    self::$workers[$hash->hash()]->run();
                }
            }
        } catch (Exception $e) {
            \Monitor\App\Log\Domain\Log::debug($e->getMessage(), "SCHEDULER");
            self::$last_error = [
                'timestamp' => time(),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => is_array($e->getTrace()) ? array_slice($e->getTrace(), 0, 3) : $e->getTrace()
            ];
        } catch (Throwable $e) {
            \Monitor\App\Log\Domain\Log::debug($e->getMessage(), "SCHEDULER");
            self::$last_error = [
                'timestamp' => time(),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => is_array($e->getTrace()) ? array_slice($e->getTrace(), 0, 3) : $e->getTrace()
            ];
        }

        $dto = new SaveEventStatisticsDto("scheduler", self::$start, time(), memory_get_peak_usage(true), self::$last_error);
        self::$save_stats->execute($dto);
    }

    public static function firstRun()
    {
        \Monitor\App\Log\Domain\Log::debug("Starting", "SCHEDULER");
        self::$api = new Api;

        $api_repository = new Client;
        self::$get_tasks = new GetTasks($api_repository);

        self::$create_worker = new CreateWorker;
        self::$start = time();
        self::$save_stats = new SaveEventStatistics;
    }
}
