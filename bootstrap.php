<?php
include "vendor/autoload.php";

use Sohris\Core\Server;

$app = new Server();

$app->setRootDir(__DIR__);
$app->loadingServer();