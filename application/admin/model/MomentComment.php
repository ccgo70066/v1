<?php

namespace app\admin\model;

use think\Model;


class MomentComment extends Model
{

    

    

    // 表名
    protected $name = 'moment_comment';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text'
    ];
    

    
    public function getTypeList()
    {
        return ['1' => __('Type 1'), '2' => __('Type 2')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function user()
    {
        return $this->belongsTo('user', 'user_id', 'id', '', 'left')->setEagerlyType(0);
    }

    public function touser()
    {
        return $this->belongsTo('user', 'to_user_id', 'id', '', 'left')->setEagerlyType(0);
    }




}
