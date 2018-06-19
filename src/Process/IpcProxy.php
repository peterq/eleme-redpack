<?php

namespace App\Process;

use App\Cache;

class IpcProxy {

    protected $process;

    protected $callWatcherMap = [];

    protected $target;

    public function __construct(\swoole_process $process, $target)
    {
        $this->process = $process;
        $this->target = $target;
        $this->init();
    }

    protected function init()
    {
        swoole_event_add($this->process->pipe, function ($pipe) {
            \swoole_coroutine::create(function ()  {
                $data = $this->process->read();
                $data = $this->unserialize($data);
                $type = $data['type'];
                if ($type == 'call') {
                    $result = $this->target->{$data['func']}($data['args'] ?? []);
                    $this->process->write($this->serialize([
                        'result' => $result,
                        'type' => 'call_return',
                        'call_id' => $data['call_id']
                    ]));
                }
                if ($type == 'call_return') {
                    $this->onResult($data);
                }
                if ($type == 'exit') exit(0);
            });
        });
    }

    protected function onResult($data)
    {
        $call_id = $data['call_id'];
        if (!isset($this->callWatcherMap[$call_id])) return;
        $this->callWatcherMap[$call_id]['result'] = $data['result'];
        \swoole_coroutine::resume($this->callWatcherMap[$call_id]['co_id']);
    }

    // 必须在协程环境下调用
    public function callRemote($func, $args = [])
    {
        $call_id = str_random();
        $co_id = \swoole_coroutine::getuid();
        $this->process->write($this->serialize([
            'type' => 'call',
            'func' => $func,
            'args' => $args,
            'call_id' => $call_id
        ]));
        $this->callWatcherMap[$call_id] = compact('co_id');
        \swoole_coroutine::suspend($co_id);
        $result = $this->callWatcherMap[$call_id]['result'];
        unset($this->callWatcherMap[$call_id]);
        return $result;
    }

    // 可以不在协程中调用
    public function callRemoteAsync($func, $args = [])
    {
        $call_id = str_random();
        return $this->process->write($this->serialize([
            'type' => 'call',
            'func' => $func,
            'args' => $args,
            'call_id' => $call_id
        ]));
    }

    protected function serialize($data)
    {
        $str = serialize($data);
        if (strlen($str) > 1024) {
            $cache_key = str_random();
            Cache::getInstance()->forever($cache_key, $str);
            $str = serialize([
                'type' => 'cache',
                'cache_key' => $cache_key,
            ]);
        }
        return $str;
    }

    protected function unserialize($data)
    {
        $data = unserialize($data);
        if ($data['type'] == 'cache') {
            $str = Cache::getInstance()->get($data['cache_key']);
            Cache::getInstance()->del($data['cache_key']);
            $data = unserialize($str);
        }
        return $data;
    }
}
