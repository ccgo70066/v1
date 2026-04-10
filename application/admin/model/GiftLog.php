<?php

namespace app\admin\model;

use think\Model;


class GiftLog extends Model
{


    // 表名
    protected $name = 'gift_log';

    // 追加属性
    protected $append = [
        'type_text',
    ];

    protected static $range_name = [1 => 'week', 2 => 'day', 3 => 'month'];
    protected static $type_name = [1 => 'contribution', 2 => 'charm'];

    public function getTypeList()
    {
        return ['1' => __('Type 1'), '2' => __('Type 2'),'3' => __('Type 3'),  '4'=>__('Type 4'), '5'=>__('Type 5')];
    }

    public function getPriceTypeList()
    {
        return ['1' => __('Price_type 1'), '2' => __('Price_type 2')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function gift()
    {
        return $this->belongsTo('Gift', 'gift_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function givers()
    {
        return $this->belongsTo('User', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function receivers()
    {
        return $this->belongsTo('User', 'to_user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function room()
    {
        return $this->belongsTo('Room', 'room_id', 'id', [], 'INNER')->setEagerlyType(0);
    }

}
