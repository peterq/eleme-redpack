<?php

namespace App\Eleme;

use App\Process\IpcProxy;
use GuzzleHttp\Client;

class Watcher {

    protected $process;

    protected $queue;

    protected $ipcProxy;

    protected $client;

    protected $added = [];

    protected $slave = [
        'open_id' => 'oEGLvjikXN-QjR1sFUB8w03_7G48',
        'sign' => '6a27f505d6ae60da430b9a5a3802f10a'
    ];

    protected $needDelete = [];

    protected $master = [
        'open_id' => 'oEGLvjn-rkb7uwCMQXbFpvmU_XaA',
        'sign' => '5e01aea0b7d5d641434c6aec1b166029'
    ];

    public function __construct(\swoole_process $process)
    {
        $this->process = $process;
        $this->queue = new \SplQueue();
        $this->ipcProxy = new IpcProxy($process, $this);
        $this->client = new Client();
        swoole_timer_tick(100, [$this, 'check']);
    }

    public function check()
    {
        if (!$this->queue->count())
            return;
        $task = $this->queue->dequeue();
        // 需要删除的任务
        if (isset($this->needDelete[$task['sn']])) {
            unset($this->needDelete[$task['sn']]);
            return;
        }
        // 防止高频调用
        if (isset($task['last_check_time']) && $task['last_check_time'] > time() - 4) {
            $this->queue->enqueue($task);
            return;
        }
        $task['last_check_time'] = time();
        // 小号查看红包情况
        $info = $this->getInformation($this->slave, $task['sn']);
        // 接口出错了
        if (isset($info['message'])) {
            $this->ipcProxy->callRemote('onApiError', [
                'message' => $info['message'],
                'task' => $task
            ]);
            $this->queue->enqueue($task);
            return;
        }
        // 首次获取
        if (!isset($task['fetched'])) {
            // 首次获取就错过了, 就没有意义了
            if ($task['lucky'] <= count($info['promotion_records']))
                return;
            $this->ipcProxy->callRemote('onNewTask', [
                'sn' => $task['sn'],
                'fetched' => count($info['promotion_records']),
                'lucky' => $task['lucky']
            ]);
            $task['fetched'] = count($info['promotion_records']);
            $this->queue->enqueue($task);
            return;
        }

        // 判断是否有更新, 没有更新放队列尾部, 不做其他处理
        if ($task['fetched'] == count($info['promotion_records'])) {
            $this->queue->enqueue($task);
            return;
        }

        // 更新已抢人数
        $task['fetched'] = count($info['promotion_records']);

        // 下一个人是大奖
        if ($task['lucky'] == $task['fetched'] + 1) {
            $this->ipcProxy->callRemote('onNextIsLucky', [
                'sn' => $task['sn']
            ]);
            $this->queue->enqueue($task);
        } else if ($task['fetched'] >= $task['lucky']) {// 大奖被人领走了
            $lucky = $info['promotion_records'][$task['lucky'] - 1];
            $this->ipcProxy->callRemote('onLuckyToken', [
                'sn' => $task['sn'],
                'desc' => $lucky['sns_username'] . ' 手气最佳, 领走' . $lucky['amount'] . '元'
            ]);
        }  else { // 还再等待更多的人抢
            $this->ipcProxy->callRemote('onUpdate', $task);
            $this->queue->enqueue($task);
        }
    }

    public function fetchForMaster($args)
    {
        return $this->getInformation($this->master, $args['sn']);
    }

    protected function getInformation($user, $group_sn)
    {
        $resp = $this->client->post('https://h5.ele.me/restapi//marketing/promotion/weixin/' . $user['open_id'], [
            'form_params' => [
                'group_sn' => $group_sn,
                'sign' => $user['sign']
            ],
            'http_errors' => false
        ]);
        return json_decode($resp->getBody()->getContents(), true);
    }

    public function hello($args)
    {
        return 'hello ' . $args['name'];
    }

    public function addTask($args)
    {
        if (isset($this->added[$args['sn']])) return;
        $this->added[$args['sn']] = 1;
        $this->queue->enqueue([
            'number' => $args['number'],
            'sn' => $args['sn'],
            'lucky' => $args['lucky']
        ]);
    }

    public function deleteTask($args)
    {
        if (!isset($this->added[$args['sn']])) return false;
        $this->needDelete[$args['sn']] = 1;
        return true;

    }

    public function taskList()
    {
        $result = [];
        foreach ($this->queue as $item)
            $result[] = $item;
        return $result;
    }
}
