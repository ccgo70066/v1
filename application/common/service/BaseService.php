<?php

namespace app\common\service;

use app\common\exception\ApiException;
use app\common\library\rabbitmq\BaseHandler;

/**
 * Service 基类
 */
class BaseService
{

    protected static $_instance;

    public static function instance(): static
    {
        if (!isset(self::$_instance[$class = get_called_class()])) {
            self::$_instance[$class] = new $class();
        }
        return self::$_instance[$class];
    }

    /**
     * 频率检测
     * 防止用户点击太快重复提交
     * @param string $operate
     * @param int    $second
     * @return void
     * @throws
     */
    public static function operateCheck(string $operate, int $second = 5): void
    {
        $redis = redis();
        if (!$redis->set('operate_check:' . $operate, 1, ['nx', 'ex' => $second])) {
            throw  new ApiException(__('Operation too fast'));
        }
    }

}
