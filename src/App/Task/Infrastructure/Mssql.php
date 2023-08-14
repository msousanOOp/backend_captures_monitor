<?php

namespace Monitor\App\Task\Infrastructure;

use Monitor\App\TaskResult\Domain\TaskResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Exception;
use Monitor\App\Task\Domain\Collector;
use Monitor\App\Task\Domain\Exceptions\CantConnect;

class Mssql extends Collector
{

    const CONNECTOR_NAME = "mssql";

    private string $host;
    private string $user;
    private string $password;
    private string $port;

    private Connection $connection;

    public function setConfig(array $config): void
    {
        list("db_host_ip" => $host, "db_host_port" => $port, "db_user" => $user, "db_password" => $pass) = $config;

        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $pass;

        $this->setHash(sha1(json_encode($config) . self::CONNECTOR_NAME));
    }

    public function connect(): void
    {

        if ($this->hasConnection()) {
            $this->connection = $this->getConnection();
            $this->isConnected();
            return;
        }

        try {
            $connectionParams = [
                'dbname' => '',
                'user' => $this->user,
                'password' => $this->password,
                'host' => $this->host,
                'port' => $this->port,
                'driver' => 'pdo_sqlsrv',
                'driverOptions' => array(
                    \PDO::ATTR_TIMEOUT => 5,
                    "TrustServerCertificate" => true
                )
            ];
            $this->connection = DriverManager::getConnection($connectionParams);
            $this->setConnection($this->connection);
        } catch (Exception $e) {
            $this->invalidate();
            throw new CantConnect(self::CONNECTOR_NAME, 1000);
        }
        return;
    }

    public function isConnected(): void
    {
        if (!$this->connection->isConnected()) {
            $this->invalidate();
            $this->connect();
        }
    }

    public function invalidate(): void
    {
        $this->deleteConnection();
    }

    public function run(string $task_id, string $command): TaskResult
    {
        $task_result = new TaskResult($task_id, self::CONNECTOR_NAME);
        try {

            $task_result->startTimer("connection_time_" . self::CONNECTOR_NAME);
            $this->connect();
            $task_result->finishTimer("connection_time_" . self::CONNECTOR_NAME);

            $stm = $this->connection->prepare($command);

            $task_result->startTimer("task_$task_id");
            $result = $stm->executeQuery();
            $task_result->finishTimer("task_$task_id");

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
            $task_result->setResult($data);
            $task_result->setStatus("successfully");
        } catch (\Exception $e) {
            $this->log("ERROR", $e->getMessage());
            $task_result->setStatus("failed");
            $task_result->log($task_id, "Error", $e->getCode(), $e->getMessage());
        } catch (\Throwable $e) {
            $this->log("ERROR", $e->getMessage());
            $task_result->setStatus("failed");
            $task_result->log($task_id, "Error", $e->getCode(), $e->getMessage());
        }

        $task_result->finish();
        return $task_result;
    }

    public function explain(string $task_id, string $command): TaskResult
    {
        $task_result = new TaskResult($task_id, self::CONNECTOR_NAME);
        try {

            $task_result->startTimer("connection_time_" . self::CONNECTOR_NAME);
            $this->connect();
            $task_result->finishTimer("connection_time_" . self::CONNECTOR_NAME);
            $task_result->startTimer("task_$task_id");
            $this->connection->beginTransaction();
            $this->connection->executeQuery("SET SHOWPLAN_TEXT ON");
            $result = $this->connection->fetchOne($command);
            $this->connection->executeQuery("SET SHOWPLAN_TEXT OFF");
            $this->connection->commit();
            $task_result->finishTimer("task_$task_id");
            $data = [];
            $data = $result;

            $task_result->setResult($data);
            $task_result->setStatus("successfully");
        } catch (\Exception $e) {
            echo $e->getMessage();
            $this->invalidate();
            $task_result->setStatus("failed");
            $task_result->log($task_id, "Error", $e->getCode(), $e->getMessage());
        } catch (\Throwable $e) {
            echo $e->getMessage();
            $this->invalidate();
            $task_result->setStatus("failed");
            $task_result->log($task_id, "Error", $e->getCode(), $e->getMessage());
        }
        $task_result->finish();
        return $task_result;
    }
}
