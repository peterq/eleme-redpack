<?php

namespace App;

use App\Eleme\Manager;
use App\Process\IpcProxy;
use function GuzzleHttp\Psr7\str;
use Hanson\Vbot\Foundation\Vbot;
use Hanson\Vbot\Message\Text;

class App {

    public static $instance;

    protected $config;

    /**
     * @var Vbot;
     */
    protected $vbot;

    protected $msgHandler;

    protected $cache;

    protected $fetcherIpc;

    /**
     * @var Manager
     */
    protected $manager;

    protected function __construct($config)
    {
        $this->config = $config;
        $this->cache = Cache::getInstance();
        $this->initVbot();
    }

    protected function initVbot()
    {
        $this->vbot = $vbot = new Vbot($this->config);
        $this->msgHandler = new MessageHandler($this);

        $vbot->observer->setQrCodeObserver(function($qrCodeUrl) {
            println('二维码', $qrCodeUrl);
        });
        $vbot->observer->setLoginSuccessObserver(function(){
            $this->manager = new Manager($this);
        });
        // master 进程需要发送消息, 所以必须在master进程完成登录
        $vbot->server->loginAndInit();

        // 获取消息会有长时间的http io 阻塞, 在新进程中执行轮训, 新消息通过ipc 传给master进程
        $process = new \swoole_process(function (\swoole_process $process) {
            swoole_set_process_name('eleme redpack, message fetcher');
            new MessageFetcher($process, $this->vbot);
        });
        $this->fetcherIpc = new IpcProxy($process, $this);
        $process->start();
    }

    public function onFetcherEvent($args)
    {
        switch ($args['event']) {
            case 'message':
                $this->msgHandler->handle($args['data']);
                break;
        }
    }

    public static function init($config)
    {
        if (static::$instance)
            return;
        static::$instance = new static($config);
    }

    public function getManager(){
        return $this->manager;
    }

    public function notify($str)
    {
        echo 'notify: ' . $str . PHP_EOL;
        if (!$this->cache->get('notify')) return;
        Text::send($this->cache->get('notify'), "通知: \n" . $str);
    }
}
