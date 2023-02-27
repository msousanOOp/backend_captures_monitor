<?php

namespace App;

use React\EventLoop\Loop;
use Sohris\Core\ComponentControl;
use Sohris\Core\Server as CoreServer;
use Sohris\Event\Event;

class Server extends ComponentControl
{
    private $server;
    private $runner;

    /**
     * @var Event
     */
    private $event;


    public function __construct()
    {
        $this->server = CoreServer::getServer();
    }

    public function install()
    {
    }

    public function start()
    {
        $this->event = $this->server->getComponent("Sohris\\Event\\Event");
        $this->runner = $this->event->getEvent("App\\Runner");
        Loop::addPeriodicTimer(360, fn () => $this->restartCollector());
        Loop::addPeriodicTimer(60, fn () => $this->sendStats());
    }

    private function restartCollector()
    {
        $last_run = file_get_contents(CoreServer::getRootDir() . DIRECTORY_SEPARATOR . "last_run");
        if ((time() - $last_run) >= 60)
            $this->runner->restart();
    }

    private function sendStats()
    {

        $base = json_decode(file_get_contents(CoreServer::getRootDir() .DIRECTORY_SEPARATOR . "stats"), true);
        $base['uptime'] = $this->server->getUptime();
        $base['thread_status'] = $this->runner->getStats();
        API::sendStats($base);
    }
}
