<?php

namespace App\Connectors;

use Bolt\protocol\V5;
use Sohris\Core\Utils;

class Neo4jAura extends \App\Connector
{
    /**
     * @var  V5
     */
    private $connector;

    public function __construct($config = [])
    {
        $this->connector_name = 'neo4j_aura_bolt';
        parent::__construct($config);
    }
    public function openConnection(): bool
    {
        if ($this->connector) return true;

        if (!$this->invalidate_op) {
            list("db_host_ip" => $host, "db_user" => $user, "db_password" => $pass) = $this->configs;
            $this->startTime("connection_time_neo4j");
            try {
                $conn = new \Bolt\connection\StreamSocket($host);
                $conn->setSslContextOptions([
                    'verify_peer' => true
                ]);
                $bolt = new \Bolt\Bolt($conn);
                // Set requested protocol versions
                //$bolt->setProtocolVersions(5.1, 5, 4.4);
                // Build and get protocol version instance which creates connection and executes handshake.
                $this->connector = $bolt->build();
                // Connect and login into database
                $this->connector->hello(\Bolt\helpers\Auth::basic($user, $pass));
                $this->finishTime("connection_time_neo4j");
                return true;
            } catch (\Exception $e) {
                $this->invalidate_op = true;
                $this->log("Connection - Neo4J Server", "Error", $e->getCode(), $e->getMessage());
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
        $result = [];
        try {
            $this->connector->run($task['command'])->pull();
            foreach ($this->connector->getResponses() as $response) {
                if ($response->getSignature() == \Bolt\protocol\Response::SIGNATURE_RECORD) {
                    $content = $response->getContent();
                    if($content instanceof \Bolt\protocol\v5\structures\Node)
                     {
                    //     foreach($content->properties() as $name => $value)
                    //     {
                    //         $tmp[$name] = $value;
                    //     }                        
                    }else{
                        $result[] = $content;
                    }
                }
            }
            $this->finishTime("task_" . $task['task_id']);
        } catch (\Exception $e) {
            $this->log($task['task_id'], "Error", $e->getCode(), $e->getMessage());
            return false;
        }
        $this->addCapture("task_" . $task['task_id'], $result);
        return true;
    }
}
