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

}
