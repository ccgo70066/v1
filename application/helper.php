<?php

use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\admin\model\User;
use app\common\exception\ApiException;
use app\common\library\rabbitmq\BaseHandler;
use app\common\library\rabbitmq\BoardNoticeMQ;
use app\common\model\MoneyLog;
use app\common\model\UserBusiness;
use app\common\service\ImService;
use GatewayClient\Gateway;
use think\Db;
use think\Env;
use think\Log;

function getLastSql()
{
    return \think\Db::getLastSql();
}

function traceInDB($content)
{
    db('test')->insert(['content' => json_encode($content)]);
}

if (!function_exists('trace')) {
    function trace($log = '[think]', $level = 'log')
    {
        if ('[think]' === $log) {
            return Log::getLog();
        }
        $back = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        Log::record($back[0]['file'] . ':' . $back[0]['line'], $level);
        Log::record($log, $level);
    }
}

function redis()
{
    static $redis;
    if (!$redis) {
        $config = config('cache.redis');
        $redis = new Redis();
        $redis->connect($config['host'], $config['port']);
        !empty($config['password']) && $redis->auth($config['password']);
        $redis->select($config['select'] ?? 1);
    }
    return $redis;
}

/**
 * 获取缓存开关
 * @return bool
 */
function cacheFlag(): bool
{
    if (Env::get('app.debug')) return false;
    return Env::get('app.cache', 1) == 1;
}


/**
 * 获取配置
 */
function get_site_config($name)
{
    $value = db('config')->cache(cacheFlag(), 86400)->where('name', $name)->value('value');
    if (!$value && $value != 0) {
        trace('找不到配置值:' . $name, 'error');
    }
    return $value;
}

/**
 * 数组下标过滤
 * @param array        $array
 * @param array|string $filter  ['id','name'] |'id,name'
 * @param bool         $exclude true=排除,false=保留
 * @return array
 */
function array_index_filter($array, $filter, $exclude = false)
{
    if (!is_array($filter) && is_string($filter)) {
        $filter = explode(',', $filter);
    }
    if ($exclude) {
        return array_diff_key($array, array_flip($filter));
    } else {
        return array_intersect_key($array, array_flip($filter));
    }
}


function show_error_notify($e)
{
    return $e->getMessage();
}

/**
 * 验证是否有锁，没锁会创建锁并返回false,如果已经有锁了则返回true
 * @param     $lockName
 * @param int $expire
 * @return bool
 */
function locked($lockName, $expire = 10)
{
    $redis = redis();
    $key = 'LOCK:' . $lockName;
    $re = $redis->setnx($key, 1);
    if ($re) {
        $redis->expire($key, $expire);
        return false;
    } else {
        return true;
    }
}

/**
 * 释放锁
 * @param     $lockName
 * @param int $expire
 * @return bool
 */
function lock_remove($lockName)
{
    $redis = redis();
    $key = 'LOCK:' . $lockName;
    $re = $redis->del($key);
    if (!$re) {
        return false;
    } else {
        return true;
    }
}

/**
 * 自增锁
 * @param     $lockName
 * @param int $expire
 * @return int
 */
function incrLock($lockName, $expire = 10)
{
    $redis = redis();
    $key = 'inClock:' . $lockName;
    $re = $redis->incr($key);
    if ($re == 1) {
        $redis->expire($key, $expire);
    }
    return $re;
}

