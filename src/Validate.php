<?php

namespace App;

use App\API;
use App\Connectors\Mssql;
use App\Connectors\Mysql;
use App\Connectors\PostgreSql;
use App\Connectors\Ssh;
use Exception;
use Sohris\Core\Server;
use Sohris\Core\Utils as CoreUtils;
use Sohris\Event\Annotations\Time;
use Sohris\Event\Event\EventControl;

/**
 * @Time(
 *  type="Interval",
 *  time="60"
 * )
 */
class Validate extends EventControl
{
    public static function run()
    {

        if(!file_exists(Server::getRootDir() . "/validate")) return;

        $hash = file_get_contents(Server::getRootDir() . "/validate");

        $server_hash = API::getValidateHash();

        if($hash != $server_hash) unlink(Server::getRootDir() . "/validate");
    }

    public static function firstRun()
    {
        
    }
   
}
