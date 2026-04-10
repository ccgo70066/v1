<?php

namespace app\admin\model;

use think\Model;


class UserWithdraw extends Model
{


    // 表名
    protected $name = 'user_withdraw';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text'
    ];


    public function getStatusList()
    {
        return [
            '0'  => __('Status 0'),
            '1'  => __('Status 1'),
            '2'  => __('Status 2'),
            '3'  => __('Status 3'),
            '-1' => __('Status -1'),
            '-2' => __('Status -2')
        ];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function operate()
    {
        return $this->belongsTo('Admin', 'operate_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function finance()
    {
        return $this->belongsTo('Admin', 'finance_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function payment()
    {
        return $this->belongsTo('payment_way', 'payment_way_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


}
