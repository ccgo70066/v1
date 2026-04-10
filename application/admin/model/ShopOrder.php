<?php

namespace app\admin\model;

use think\Model;


class ShopOrder extends Model
{

    

    

    // 表名
    protected $name = 'shop_order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'price_type_text',
        'pay_way_text',
        'status_text',
        'system_text'
    ];



    public function getPriceTypeList()
    {
        return ['1' => __('Price_type 1'), '2' => __('Price_type 2')];
    }

    public function getPayWayList()
    {
        return ['1' => __('Pay_way 1'), '2' => __('Pay_way 2')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }

    public function getSystemList()
    {
        return ['1' => __('System 1'), '2' => __('System 2')];
    }

    public function getPriceTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['price_type']) ? $data['price_type'] : '');
        $list = $this->getPriceTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getPayWayTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['pay_way']) ? $data['pay_way'] : '');
        $list = $this->getPayWayList();
        return isset($list[$value]) ? $list[$value] : '';
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
        return $this->belongsTo('user', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    public function shop()
    {
        return $this->belongsTo('shop_item', 'item_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
    

    







}
