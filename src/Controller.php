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
        if(self::checkServers()) return;

        foreach(self::$timers as $timer)
        {
            Loop::cancelTimer($timer);
        }

        $servers = Utils::getServers();

        foreach($servers as $server)
        {
            $configs = Utils::getConfigs($server);
            foreach($configs['tasks'] as $task)
            {
                self::$timers[] = Loop::addPeriodicTimer((int)$task['frequency'],self::runTask($configs['server_id'],$configs['customer_id'], $task, $configs['configs']));
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
        file_put_contents(Server::getRootDir(). "/last_run", time());
    }

    private static function checkServers()
    {
        if(file_exists(Server::getRootDir() . "/validate")) return true;

         $server = API::getServersConfigs();
         foreach($server['servers'] as $id => $content)
         {
            Utils::saveServerConfig($id, $content);
         }

         Utils::saveServers(array_keys($server['servers']));

         file_put_contents(Server::getRootDir() . "/validate", $server['valid_hash']);
        return false;

    }

    private static function runTask($server, $customer, $task, $configs)
    {
        self::$total_tasks++;
        $process_start = CoreUtils::microtimeFloat();
        if(!array_key_exists($server, self::$connectors))
            self::$connectors = [
                'mysql' => null,
                'postgresql' => null,
                'ssh' => null,
                'mssql' => null
            ];
        $config = $configs[$task['service']];
        
        $result = [
            'type' => $task['type'],
            'result' => []
        ];
        switch ($task['type']) {
            case 'mysql':
                if (!self::$connectors['mysql'])
                    self::$connectors['mysql'] = new Mysql((array)$config);
                if (!self::$connectors['mysql']->openConnection())
                    break;
                self::$connectors['mysql']->process($task);
                break;
            case 'mssql':
                if (!self::$connectors['mssql'])
                    self::$connectors['mssql'] = new Mssql((array)$config);
                if (!self::$connectors['mssql']->openConnection())
                    break;
                self::$connectors['mssql']->process($task);
                break;
            case 'postgresql':
                if (!self::$connectors['postgresql'])
                    self::$connectors['postgresql'] = new PostgreSql((array)$config);
                if (!self::$connectors['postgresql']->openConnection())
                    break;
                self::$connectors['postgresql']->process($task);
                break;
            case 'ssh':
                if (!self::$connectors['ssh'])
                    self::$connectors['ssh'] = new Ssh((array)$config);
                if (!self::$connectors['ssh']->openConnection())
                    break;
                self::$connectors['ssh']->process($task);
                break;
        }

        $pre_process_tasks = [
            "captures" => [],
            "timers" => [],
            "logs" => []
        ];

        foreach (self::$connectors as $connector) {
            if ($connector) {
                $content = $connector->getContent();
                $pre_process_tasks['captures'] = array_merge($pre_process_tasks['captures'], $content['captures']);
                $pre_process_tasks['timers'] = array_merge($pre_process_tasks['timers'], $content['timers']);
                $pre_process_tasks['logs'] = array_merge($pre_process_tasks['logs'], $content['logs']);
                $connector->closeConnection();
            }
        };
        $result['result'] = [
            "version" => "2",
            "timestamp" => time(),
            "tasks_id" => [$task['task_id']],
            "customer_id" => $customer,
            "server_id" => $server,
            "handshake_id" => "",
            "service" => $task['service'],
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
