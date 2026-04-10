<?php

namespace app\admin\model;

use think\Model;


class UnionUser extends Model
{


    // 表名
    protected $name = 'union_user';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'role_text',
        'status_text',
        'create_time_text',
        'status_update_time_text'
    ];


    public function getRoleList()
    {
        return ['1' => __('Role 1'), '2' => __('Role 2')];
    }

    public function getStatusList()
    {

        return [
            '1' => __('Status 1'),
            '7' => __('Status 7'),
            '6' => __('Status 6'),
            '2' => __('Status 2'),
            '3' => __('Status 3'),
            '4' => __('Status 4'),
        ];
    }


    public function getRoleTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['role']) ? $data['role'] : '');
        $list = $this->getRoleList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getCreateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['create_time']) ? $data['create_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getStatusUpdateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status_update_time']) ? $data['status_update_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setCreateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setStatusUpdateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function union()
    {
        return $this->belongsTo('Union', 'union_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function checker()
    {
        return $this->belongsTo('admin', 'audit_admin', 'id', [], 'left')->setEagerlyType(0);
    }
}
