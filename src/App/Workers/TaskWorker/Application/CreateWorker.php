<?php

namespace Monitor\App\Workers\TaskWorker\Application;

use Monitor\App\Workers\TaskWorker\Application\CreateWorkerDto;
use Monitor\App\API\Application\GetConfiguration;
use Monitor\App\API\Application\GetConfigurationDto;
use Monitor\App\API\Domain\Api;
use Monitor\App\API\Infrastructure\Client;
use Monitor\App\Shared\Timer;
use Monitor\App\Task\Domain\Task;
use Monitor\App\Workers\TaskWorker\Domain\TaskWorker;

class CreateWorker
{
    private GetConfiguration $get_tasks_config;


    public function __construct()
    {
        $this->get_tasks_config = new GetConfiguration(new Client);
    }

    public function execute(CreateWorkerDto $data): TaskWorker
    {
        $api = new Api;
        $get_tasks_config_dto = new GetConfigurationDto($api, $data->hash);

        $result = $this->get_tasks_config->execute($get_tasks_config_dto);
        $server = $result['server_id'];
        $connections = (array)$result['connections'];
        $tasks = $result['tasks'];
        $worker = new TaskWorker($server);
        switch ($data->type) {
            case "monitor":
                foreach ($tasks as $service => $service_tasks) {
                    foreach ($service_tasks as $task_config) {
                        if (empty($task_config)) continue;
                        $task_config = (array)$task_config;
                        $task = new Task(
                            $task_config['task_id'],
                            $server,
                            $service,
                            $task_config['type'],
                            (array)$connections[$task_config['type']],
                            $task_config['command'],
                            Timer::create((array)$task_config),
                            $data->type
                        );
                        $task->setLastRun($task_config['last_run']);
                        $worker->addTask($task);
                    }
                }
                break;
            case "scheduler":
                foreach ($tasks as $task_config) {
                    if (empty($task_config)) continue;
                    $task_config = (array)$task_config;
                    $task = new Task(
                        $task_config['task_id'],
                        $server,
                        "scheduler",
                        $task_config['type'],
                        (array)$connections[$task_config['type']],
                        $task_config['command'],
                        Timer::create((array)$task_config),
                        $data->type
                    );
                    $worker->addTask($task);
                }
        }
        return $worker;
    }
}
