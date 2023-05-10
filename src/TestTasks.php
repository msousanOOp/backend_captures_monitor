<?php

namespace App;

use App\API;
use App\Connectors\Mssql;
use App\Connectors\Mysql;
use App\Connectors\PostgreSql;
use App\Connectors\Ssh;
use Exception;
use Sohris\Core\Utils as CoreUtils;
use Sohris\Event\Annotations\Time;
use Sohris\Event\Annotations\StartRunning;
use Sohris\Event\Event\EventControl;

/**
 * @Time(
 *  type="Interval",
 *  time="5"
 * )
 * @StartRunning
 */
class TestTasks extends EventControl
{
    private static $key;

    public static function run()
    {
        try {
            if (!($tasks = API::getTests())) {
                return;
            }
            if (empty($tasks)) {
                return;
            }
            $result = [
                'type' => $tasks['type'],
                'result' => []
            ];
            $connector = null;
            switch ($tasks['connection']) {
                case 'mysql':
                    $connector = new Mysql((array)$tasks);
                    break;
                case 'postgresql':
                    $connector = new PostgreSql((array)$tasks);
                    break;
                case 'mssql':
                    $connector = new Mssql((array)$tasks);
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
            } else {
                if (!$connector->openConnection()) {
                    $result['result']['status'] = 'failure';
                    $result['result']['log'] = array_pop($connector->getContent()['logs']);
                } elseif (!empty($tasks['tasks'])) {
                    foreach ($tasks['tasks'] as $task) {
                        $connector->process(["task_id" => sha1($task), "command" => $task]);
                    }
                    $result['result']['results'] = self::utf8ize($connector->getContent());
                }
                $connector->clearContent();
            }

            API::sendTestResults($result);
            unset($result);
        } catch (Exception $e) {
            var_dump($e->getMessage());
        }
    }

    private static function utf8ize( $mixed ) {
        if (is_array($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed[$key] = self::utf8ize($value);
            }
        } elseif (is_string($mixed)) {
            return mb_convert_encoding($mixed, "UTF-8", "UTF-8");
        }
        return $mixed;
    }

    public static function firstRun()
    {
        if (!self::$key) {
            self::$key = CoreUtils::getConfigFiles('system')['key'];
        }
    }
}
