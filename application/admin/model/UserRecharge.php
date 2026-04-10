<?php

namespace app\admin\model;

use think\Model;


class UserRecharge extends Model
{


    // 表名
    protected $name = 'user_recharge';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'system_text'
    ];


    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '3' => __('Status 3'), '4' => __('Status 4'), '5' => __('Status 5')];
    }

    public function getSystemList()
    {
        return ['1' => __('System 1'), '2' => __('System 2'), '3' => __('System 3'), '4' => __('System 4')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
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

    public function agent()
    {
        return $this->belongsTo('agent', 'agent_id', 'id', [], 'left')->setEagerlyType(0);
    }

    public function card()
    {
        return $this->belongsTo('channel_card', 'card_id', 'id', [], 'left')->setEagerlyType(0);
    }

}
