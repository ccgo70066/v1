<?php

namespace app\admin\model;

use app\union\model\Room;
use think\Model;


class RoomStatistic extends Model
{

    

    

    // 表名
    protected $name = 'room_statistic';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'active_text'
    ];
    

    
    public function getActiveList()
    {
        return ['1' => __('Active 1'), '0' => __('Active 0')];
    }


    public function getActiveTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['active']) ? $data['active'] : '');
        $list = $this->getActiveList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function room()
    {
        return $this->belongsTo('room', 'room_id', 'id', '', 'left')->setEagerlyType(0);
    }

}
