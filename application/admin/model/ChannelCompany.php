<?php

namespace app\admin\model;

use think\Model;


class ChannelCompany extends Model
{

    

    

    // 表名
    protected $name = 'channel_company';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'sign_type_text',
        'status_text'
    ];
    

    protected static function init()
    {
        self::afterInsert(function ($row) {
            $pk = $row->getPk();
            //$row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
        });
    }

    
    public function getSignTypeList()
    {
        // return ['1' => __('Sign_type 1'), '2' => __('Sign_type 2')];
        return [ '2' => __('Sign_type 2')];

    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '0' => __('Status 0')];
    }


    public function getSignTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['sign_type']) ? $data['sign_type'] : '');
        $list = $this->getSignTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
