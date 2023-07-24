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
        foreach ($this->service_tasks as $task) {
            if (!is_array($task)) continue;
            switch ($task['timer_type']) {
                case "CRON":
                    self::$logger->info("Configuring Scheduler CRON Server " . $server . " -  ID#$task[task_id] - Frequency $task[timer_value]");
                    $this->worker->callCronFunction(static fn () => self::runTask($server, $task, $connections), $task['timer_value']);
                    break;
                case "DATE":
                    self::$logger->info("Configuring Scheduler Timer Server " . $server . " -  ID#$task[task_id] - Frequency $task[timer_value]");
                    $this->worker->callTimeoutFunction(static fn () => self::runTask($server, $task, $connections), $task['timer_value'] - time());
                    break;
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
            if(!$connector = Factory::getConnector($task['type'], (array) $config)) return;   
            $connector->process($task);   
            $pre_process_tasks = [
                "captures" => [],
                "timers" => [],
                "logs" => []
            ];
            $content = $connector->getContent();
            $pre_process_tasks['captures'] = array_merge($pre_process_tasks['captures'], $content['captures']);
            $pre_process_tasks['timers'] = array_merge($pre_process_tasks['timers'], $content['timers']);
            $pre_process_tasks['logs'] = array_merge($pre_process_tasks['logs'], $content['logs']);
            $connector->clearContent();

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
        $this->worker->stayAlive();
        $this->worker->on('restart', function ($e) {
            self::$logger->critical("Restart Scheduler", $e);
        });
        $this->worker->run();
    }
    public function stop()
    {
        $this->worker->kill();
    }
}
