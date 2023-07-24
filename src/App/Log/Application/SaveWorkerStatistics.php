<?php

namespace Monitor\App\Log\Application;

use Sohris\Core\Server;
use Sohris\Core\Utils;

class SaveWorkerStatistics
{

    public function execute(SaveWorkerStatisticsDto $data): void
    {
        $id = $data->sub_worker_id;
        $root_path = Server::getRootDir();
        $path = "$root_path/storage/statistics/sub_workers/$id";
        Utils::checkFolder($path, 'create');
        file_put_contents("$path/stats.json", json_encode($data->toArray()));
    }
}
