<?php
include "vendor/autoload.php";


echo "     _ _                ___   ___        
  __| | |__  ___ _ __  / _ \ / _ \ _ __  
 / _` | '_ \/ __| '_ \| | | | | | | '_ \ 
| (_| | |_) \__ \ | | | |_| | |_| | |_) |
 \__,_|_.__/|___/_| |_|\___/ \___/| .__/ 
                                |_|         " . PHP_EOL;
echo '===================Colletor====================' . PHP_EOL;

use Sohris\Core\Server;

$app = new Server();

$app->setRootDir(__DIR__);
$app->loadingServer();