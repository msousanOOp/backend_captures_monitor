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
 *  time="60"
 * )
 */
class HealthCheck extends AbstractEvent
{
    private static $key;
    

    public static function run()
    {

        $base = json_decode(file_get_contents(Server::getRootDir() . "/stats"),true);
        $base['uptime'] = round(CoreUtils::microtimeFloat() / 1000,0);

        API::sendStats($base);
    }

}
