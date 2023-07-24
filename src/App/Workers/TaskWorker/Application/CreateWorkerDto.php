<?php

namespace Monitor\App\Workers\TaskWorker\Application;

use Monitor\App\Shared\TasksServerHash;

class CreateWorkerDto
{
    public TasksServerHash $hash;
    public string $type;

    public function __construct(TasksServerHash $hash, string $type)
    {
        $this->hash = $hash;
        $this->type = $type;
    }
}
