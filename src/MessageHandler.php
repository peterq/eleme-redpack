<?php

namespace App;

use function GuzzleHttp\Psr7\str;
use Hanson\Vbot\Message\Text;
use Illuminate\Support\Collection;

class MessageHandler {

    protected $app;

    protected $command;


    public function __construct(App $app)
    {
        $this->app = $app;
        $this->command = new Command($app);
    }

    public function handle(Collection $message)
    {
        if ($message['type'] === 'share') {
            if ($message['app'] == '饿了么') {
                if (preg_match('/;lucky_number=(\d+).*;sn=([a-z\d]+)/', $message['url'], $matches)) {
                    $this->app->getManager()->addTask($matches[2], $matches[1]);
                    // var_dump($matches);
                }
                // Text::send('filehelper', '收到分享:'.$message['title'].$message['description'].$message['app'].$message['url']);
            }
        }
        if ($message['type'] == 'text') {
            if (in_array($message['fromType'], ['Group', 'Friend', 'Self'])) {
                $ret = $this->handleCommand($message);
                $ret and Text::send($message['from']['UserName'], '@'. $this->sender($message)['NickName'] ." \n" . $ret);
            }
        }
    }

    protected function sender($message)
    {
        return $message['fromType'] == 'Self'? vbot('friends')->getAccount(vbot('myself')->username) : ($message['sender'] ?? $message['from']);
    }

    protected function handleCommand(Collection $message)
    {
        $str = trim($message['message']);
        if (preg_match('/^#([\d_a-z]+)( (.+))?/', $str, $matches)) {
            $method = $matches[1];
            $arg = $matches[3] ?? '';
            if (!method_exists($this->command, $method)) {
                return '该命令不存在';
            }
            try {
                return $this->command->{$method}($message, $arg);
            } catch (\Throwable $exception) {
                return $exception->getMessage();
            }
        }

    }

}
