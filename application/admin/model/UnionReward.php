<?php

namespace app\admin\model;

use think\Model;


class UnionReward extends Model
{

    

    

    // 表名
    protected $name = 'union_reward';

    // 追加属性
    protected $append = [
        'status_text',
    ];
    

    
    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3'),'6' => __('Status 6')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function union()
    {
        return $this->belongsTo('Union', 'union_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function room()
    {
        return $this->belongsTo('Room', 'room_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function auditor()
    {
        return $this->belongsTo('Admin', 'auditor', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function checker()
    {
        return $this->belongsTo('Admin', 'checker', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function creator()
    {
        return $this->belongsTo('Admin', 'creator', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function handle()
    {
        return $this->belongsTo('Admin', 'handler', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

}
