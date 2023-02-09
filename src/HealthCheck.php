<?php

namespace App;

use App\API;
use Sohris\Core\Server;
use Sohris\Core\Utils as CoreUtils;
use Sohris\Event\Annotations\Time;
use Sohris\Event\Event\AbstractEvent;


/**
 * @Time(
 *  type="Interval",
 *  time="60"
 * )
 * 
 */
class HealthCheck extends AbstractEvent
{

    static $time = 0;
    public static function run()
    {
        if(self::$time == 0) self::$time = time();

        $base = json_decode(file_get_contents(Server::getRootDir() . "/stats"),true);

        $base['uptime'] = time() - self::$time;

        API::sendStats($base);
    }


}
