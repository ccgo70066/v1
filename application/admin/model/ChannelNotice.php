<?php

namespace app\admin\model;

use think\Model;


class ChannelNotice extends Model
{


    // 表名
    protected $name = 'channel_notice';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text',
        'action_text',
        'status_text',
        'show_type_text'
    ];


    public function getTypeList()
    {
        return ['1' => __('Type 1'), '2' => __('Type 2')];
    }

    public function getActionList()
    {
        return ['0' => __('Action 0'), '1' => __('Action 1'), '7' => __('Action 7'), '8' => __('Action 8'), '5' => __('Action 5'),];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '0' => __('Status 0')];
    }

    public function getShowTypeList()
    {
        return ['2' => __('Show_type 2')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
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


    public function getShowTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['show_type']) ? $data['show_type'] : '');
        $valueArr = explode(',', $value);
        $list = $this->getShowTypeList();
        return implode(',', array_intersect_key($list, array_flip($valueArr)));
    }

    protected function setShowTypeAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }


}
