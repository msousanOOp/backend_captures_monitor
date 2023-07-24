<?php

namespace Monitor\App\Shared;


class TasksServerHash
{
    private string $hash;

    public function __construct(string $hash)
    {
        $this->hash = $hash;
    }

    public function hash()
    {
        return $this->hash;
    }

    public function __toString()
    {
        return $this->hash;
    }
}