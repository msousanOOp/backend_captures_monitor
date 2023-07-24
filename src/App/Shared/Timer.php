<?php

namespace Monitor\App\Shared;


class Timer
{
    const INTERVAL = 0;
    const CRON = 1;
    const DATETIME = 2;
    const INSTANTE = 3;

    private $timer;
    private string $type = "";

    public function __construct(string $timer, string $type = self::INTERVAL)
    {
        $this->type = $type;
        $this->timer = $timer;
    }

    public static function create(array $params): Timer
    {
        if(array_key_exists("timer_type", $params))
        {
            switch($params['timer_type'])
            {
                case "CRON":
                    return new self($params['timer_value'], self::CRON);
                case "DATE": 
                    return new self($params['timer_value'], self::DATETIME);
            }
            
        }else if(array_key_exists("frequency", $params))
        {
            return new self($params['frequency'], self::INTERVAL);
            
        }
    }

    public function getTimerFunction(): string
    {
        switch ($this->type) {
            case 0:
                return "callFunction";
            case 1:
                return "callCronFunction";
            case 2:
                return "callTimeoutFunction";
            default:
                return "";
        }
    }

    public function getTimer()
    {
        switch ($this->type) {
            case 0:
            case 1:
                return $this->timer;
            case 2:
                return time() - $this->timer;
            default: 
                return 0;
        }
    }
}
