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

}
