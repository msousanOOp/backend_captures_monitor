<?php



namespace Monitor\App\Task\Domain;

class Connections
{
    private array $connections = [];


    public function addConnection($hash, $connection)
    {
        $this->connections[$hash] = $connection;
    }

    public function hasConnection($hash)
    {
        return array_key_exists($hash, $this->connections) && !is_null($this->connections[$hash]);
    }

    public function getConnection($hash)
    {
        return $this->connections[$hash];
    }

    public function deleteConnection($hash)
    {
        $this->connections[$hash] = null;
    }

}