<?php

include './vendor/autoload.php';

if (!function_exists('println')) {
    function println(...$args) {
        foreach ($args as $arg) {
            echo PHP_EOL.'---------------------------------------------------------' . PHP_EOL;
            echo $arg;
            echo PHP_EOL.'--------------------------------------------------------- ' .PHP_EOL;
        }
    }
}

\App\App::init(require 'config.php');