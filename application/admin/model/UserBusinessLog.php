<?php

namespace app\admin\model;

use think\Model;


class UserBusinessLog extends Model
{

    

    

    // 表名
    protected $name = 'user_business_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text',
        'from_text',
        'cate_text'
    ];
    

    
    public function getTypeList()
    {
        return ['2' => __('Type 2'), '3' => __('Type 3'), '4' => __('Type 4'), '5' => __('Type 5')];
    }

    public function getFromList()
    {
        return ['0' => __('From 0'), '1' => __('From 1'), '2' => __('From 2'), '3' => __('From 3'), '4' => __('From 4'), '5' => __('From 5'), '6' => __('From 6'), '7' => __('From 7'), '8' => __('From 8'), '9' => __('From 9'), '10' => __('From 10'), '11' => __('From 11'), '12' => __('From 12'), '13' => __('From 13'),];
    }

    public function getCateList()
    {
        return ['0' => __('Cate 0'), '1' => __('Cate 1')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getFromTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['from']) ? $data['from'] : '');
        $list = $this->getFromList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getCateTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['cate']) ? $data['cate'] : '');
        $list = $this->getCateList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function user()
    {
        return $this->belongsTo('user', 'user_id', 'id', '', 'left')->setEagerlyType(0);
    }




}
