<?php
namespace App;


class Command {

    protected $cache;

    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->cache = Cache::getInstance();
    }

    public function notify($message, $switch = 'on')
    {
        $this->checkAdmin($message);

        if ($switch == 'on') {
            if ($this->cache->get('notify'))
                throw new CommandException('再次开启通知前请关闭之前的通知');
            $this->cache->forever('notify', $message['from']['UserName']);
            return '通知开启成功';
        }

        if ($switch == 'off') {
            $notify = $this->cache->get('notify');
            if ($notify != $message['from']['UserName'])
                throw new CommandException('未再此开启通知');
            $this->cache->forever('notify', '');
            return '通知关闭成功';
        }
        return '参数为 on 或 off';
    }

    public function auto_fetch($message, $switch = 'on')
    {
        $this->checkAdmin($message);

        if ($switch == 'on') {
            if ($this->cache->get('auto_fetch'))
                throw new CommandException('已开启, 无需再次开启');
            $this->cache->forever('auto_fetch', true);
            return '自动抢最大红包开启成功';
        }

        if ($switch == 'off') {
            if (!$this->cache->get('auto_fetch'))
                throw new CommandException('未开启, 无需关闭');
            $this->cache->forever('auto_fetch', false);
            return '自动抢最大红包关闭成功';
        }
        return '参数为 on 或 off';
    }

    public function bind($message)
    {
        if ($this->cache->get('admin'))
             throw new CommandException('管理员已绑定至 ' . $this->cache->get('admin_name') . '更换管理员请先解绑');
        $this->cache->forever('admin', $message['username']);
        $this->cache->forever('admin_name', $this->sender($message)['NickName']);
        return '成功设置' . $this->cache->get('admin_name') .'为管理员';
    }

    public function unbind($message)
    {
        $this->checkAdmin($message);
        $this->cache->set('admin', '');
        return '已解绑管理员身份';
    }

    public function list($message)
    {
        $this->checkAdmin($message);
        $list = $this->manager()->taskList();
        $arr = [];
        foreach ($list as $task) {
            $arr[] = $task['number'] . '        ' . ($task['fetched'] ?? '[未知]') . '         ' . $task['lucky'];
        }
        empty($arr) or array_unshift($arr, '序号   已抢   最大');
        return !empty($arr) ? join("\n", $arr) : '任务列表为空';
    }

    public function link($message, $number)
    {
        $map = $this->app->getManager()->getLinkMap();
        if (!isset($map[$number]))
            throw new CommandException('任务不存在');
        return 'https://h5.ele.me/hongbao/#sn=' . $map[$number];
    }

    protected function manager()
    {
        return $this->app->getManager();
    }

    protected function checkAdmin($message)
    {
        if ($message['username'] != $this->cache->get('admin'))
            throw new CommandException('仅管理员可操作');
    }

    protected function sender($message)
    {
        return $message['fromType'] == 'Self'? vbot('friends')->getAccount(vbot('myself')->username) : ($message['sender'] ?? $message['from']);
    }

}