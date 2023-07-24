<?php

namespace Monitor\App\Log\Application;

use Sohris\Core\Server;
use Sohris\Core\Utils;

class SaveEventStatistics
{

    public function execute(SaveEventStatisticsDto $data): void
    {
        $name = $data->event_name;
        $root_path = Server::getRootDir();
        $path = "$root_path/storage/statistics/$name";
        Utils::checkFolder($path, 'create');
        file_put_contents("$path/stats.json", json_encode($data->toArray()));
    }
}
