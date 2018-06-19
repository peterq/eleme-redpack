<?php

namespace App;

use App\Process\IpcProxy;
use Hanson\Vbot\Foundation\Vbot;

class MessageFetcher {

    protected $masterIpc;

    protected $vbot;

    public function __construct(\swoole_process $process, Vbot $vbot)
    {
        $this->masterIpc = new IpcProxy($process, $this);
        $this->vbot = $vbot;
        $this->init();
    }

    protected function init()
    {
        $vbot = $this->vbot;
        $vbot->messageHandler->setHandler(function ($message) {
            $this->notifyMaster('message', $message);
        });
        $vbot->server->listen();
    }

    protected function notifyMaster($event, $data = [])
    {
        $this->masterIpc->callRemoteAsync('onFetcherEvent', compact('event', 'data'));
    }

}
