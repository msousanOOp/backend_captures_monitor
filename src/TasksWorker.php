<?php

namespace App;

use App\Connectors\Mssql;
use App\Connectors\Mysql;
use App\Connectors\PostgreSql;
use App\Connectors\Ssh;
use React\EventLoop\Loop;
use Sohris\Core\Logger;
use Sohris\Core\Tools\Worker\Worker;

class TasksWorker
{
    private $hash;
    private $worker;
    private $service_tasks = [];
    private $server;
    private $customer;
    private $timer;
    private $connections = [];
    private static $logger;
    private static $connectors = [];

    public function __construct($hash)
    {
        self::$logger = new Logger("Controller");
        $this->worker = new Worker;
        $this->worker->callOnFirst(static fn () => self::firstRun());

        $this->hash = $hash;

        $config = Utils::objectToArray(API::getConfig($hash));
        $this->service_tasks = $config['tasks'];
        $this->server = $config['server_id'];
        $this->customer = $config['customer_id'];
        $this->connections = $config['connections'];

        $this->organize();
    }

    private function organize()
    {
        $server = $this->server;
        $customer = $this->customer;
        $connections = $this->connections;
        foreach ($this->service_tasks as $service => $tasks) {
            foreach ($tasks as $task) {
                if (!is_array($task)) continue;
                if (array_key_exists('last_run', $task)) {
                    $timer = strtotime($task['last_run']);
                    if (time() - $timer > $task['frequency'])
                        $this->worker->callOnFirst(static fn () => self::runTask($server, $customer, $service, $task, $connections));
                }

                self::$logger->info("Configuring Server " . $server . " - Service $service - ID#$task[task_id] - Frequency $task[frequency]");
                $this->worker->callFunction(static fn () => self::runTask($server, $customer, $service, $task, $connections), $task['frequency']);
            }
        }
    }


    public static function firstRun()
    {
        self::$logger = new Logger("Controller");
    }

    public static function runTask($server, $customer, $service, $task, $configs)
    {
        self::$logger->info("Running Task $task[task_id] $server - $service ");

        $config = $configs[$task['type']];

        $result = [
            'type' => $task['type'],
            'result' => []
        ];
        if(!$connector = Factory::getConnector($task['type'], (array) $config)) return;       
        $connector->process($task);
        $pre_process_tasks = [
            "captures" => [],
            "timers" => [],
            "logs" => []
        ];
        try {
            $content = $connector->getContent();
            $pre_process_tasks['captures'] = array_merge($pre_process_tasks['captures'], $content['captures']);
            $pre_process_tasks['timers'] = array_merge($pre_process_tasks['timers'], $content['timers']);
            $pre_process_tasks['logs'] = array_merge($pre_process_tasks['logs'], $content['logs']);
            $connector->clearContent();

            $result['result'] = [
                "timestamp" => time(),
                "tasks_id" => [$task['task_id']],
                "customer_id" => $customer,
                "server_id" => $server,
                "service" => $service,
                "captures" => $pre_process_tasks['captures'],
                "timers" => $pre_process_tasks['timers'],
                "logs" => $pre_process_tasks['logs'],
            ];
            API::sendResults($result);
            unset($result);
            self::$logger->info("Task Runned $task[task_id] $server - $service");
        } catch (\Exception $e) {
            self::$logger->info("Error Task $task[task_id] $server - $service");
            self::$logger->critical("[Error][" . $e->getCode() . "] " . $e->getMessage());

            $result['result'] = [
                "timestamp" => time(),
                "tasks_id" => [$task['task_id']],
                "customer_id" => $customer,
                "server_id" => $server,
                "service" => $service,
                "captures" => [],
                "timers" => [],
                "logs" => [["type" => "RUNNER", "level" => "CRITICAL", "code" => $e->getCode(), "message" => $e->getMessage()]],
            ];
            API::sendResults($result);
        }
    }

    public function run()
    {
        $this->worker->stayAlive();
        $this->worker->run();
    }
    public function stop()
    {
        $this->worker->kill();
    }
}
