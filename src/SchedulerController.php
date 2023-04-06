<?php

namespace App;

use App\API;
use Exception;
use Sohris\Core\Logger;
use Sohris\Core\Server;
use Sohris\Core\Utils as CoreUtils;
use Sohris\Event\Annotations\Time;
use Sohris\Event\Annotations\StartRunning;
use Sohris\Event\Event\EventControl;

/**
 * @Time(
 *  type="Interval",
 *  time="60"
 * )
 * @StartRunning
 */
class Controller extends EventControl
{
    private static $key;
    private static $timers = [];
    private static $logger;
    private static $task_runned = [];
    private static $hashes = [];


    public static function run()
    {
        self::checkServers();
        self::recreate();

    }

    private static function recreate()
    {
        try {

            $keys = array_keys(self::$timers);

            $delete = array_diff($keys, self::$hashes);
            $create = array_diff(self::$hashes, $keys);

            if (!empty($delete)) {
                foreach ($delete as  $hash) {
                    self::$logger->info("Delete $hash");
                    self::$timers[$hash]->stop();
                    unset(self::$timers[$hash]);
                }
            }
            if (!empty($create)) {
                foreach ($create as  $hash) {
                    self::$logger->info("Create $hash");
                    self::$timers[$hash] = new SchedulerWorker($hash);
                    self::$timers[$hash]->run();
                }
            }
            
        } catch (Exception $e) {
            self::$logger->info("Controller Error");
            self::$logger->critical("[Error][" . $e->getCode() . "] " . $e->getMessage());
        }
    }

    public static function firstRun()
    {
        if (!self::$key) {
            self::$key = CoreUtils::getConfigFiles('system')['key'];
        }
        self::$logger = new Logger("Controller");
    }


    private static function checkServers()
    {
        try {
            $hashs = API::getValidateHashScheduler();
            if (empty($hashs)) {
                self::$logger->info("No server configs");
                return;
            }
            
            self::$logger->info("Hashs", $hashs);
            self::$hashes = $hashs;
        } catch (Exception $e) {
            self::$logger->info("Check Server Error");
            self::$logger->critical("[Error][" . $e->getCode() . "] " . $e->getMessage());
        }
        return false;
    }

    private static function logger()
    {
        foreach (self::$task_runned as $server => $tasks) {
            foreach ($tasks as $id => $a) {
                self::$logger->info("Server$server - Task$id => $a");
            }
        }
    }
}
