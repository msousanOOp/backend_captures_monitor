<?php

namespace App\Connectors;

use PDO;
use Sohris\Core\Utils;

class Mysql extends \App\Connector
{
    /**
     * @var \PDO
     */
    private $connector;
    
    public function __construct($config)
    {
        $this->connector_name = 'mysql';
        parent::__construct($config);
        
    }
    public function openConnection(): bool
    {   
        if($this->connector) return true;

        if (!$this->invalidate_op) {
            list("db_host_ip" => $host, "db_host_port" => $port, "db_user" => $user, "db_password" => $pass) = $this->configs;
            $this->startTime("connection_time_mysql");
            try {
                $pdo = new \PDO("mysql:host=$host;port=$port;dbname=mysql", $user, $pass, array(
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ));
                $this->connector = $pdo;
                $this->finishTime("connection_time_mysql");
                return true;
            } catch (\PDOException $e) {
                $this->invalidate_op = true;
                $this->log("Connection - MYSQL", "Error", $e->getCode(), $e->getMessage());
                return false;
            }
        }
        return false;
    }
    
    public function isConnected() :bool
    {
        if($this->connector)
            return true;
        return false;
    }

    public function closeConnection(): void
    {
        if ($this->connector) {
            $this->connector = null;
        }
    }

    public function process($task)
    {
        $this->startTime("task_".$task['task_id']);
        try {
            $stm = $this->connector->query($task['command']);
            $this->finishTime("task_".$task['task_id']);
        } catch (\PDOException $e) {
            $this->log($task['task_id'], "Error", $e->getCode(), $e->getMessage());
            return false;
        }
        $this->addCapture("task_".$task['task_id'], $stm->fetchAll(PDO::FETCH_NUM));
        return true;
    }
}
