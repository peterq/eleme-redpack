<?php
namespace App;


class Cache {

    protected function __construct()
    {
        $this->init();
    }

    public static function getInstance()
    {
        static $instance = null;
        if (!$instance) {
            $instance = new static();
        }
        return $instance;
    }

    /**
     * @var Table
     */
    protected $table;

    protected $dir = '/dev/shm/cache/red_pack/';

    protected function init () {
        if (!is_dir($this->dir)) mkdir($this->dir, 0777, true);
    }

    public function set($key, $data, $minutes = 1) {
        return file_put_contents($this->dir . $key, serialize([time() + 60 * $minutes, $data]));
    }

    public function forever($key, $data)
    {
        return file_put_contents($this->dir . $key, serialize([PHP_INT_MAX, $data]));
    }

    public function get($key, $default = null)
    {
        if (!file_exists($this->dir . $key)) {
            return $default;
        }
        list($timestamp, $data) = unserialize(file_get_contents($this->dir . $key));
        if (time() > $timestamp) {
            unlink($this->dir . $key);
            return $default;
        }
        return $data;
    }

    public function del($key)
    {
        if (!file_exists($this->dir . $key)) return false;
        return unlink($this->dir . $key);
    }
}
