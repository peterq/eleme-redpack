<?php

namespace App\Eleme;

use App\App;
use App\Cache;
use App\Process\IpcProxy;

class Manager {

    protected $process;

    protected $ipcProxy;

    protected $app;

    protected $linkMap = [];

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->process = $process = new \swoole_process(function (\swoole_process $process) {
            swoole_set_process_name('eleme redpack, watcher');
            new Watcher($process);
        });
        $this->ipcProxy = new IpcProxy($process, $this);
        $process->start();
    }

    public function addTask($sn, $lucky)
    {
        $number = count($this->linkMap);
        $this->linkMap[$number] = $sn;
        return $this->ipcProxy->callRemote('addTask', compact('number', 'sn', 'lucky'));
    }

    public function taskList()
    {
        return $this->ipcProxy->callRemote('taskList');
    }

    public function hello($name)
    {
        return $this->ipcProxy->callRemote('hello', compact('name'));
    }

    public function getLinkMap()
    {
        return $this->linkMap;
    }

    public function onApiError($args)
    {
        $this->app->notify($args['task']['sn'] . ' 接口调用出错' . $args['message']);
    }

    public function onNewTask($args)
    {
        $this->app->notify('新任务 :' . $args['sn'] . ' 第' . $args['lucky'] . '个手气最佳, 已领取' . $args['fetched'] . '个');
    }

    public function onNextIsLucky($args)
    {
        $this->app->notify('下一个红包就是最佳手气了: https://h5.ele.me/hongbao/#sn=' . $args['sn']);
        if (Cache::getInstance()->get('auto_fetch', false)) {
            $this->ipcProxy->callRemoteAsync('fetchForMaster', $args);
        }
    }

    public function onLuckyToken($args)
    {
        $this->app->notify('手气最佳诞生:' . $args['sn'] . ', ' . $args['desc']);
    }

    public function onUpdate($task)
    {
        $this->app->notify('有人抢红包了:' . $task['sn'] . ', 已抢' . $task['fetched'] . '个, 第' . $task['lucky'] . '个手气最佳');
    }
}
