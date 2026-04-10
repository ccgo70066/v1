<?php

namespace app\common\exception;

use Exception;
use think\exception\Handle;

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
        parent::report($exception);
        error_log_out($exception);
    }

}
