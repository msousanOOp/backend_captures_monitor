<?php

namespace Monitor\App\Task\Domain;

use Monitor\App\API\Domain\Api;
use Monitor\App\API\Infrastructure\Client;
use Monitor\App\Log\Application\SendLog;
use Monitor\App\Log\Application\SendLogDto;
use Monitor\App\Task\Domain\Interfaces\ICollector;

abstract class Collector implements ICollector
{
    protected int $limit = 0;
    protected string $connection_hash = "";
    private SendLog $send_log;
    private static Connections $connections;

    public function __construct()
    {
        if (empty(self::$connections) || !self::$connections)
            self::$connections = new Connections;

        $this->send_log = new SendLog(new Client, new Api);
    }

    private static function create()
    {
        if (!self::$connections || !isset(self::$connections))
            self::$connections = new Connections;
    }

    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }
    public function cleanLimit(): void
    {
        $this->limit = 0;
    }

    protected function hasConnection(): bool
    {
        self::create();
        return self::$connections->hasConnection($this->connection_hash);
        //return array_key_exists($this->connection_hash, self::$connections) && !is_null(self::$connections[$this->connection_hash]);
    }


    protected function setHash(string $hash)
    {
        $this->connection_hash = $hash;
    }

    protected function setConnection($connection)
    {
        self::create();
        self::$connections->addConnection($this->connection_hash, $connection);
        //self::$connections[$this->connection_hash] = $connection;
    }

    protected function getConnection()
    {
        self::create();
        return self::$connections->getConnection($this->connection_hash);
    }

    protected function deleteConnection()
    {
        self::create();
        self::$connections->deleteConnection($this->connection_hash);
    }

    protected function log($level, $message, $context = [])
    {
        $dto = new SendLogDto(random_int(10000, 99999), $level, ['message' => $message, "context" => $context]);
        $this->send_log->execute($dto);
    }
}
