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
    private static $start;

    public static function run()
    {
        if (!self::checkServers() || empty(self::$timers)) {
            self::recreate();
        }
        self::logger();
    }

    private static function recreate()
    {
        try {
            self::$logger->info("Recreate Timers");
            if (!empty(self::$timers)) {
                self::$logger->info("Clean Timers");
                foreach (self::$timers as $key => $timer) {
                    self::$timers[$key]->kill();
                    unset(self::$timers[$key]);
                }
            }
            self::$logger->info("Getting Servers");
            $servers = Utils::getServers();
            self::$logger->info("Configuring Servers " . implode(" - ",$servers));

            foreach ($servers as $server) {
                $configs = Utils::objectToArray(Utils::getConfigs($server));
                if(!array_key_exists('server_id', $configs) || empty($configs['server_id'])) continue;
                self::$timers[$configs['server_id']] = new Worker;
                self::$timers[$configs['server_id']]->callOnFirst(fn() => self::firstRun());
                self::$timers[$configs['server_id']]->callFunction(fn () => self::logger(),60);
                
                foreach ($configs['tasks'] as $service => $tasks) {
                    foreach ($tasks as $task) {
                        if($task['frequency'] == 0) continue;
                        self::$logger->info("Configuring Server $configs[server_id] - Service $service - ID#$task[task_id] - Frequency $task[frequency]");
                        $task = Utils::objectToArray($task);
                        self::$timers[$configs['server_id']]->callFunction(fn () => self::runTask($configs['server_id'], $configs['customer_id'], $service, $task, $configs['configs']),(int)$task['frequency']);
                    }
                }

                self::$timers[$configs['server_id']]->run();
            }
            self::$start = CoreUtils::microtimeFloat();

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

    private static function lastRun()
    {
        file_put_contents(Server::getRootDir() . "/last_run", time());
    }

    private static function checkServers()
    {
        if (file_exists(Server::getRootDir() . "/validate")) return true;
        try {
            self::$logger->info("Getting new Servers");
            $server = Utils::objectToArray(API::getServersConfigs());

            self::$logger->info("Configuring " . count($server['servers']) . " servers");
            foreach ($server['servers'] as $id => $content) {
                Utils::saveServerConfig($id, (array)$content);
            }
            self::$logger->info("Saving servers...");
            Utils::saveServers(array_keys($server['servers']));
            file_put_contents(Server::getRootDir() . "/validate", $server['valid_hash']);
            self::$logger->info("Servers saved!");
        } catch (Exception $e) {
            self::$logger->info("Check Server Error");
            self::$logger->critical("[Error][" . $e->getCode() . "] " . $e->getMessage());
        }
        return false;
    }

    private static function runTask($server, $customer, $service, $task, $configs)
    {
        try {
            self::$logger->info("Running Task $task[task_id] $server - $service ");
            self::$total_tasks++;
            $process_start = CoreUtils::microtimeFloat();
            if (!array_key_exists($server, self::$connectors))
                self::$connectors[$server] = [
                    'mysql' => null,
                    'postgresql' => null,
                    'ssh' => null,
                    'mssql' => null
                ];

            $config = $service == 'business' ?  $configs[$task['ref_service']] : $configs[$service];

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


            $content = self::$connectors[$server][$task['type']]->getContent();
            $pre_process_tasks['captures'] = array_merge($pre_process_tasks['captures'], $content['captures']);
            $pre_process_tasks['timers'] = array_merge($pre_process_tasks['timers'], $content['timers']);
            $pre_process_tasks['logs'] = array_merge($pre_process_tasks['logs'], $content['logs']);
            self::$connectors[$server][$task['type']]->clearContent();

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
            if(!array_key_exists($server, self::$task_runned))
            {
                self::$task_runned[$server] = [];
            }
            if(!array_key_exists($task['task_id'], self::$task_runned[$server]))
            {
                self::$task_runned[$server][$task['task_id']] = 0;
            }
            self::$task_runned[$server][$task['task_id']]++;
            //self::$logger->info("Task Runned $task[task_id] $server - $service " .round(($process_end - $process_start), 3));

        } catch (Exception $e) {
            self::$logger->info("Error Task $task[task_id] $server - $service");
            self::$logger->critical("[Error][" . $e->getCode() . "] " . $e->getMessage());
        }
    }
    private static function logger()
    {
        foreach(self::$task_runned as $server => $tasks)
        {
            foreach($tasks as $id => $a)
            {
                self::$logger->info("Server$server - Task$id => $a");
            }
        }
    }
}
