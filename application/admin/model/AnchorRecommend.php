<?php

namespace app\admin\model;

use think\Model;

/**
 * 推荐主播
 */
class AnchorRecommend Extends Model
{

    // 表名
    protected $name = 'anchor_recommend';
    // 开启自动写入时间戳字段
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = false;
    // 追加属性
    protected $append = [

    ];

    public function operator()
    {
        return $this->belongsTo('admin', 'operator', 'id', '', 'left')->setEagerlyType(0);
    }

    public function user()
    {
        return $this->belongsTo('user', 'user_id', 'id', '', 'left')->setEagerlyType(0);
    }
}
