<?php

namespace Monitor\App\Task\Infrastructure;

use Bolt\protocol\V5;
use Monitor\App\TaskResult\Domain\TaskResult;
use Exception;
use Monitor\App\Task\Domain\Collector;
use Monitor\App\Task\Domain\Exceptions\CantConnect;

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

            $conn = new \Bolt\connection\StreamSocket($this->host);
            $conn->setSslContextOptions([
                'verify_peer' => true
            ]);
            $bolt = new \Bolt\Bolt($conn);
            // Set requested protocol versions
            //$bolt->setProtocolVersions(5.1, 5, 4.4);
            // Build and get protocol version instance which creates connection and executes handshake.
            $this->connection = $bolt->build();
            // Connect and login into database
            $this->connection->hello(\Bolt\helpers\Auth::basic($this->user, $this->password));
            $this->setConnection($this->connection);
        } catch (Exception $e) {
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
            throw new CantConnect(self::CONNECTOR_NAME, 1000);
        }
    }

    public function invalidate(): void
    {
        $this->deleteConnection();
        $this->connection = null;
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

            $this->connection->run($command)->pull();

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
                        $result[] = $content;
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
}
