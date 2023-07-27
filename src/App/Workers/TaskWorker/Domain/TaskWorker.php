<?php


namespace Monitor\App\Workers\TaskWorker\Domain;

use DomainException;
use Exception;
use Monitor\App\API\Application\SendTaskResult;
use Monitor\App\API\Application\SendTaskResultDto;
use Monitor\App\API\Domain\Api;
use Monitor\App\API\Infrastructure\Client;
use Monitor\App\Log\Application\DeleteWorkerStatistics;
use Monitor\App\Log\Application\DeleteWorkerStatisticsDto;
use Monitor\App\Log\Application\SaveWorkerStatistics;
use Monitor\App\Log\Application\SaveWorkerStatisticsDto;
use Monitor\App\Log\Application\SendLog;
use Monitor\App\Log\Application\SendLogDto;
use Monitor\App\Task\Application\ExecuteCommand;
use Monitor\App\Task\Application\ExecuteCommandDto;
use Monitor\App\Task\Domain\Task;
use Sohris\Core\Tools\Worker\Worker;
use Throwable;

class TaskWorker
{

    /**
     * @var Task[]
     */
    private array $tasks;

    private Worker $worker;

    private static string $sub_worker_id;
    private static int $create_in = 0;
    private static int $last_run = 0;
    private static array $task_registers = [];
    private static int $instance_id = 0;
    private static int $memory_usage = 0;
    private static array $tasks_runners = [];
    private static array $worker_last_error = [];


    public function __construct(int $instance_id = 0)
    {
        $this->worker = new Worker;
        $this->instance_id = $instance_id;
    }

    public function addTask(Task $task)
    {
        $this->tasks[] = $task;
    }

    public function run(): void
    {
        $tasks = [];
        foreach ($this->tasks as $task) {
            $service = $task->service();
            if (!array_key_exists($service, $tasks))
                $tasks[$service] = [];
            $tasks[$service][] = ["task" => $task->id(), "frequency" => $task->timer()->getTimer()];
            $func = $task->timer()->getTimerFunction();
            $this->worker->{$func}(static fn () => self::runTask($task), $task->timer()->getTimer());

            if($task->needRunning()){
                $this->worker->callOnFirst(static fn () => self::runTask($task));
            }
        }
        $id = $this->worker->getChannelName();
        $instance_id = $this->instance_id;

        $this->worker->callOnFirst(static function () use ($id, $instance_id, $tasks) {
            $tasks_id = [];
            foreach ($tasks as $service => $tasks_ids) {
                foreach ($tasks_ids as $task_info) {
                    $id = $task_info['task'];
                    $tasks_id[] = $id;
                    self::$tasks_runners[$service][$id] = [
                        "create_in" => time(),
                        "frequency" => $task_info['frequency'],
                        "last_run" => 0,
                        "count" => 0,
                        "last_error" => []
                    ];
                }
            }

            self::$create_in = time();
            self::$sub_worker_id = $id;
            self::$instance_id = $instance_id;
            self::$task_registers = array_map(fn ($el) => $el['task'], $tasks);
        });

        $this->worker->callFunction(static function () {
            self::$memory_usage = memory_get_peak_usage(true);
            $dto = new SaveWorkerStatisticsDto(
                self::$sub_worker_id,
                self::$create_in,
                self::$last_run,
                self::$task_registers,
                self::$instance_id,
                self::$memory_usage,
                self::$tasks_runners,
                self::$worker_last_error
            );

            $save_stats = new SaveWorkerStatistics;
            $save_stats->execute($dto);
        }, 10);

        $this->worker->on("error", function ($err_info) {
            self::$worker_last_error = [
                'timestamp' => time(),
                'message' => $err_info['errmsg'],
                'code' => $err_info['errcode'],
                'trace' => is_array($err_info['trace']) ? array_slice($err_info['trace'], 0, 3) : $err_info['trace']
            ];
        });

        $this->worker->run();
    }

    public function stop(): void
    {
        $this->worker->stop();
        $dto = new DeleteWorkerStatisticsDto($this->worker->getChannelName());
        $delete_stats = new DeleteWorkerStatistics;
        $delete_stats->execute($dto);
    }


    public static function runTask(Task $task)
    {
        $service = $task->service();
        $id = $task->id();
        if (!array_key_exists($service, self::$tasks_runners))
            self::$tasks_runners[$service] = [];
        if (!array_key_exists($id, self::$tasks_runners[$service]))
            self::$tasks_runners[$service][$id] = [
                "create_in" => time(),
                "last_run" => 0,
                "count" => 0,
                "last_error" => []
            ];
        self::$tasks_runners[$service][$id]['count']++;
        self::$tasks_runners[$service][$id]['last_run'] = time();

        try {
            //Process Task
            \Monitor\App\Log\Domain\Log::debug("[Instance" . $task->instance() . "][Task" . $task->id() . "] Running", strtoupper($task->service()));
            $connector = $task->connector();
            $execute_command_dto = new ExecuteCommandDto($task->id(), $task->command());
            $execute_command = new ExecuteCommand($connector);
            $result = $execute_command->execute($execute_command_dto);
            $result->setInstance($task->instance());
            $result->setService($task->service());
            $result->setType($task->type());
            //Send Result
            $api = new Api;
            $send_result_dto = new SendTaskResultDto($result, $api);
            $send_result = new SendTaskResult(new Client);
            $send_result->execute($send_result_dto);
        } catch (DomainException $e) {
            self::log("ERROR", $e->getMessage(), ["instance" => $task->instance(), "task_id" => $task->id()]);
            \Monitor\App\Log\Domain\Log::debug("[Instance" . $task->instance() . "][Task" . $task->id() . "] Domain Error " . $e->getMessage(), strtoupper($task->service()));
            self::$tasks_runners[$service][$id]['last_error'] = [
                "type" => "domain",
                "code" => $e->getCode(),
                "message" => $e->getMessage()
            ];
        } catch (Exception $e) {
            self::log("ERROR", $e->getMessage(), ["instance" => $task->instance(), "task_id" => $task->id()]);
            \Monitor\App\Log\Domain\Log::debug("[Instance" . $task->instance() . "][Task" . $task->id() . "] Exception " . $e->getMessage(), strtoupper($task->service()));
            self::$tasks_runners[$service][$id]['last_error'] = [
                "type" => "exception",
                "code" => $e->getCode(),
                "message" => $e->getMessage()
            ];
        } catch (Throwable $e) {
            self::log("ERROR", $e->getMessage(), ["instance" => $task->instance(), "task_id" => $task->id()]);
            \Monitor\App\Log\Domain\Log::debug("[Instance" . $task->instance() . "][Task" . $task->id() . "] Exception " . $e->getMessage(), strtoupper($task->service()));
            self::$tasks_runners[$service][$id]['last_error'] = [
                "type" => "throwable",
                "code" => $e->getCode(),
                "message" => $e->getMessage()
            ];
        }
        self::$last_run = time();
        self::$tasks_runners[$service][$id]['last_run'] = time();
    }

    public function stage()
    {
        return $this->worker->getStage();
    }

    public function workerLastError()
    {
        return $this->worker->getLastError();
    }

    public static function log($level, $message, $context = [])
    {
        $send_log = new SendLog(new Client, new Api);
        $dto = new SendLogDto(random_int(10000, 99999), $level, ['message' => $message, 'context' => $context]);
        $send_log->execute($dto);
    }
}
