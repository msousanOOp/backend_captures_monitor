<?php

use Monitor\App\Task\Infrastructure\Curl;
use Monitor\App\Task\Infrastructure\Postgresql;

include "vendor/autoload.php";

$file = new Postgresql;

$config = [
    "db_host_ip" => "10.0.0.101",
    "db_host_port" => "5430",
    "db_user" => "postgre",
    "db_password" => "password"
];

$file->setConfig($config);

var_dump(json_encode($query));

$result = $file->run(123, "SELECT
datid,
datname,
pid,
usename,
client_addr,
state,
wait_event_type,
query,
backend_type,
EXTRACT(EPOCH FROM NOW() - backend_start) AS seconds_since_backend_start,
EXTRACT(EPOCH FROM NOW() - xact_start) AS seconds_since_xact_start,
EXTRACT(EPOCH FROM NOW() - query_start) AS seconds_since_query_start,
EXTRACT(EPOCH FROM NOW() - state_change) AS seconds_since_state_change
FROM
pg_stat_activity;")->toArray();

var_dump($result);
