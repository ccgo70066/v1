<?php

namespace app\common\model;

use think\Model;

class UserBaseStatistics extends Model
{
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $deleteTime = false;
}
