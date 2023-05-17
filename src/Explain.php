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
 *  time="30"
 * )
 * @StartRunning
 */
class Explain extends EventControl
{
    private static $key;

    public static function run()
    {
        try {
            if (!($tasks = API::getExplain())) {
                return;
            }
            if (empty($tasks)) {
                return;
            }
            $result = [];
            $connector = null;
            $config = $tasks['config'];
            switch ($tasks['connection_type']) {
                case 'mysql':
                    $connector = new Mysql((array)$config);
                    break;
                case 'postgresql':
                    $connector = new PostgreSql((array)$config);
                    break;
                case 'mssql':
                    $connector = new Mssql((array)$config);
                    break;
            }

            $result['status'] = 'success';

            if (!$connector) {
                $result['status'] = 'failure';
                $result['log'] = "SERVICE_IS_NOT_ENABLED";
            } else {
                if (!$connector->openConnection()) {
                    $result['status'] = 'failure';
                    $result['log'] = array_pop($connector->getContent()['logs']);
                } elseif (!empty($tasks['command'])) {
                    $connector->explain($tasks);
                    $result['result'] = self::utf8ize($connector->getContent());
                    $result['result']['time'] = time();
                    $result['result']['query_id'] = $tasks['query_id'];
                }
                $connector->clearContent();
            }
            $connector = null;
            API::sendExResults($result);
            unset($result);
        } catch (Exception $e) {
            var_dump($e->getMessage());
        }
    }

    private static function utf8ize($mixed)
    {
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
