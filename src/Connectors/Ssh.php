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
    private $valid_key = '';
    private $connect = false;
    static $connections = [];
    static $keys = [];
    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->connector_name = 'ssh';
    }
    public function openConnection(): bool
    {
        if ($this->connect) return true;
        if (!$this->invalidate_op) {
            list("ssh_host_ip" => $host, "ssh_host_port" => $port, "ssh_user" => $user, "ssh_password" => $pass) = $this->configs;
            $this->startTime("connection_time_ssh");
            try {
                $this->valid_key = sha1($host . $port . $user . $pass);
                if (!array_key_exists($this->valid_key, self::$connections)) {
                    self::$connections[$this->valid_key] = new SSH2($host, $port);
                    self::$keys[$this->valid_key] = PublicKeyLoader::load($pass);
                }

                // if(!self::$connections[$this->valid_key]->isConnected())
                // {
                //     self::$connections[$this->valid_key]->reconnect();
                // }

                if (!self::$connections[$this->valid_key]->ping() && !self::$connections[$this->valid_key]->login($user, self::$keys[$this->valid_key])) {
                    throw new \Exception(self::$connections[$this->valid_key]->getLastError());
                }
                $this->connect = true;
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
        if (self::$connections[$this->valid_key])
            return true;
        return false;
    }

    public function closeConnection(): void
    {
        if (self::$connections[$this->valid_key]) {
            //self::$connections[$this->valid_key]->disconnect();
        }
    }

    public function process($task)
    {
        $this->startTime("task_" . $task['task_id']);
        try {
            $stm = self::$connections[$this->valid_key]->exec($task['command']);
            $clearly = self::$connections[$this->valid_key]->exec("echo 'trim_dbsnoop'");
            $exploded = explode("trim_dbsnoop", $clearly);
            if ($exploded[0] && strpos($stm, $exploded[0]) !== false) {
                $stm = explode($exploded[0], $stm);
                $stm = array_pop($stm);
            }
            $this->finishTime("task_" . $task['task_id']);
        } catch (\Exception $e) {
            $this->log($task['task_id'], "Error", $e->getCode(), $e->getMessage());
            return false;
        }
        $this->addCapture("task_" . $task['task_id'], $stm);
        return true;
    }
}
