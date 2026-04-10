<?php

namespace app\admin\model\general;

use app\admin\model\Car;
use think\Model;


class Vip extends Model
{


    // 表名
    protected $name = 'vip';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];


    public function car()
    {
        return $this->belongsTo(Car::class, 'car', 'id', [], 'LEFT')->setEagerlyType(0);
    }


}
