<?php

use App\API;
use App\Connectors\Mysql;
use App\Connectors\PostgreSql;
use App\Connectors\Ssh;
use App\Utils;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;

include __DIR__ . "/../bootstrap.php";

function get_connector($service)
{
    switch ($service) {
        case "mysql":
            $connector = new Mysql();
            break;
        case "postgresql":
            $connector = new PostgreSql();
            break;
        case "ssh":
            $connector = new Ssh();
            break;
    }

    return $connector;
}

function main($argv)
{
    $server = $argv[2];
    $service = $argv[1];
    $task = $argv[3];

    $connector = null;

    if (!empty($task)) {

        $task_info = API::getTaskInfo($service, $task);
        if (empty($task_info)) {
            echo "Invalid Task!";
            exit(-1);
        }
        $connector = get_connector($task_info['type']);
    } else {
        $connector = get_connector($service);
    }

    if (!$connector) {
        echo "Service is not configured ($service)" . PHP_EOL;
        exit(-1);
    }

    $config = Utils::getConfigs($server, $service);
    if (empty($config)) {
        echo "Server $server is not configured to collect $service, or is not enable to this worker!" . PHP_EOL;
        exit(-1);
    }

    $connector->setConfig($config);

    if ($connector->openConnection()) {
        echo "Connection Successfully!" . PHP_EOL;
        if (empty($task)) exit(0);

        echo "Trying Task $task" . PHP_EOL;
        $connector->process($task_info);

        $result = $connector->getContent();

        foreach ($result['logs'] as $r) {
            echo "$r[type] - $r[code] $r[message]" . PHP_EOL;
        }
        if (!empty($result['captures'])) {
            $output = new ConsoleOutput();
            foreach ($result['captures'] as $capture) {
                $r = $capture['result'];
                if (!is_array($r)) {
                    $r = [explode("|", $r)];
                }
                $header = array_keys($r[0]);
                $table = new Table($output);
                $table->setHeaders($header);
                $table->setRows($r);
                $table->render();
            }
        }
    }

    $content = $connector->getContent();
    foreach ($content['logs'] as $log) {
        echo "$log[type] - $log[code] $log[message]" . PHP_EOL;
    }
    exit(-1);
}

main($argv);
