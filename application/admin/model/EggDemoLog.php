<?php

namespace app\admin\model;

use think\Model;


class EggDemoLog extends Model
{

    

    

    // 表名
    protected $name = 'egg_demo_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'box_type_text',
        'count_type_text',
        'jump_status_text',
        'create_time_text',
        'update_time_text'
    ];
    

    
    public function getBoxTypeList()
    {
        return ['1' => __('Box_type 1'), '2' => __('Box_type 2')];
    }

    public function getCountTypeList()
    {
        return ['1' => __('Count_type 1'), '10' => __('Count_type 10'), '40' => __('Count_type 40'), '100' => __('Count_type 100')];
    }

    public function getJumpStatusList()
    {
        return ['0' => __('Jump_status 0'), '1' => __('Jump_status 1'), '2' => __('Jump_status 2'), '3' => __('Jump_status 3'), '4' => __('Jump_status 4'), '5' => __('Jump_status 5'), '6' => __('Jump_status 6')];
    }


    public function getBoxTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['box_type']) ? $data['box_type'] : '');
        $list = $this->getBoxTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getCountTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['count_type']) ? $data['count_type'] : '');
        $list = $this->getCountTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getJumpStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['jump_status']) ? $data['jump_status'] : '');
        $list = $this->getJumpStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getCreateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['create_time']) ? $data['create_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getUpdateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['update_time']) ? $data['update_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setCreateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setUpdateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
