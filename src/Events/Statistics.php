<?php

namespace Monitor\Events;

use Exception;
use Monitor\App\API\Application\SendStatistics;
use Monitor\App\API\Application\SendStatisticsDto;
use Monitor\App\API\Domain\Api;
use Monitor\App\API\Infrastructure\Client;
use Monitor\App\Log\Application\ClearStatistics;
use Monitor\App\Log\Application\GetEventsStatistics;
use Monitor\App\Log\Application\GetEventStatisticsDto;
use Monitor\App\Log\Application\GetWorkersStatistics;
use Sohris\Event\Annotations\Time;
use Sohris\Event\Annotations\StartRunning;
use Sohris\Event\Event\EventControl;
use Monitor\App\Log\Application\SendLog;
use Monitor\App\Log\Application\SendLogDto;
use Sohris\Core\Server;
use Throwable;

/**
 * @Time(
 *  type="Interval",
 *  time="60"
 * )
 * @StartRunning
 */
class Statistics extends EventControl
{
    const EVENTS = ['monitor', 'command', 'scheduler'];
    private static GetWorkersStatistics $get_worker_stats;
    private static GetEventsStatistics $get_event_stats;
    private static Api $api;
    private static int $start;
    private static SendStatistics $send_statistics;
    private static SendLog $send_log;

    public static function run()
    {
        try {
            \Monitor\App\Log\Domain\Log::debug("Saving", "STATISTICS");
            $worker_data = self::$get_worker_stats->execute();
            $tasks_count = 0;
            $tasks_services_count = [];
            $do_restart = false;
            foreach ($worker_data as $w_data) {
                foreach ($w_data['tasks_runners'] as $service => $ts_run) {
                    if (!array_key_exists($service, $tasks_services_count))
                        $tasks_services_count[$service] = 0;
                    foreach ($ts_run as $id => $t_info) {
                        $tasks_services_count[$service] += $t_info['count'];
                        $tasks_count += $t_info['count'];
                        if ($t_info['last_run'] > 0 && time() - $t_info['last_run'] > 3 * $t_info['frequency']) {
                            try {
                                $dto = new SendLogDto(random_int(10000, 99999), "INFO", ["message" => "TASK_DEATH_WORKER", "instance" => $t_info["instance_id"], "worker" => $t_info]);
                                self::$send_log->execute($dto);
                            } catch (Throwable $e) {
                            }
                            $do_restart = true;
                        }
                    }
                }
            }

            $events = [];

            foreach (self::EVENTS as $event) {
                $dto = new GetEventStatisticsDto($event);
                $events[] = self::$get_event_stats->execute($dto);
            }
            $statitics = [
                "events" => $events,
                "workers" => $worker_data,
                "creating" => self::$start,
                "last_run" => time(),
                "uptime" => time() - self::$start,
                "tasks_per_seconds" => ($tasks_count > 0 && (time() - self::$start) > 0) ? round($tasks_count / (time() - self::$start), 1) : 0,
                "task_count" => $tasks_count,
                "task_count_per_service" => $tasks_services_count
            ];

            $stats = new SendStatisticsDto(self::$api, $statitics);

            self::$send_statistics->execute($stats);

            // if ($do_restart) {
            //     \Monitor\App\Log\Domain\Log::debug("Killing by task", "STATISTICS");
            //     exec('kill ' . getmygid());
            // }

            // foreach ($events as $event) {
            //     if (empty($event)) continue;
            //     if (time() - $event['last_run'] > 300) {
            //         $dto = new SendLogDto(random_int(10000, 99999), "INFO", ["message" => "Killing Monitor"]);
            //         self::$send_log->execute($dto);

            //         \Monitor\App\Log\Domain\Log::debug("Killing", "STATISTICS");

            //         exec('kill ' . getmygid());
            //     }
            // }

            \Monitor\App\Log\Domain\Log::debug("FINISH", "STATISTICS");
        } catch (Exception $e) {
            \Monitor\App\Log\Domain\Log::debug($e->getMessage(), "STATISTICS");
        } catch (Throwable $e) {
            \Monitor\App\Log\Domain\Log::debug($e->getMessage(), "STATISTICS");
        }
    }

    public static function firstRun()
    {
        \Monitor\App\Log\Domain\Log::debug("Starting", "STATISTICS");
        $clear = new ClearStatistics;
        $clear->execute();
        self::$api = new Api;
        self::$send_statistics = new SendStatistics(new Client);
        self::$get_worker_stats = new GetWorkersStatistics;
        self::$get_event_stats = new GetEventsStatistics;
        self::$start = time();

        self::$send_log = new SendLog(new Client, new Api); 
        $dto = new SendLogDto(random_int(10000, 99999), "INFO", ['message' => "Starting Worker"]);
        self::$send_log->execute($dto);
        $root_path = Server::getRootDir();
        $path = $root_path . "/pid";
        file_put_contents($path, getmygid());
    }
}
