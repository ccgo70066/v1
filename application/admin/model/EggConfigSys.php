<?php

namespace app\admin\model;

use think\Model;


class EggConfigSys extends Model
{

    

    

    // 表名
    protected $name = 'egg_config_sys';
    
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
        'status_text'
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

    public function getCountTypeList()
    {
        return ['10' => __('Count_type 10'), '100' => __('Count_type 100')];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '0' => __('Status 0')];
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


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
