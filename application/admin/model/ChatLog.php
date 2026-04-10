<?php

namespace app\admin\model;

use think\Model;


class ChatLog extends Model
{

    

    

    // 表名
    protected $name = 'chat_log';
    protected $connection = 'mongodb';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];


    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function touser()
    {
        return $this->belongsTo('User', 'to_user_id', 'id', [], 'LEFT')->setEagerlyType(0);

    }
    

    







}
