<?php

namespace app\common\model;

use think\Model;


class UserNoble extends Model
{

    // 表名
    protected $name = 'user_noble';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];

    //家族房隐身:0=无权限,1=开启,2=未开启
    const ROOM_HIDE_PERMISSION = 0;
    const ROOM_HIDE_ON = 1;
    const ROOM_HIDE_OFF = 2;

    //炫彩昵称:0=无权限,1=开启,2=未开启
    const NAME_COLOR_PERMISSION = 0;
    const NAME_COLOR_ON = 1;
    const NAME_COLOR_OFF = 2;


}
