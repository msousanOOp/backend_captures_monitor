<?php

namespace App\Connectors;

use PDO;
use phpseclib3\Crypt\PublicKeyLoader;
use Sohris\Core\Utils;
use phpseclib3\Net\SSH2;

class Ssh extends \App\Connector
{
    /**
     * @var SSH2
     */
    private $connector;
    public function __construct($config)
    {
        parent::__construct($config);
        $this->connector_name = 'ssh';
    }
    public function openConnection(): bool
    {
        if($this->connector) return true;
        if (!$this->invalidate_op) {
            list("ssh_host_ip" => $host, "ssh_host_port" => $port, "ssh_user" => $user, "ssh_password" => $pass) = $this->configs;
            $this->startTime("connection_time_ssh");
            try {

                $key = PublicKeyLoader::load($pass);
                $conn = new SSH2($host, $port);
                if (!$conn->login($user, $key)) {
                    throw new \Exception($conn->getLastError());
                }
                $this->connector = $conn;
                $this->finishTime("connection_time_ssh");
                return true;
            } catch (\Exception $e) {
                $this->log("Connection - SSH", "Error", $e->getCode(),  str_replace("\\", "", $e->getMessage()));
                $this->invalidate_op = true;
                return false;
            }
        }
        return false;
    }


    public function isConnected(): bool
    {
        if ($this->connector)
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
        $this->startTime("task_" . $task['task_id']);
        try {
            $stm = $this->connector->exec($task['command']);
            $this->finishTime("task_" . $task['task_id']);
        } catch (\Exception $e) {
            $this->log($task['task_id'], "Error", $e->getCode(), $e->getMessage());
            return false;
        }
        $this->addCapture("task_" . $task['task_id'], $stm);
        return true;
    }
}
