<?php

namespace App;

use Sohris\Core\ComponentControl;
use Sohris\Core\Server as CoreServer;

class Server extends ComponentControl
{
    private $server;

    public function __construct()
    {
        $this->server = CoreServer::getServer();
    }

    public function install()
    {
    }

    public function start()
    {
    }

}
