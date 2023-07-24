<?php

use Sohris\Core\Server;
use Sohris\Core\Utils;

include __DIR__ . "/../bootstrap.php";

const EVENTS = ['monitor', 'command', 'scheduler'];

try {
    $events = [];

    foreach (EVENTS as $event) {
        $name = $event;
        $root_path = Server::getRootDir();
        $path = "$root_path/storage/statistics/$name";

        Utils::checkFolder($path, 'create');

        if (file_exists("$path/stats.json"))
            $events[] = json_decode(file_get_contents("$path/stats.json"), true);
    }

    foreach ($events as $event) {
        if (empty($event)) continue;
        if (time() - $event['last_run'] > 300) {
            printf("Killing", "STATISTICS");
            $pid = file_get_contents("/app/pid");
            exec('kill ' . $pid);
        }
    }
    printf("FINISH", "STATISTICS");
} catch (Exception $e) {
    printf($e->getMessage(), "STATISTICS");
} catch (Throwable $e) {
    printf($e->getMessage(), "STATISTICS");
}
