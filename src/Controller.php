<?php

namespace App;

use App\API;
use App\Connectors\Mssql;
use App\Connectors\Mysql;
use App\Connectors\PostgreSql;
use App\Connectors\Ssh;
use App\Utils;
use Exception;
use React\EventLoop\Loop;
use Sohris\Core\Logger;
use Sohris\Core\Server;
use Sohris\Core\Tools\Worker\Worker;
use Sohris\Core\Utils as CoreUtils;
use Sohris\Event\Annotations\Time;
use Sohris\Event\Annotations\StartRunning;
use Sohris\Event\Event\EventControl;

/**
 * @Time(
 *  type="Interval",
 *  time="60"
 * )
 * @StartRunning
 */
class Controller extends EventControl
{
    private static $key;
    private static $total_tasks = 0;
    private static $total_tests = 0;
    private static $total_dequeue = 0;
    private static $time_task = 0;
    private static $time_process = 0;
    private static $timers = [];
    private static $connectors = [];
    private static $logger;
    private static $task_runned = [];
    private static $hashes = [];


    public static function run()
    {
        self::checkServers();
        self::recreate();

        // self::logger();
    }

    private static function recreate()
    {
        try {

            $keys = array_keys(self::$timers);

            $delete = array_diff($keys, self::$hashes);
            $create = array_diff(self::$hashes, $keys);

            if (!empty($delete)) {
                foreach ($delete as  $hash) {
                    self::$logger->info("Delete $hash");
                    self::$timers[$hash]->stop();
                    unset(self::$timers[$hash]);
                }
            }
            if (!empty($create)) {
                foreach ($create as  $hash) {
                    self::$logger->info("Create $hash");
                    self::$timers[$hash] = new TasksWorker($hash);
                    self::$timers[$hash]->run();
                }
            }
        } catch (Exception $e) {
            self::$logger->info("Controller Error");
            self::$logger->critical("[Error][" . $e->getCode() . "] " . $e->getMessage());
        }
    }

    public static function firstRun()
    {
        if (!self::$key) {
            self::$key = CoreUtils::getConfigFiles('system')['key'];
        }
        self::$logger = new Logger("Controller");
    }

    private static function saveStatistcs()
    {
        $stats = [
            'tasks' => self::$total_tasks,
            'tests' => self::$total_tests,
            'time_process' => round(self::$time_process, 0),
            'time_task' => round(self::$time_task, 0),
            'total' => self::$total_dequeue
        ];
        file_put_contents(Server::getRootDir() . "/stats", json_encode($stats));
    }

    private static function checkServers()
    {
        try {
            $hashs = API::getValidateHash();
            if (empty($hashs)) {
                self::$logger->info("No server configs");
            }
            self::$logger->info("Hashs", $hashs);
            self::$hashes = $hashs;
        } catch (Exception $e) {
            self::$logger->info("Check Server Error");
            self::$logger->critical("[Error][" . $e->getCode() . "] " . $e->getMessage());
        }
        return false;
    }

    private static function logger()
    {
        foreach (self::$task_runned as $server => $tasks) {
            foreach ($tasks as $id => $a) {
                self::$logger->info("Server$server - Task$id => $a");
            }
        }
    }
}
