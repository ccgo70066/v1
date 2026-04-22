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
        return ['1' => __('Action 1'), '2' => __('Action 2')];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '0' => __('Status 0')];
    }

    public function getShowTypeList()
    {
        return ['1' => __('Show_type 1'), '2' => __('Show_type 2')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['type'] ?? '');
        $list = $this->getTypeList();
        return $list[$value] ?? '';
    }


    public function getActionTextAttr($value, $data)
    {
        $value = $value ?: ($data['action'] ?? '');
        $list = $this->getActionList();
        return $list[$value] ?? '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }


    public function getShowTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['show_type'] ?? '');
        $valueArr = explode(',', $value);
        $list = $this->getShowTypeList();
        return implode(',', array_intersect_key($list, array_flip($valueArr)));
    }

    protected function setShowTypeAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }


}
