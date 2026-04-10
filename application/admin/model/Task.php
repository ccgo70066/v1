<?php

namespace app\admin\model;

use think\Model;


class Task extends Model
{

    

    

    // 表名
    protected $name = 'task';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text',
        'jump_page_text',
        'status_text'
    ];
    

    protected static function init()
    {
        self::afterInsert(function ($row) {
            $pk = $row->getPk();
            //$row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
        });
    }

    public function getJumpPageListNew()
    {
        return [
            1 => __('首页'),
            2 => __('个人中心'),
            3 => __('分享页面'),
            4 => __('充值页面'),
            5 => __('广场动态'),
            6 => __('实名认证'),
        ];
    }

    
    public function getTypeList()
    {
        return ['1' => __('Type 1'), '2' => __('Type 2')];
    }

    public function getJumpPageList()
    {
        return ['1' => __('Jump_page 1'), '2' => __('Jump_page 2'), '3' => __('Jump_page 3'), '4' => __('Jump_page 4'), '5' => __('Jump_page 5'), '6' => __('Jump_page 6'), '7' => __('Jump_page 7')];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '0' => __('Status 0')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getJumpPageTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['jump_page']) ? $data['jump_page'] : '');
        $list = $this->getJumpPageList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
