<?php

namespace App\Connectors;


use Doctrine\DBAL\DriverManager;

class Mssql extends \App\Connector
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $connector;
    private $limit = 0;

    public function __construct($config = [])
    {
        $this->connector_name = 'mssql';
        parent::__construct($config);
    }


    public function openConnection(): bool
    {
        if ($this->connector) return true;

        if (!$this->invalidate_op) {
            list("db_host_ip" => $host, "db_host_port" => $port, "db_user" => $user, "db_password" => $pass) = $this->configs;
            $this->startTime("connection_time_mssql");
            try {

                $connectionParams = [
                    'dbname' => '',
                    'user' => $user,
                    'password' => $pass,
                    'host' => $host,
                    'port' => $port,
                    'driver' => 'pdo_sqlsrv',
                    'driverOptions' => array(
                        \PDO::ATTR_TIMEOUT => 5,
                        "TrustServerCertificate" => true
                    )
                ];
                $this->connector = DriverManager::getConnection($connectionParams);
                $this->finishTime("connection_time_mssql");

                return true;
            } catch (\Exception $e) {
                $this->invalidate_op = true;
                $this->log("Connection - Sql Server", "Error", $e->getCode(), $e->getMessage());
                return false;
            }
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
}
