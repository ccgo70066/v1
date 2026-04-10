<?php

namespace app\admin\model;

use think\Model;


class UserFollow extends Model
{

    

    

    // 表名
    protected $name = 'user_follow';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'is_mutual_text'
    ];
    

    
    public function getIsMutualList()
    {
        return ['0' => __('Is_mutual 0'), '1' => __('Is_mutual 1')];
    }



}
