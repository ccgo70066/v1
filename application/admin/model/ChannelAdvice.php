<?php

namespace app\admin\model;

use think\Model;


class ChannelAdvice extends Model
{

    

    

    // 表名
    protected $name = 'channel_advice';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];


    public function operator()
    {
        return $this->belongsTo('admin', 'operator', 'id', '', 'left')->setEagerlyType(0);
    }






}
