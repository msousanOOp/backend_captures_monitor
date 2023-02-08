<?php

namespace App;

use App\API;
use App\Connectors\Mysql;
use App\Connectors\PostgreSql;
use App\Connectors\Ssh;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
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

    public static function run()
    {
        try {
            self::firstRun();
            if (!($tasks = API::getNextTasks())) {
                return;
            }

            $result = [
                'type' => $tasks['type'],
                'result' => []
            ];
            $ids = [];
            if ($tasks['type'] == 'task') {
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
                    if ($task['connection'] == 'mysql') {
                        if (!$connectors['mysql'])
                            $connectors['mysql'] = new Mysql((array)$server_config);
                        if (!$connectors['mysql']->openConnection())
                            continue;
                        $connectors['mysql']->process($task);
                    } elseif ($task['connection'] == 'postgresql') {
                        if (!$connectors['postgresql'])
                            $connectors['postgresql'] = new PostgreSql((array)$server_config);
                        if (!$connectors['postgresql']->openConnection())
                            continue;
                        $connectors['postgresql']->process($task);
                    } elseif ($task['connection'] == 'ssh') {
                        if (!$connectors['ssh'])
                            $connectors['ssh'] = new Ssh((array)$server_config);
                        if (!$connectors['ssh']->openConnection())
                            continue;
                        $connectors['ssh']->process($task);
                    } elseif ($task['connection'] == 'odbc') {
                        continue;
                    } else {
                        continue;
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
            } elseif ($tasks['type'] == "test_connection") {
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
                }

                if (!$connector->openConnection()) {
                    $result['result']['status'] = 'failure';
                    $result['result']['log'] = array_pop($connector->getContent()['logs']);
                } elseif (!empty($tasks['tasks'])) {
                    foreach ($tasks['tasks'] as $task) {
                        $connector->process(["task_id" => time(), "command" => $task]);
                    }
                    $result['result']['results'] = $connector->getContent();
                }
            }
            unset($connector);
            API::sendResults($result);
            unset($result);
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
}
