<?php

namespace app\common\model;

use think\Model;


class Blacklist extends Model
{
    // 表名
    protected $name = 'blacklist';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];


    public function admin()
    {
        return $this->belongsTo('Admin', 'creator', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public static function is_blacklist($user_id, $imei, $mobile)
    {
        $count = db('blacklist')
            ->where(['number' => [['eq', $user_id], ['eq', $imei], ['eq', $mobile], 'or']])
            ->where("number <> ''")
            ->where(['end_time' => ['gt', time()]])
            ->count();
        return $count == 0 ? 0 : 1;
    }


}
