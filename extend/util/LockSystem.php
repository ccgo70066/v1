<?php

namespace util;

use Redis;

class LockSystem
{
    const EXPIRE = 5;
    private $redis;

    public function __construct()
    {
        $config = config('cache.redis');
        $redis = new Redis();
        $redis->connect($config['host'], $config['port']);
        $redis->auth($config['password']);
        $redis->select(1);
        $this->redis = $redis;
    }

    public function getLock($key, $timeout = self::EXPIRE)
    {
        $waitime = 20000;
        $totalWaitime = 0;
        $time = $timeout * 1000000;
        while ($totalWaitime < $time && false == $this->redis->setnx($key, 1)) {
            usleep($waitime);
            $totalWaitime += $waitime;
        }
        $this->redis->expire($key, $timeout);
        if ($totalWaitime >= $time) {
            $this->releaseLock($key);
        }
    }

    public function releaseLock($key)
    {
        $this->redis->del($key);
    }
}
