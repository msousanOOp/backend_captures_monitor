<?php

namespace Monitor\App\Task\Infrastructure;

use Monitor\App\TaskResult\Domain\TaskResult;
use Exception;
use Monitor\App\Task\Domain\Collector;
use Monitor\App\Task\Domain\Exceptions\CantConnect;
use MongoDB\Driver\Command;
use MongoDB\Driver\Cursor;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use Monitor\App\Shared\Utils;

class MongoDB extends Collector
{

    const CONNECTOR_NAME = "mongodb";

    private string $host;
    private string $user;
    private string $password;
    private string $port;

    private Manager $connection;

    public function setConfig(array $config): void
    {
        list("db_host_ip" => $host, "db_host_port" => $port, "db_user" => $user, "db_password" => $pass) = $config;

        $this->host = $host;
        $this->port = $port;
        $this->user = urlencode($user);
        $this->password = urlencode($pass);

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

            $uri = "mongodb://" . $this->user . ":" . $this->password . "@" . $this->host . (!empty($this->port) ? ":" . $this->port : "") . "/";
            $this->connection = new Manager($uri);
            $command = new \MongoDB\Driver\Command(['ping' => 1]);
            $this->connection->executeCommand('admin', $command);
            $this->setConnection($this->connection);
        } catch (\MongoDB\Driver\Exception $e) {
            $this->invalidate();
            throw new CantConnect(self::CONNECTOR_NAME, 1000);
        }
        return;
    }

    public function isConnected(): void
    {
        try {
            $command = new \MongoDB\Driver\Command(['ping' => 1]);
            $this->connection->executeCommand('admin', $command);
        } catch (\MongoDB\Driver\Exception $e) {
            $this->invalidate();
            $this->connect();
        }
    }

    public function invalidate(): void
    {
        $this->deleteConnection();
    }

    public function run(string $task_id, $command): TaskResult
    {
        $task_result = new TaskResult($task_id, self::CONNECTOR_NAME);
        try {

            $task_result->startTimer("connection_time_" . self::CONNECTOR_NAME);
            $this->connect();
            $task_result->finishTimer("connection_time_" . self::CONNECTOR_NAME);

            $command = json_decode($command, true);

            $task_result->startTimer("task_$task_id");
            $result = [];
            switch ($command['type']) {
                case "command":
                    $cursor = $this->command($command['db'], $command['command']);
                    $result = $this->output($cursor->toArray(), $command['output']);
                    if (array_key_exists('output_filter', $command))
                        $result = $this->outputFilter($result, $command['output_filter']);
                    break;
                case "query":
                    $cursor = $this->query($command['command'], $command['filter'], $command['options']);
                    $result = $cursor->toArray();
                    if (array_key_exists('output_filter', $command))
                        $result = $this->outputFilter($result, $command['output_filter']);
                    break;
                case "write":
                    break;
                default:
                    throw new Exception("Command not defined");
            }
            $task_result->finishTimer("task_$task_id");
            $task_result->setResult(Utils::objectToArray($result));
            $task_result->setStatus("successfully");
        } catch (\MongoDB\Driver\Exception $e) {
            $task_result->setStatus("failed");
            $task_result->log($task_id, "Error", $e->getCode(), $e->getMessage());
        } catch (\DomainException $e) {
            $task_result->setStatus("failed");
            $task_result->log($task_id, "Error", $e->getCode(), $e->getMessage());
        } catch (\Exception $e) {
            $task_result->setStatus("failed");
            $task_result->log($task_id, "Error", $e->getCode(), $e->getMessage());
        }


        $task_result->finish();
        return $task_result;
    }

    private function command($db, $command): Cursor
    {
        $command = new Command($command);
        return $this->connection->executeCommand($db, $command);
    }

    private function query($db, $filter, ?array $options = []): Cursor
    {
        $query = new Query($filter, $options);
        return $this->connection->executeQuery($db, $query);
    }


    private function output($result, $configs = [])
    {
        if (empty($configs)) return $result;
        if (strpos(json_encode($configs), "[]") !== false) {
            return $this->multiOutput($result, $configs);
        }
        if (strpos(json_encode($configs), "<?>") !== false) {
            return $this->multiDynamicOutput($result, $configs);
        } else {
            return $this->simpleOutput($result, $configs);
        }
    }

    private function outputFilter($result, $filters = [])
    {
        foreach ($filters as $key => $filter) {
            foreach ($result as $val => $item) {
                if (array_key_exists($key, $item) && in_array($item[$key], $filter))
                    unset($result[$val]);
            }
        }
        return $result;
    }

    private function simpleOutput($result, $configs, $key = "")
    {
        $items = [];
        foreach ($configs as $name => $config) {
            $fields = explode(".", $config);
            $value = $result;
            foreach ($fields as $key => $field) {
                if (empty($field) && $field !== "0") $value = $key;
                if (is_numeric($field))
                    $value = $value[$field];
                else {
                    if ($key == count($fields) - 1 && !is_null($value) && get_class($value) == "MongoDB\BSON\UTCDateTime") {
                        $value = $value->toDateTime()->format('U');
                    } else
                        $value = $value->{$field};
                }
            }
            if (is_array($value))
                $value = json_encode($value);
            $items[$name] = $value;
        }
        return [$items];
    }

    private function multiOutput($result, $configs)
    {
        $final = [];
        $config_fields = [];
        $base_configs = "";
        foreach ($configs as $name => $config) {
            $fields = explode("[]", $config);
            $base_configs = $fields[0];
            $config_fields[$name] = $fields[1][0] == "." ? substr($fields[1], 1) : $fields[1];
        }
        $base_configs = explode(".", $base_configs);

        $base_result = $result;
        foreach ($base_configs as $key =>  $field) {
            if (empty($field) && $field !== "0") continue;
            if (is_numeric($field))
                $base_result = $base_result[$field];
            else {
                if ($key == count($fields) - 1 && !is_null($base_configs) && get_class($base_result) == "MongoDB\BSON\UTCDateTime") {
                    $base_result = $base_result->toDateTime()->format('U');
                } else
                    $base_result = $base_result->{$field};
            }
        }
        if (is_array($base_result))
            foreach ($base_result as $n_result) {
                $final[] = $this->simpleOutput($n_result, $config_fields)[0];
            }

        return $final;
    }

    private function multiDynamicOutput($result, $configs)
    {
        $final = [];
        $config_fields = [];
        $base_configs = "";
        foreach ($configs as $name => $config) {
            $fields = explode("<?>", $config);
            $base_configs = $fields[0];
            $config_fields[$name] = $fields[1][0] == "." ? substr($fields[1], 1) : $fields[1];
            if (empty($config_fields[$name])) $config_fields[$name] = "<?>";
        }
        $base_configs = explode(".", $base_configs);

        $base_result = Utils::objectToArray($result);
        foreach ($base_configs as $field) {
            if (empty($field) && $field !== "0") continue;
            $base_result = $base_result[$field];
        }
        if (is_array($base_result))
            foreach ($base_result as $key => $n_result) {
                $items = [];
                foreach ($config_fields as $name => $config) {
                    $fields = explode(".", $config);
                    $value = $n_result;
                    foreach ($fields as $field) {
                        if ($field == "<?>")
                            $value = $key;
                        else
                            $value = $value[$field];
                    }
                    $items[$name] = $value;
                }
                $final[] = $items;
            }

        return $final;
    }
}
