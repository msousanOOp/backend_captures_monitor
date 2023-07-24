<?php

namespace Monitor\App\Log\Application;

use Sohris\Core\Server;
use Sohris\Core\Utils;

class GetWorkersStatistics
{

    public function execute(): array
    {
        $root_path = Server::getRootDir();
        $path = "$root_path/storage/statistics/sub_workers/";

        Utils::checkFolder($path, 'create');

        $folders = scandir($path);
        $data = [];
        foreach ($folders as $folder) {
            if (in_array($folder, [".", ".."])) continue;
            $data[] = json_decode(file_get_contents("$path/$folder/stats.json"), true);
        }

        return $data;
    }
}
