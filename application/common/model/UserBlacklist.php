<?php

namespace app\common\model;

use think\Model;


class UserBlacklist extends Model
{
    // 表名
    protected $name = 'user_blacklist';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];

    public static function is_blacklist($user_id, $to_user_id)
    {
        $count = db('user_blacklist')
            ->where('user_id', $user_id)
            ->where('to_user_id', $to_user_id)
            ->count();
        return $count == 0 ? 0 : 1;
    }


}
