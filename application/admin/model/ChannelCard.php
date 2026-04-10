<?php

namespace app\admin\model;

use think\Model;


class ChannelCard extends Model
{





    // 表名
    protected $name = 'channel_card';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'unit_text',
        'system_text',
        'bage_text',
        'status_text'
    ];


    protected static function init()
    {
        self::afterInsert(function ($row) {
            $pk = $row->getPk();
            $row->getQuery()->where($pk, $row[$pk])->where('weigh', 0)->update(['weigh' => $row[$pk]]);
        });
    }


    public function getUnitList()
    {
        return ['1' => __('Unit 1'), '2' => __('Unit 2')];
    }

    public function getSystemList()
    {
        return ['iOS' => __('System ios'), 'Android' => __('System android')];
    }

    public function getBageList()
    {
        return ['0' => __('Bage 0'), '1' => __('Bage 1'), '2' => __('Bage 2'), '3' => __('Bage 3')];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '0' => __('Status 0')];
    }


    public function getUnitTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['unit']) ? $data['unit'] : '');
        $list = $this->getUnitList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getSystemTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['system']) ? $data['system'] : '');
        $list = $this->getSystemList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getBageTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['bage']) ? $data['bage'] : '');
        $list = $this->getBageList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function parcel()
    {
        return $this->belongsTo('Parcel', 'parcel_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


}
