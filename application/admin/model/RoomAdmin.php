<?php

namespace app\admin\model;

use think\Model;


class RoomAdmin extends Model
{

    

    

    // 表名
    protected $name = 'room_admin';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'role_text',
        'create_time_text'
    ];
    

    
    public function getRoleList()
    {
        return ['1' => __('Role 1'), '2' => __('Role 2'), '3' => __('Role 3')];
    }


    public function getRoleTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['role']) ? $data['role'] : '');
        $list = $this->getRoleList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getCreateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['create_time']) ? $data['create_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setCreateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
