<?php

namespace Monitor\App\Log\Application;

use Sohris\Core\Server;
use Sohris\Core\Utils;

class DeleteWorkerStatistics
{

    public function execute(DeleteWorkerStatisticsDto $dto): void
    {
        $root_path = Server::getRootDir();
        $path = "$root_path/storage/statistics/sub_workers/";

        Utils::checkFolder($path, 'create');

        $folders = scandir($path);
        $id = $dto->sub_worker_id;

        foreach ($folders as $folder) {
            if (in_array($folder, [".", ".."])) continue;
            if ($folder == $id)
                rmdir("$path/$folder");
        }
    }
}
