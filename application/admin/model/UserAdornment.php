<?php

namespace app\admin\model;

use think\Model;


class UserAdornment extends Model
{

    

    

    // 表名
    protected $name = 'user_adornment';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'from_by_text',
        'use_status_text',
        'is_wear_text'
    ];
    

    
    public function getFromByList()
    {
        return ['1' => __('From_by 1'), '2' => __('From_by 2')];
    }

    public function getUseStatusList()
    {
        return ['0' => __('Use_status 0'), '1' => __('Use_status 1'), '2' => __('Use_status 2')];
    }

    public function getIsWearList()
    {
        return ['0' => __('Is_wear 0'), '1' => __('Is_wear 1')];
    }


    public function getFromByTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['from_by']) ? $data['from_by'] : '');
        $list = $this->getFromByList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getUseStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['use_status']) ? $data['use_status'] : '');
        $list = $this->getUseStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsWearTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_wear']) ? $data['is_wear'] : '');
        $list = $this->getIsWearList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function user()
    {
        return $this->belongsTo('user', 'user_id', 'id', [], 'left')->setEagerlyType(0);
    }

    public function adornment()
    {
        return $this->belongsTo('adornment', 'adornment_id', 'id');
    }




}
