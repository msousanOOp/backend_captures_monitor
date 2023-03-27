<?php

namespace App;

use App\API;
use App\Connectors\Mssql;
use App\Connectors\Mysql;
use App\Connectors\PostgreSql;
use App\Connectors\Ssh;
use Exception;
use Monolog\Logger;
use Sohris\Core\Server;
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
class Validate extends EventControl
{
    private static $logger;
    public static function run()
    {


        if (!file_exists(Server::getRootDir() . "/validate")) return;
        try {
            self::$logger->info("Validate Servers");

            $hash = file_get_contents(Server::getRootDir() . "/validate");
            $server_hash = API::getValidateHash();

            if ($hash != $server_hash[0]) {
                self::$logger->info("Invalid Servers!");

                unlink(Server::getRootDir() . "/validate");
            } else {
                self::$logger->info("Servers valid!");
            }
        } catch (Exception $e) {
            self::$logger->info("Error Validation");
            self::$logger->critical("[Error][" . $e->getCode() . "] " . $e->getMessage());
        }
    }

    public static function firstRun()
    {
        self::$logger = new Logger("Controller");
    }
}
