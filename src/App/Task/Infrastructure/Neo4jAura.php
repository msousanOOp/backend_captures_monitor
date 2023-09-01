<?php

namespace Monitor\App\Task\Infrastructure;

use Bolt\protocol\V5;
use Monitor\App\TaskResult\Domain\TaskResult;
use Exception;
use Monitor\App\Task\Domain\Collector;
use Monitor\App\Task\Domain\Exceptions\CantConnect;
use Throwable;

class Neo4jAura extends Collector
{

    const CONNECTOR_NAME = "neo4j";

    private string $host;
    private string $user;
    private string $password;
    private string $port;

    private V5 $connection;

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
            $conn = new \Bolt\connection\StreamSocket($this->host, $this->port);
            
            $bolt = new \Bolt\Bolt($conn);
            // Set requested protocol versions
            //$bolt->setProtocolVersions(5.1, 5, 4.4);
            // Build and get protocol version instance which creates connection and executes handshake.
            $this->connection = $bolt->build();
            // Connect and login into database
            $this->connection->hello(\Bolt\helpers\Auth::basic($this->user, $this->password));
            $this->setConnection($this->connection);
        } catch (Exception $e) {
            var_dump($e->getMessage());
            $this->invalidate();
            throw new CantConnect(self::CONNECTOR_NAME, 1000);
        } catch (Throwable $e) {
            var_dump($e->getMessage());
            $this->invalidate();
            throw new CantConnect(self::CONNECTOR_NAME, 1000);
        }
        return;
    }

    public function isConnected(): void
    {
        try {
            $this->connection->reset();
        } catch (Exception $e) {
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
            $result  = [];
            $task_result->startTimer("connection_time_" . self::CONNECTOR_NAME);
            $this->connect();
            $task_result->finishTimer("connection_time_" . self::CONNECTOR_NAME);

            $task_result->startTimer("task_$task_id");
            $config = json_decode($command, true);
            $this->connection->run($config['command'])->pull();

            $task_result->finishTimer("task_$task_id");

            foreach ($this->connection->getResponses() as $response) {
                if ($response->getSignature() == \Bolt\protocol\Response::SIGNATURE_RECORD) {
                    $content = $response->getContent();
                    if ($content instanceof \Bolt\protocol\v5\structures\Node) {
                        //     foreach($content->properties() as $name => $value)
                        //     {
                        //         $tmp[$name] = $value;
                        //     }                        
                    } else {
                        $result = $content;
                        $result = $this->output($result,$config['output']);
                    }
                } else if ($response->getSignature() == \Bolt\protocol\Response::SIGNATURE_FAILURE) {
                    $content = $response->getContent();                    
                    throw new Exception($content['message']);
                }
            }
            $task_result->setResult($result);
            $task_result->setStatus("successfully");
        } catch (\Exception $e) {
            $task_result->setStatus("failed");
            $task_result->log($task_id, "Error", $e->getCode(), $e->getMessage());
        }
        $task_result->finish();
        return $task_result;
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
