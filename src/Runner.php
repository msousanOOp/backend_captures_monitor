<?php

namespace App;

use App\API;
use App\Connectors\Mysql;
use App\Connectors\Ssh;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Sohris\Core\Utils;
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


    public static function run()
    {
        try {
            self::firstRun();
            if (!($tasks = API::getNextTasks()) || empty($tasks['tasks'])) {
                return;
            }

            $result = [
                'type' => $tasks['type'],
                'result' => []
            ];
            $ids = [];
            switch ($tasks['type']) {
                case "task":
                    $server_config = self::getConfigs($tasks['server_id'], $tasks['service_code']);
                    $connectors = [
                        'mysql' => null,
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
                    break;
                case "test_connection":
                    $server_config = self::getConfigs($tasks['server_id'], $tasks['service_code']);
                    $connector = null;
                    switch ($tasks['connection']) {
                        case 'mysql':
                            $connector = new Mysql((array)$server_config);
                            break;
                        case 'ssh':
                            $connector = new Ssh((array)$server_config);
                            break;
                        case 'odbc':
                            break;
                    }
                    $connector->openConnection();
                    $connection = $connector->getContent()['logs'];
                    $result['results']['connection'] = !empty($connection);
                    $result['results']['errors'] = $connection;
                    break;
            }

            API::sendResults($result);
        } catch (Exception $e) {
            var_dump($e->getMessage());
        }
    }

    private static function firstRun()
    {
        if (!self::$key) {
            self::$key = Utils::getConfigFiles('system')['key'];
        }
    }

    private static function getConfigs($server, $service): array
    {
        $key = sha1($server . "_" . $service);
        $path = __DIR__ . "/../storage/cache/";
        if (file_exists($path . $key)) {

            $content = file_get_contents($path . $key);
            $decoder = JWT::decode($content, new Key(self::$key, "HS256"));
            return (array) $decoder;
        }

        $external = (array)API::getServerConfig($server, $service);
        Utils::checkFolder($path, 'create');
        file_put_contents($path . $key, JWT::encode($external, self::$key, "HS256"));

        return (array) $external;
    }
}
