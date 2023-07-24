<?php

namespace Monitor\App\Log\Application;

use Monitor\App\Shared\Utils as SharedUtils;
use Sohris\Core\Server;
use Sohris\Core\Utils;

class ClearStatistics
{

    public function execute(): void
    {
        $root_path = Server::getRootDir();
        $path = "$root_path/storage/statistics";
        Utils::checkFolder($path, 'create');

        $folders = scandir($path);
        foreach ($folders as $folder) {
            if (in_array($folder, [".", ".."])) continue;
            SharedUtils::rrmdir("$path/$folder");
        }
    }
}
