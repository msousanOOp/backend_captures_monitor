<?php

use Monitor\App\API\Application\GetTaskConfig;
use Monitor\App\API\Application\GetTaskConfigDto;
use Monitor\App\API\Domain\Api;
use Monitor\App\API\Infrastructure\Client;
use Monitor\App\Task\Domain\Collector;
use Monitor\App\Task\Infrastructure\Mssql;
use Monitor\App\Task\Infrastructure\Mysql;
use Monitor\App\Task\Infrastructure\Neo4jAura;
use Monitor\App\Task\Infrastructure\Postgresql;
use Monitor\App\Task\Infrastructure\Ssh;

include __DIR__ . "/../bootstrap.php";

function get_connector($service): Collector
{
    switch ($service) {
        case "mysql":
            $connector = new Mysql();
            break;
        case "postgresql":
            $connector = new Postgresql();
            break;
        case "ssh":
            $connector = new Ssh();
            break;
        case "neo4j":
            $connector = new Neo4jAura();
            break;
        case "mssql":
            $connector = new Mssql();
            break;
    }

    return $connector;
}

function main($argv)
{
    $instance = $argv[2];
    $service = $argv[1];
    $task = $argv[3];
    echo "Testing -- Instance $instance -- Task $task -- Service $service " . PHP_EOL;
    $get_config = new GetTaskConfig(new Client);
    $api = new Api;
    try {
        $config = [];

        /**
         * @var Collector
         */
        $connector = null;

        if (!empty($task)) {
            echo "Getting Task&Instance Config" . PHP_EOL;
            $dto = new GetTaskConfigDto($api, $instance, $service, $task);
            $config = $get_config->execute($dto);

            if (empty($config)) {
                throw new Exception("Invalid Server! Empty Configuration");
            }
            echo "Instance Collector" . PHP_EOL;
            $connector = get_connector($config['type']);
        }

        if (!$connector) {
            throw new Exception("Service is not configured ($service)");
        }

        echo "Setting Config" . PHP_EOL;
        $connector->setConfig($config);

        echo "Executing Task" . PHP_EOL;
        $result = $connector->run($task, $config['command']);

        echo "-----Task Result-------" . PHP_EOL;
        echo "--Status: " . $result->status() . PHP_EOL;
        echo "--Timer: " . PHP_EOL;
        var_dump($result->timers());
        echo "--Response: " . PHP_EOL;
        var_dump($result->result());
        echo "--Logs: " . PHP_EOL;
        var_dump($result->logs());
    } catch (Throwable $e) {
        echo "[ERROR] " . $e->getMessage() . PHP_EOL;
    }
}

main($argv);
