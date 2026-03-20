<?php

namespace app\admin\model\api;

use think\Model;


class Field extends Model
{

    

    

    // 表名
    protected $name = 'api_field';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    







}
