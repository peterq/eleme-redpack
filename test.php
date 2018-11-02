<?php

include 'vendor/autoload.php';

$http = new \GuzzleHttp\Client();

$resp = $http->get('https://wx.qq.com/', [
    'stream' => true
]);

function readStreamWithCoroutine($stream)
{
    stream_set_blocking($stream, 0);
    $result = '';
    $co_id = swoole_coroutine::getuid();
    swoole_event_add($stream, function ($stream) use (&$result, $co_id) {
        $data = fread($stream, 1024);
        if (empty($data) && feof($stream)) {
            swoole_event_del($stream);
            swoole_coroutine::resume($co_id);
            return;
        }
        $result .= $data;
    });
    swoole_coroutine::suspend();
    return $result;
}
$stream = $resp->getBody()->detach();

go(function () use ($stream) {
    echo readStreamWithCoroutine($stream);
});