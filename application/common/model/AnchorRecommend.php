<?php

namespace app\common\model;

use think\Model;

/**
 * 推荐主播
 */
class AnchorRecommend Extends Model
{

    // 表名
    protected $name = 'anchor_recommend';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = false;
    // 追加属性
    protected $append = [
    ];

}
