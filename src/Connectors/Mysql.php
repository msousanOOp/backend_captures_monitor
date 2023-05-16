<?php

namespace App\Connectors;


use Doctrine\DBAL\DriverManager;

class Mysql extends \App\Connector
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $connector;
    private $limit = 0;

    public function __construct($config = [])
    {
        $this->connector_name = 'mysql';
        parent::__construct($config);
    }
    public function openConnection(): bool
    {
        if ($this->connector && $this->connector->isConnected()) return true;

        list("db_host_ip" => $host, "db_host_port" => $port, "db_user" => $user, "db_password" => $pass) = $this->configs;
        $this->startTime("connection_time_mysql");
        try {
            $connectionParams = [
                'dbname' => 'mysql',
                'user' => $user,
                'password' => $pass,
                'host' => $host,
                'port' => $port,
                'driver' => 'pdo_mysql',
                'driverOptions' => array(
                    \PDO::ATTR_TIMEOUT => 5
                )
            ];
            $this->connector = DriverManager::getConnection($connectionParams);
            $this->finishTime("connection_time_mysql");
            return true;
        } catch (\Exception $e) {
            $this->invalidate_op = true;
            $this->log("Connection - MYSQL", "Error", $e->getCode(), $e->getMessage());
        }
        return false;
    }

    public function setLimit(int $limit)
    {
        $this->limit = $limit;
    }

    public function clearLimit()
    {
        $this->limit = 0;
    }

    public function isConnected(): bool
    {
        if ($this->connector) {
            if ($this->connector->isConnected()) return true;
        }

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
        $this->startTime("task_" . $task['task_id']);
        try {

            $stm = $this->connector->prepare($task['command']);
            $result = $stm->executeQuery();
            $this->finishTime("task_" . $task['task_id']);
            $data = [];
            if ($this->limit > 0) {
                for ($i = 0; $i < $this->limit; $i++) {
                    if (!$row = $result->fetchAssociative())
                        break;
                    $data[] = $row;
                }
            } else {
                $data = $result->fetchAllAssociative();
            }
            $this->addCapture("task_" . $task['task_id'], $data);

            return true;
        } catch (\Exception $e) {
            $this->log($task['task_id'], "Error", $e->getCode(), $e->getMessage());
            return false;
        }
    }
    public function explain($task)
    {
        $this->startTime("explain");
        try {
            $stm = $this->connector->prepare("EXPLAIN " . $task['command']);
            $result = $stm->executeQuery();
            $this->finishTime("explain");
            $this->addCapture("explain", $result);
            return true;
        } catch (\Exception $e) {
            $this->log("explain", "Error", $e->getCode(), $e->getMessage());
            return false;
        }
    }
}
