<?php

namespace app\admin\model;

use think\Model;


class Adornment extends Model
{

    

    

    // 表名
    protected $name = 'adornment';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'is_renew_text',
        'is_sell_text',
        'status_text'
    ];
    

    protected static function init()
    {
        self::afterInsert(function ($row) {
            $pk = $row->getPk();
            //$row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
        });
    }

    
    public function getIsRenewList()
    {
        return ['0' => __('Is_renew 0'), '1' => __('Is_renew 1')];
    }

    public function getIsSellList()
    {
        return ['0' => __('Is_sell 0'), '1' => __('Is_sell 1')];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '0' => __('Status 0')];
    }


    public function getIsRenewTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_renew']) ? $data['is_renew'] : '');
        $list = $this->getIsRenewList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsSellTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_sell']) ? $data['is_sell'] : '');
        $list = $this->getIsSellList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
