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
                if(!is_array($task)) continue;

                self::$logger->info("Configuring Server ".$server." - Service $service - ID#$task[task_id] - Frequency $task[frequency]");
                if (time() - $task['last_run'] > $task['frequency'])
                    $this->worker->callOnFirst(static fn () => self::runTask($server, $customer, $service, $task, $connections));
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
        try {
            self::$logger->info("Running Task $task[task_id] $server - $service ");

            $config = $configs[$task['type']];

            $result = [
                'type' => $task['type'],
                'result' => []
            ];
            switch ($task['type']) {
                case 'mysql':
                    if (!array_key_exists('mysql', self::$connectors))
                        self::$connectors['mysql'] = new Mysql((array)$config);
                    if (!self::$connectors['mysql']->openConnection())
                        break;
                    self::$connectors['mysql']->process($task);
                    break;
                case 'mssql':
                    if (!array_key_exists('mssql', self::$connectors))
                        self::$connectors['mssql'] = new Mssql((array)$config);
                    if (!self::$connectors['mssql']->openConnection())
                        break;
                    self::$connectors['mssql']->process($task);
                    break;
                case 'postgresql':
                    if (!array_key_exists('postgresql', self::$connectors))
                        self::$connectors['postgresql'] = new PostgreSql((array)$config);
                    if (!self::$connectors['postgresql']->openConnection())
                        break;
                    self::$connectors['postgresql']->process($task);
                    break;
                case 'ssh':
                    if (!array_key_exists('ssh', self::$connectors))
                        self::$connectors['ssh'] = new Ssh((array)$config);
                    if (!self::$connectors['ssh']->openConnection())
                        break;
                    self::$connectors['ssh']->process($task);
                    break;
            }

            $pre_process_tasks = [
                "captures" => [],
                "timers" => [],
                "logs" => []
            ];
            $content = self::$connectors[$task['type']]->getContent();
            $pre_process_tasks['captures'] = array_merge($pre_process_tasks['captures'], $content['captures']);
            $pre_process_tasks['timers'] = array_merge($pre_process_tasks['timers'], $content['timers']);
            $pre_process_tasks['logs'] = array_merge($pre_process_tasks['logs'], $content['logs']);
            self::$connectors[$task['type']]->clearContent();

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
        }
    }

    public function run()
    {
        $this->worker->run();
        
        $this->timer = Loop::addPeriodicTimer(function () {
            if($this->worker->getStage() != 'running'){
                $this->worker->restart();
                self::$logger->critical("Restart worker!!!", $this->worker->getLastError());
            }
        },10);
    }
    public function stop()
    {
        Loop::cancelTimer($this->timer);
        $this->worker->kill();
    }
}
