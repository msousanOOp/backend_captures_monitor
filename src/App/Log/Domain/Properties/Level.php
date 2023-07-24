<?php

namespace Monitor\App\Log\Domain\Properties;

class Level
{
    const DEBUG = 0;
    const INFO = 1;
    const WARNING = 2;
    const CRITICAL = 3;

    private int $level;

    public function __construct(int $level)
    {
        switch($level)
        {
            case 0:
            case 1:
            case 2:
            case 3:
                $this->level = $level;
                break;
            default:
                

        }
    }
}
