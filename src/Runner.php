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
    private static $log;
    private static $key;
    private static $tasks;

    public static function run()
    {
        try {
            self::firstRun();
            if (!(self::$tasks = API::getNextTasks())) {
                return;
            }

            $result = [
                'type' => self::$tasks['type'],
                'result' => []
            ];
            $ids = [];
            switch (self::$tasks['type']) {
                case "task":
                    $server_config = Utils::getConfigs(self::$tasks['server_id'], self::$tasks['service_code']);
                    $connectors = [
                        'mysql' => null,
                        'postgresql' => null,
                        'ssh' => null,
                        'odbc' => null
                    ];
                    foreach (self::$tasks['tasks'] as $task) {
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
                        "customer_id" => self::$tasks['customer_id'],
                        "server_id" => self::$tasks['server_id'],
                        "handshake_id" => self::$tasks['handshake_id'],
                        "service" => self::$tasks['service_code'],
                        "captures" => $pre_process_tasks['captures'],
                        "timers" => $pre_process_tasks['timers'],
                        "logs" => $pre_process_tasks['logs'],
                    ];
                    break;
                case "test_connection":
                    $connector = null;
                    switch (self::$tasks['connection']) {
                        case 'mysql':
                            $connector = new Mysql((array)self::$tasks);
                            break;
                        case 'postgresql':
                            $connector = new Mysql((array)self::$tasks);
                            break;
                        case 'ssh':
                            $connector = new Ssh((array)self::$tasks);
                            break;
                        case 'odbc':
                            break;
                    }
                    $result['result']['status'] = 'success';
                    $result['result']['hash'] = self::$tasks['hash'];
                    if (!$connector) {
                        $result['result']['status'] = 'failure';
                        $result['result']['log'] = "SERVICE_IS_NOT_ENABLED";
                    }

                    if (!$connector->openConnection()) {
                        $result['result']['status'] = 'failure';
                        $result['result']['log'] = array_pop($connector->getContent()['logs']);
                        break;
                    }
                    if (!empty(self::$tasks['tasks'])) {
                        foreach (self::$tasks['tasks'] as $task) {
                            $connector->process(["task_id" => time(), "command" => $task]);
                        }
                        $result['result']['results'] = $connector->getContent();
                    }
                    break;
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
