<?php

namespace app\admin\model\room;

use think\Model;


class Admin extends Model
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
        'status_text'
    ];
    

    
    public function getRoleList()
    {
        return ['1' => __('Role 1'), '2' => __('Role 2'), '3' => __('Role 3')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '-1' => __('Status -1'), '2' => __('Status 2'), '-2' => __('Status -2')];
    }


    public function getRoleTextAttr($value, $data)
    {
        $value = $value ?: ($data['role'] ?? '');
        $list = $this->getRoleList();
        return $list[$value] ?? '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }




}
