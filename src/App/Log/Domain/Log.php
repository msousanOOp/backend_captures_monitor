<?php


namespace Monitor\App\Log\Domain;

use Monitor\App\Log\Domain\Properties\Level;
use Sohris\Core\Utils;

class Log
{

    private Level $level;
    private string $message;
    private $extra;
    private $debug = true;

    public function __construct(Level $level, string $message, $extra)
    {
        $this->level = $level;
        $this->message = $message;
        $this->extra = $extra;
    }

    public static function debug(string $message, string $context)
    {

        $config = Utils::getConfigFiles('system');
        if (array_key_exists('debug', $config) && $config['debug'] === true) {
            $time = date("Y-m-d H:i:s");
            echo "[$time][DEBUG][$context] " . $message . PHP_EOL;
        }
    }
}
