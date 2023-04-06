<?php

namespace App;

use App\Connectors\Mssql;
use App\Connectors\Mysql;
use App\Connectors\PostgreSql;
use App\Connectors\Ssh;
use React\EventLoop\Loop;
use Sohris\Core\Logger;
use Sohris\Core\Tools\Worker\Worker;

class SchedulerWorker
{
    private $hash;
    private $worker;
    private $service_tasks = [];
    private $server;
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
        $this->connections = $config['connections'];

        $this->organize();
    }

    private function organize()
    {
        $server = $this->server;
        $connections = $this->connections;
        foreach ($this->service_tasks as $tasks) {
            foreach ($tasks as $task) {
                if (!is_array($task)) continue;

                switch ($task['timer_type']) {
                    case "CRON":
                        self::$logger->info("Configuring Scheduler CRON Server " . $server . " -  ID#$task[task_id] - Frequency $task[frequency]");
                        $this->worker->callCronFunction(static fn () => self::runTask($server, $task, $connections), $task['timer_value']);
                        break;
                    case "DATE":
                        self::$logger->info("Configuring Scheduler Timer Server " . $server . " -  ID#$task[task_id] - Frequency $task[frequency]");
                        $this->worker->callTimeoutFunction(static fn () => self::runTask($server, $task, $connections), $task['timer_value'] - time());
                        break;
                }
            }
        }
       
    }


    public static function firstRun()
    {
        self::$logger = new Logger("Controller");
    }

    public static function runTask($server, $task, $configs)
    {
        try {
            self::$logger->info("Running Task $task[task_id] $server ");
            $start = time();
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
                "start" => $start,
                "end" => time(),
                "tasks_id" => [$task['task_id']],
                "captures" => $pre_process_tasks['captures'],
                "timers" => $pre_process_tasks['timers'],
                "logs" => $pre_process_tasks['logs'],
            ];
            API::sendResultScheduler($result);
            unset($result);
            self::$logger->info("Scheduler Runned $task[task_id] $server ");
        } catch (\Exception $e) {
            self::$logger->info("Error Scheduler $task[task_id] $server ");
            self::$logger->critical("[Error][" . $e->getCode() . "] " . $e->getMessage());
        }
    }

    public function run()
    {
        $this->worker->run(); 
        $this->timer = Loop::addPeriodicTimer(function () {
            if($this->worker->getStage() != 'running'){
                $this->worker->clearTimeoutCallFunction();
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
