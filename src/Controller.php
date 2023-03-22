<?php

namespace App;

use App\API;
use App\Connectors\Mssql;
use App\Connectors\Mysql;
use App\Connectors\PostgreSql;
use App\Connectors\Ssh;
use Exception;
use React\EventLoop\Loop;
use Sohris\Core\Server;
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

    public static function run()
    {

        if (!self::checkServers() || empty(self::$timers)) {
            self::recreate();
        }
    }

    private static function recreate(){
        foreach (self::$timers as $timer) {
            Loop::cancelTimer($timer);
        }

        $servers = Utils::getServers();
        foreach ($servers as $server) {
            $configs = Utils::objectToArray(Utils::getConfigs($server));
            foreach ($configs['tasks'] as $service => $tasks) {
                foreach ($tasks as $task) {
                    $task = Utils::objectToArray($task);
                    //echo "Configuring  $configs[server_id] $service $task[frequency] - $task[task_id] " . PHP_EOL;
                    self::$timers[] = Loop::addPeriodicTimer((int)$task['frequency'], fn () => self::runTask($configs['server_id'], $configs['customer_id'], $service, $task, $configs['configs']));
                }
            }
        }
    }

    public static function firstRun()
    {
        if (!self::$key) {
            self::$key = CoreUtils::getConfigFiles('system')['key'];
        }
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

    private static function lastRun()
    {
        file_put_contents(Server::getRootDir() . "/last_run", time());
    }

    private static function checkServers()
    {
        if (file_exists(Server::getRootDir() . "/validate")) return true;
        $server = Utils::objectToArray(API::getServersConfigs());
        foreach ($server['servers'] as $id => $content) {
            Utils::saveServerConfig($id, (array)$content);
        }

        Utils::saveServers(array_keys($server['servers']));
        file_put_contents(Server::getRootDir() . "/validate", $server['valid_hash']);
        return false;
    }

    private static function runTask($server, $customer, $service, $task, $configs)
    {
        self::$total_tasks++;
        $process_start = CoreUtils::microtimeFloat();
        if (!array_key_exists($server, self::$connectors))
            self::$connectors[$server] = [
                'mysql' => null,
                'postgresql' => null,
                'ssh' => null,
                'mssql' => null
            ];
        $config = $configs[$service];

        $result = [
            'type' => $task['type'],
            'result' => []
        ];
        switch ($task['type']) {
            case 'mysql':
                if (!self::$connectors[$server]['mysql'])
                    self::$connectors[$server]['mysql'] = new Mysql((array)$config);
                if (!self::$connectors[$server]['mysql']->openConnection())
                    break;
                self::$connectors[$server]['mysql']->process($task);
                break;
            case 'mssql':
                if (!self::$connectors[$server]['mssql'])
                    self::$connectors[$server]['mssql'] = new Mssql((array)$config);
                if (!self::$connectors[$server]['mssql']->openConnection())
                    break;
                self::$connectors[$server]['mssql']->process($task);
                break;
            case 'postgresql':
                if (!self::$connectors[$server]['postgresql'])
                    self::$connectors[$server]['postgresql'] = new PostgreSql((array)$config);
                if (!self::$connectors[$server]['postgresql']->openConnection())
                    break;
                self::$connectors[$server]['postgresql']->process($task);
                break;
            case 'ssh':
                if (!self::$connectors[$server]['ssh'])
                    self::$connectors[$server]['ssh'] = new Ssh((array)$config);
                if (!self::$connectors[$server]['ssh']->openConnection())
                    break;
                self::$connectors[$server]['ssh']->process($task);
                break;
        }

        $pre_process_tasks = [
            "captures" => [],
            "timers" => [],
            "logs" => []
        ];

        foreach (self::$connectors[$server] as $connector) {
            if ($connector) {
                $content = $connector->getContent();
                $pre_process_tasks['captures'] = array_merge($pre_process_tasks['captures'], $content['captures']);
                $pre_process_tasks['timers'] = array_merge($pre_process_tasks['timers'], $content['timers']);
                $pre_process_tasks['logs'] = array_merge($pre_process_tasks['logs'], $content['logs']);
                $connector->closeConnection();
            }
        };
        $result['result'] = [
            "timestamp" => time(),
            "tasks_id" => [$task['task_id']],
            "customer_id" => $customer,
            "server_id" => $server,
            "service" => $service,
            "captures" => $pre_process_tasks['captures'],
            "timers" => $pre_process_tasks['timers'],
            "logs" => $pre_process_tasks['logs'],
        ];
        API::sendResults($result);
        unset($result);
        $process_end = CoreUtils::microtimeFloat();
        self::$time_process += ($process_end - $process_start);
        self::saveStatistcs();
    }
}
