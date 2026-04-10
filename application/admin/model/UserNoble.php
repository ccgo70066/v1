<?php

namespace app\admin\model;

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


    public function user()
    {
        return $this->belongsTo('user', 'user_id', 'id', [], 'left')->setEagerlyType(0);
    }

    public function noble()
    {
        return $this->belongsTo('noble', 'noble_id', 'id');
    }

    public function userbusiness()
    {
        return $this->belongsTo('UserBusiness', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
    

    







}
