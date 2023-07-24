<?php


namespace Monitor\App\TaskResult\Domain;

use DateTime;
use Sohris\Core\Utils;

class TaskResult
{
    private string $status;
    private string $id;
    private string $service = "";
    private string $type = "";
    private int $instance = 0;
    private string $connector = "";
    private array $result = [];
    private int $timestamp_result;
    private array $current_timer = [];
    private array $timer = [];
    private array $log = [];
    private int $start;

    public function __construct(string $id,  string $connector)
    {
        $this->id = $id;
        $this->connector = $connector;
        $this->start = time();
    }

    public function setInstance(int $instance): void
    {
        $this->instance = $instance;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function setService(string $service): void
    {
        $this->service = $service;
    }

    public function status()
    {
        return $this->status;
    }

    public function toArray(): array
    {

        return [
            "id" => $this->id,
            "connector" => $this->connector,
            "instance" => $this->instance,
            "service" => $this->service,
            "type" => $this->type,
            "start" => $this->start,
            "timestamp" => $this->timestamp_result,
            "status" => $this->status,
            "result" => $this->result,
            "timers" => $this->timer,
            "log" => $this->log
        ];
    }

    public function log($type, $level, $code, $message)
    {
        $this->log = ["type" => $type, "level" => $level, "code" => $code, "message" => $message];
    }

    public function startTimer(string $name)
    {
        $this->current_timer[$name] = Utils::microtimeFloat() * 1000;
    }

    public function finishTimer(string $name)
    {
        if (array_key_exists($name, $this->current_timer)) {
            $this->timer[$name] = round((Utils::microtimeFloat() * 1000) - $this->current_timer[$name]);
            unset($this->current_timer[$name]);
        } else
            $this->timer[$name] = round((Utils::microtimeFloat() * 1000) - $this->timer);
    }

    public function setStatus(string $status)
    {
        $this->status = $status;
    }

    public function setResult(array $result)
    {
        $this->result = $result;
    }
    public function finish()
    {
        $this->timestamp_result = time();
    }

    public function result(): array
    {
        return $this->result;
    }
    public function timers(): array
    {
        return $this->timer;
    }

    public function logs(): array
    {
        return $this->log;
    }
}
