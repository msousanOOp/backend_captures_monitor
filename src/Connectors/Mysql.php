<?php

namespace App\Connectors;

use PDO;
use Sohris\Core\Utils;

class Mysql extends \App\Connector
{
    /**
     * @var \PDO
     */
    static $connector;
    
    public function __construct()
    {
        $this->connector_name = 'mysql';
        
    }
    public function openConnection(): bool
    {   
        
        if (!self::$connector) {
            list("db_host_ip" => $host, "db_host_port" => $port, "db_user" => $user, "db_password" => $pass) = $this->configs;
            $this->startTime("connection_time_mysql");
            try {
                $pdo = new \PDO("mysql:host=$host;port=$port;dbname=mysql", $user, $pass, array(
                    PDO::ATTR_TIMEOUT => 15,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ));
                self::$connector = $pdo;
                $this->finishTime("connection_time_mysql");
                return true;
            } catch (\PDOException $e) {
                $this->log("Connection - MYSQL", "Error", $e->getCode(), str_replace("\\","",$e->getMessage()));
                return false;
            }
        }
        return true;
    }

    public function closeConnection(): void
    {
        if (self::$connector) {
            self::$connector = null;
        }
    }

    public function process($task)
    {
        $this->startTime("task_".$task['task_id']);
        try {
            $stm = self::$connector->query($task['command']);
            $this->finishTime("task_".$task['task_id']);
        } catch (\PDOException $e) {
            $this->log($task['task_id'], "Error", $e->getCode(), $e->getMessage());
            return false;
        }
        $this->addCapture("task_".$task['task_id'], $stm->fetchAll(PDO::FETCH_NUM));
        return true;
    }
}
