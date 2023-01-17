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
    static $connector;
    public function __construct($config)
    {
        $this->configs = $config;
        $this->connector_name = 'ssh';
        
    }
    public function openConnection(): bool
    {
        if (!self::$connector) {
            list("ssh_host_ip" => $host, "ssh_host_port" => $port, "ssh_user" => $user, "ssh_password" => $pass) = $this->configs;
            $this->startTime("connection_time_ssh");
            try {

                $key = PublicKeyLoader::load($pass);
                $conn = new SSH2($host, $port);
                if (!$conn->login($user, $key)) {
                    throw new \Exception($conn->getLastError());
                }
                self::$connector = $conn;
                $this->finishTime("connection_time_ssh");
                return true;
            } catch (\Exception $e) {
                $this->log("Connection - SSH", "Error", $e->getCode(),  str_replace("\\","",$e->getMessage()));
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
        $this->startTime("task_" . $task['task_id']);
        try {
            $stm = self::$connector->exec($task['command']);
            $this->finishTime("task_" . $task['task_id']);
        } catch (\Exception $e) {
            $this->log($task['task_id'], "Error", $e->getCode(), $e->getMessage());
            return false;
        }
        $this->addCapture("task_".$task['task_id'], $stm);
        return true;
    }
}
