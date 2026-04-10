<?php

namespace app\admin\model;

use think\Model;


class AdminOptionLog extends Model
{

    

    

    // 表名
    protected $name = 'admin_option_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'option_text'
    ];
    

    
    public function getOptionList()
    {
        return ['1' => __('Option 1'), '2' => __('Option 2'), '3' => __('Option 3'), '4' => __('Option 4'), '5' => __('Option 5'), '6' => __('Option 6')];
    }


    public function getOptionTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['option']) ? $data['option'] : '');
        $list = $this->getOptionList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function admin()
    {
        return $this->belongsTo('admin', 'admin_id', 'id', '', 'left')->setEagerlyType(0);
    }

    public function user()
    {
        return $this->belongsTo('user', 'user_id', 'id', '', 'left')->setEagerlyType(0);
    }




}
