<?php

namespace app\common\service;

use app\common\exception\ApiException;

/**
 * Service 基类
 */
class BaseService
{




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
            throw  new ApiException(__('Operation too fast'));
        }
    }

}
