<?php

namespace app\common\service;

/**
 * Service 基类
 */
class BaseService
{

    protected static $instance = null;

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }


    /**
     * 频率检测
     * 防止用户点击太快重复提交
     * @param string $operate
     * @param int    $second
     * @return void
     */
    public static function operateCheck(string $operate, int $second = 5)
    {
        $redis = redis();
        if (!$redis->set('operate_check:' . $operate, 1, ['nx', 'ex' => $second])) {
            self::error(__('Operation too fast'));
        }
    }

}
