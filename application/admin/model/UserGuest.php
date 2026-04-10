<?php

namespace app\admin\model;

use think\Db;
use think\Model;


class UserGuest extends Model
{


    // 表名
    protected $name = 'user_guest';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];


    public static function visit($user_id, $to_user_id)
    {
        $sql = self::fetchSql(true)->insert(['user_id' => $user_id, 'to_user_id' => $to_user_id]);
        self::execute($sql . ' on duplicate key update count=count+1');

        $count = self::where(['to_user_id' => $to_user_id])->count();
        db('user')->where(['id' => $to_user_id])->update(['guest_num' => $count]);
    }


}
