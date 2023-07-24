<?php

namespace Monitor\App\Log\Application;

use Sohris\Core\Server;
use Sohris\Core\Utils;

class GetEventsStatistics
{

    public function execute(GetEventStatisticsDto $dto): array
    {
        $name = $dto->event_name;
        $root_path = Server::getRootDir();
        $path = "$root_path/storage/statistics/$name";

        Utils::checkFolder($path, 'create');

        if (file_exists("$path/stats.json"))
            return json_decode(file_get_contents("$path/stats.json"), true);
        return [];
    }
}
