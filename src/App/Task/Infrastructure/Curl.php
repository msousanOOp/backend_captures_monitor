<?php

namespace Monitor\App\Task\Infrastructure;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monitor\App\TaskResult\Domain\TaskResult;
use Monitor\App\Shared\Utils;
use Monitor\App\Task\Domain\Collector;
use Monitor\App\Task\Domain\Exceptions\CantConnect;

class Curl extends Collector
{

    const CONNECTOR_NAME = "curl";
    private string $host;
    private string $port;
    private Client $connection;


    public function setConfig(array $config): void
    {
        
        list("host" => $host, "port" => $port) = $config;

        $this->host = $host;
        $this->port = $port;
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

            $uri = $this->host . (!empty($this->port) ? ":" . $this->port : "");
          
            $client = new Client([
                "base_uri" => "http://$uri/",
                "headers" => [
                    "Content-Type" => "application/json"
                ]
            ]);
            $this->connection = $client;
            $this->setConnection($this->connection);
        } catch (Exception $e) {
            throw new CantConnect(self::CONNECTOR_NAME, $e->getCode());
        }
    }

    public function isConnected(): void
    {
        if (!$this->connection) {
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

            $task_result->startTimer("task_$task_id");
            $command = json_decode($command, true);
            $stm = "";
            $params = [];

            if (!empty($command['options'])) {
                $params = $command['options'];
            }

            if (array_key_exists("timeout", $params)) {
                $params['timeout'] = 5;
            }

            try {
            
                $result = $this->connection->request($command['method'], $command["path"], $params);
              
               
            } catch (\GuzzleHttp\Exception\BadResponseException $e) {
              
                $result = $e->getResponse();
            }
            $status = $result->getStatusCode();
            $headers = json_encode($result->getHeaders());
            $body = $result->getBody()->getContents();
            
            $stm = [];
            if (empty($command['output']) || !is_array($command['output'])) {
             
                $stm = [
                    "req_status" => $status,
                    "req_headers" => $headers,
                    "req_body" => $body
                ];
            } else {
               
                foreach ($command['output'] as $header => $regex) {
                   var_dump($regex);
                    if ($header == "req_status") {
                        $stm['req_status'] = $status;
                        continue;
                    }
                    if ($header == "req_body") {
                        $stm['req_body'] = $body;
                        continue;
                    }
                    if ($header == "req_headers") {
                        $stm['req_headers'] = $headers;
                        continue;
                    }
                    $stm[$header] = '';
                    preg_match_all($regex, $body, $output);
                  
                    if (!empty($output) && !empty($output[1])) {

                        $stm[$header] = array_pop($output[1]);
                    }
                }
            }
            $task_result->finishTimer("task_$task_id");
            $task_result->setResult([$stm]);
            $task_result->setStatus("successfully");
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            $this->log("ERROR", $e->getMessage());
            $task_result->setStatus("failed");
            $task_result->log($task_id, "Error", $e->getCode(), $e->getMessage());
        } catch (\Throwable $e) {
            echo $e->getMessage() . PHP_EOL;
            $this->log("ERROR", $e->getMessage());
            $task_result->setStatus("failed");
            $task_result->log($task_id, "Error", $e->getCode(), $e->getMessage());
        }
        $task_result->finish();
      
        return $task_result;
    }
}
