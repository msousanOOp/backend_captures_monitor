<?php

use React\EventLoop\Loop;
use Sohris\Core\Utils;

include "vendor/autoload.php";


$report = $argv[1];

$method = array_key_exists(2, $argv) ? $argv[2] : "run";
if (!class_exists($report)) {
    throw new Exception("Event is not register!");
}

if (!method_exists($report, $method)) {
    throw new Exception("Method not exist in Event!");
}
$func = $report . "::" . $method;

// Loop::addPeriodicTimer(0.5, function () use ($func, $report, $method) {

//     echo "Running $func" . PHP_EOL;
//     $start = Utils::microtimeFloat();
//     \call_user_func($report . "::" . $method);
//     $end = Utils::microtimeFloat();
//     echo "Finish $func  -  " . ($end - $start) . "sec " . PHP_EOL; 
// });

echo "Running $func" . PHP_EOL;
$start = Utils::microtimeFloat();
\call_user_func($report . "::" . $method);
$end = Utils::microtimeFloat();
echo "Finish $func  -  " . ($end - $start) . "sec " . PHP_EOL;
Loop::run();
