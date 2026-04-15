<?php

namespace app\common\service;

/**
 * 主页服务类
 */
class HomeService extends BaseService
{
    protected static $instance = null;

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }
}
