<?php

namespace app\common\driver\cache;

use think\cache\Driver;

/**
 * Redis缓存驱动（支持自动重连）
 * 适用于 Workerman 等长连接环境
 */
class Redis extends Driver
{
    protected $options = [
        'host'       => '127.0.0.1',
        'port'       => 6379,
        'password'   => '',
        'select'     => 0,
        'timeout'    => 0,
        'expire'     => 0,
        'persistent' => false,
        'prefix'     => '',
    ];

    /**
     * 构造函数
     */
    public function __construct($options = [])
    {
        if (!extension_loaded('redis')) {
            throw new \BadFunctionCallException('not support: redis');
        }
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
        $this->connect();
    }

    /**
     * 连接Redis
     */
    protected function connect()
    {
        $this->handler = new \Redis;
        if ($this->options['persistent']) {
            $this->handler->pconnect($this->options['host'], $this->options['port'], $this->options['timeout'], 'persistent_id_' . $this->options['select']);
        } else {
            $this->handler->connect($this->options['host'], $this->options['port'], $this->options['timeout']);
        }

        if ('' != $this->options['password']) {
            $this->handler->auth($this->options['password']);
        }

        if (0 != $this->options['select']) {
            $this->handler->select($this->options['select']);
        }
    }

    /**
     * 检查连接并自动重连
     */
    protected function checkConnection()
    {
        try {
            $this->handler->ping();
        } catch (\RedisException $e) {
            // 连接断开，尝试重连
            $this->connect();
        }
    }

    /**
     * 判断缓存
     */
    public function has($name)
    {
        $this->checkConnection();
        return (bool) $this->handler->exists($this->getCacheKey($name));
    }

    /**
     * 读取缓存
     */
    public function get($name, $default = false)
    {
        $this->checkConnection();
        $value = $this->handler->get($this->getCacheKey($name));
        if (is_null($value) || false === $value) {
            return $default;
        }

        try {
            $result = 0 === strpos($value, 'think_serialize:') ? unserialize(substr($value, 16)) : $value;
        } catch (\Exception $e) {
            $result = $default;
        }

        return $result;
    }

    /**
     * 写入缓存
     */
    public function set($name, $value, $expire = null)
    {
        $this->checkConnection();
        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }
        if ($expire instanceof \DateTime) {
            $expire = $expire->getTimestamp() - time();
        }
        if ($this->tag && !$this->has($name)) {
            $first = true;
        }
        $key   = $this->getCacheKey($name);
        $value = is_scalar($value) ? $value : 'think_serialize:' . serialize($value);
        if ($expire) {
            $result = $this->handler->setex($key, $expire, $value);
        } else {
            $result = $this->handler->set($key, $value);
        }
        isset($first) && $this->setTagItem($key);
        return $result;
    }

    /**
     * 自增缓存
     */
    public function inc($name, $step = 1)
    {
        $this->checkConnection();
        $key = $this->getCacheKey($name);
        return $this->handler->incrby($key, $step);
    }

    /**
     * 自减缓存
     */
    public function dec($name, $step = 1)
    {
        $this->checkConnection();
        $key = $this->getCacheKey($name);
        return $this->handler->decrby($key, $step);
    }

    /**
     * 删除缓存
     */
    public function rm($name)
    {
        $this->checkConnection();
        return $this->handler->del($this->getCacheKey($name));
    }

    /**
     * 清除缓存
     */
    public function clear($tag = null)
    {
        $this->checkConnection();
        if ($tag) {
            $keys = $this->getTagItem($tag);
            foreach ($keys as $key) {
                $this->handler->del($key);
            }
            $this->rm('tag_' . md5($tag));
            return true;
        }
        return $this->handler->flushDB();
    }
}
