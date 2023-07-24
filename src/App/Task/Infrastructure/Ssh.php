<?php

namespace Monitor\App\Task\Infrastructure;

use Monitor\App\TaskResult\Domain\TaskResult;
use Exception;
use Monitor\App\Shared\Utils;
use Monitor\App\Task\Domain\Collector;
use Monitor\App\Task\Domain\Exceptions\CantConnect;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

class Ssh extends Collector
{

    const CONNECTOR_NAME = "ssh";

    private string $host;
    private string $user;
    private string $password;
    private string $port;

    private SSH2 $connection;

    public function setConfig(array $config): void
    {
        list("ssh_host_ip" => $host, "ssh_host_port" => $port, "ssh_user" => $user, "ssh_password" => $pass) = $config;

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
            $this->connection = new SSH2($this->host, $this->port);
            $keys = PublicKeyLoader::load($this->password);
            $this->connection->login($this->user, $keys);
            $this->setConnection($this->connection);
        } catch (Exception $e) {
            $this->invalidate();
            throw new CantConnect(self::CONNECTOR_NAME, 1000);
        }
        return;
    }

    public function isConnected(): void
    {
        $this->connection->ping();
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

            $task_result->startTimer("task_$task_id");
            $stm = $this->connection->exec($command);

            $task_result->finishTimer("task_$task_id");
            $clearly = $this->connection->exec("echo 'trim_dbsnoop'");

            $exploded = explode("trim_dbsnoop", $clearly);

            if ($exploded[0] && strpos($stm, $exploded[0]) !== false) {
                $stm = explode($exploded[0], $stm);
                $stm = array_pop($stm);
            }
            $task_result->setResult(Utils::convertTextArray($stm));
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
}
