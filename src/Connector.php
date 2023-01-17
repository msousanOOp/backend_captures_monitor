<?php

namespace App;

use Sohris\Core\Logger;
use Sohris\Core\Utils;

class Connector
{
    protected $configs;
    protected $statistics = [];
    protected $invalidate_op = false;
    protected $connector_name = '';
    private $timer = 0;
    private $timers = [];
    private $logs = [];
    private $captures = [];
    private $logger;
    

    public function __construct(array $connect_setings = [])
    {
        $this->logger = new Logger("Connector");
        $this->configs = $connect_setings;
    }

    protected function log($type, $level, $code, $message)
    {

        $this->logs[] = ["type" => $type, "level" => $level, "code" => $code, "message" => $message];
        $this->logger->warning($message);
    }

    protected function reloadTime()
    {
        $this->timer = Utils::microtimeFloat();
    }

    protected function startTime(string $name)
    {
        $this->timers[$name] = Utils::microtimeFloat() * 1000;
    }

    protected function finishTime(string $name)
    {
        if (array_key_exists($name, $this->timers)) {
            $this->statistics[$name] = round((Utils::microtimeFloat() * 1000) - $this->timers[$name]);
            unset($this->timers[$name]);
        } else
            $this->statistics[$name] = round((Utils::microtimeFloat() * 1000) - $this->timer);
    }

    protected function addCapture($task, $capture)
    {
        $this->captures[$task] = [
            'connector' => $this->connector_name,
            'result' => $capture
        ];
    }

    public function getContent()
    {
        return [
            "captures" => $this->captures,
            "timers" => $this->statistics,
            "logs" => $this->logs
        ];
    }
}
