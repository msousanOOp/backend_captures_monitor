<?php

namespace App;

use App\API;
use App\Connectors\Mysql;
use App\Connectors\PostgreSql;
use App\Connectors\Ssh;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Sohris\Core\Server;
use Sohris\Core\Utils as CoreUtils;
use Sohris\Event\Annotations\Time;
use Sohris\Event\Event\AbstractEvent;


/**
 * @Time(
 *  type="Interval",
 *  time="1"
 * )
 */
class Runner extends AbstractEvent
{
    private static $key;
    private static $total_tasks = 0;
    private static $total_tests = 0;
    private static $total_dequeue = 0;
    private static $time_task = 0;
    private static $time_process = 0;

    public static function run()
    {
        try {
            self::firstRun();
            $task_start = CoreUtils::microtimeFloat();
            if (!($tasks = API::getNextTasks())) {
                return;
            }
            $task_end = CoreUtils::microtimeFloat();
            self::$total_dequeue++;
            self::$time_task += ($task_end - $task_start);

            $process_start = CoreUtils::microtimeFloat();
            $result = [
                'type' => $tasks['type'],
                'result' => []
            ];
            $ids = [];
            switch ($tasks['type']) {
                case "task":
                    $server_config = Utils::getConfigs($tasks['server_id'], $tasks['service_code']);
                    $connectors = [
                        'mysql' => null,
                        'postgresql' => null,
                        'ssh' => null,
                        'odbc' => null
                    ];
                    foreach ($tasks['tasks'] as $task) {
                        $task = (array) $task;
                        $ids[] = $task['task_id'];
                        switch ($task['connection']) {
                            case 'mysql':
                                if (!$connectors['mysql'])
                                    $connectors['mysql'] = new Mysql((array)$server_config);
                                if (!$connectors['mysql']->openConnection())
                                    break;
                                $connectors['mysql']->process($task);
                                break;
                            case 'postgresql':
                                if (!$connectors['postgresql'])
                                    $connectors['postgresql'] = new PostgreSql((array)$server_config);
                                if (!$connectors['postgresql']->openConnection())
                                    break;
                                $connectors['postgresql']->process($task);
                                break;
                            case 'ssh':
                                if (!$connectors['ssh'])
                                    $connectors['ssh'] = new Ssh((array)$server_config);
                                if (!$connectors['ssh']->openConnection())
                                    break;
                                $connectors['ssh']->process($task);
                                break;
                            case 'odbc':
                                break;
                        }
                    }


                    $pre_process_tasks = [
                        "captures" => [],
                        "timers" => [],
                        "logs" => []
                    ];
                    foreach ($connectors as $connector) {
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
                        "tasks_id" => $ids,
                        "customer_id" => $tasks['customer_id'],
                        "server_id" => $tasks['server_id'],
                        "handshake_id" => $tasks['handshake_id'],
                        "service" => $tasks['service_code'],
                        "captures" => $pre_process_tasks['captures'],
                        "timers" => $pre_process_tasks['timers'],
                        "logs" => $pre_process_tasks['logs'],
                    ];
                case "test_connection":
                    $connector = null;
                    switch ($tasks['connection']) {
                        case 'mysql':
                            $connector = new Mysql((array)$tasks);
                            break;
                        case 'postgresql':
                            $connector = new PostgreSql((array)$tasks);
                            break;
                        case 'ssh':
                            $connector = new Ssh((array)$tasks);
                            break;
                        case 'odbc':
                            break;
                    }
                    $result['result']['status'] = 'success';
                    $result['result']['hash'] = $tasks['hash'];
                    if (!$connector) {
                        $result['result']['status'] = 'failure';
                        $result['result']['log'] = "SERVICE_IS_NOT_ENABLED";

                        if (!$connector->openConnection()) {
                            $result['result']['status'] = 'failure';
                            $result['result']['log'] = array_pop($connector->getContent()['logs']);
                        } elseif (!empty($tasks['tasks'])) {
                            foreach ($tasks['tasks'] as $task) {
                                $connector->process(["task_id" => time(), "command" => $task]);
                            }
                            $result['result']['results'] = $connector->getContent();
                        }
                        break;
                    }
            }
            API::sendResults($result);
            unset($result);
            $process_end = CoreUtils::microtimeFloat();
            self::$time_process += ($process_end - $process_start);
            self::saveStatistcs();
        } catch (Exception $e) {
            var_dump($e->getMessage());
        }
    }

    private static function firstRun()
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
            'time_process' => round(self::$time_process / 1000, 0),
            'time_task' => round(self::$time_task / 1000, 0),
            'total' => self::$total_dequeue
        ];
        file_put_contents(Server::getRootDir() . "/stats", json_encode($stats));
    }
}
