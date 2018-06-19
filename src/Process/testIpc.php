<?php

include './vendor/autoload.php';

class Foo {

    protected $ipcProxy;

    public function __construct()
    {
        // 新进程中创建bar
        $process =  new swoole_process(function (swoole_process $process) {
            new Bar($process);
        });
        $this->ipcProxy = new \App\Process\IpcProxy($process, $this);
        $process->start();
    }

    public function master()
    {
        return 'im master';
    }
    public function testRemoteHello($name)
    {
        return $this->ipcProxy->callRemote('hello', compact('name'));
    }
}

class Bar {
    protected $ipcProxy;

    public function __construct(swoole_process $process)
    {
        $this->ipcProxy = new \App\Process\IpcProxy($process, $this);
        swoole_timer_after(100, function () {
           swoole_coroutine::create(function () {
               echo $this->ipcProxy->callRemote('master');
           });
        });
    }

    public function hello($args) {
        return 'hello ' . $args['name'] . $this->ipcProxy->callRemote('master');
    }
}

swoole_coroutine::create(function () {
    echo __LINE__;
    $foo = new Foo();
    echo $foo->testRemoteHello('peter');
    echo __LINE__;
});

