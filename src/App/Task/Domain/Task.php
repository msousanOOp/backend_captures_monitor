<?php


namespace Monitor\App\Task\Domain;

use DateTime;
use Monitor\App\Shared\Timer;
use Monitor\App\Task\Domain\Interfaces\ICollector;

class Task
{

    private string $id;
    private int $instance;
    private string $service;
    private string $connector;
    private array $connector_config;
    private string $command;
    private string $type;
    private array $replacer_command = [];
    private string $rollback;
    private Timer $timer;
    private int $last_run;
    private DateTime $last_running;

    /**
     * @var 
     */
    private array $results;


    public function __construct(
        string $id,
        int $instance,
        string $service,
        string $connector,
        array $connector_config,
        string $command,
        Timer $timer,
        string $type
    ) {
        $this->id = $id;
        $this->instance = $instance;
        $this->service = $service;
        $this->connector = $connector;
        $this->connector_config = $connector_config;
        $this->command = $command;
        $this->timer = $timer;
        $this->type = $type;
    }

    public function setRollback(string $rollback)
    {
        $this->rollback = $rollback;
    }

    public function setReplacer(array $replacer)
    {
        $this->replacer_command = $replacer;
    }

    public function setLastRun($last_run)
    {
        if (empty($last_run) && $this->timer->type() == Timer::INTERVAL)
            $this->last_run = time() - (2 * $this->timer->getTimer());
        else
            $this->last_run = strtotime($last_run);
    }

    public function instance(): int
    {
        return $this->instance;
    }

    public function command(): string
    {
        $command = $this->command;
        if (!empty($this->replacer_command)) {
            foreach ($this->replacer_command as $reference => $value)
                $command = str_replace("[[" . strtoupper($reference) . "]]", $value, $command);
        }

        return $command;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function service(): string
    {
        return $this->service;
    }

    public function timer(): Timer
    {
        return $this->timer;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function connector(): ICollector
    {
        $connector =  CollectorFactory::get($this->connector);
        $connector->setConfig($this->connector_config);

        return $connector;
    }

    public function needRunning(): bool
    {
        if ($this->timer->type() != Timer::INTERVAL) return false;

        if (!isset($this->last_run)) return false;

        return time() - $this->last_run > $this->timer->getTimer();
    }
}
