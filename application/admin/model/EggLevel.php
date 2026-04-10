<?php

namespace app\admin\model;

use think\Model;


class EggLevel extends Model
{

    

    

    // 表名
    protected $name = 'egg_level';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'box_type_text',
        'or_data_text',
        'jump_data_text'
    ];
    

    protected static function init()
    {
        self::afterInsert(function ($row) {
            $pk = $row->getPk();
            
        });
    }

    
    public function getBoxTypeList()
    {
        return ['1' => __('Box_type 1'), '2' => __('Box_type 2')];
    }

    public function getOrDataList()
    {
        return ['1' => __('Or_data 1'), '2' => __('Or_data 2'), '3' => __('Or_data 3')];
    }

    public function getJumpDataList()
    {
        return ['1' => __('Jump_data 1'), '2' => __('Jump_data 2'), '3' => __('Jump_data 3'), '4' => __('Jump_data 4')];
    }


    public function getBoxTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['box_type']) ? $data['box_type'] : '');
        $list = $this->getBoxTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getOrDataTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['or_data']) ? $data['or_data'] : '');
        $list = $this->getOrDataList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getJumpDataTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['jump_data']) ? $data['jump_data'] : '');
        $valueArr = explode(',', $value);
        $list = $this->getJumpDataList();
        return implode(',', array_intersect_key($list, array_flip($valueArr)));
    }

    protected function setJumpDataAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }


}
