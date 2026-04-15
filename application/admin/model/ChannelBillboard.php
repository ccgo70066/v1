<?php

namespace app\admin\model;

use think\Model;


class ChannelBillboard extends Model
{


    // 表名
    protected $name = 'channel_billboard';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text',
        'position_text',
        'action_text',
        'status_text'
    ];


    protected static function init()
    {
        self::afterInsert(function ($row) {
            $pk = $row->getPk();
            //$row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
        });
    }


    public function getTypeList()
    {
        return ['1' => __('Type 1'), '2' => __('Type 2')];
    }

    public function getPositionList()
    {
        return ['1' => __('Position 1'), '6' => __('Position 6'),];
    }

    public function getActionList()
    {
        return ['0' => __('Action 0'), '1' => __('Action 1'), '7' => __('Action 7'), '8' => __('Action 8'), '5' => __('Action 5'),];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '0' => __('Status 0'), '-1' => __('Status -1')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getPositionTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['position']) ? $data['position'] : '');
        $list = $this->getPositionList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getActionTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['action']) ? $data['action'] : '');
        $list = $this->getActionList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


}
