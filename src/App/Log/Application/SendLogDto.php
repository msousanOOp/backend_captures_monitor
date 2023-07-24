<?php

namespace Monitor\App\Log\Application;

class SendLogDto
{

    public string $ref_id;
    public string $level;
    public int $timestamp;
    public array $params;

    public function __construct(
        string $ref_id,
        string $level,
        array $params = []
    ) {
        $this->ref_id = $ref_id;
        $this->level = $level;
        $this->params = $params;
        $this->timestamp = time();
    }
}
