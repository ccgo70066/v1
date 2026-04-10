<?php

namespace app\common\exception;

use app\common\exception\ApiException;
use Exception;
use think\Env;
use think\exception\Handle;
use app\common\library;

/**
 * 自定义API模块的错误显示
 */
class ExceptionHandle extends Handle
{

    public function render(Exception $e)
    {
        return parent::render($e);
    }

    public function report(Exception $exception)
    {
        t(111);
        error_log_out($exception);
        parent::report($exception);
    }

}
