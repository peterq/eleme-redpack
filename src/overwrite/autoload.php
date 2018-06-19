<?php

spl_autoload_register(function ($cls) {
    $map = [
        'Hanson\Vbot\Core\MessageHandler' => __DIR__ . '/MessageHandler.php',
        'Hanson\Vbot\Core\Server' => __DIR__  . '/Server.php',
    ];

    if (isset($map[$cls])) {
        echo $cls . ' loaded' . PHP_EOL;
        include $map[$cls];
        return true;
    }

}, true, true);
