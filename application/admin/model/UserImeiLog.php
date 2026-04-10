<?php

namespace app\admin\model;

use think\Model;


class UserImeiLog extends Model
{

    

    

    // 表名
    protected $name = 'user_imei_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'orig_system_text',
        'system_text'
    ];
    

    
    public function getOrigSystemList()
    {
        return ['IOS' => __('Ios'), 'ANDROID' => __('Android')];
    }

    public function getSystemList()
    {
        return ['IOS' => __('Ios'), 'ANDROID' => __('Android')];
    }


    public function getOrigSystemTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['orig_system']) ? $data['orig_system'] : '');
        $list = $this->getOrigSystemList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getSystemTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['system']) ? $data['system'] : '');
        $list = $this->getSystemList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function user()
    {
        return $this->belongsTo('user', 'user_id', 'id', [], 'left')->setEagerlyType(0);
    }




}
