<?php

use Monitor\App\Task\Infrastructure\Curl;


include "vendor/autoload.php";

$file = new Curl;

$config = [
    "host" => "google.com",
    "port" => "80",
];

$file->setConfig($config);

// $query = [
//     "path"=> '/ping',
//     "method" => "POST", 
//     "result" => [
//         "success" => '/\"status\":\"(.*)\",/',
//         "data" => '/\"data\":\"(.*)\"/'
//     ]   
// ];

$query = [
    "path" => 'nginx_status',
    "method" => "GET",
    "result" => [
        "req_status" =>1,
        "req_headers" => 1,
        "req_body" => 1,
        "connections" => '/\"status\":\"(.*)\",/',
        "req_per_sec" => '/\"data\":\"(.*)\"/'
    ]
];

var_dump(json_encode($query));

$result = $file->run(123, json_encode($query))->toArray();

var_dump($result);
