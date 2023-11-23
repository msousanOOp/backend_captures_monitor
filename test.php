<?php

use Monitor\App\Task\Infrastructure\Curl;
use Monitor\App\Task\Infrastructure\Oracle;

include "vendor/autoload.php";

$file = new Curl;
$config = [
    "host" => "pool.hti.dbsnoop.com",
    "port" => "80",
];
$file->setConfig($config);

$query = [
    "path"=> '/ping',
    "method" => "POST", 
    "result" => [
        "success" => '/\"status\":\"(.*)\",/',
        "data" => '/\"data\":\"(.*)\"/'
    ]   
];
$result = $file->run(123, json_encode($query))->toArray();

var_dump($result);
