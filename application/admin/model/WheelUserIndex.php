<?php

namespace app\admin\model;

use think\Model;


class WheelUserIndex extends Model
{





    // 表名
    protected $name = 'wheel_user_index';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'box_type_text'
    ];



    public function getBoxTypeList()
    {
        return ['1' => __('Box_type 1'), '2' => __('Box_type 2')];
    }


    public function getBoxTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['box_type']) ? $data['box_type'] : '');
        $list = $this->getBoxTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function user()
    {
        return $this->belongsTo('user', 'user_id', 'id', '', 'left')->setEagerlyType(0);
    }


    public function level()
    {
        return $this->belongsTo('wheel_level', 'level_id', 'id', [], 'left')->setEagerlyType(0);
    }

    public function room()
    {
        return $this->belongsTo('room', 'current_room_id', 'id', '', 'left')->setEagerlyType(0);
    }

//    public function master()
//    {
//        return $this->belongsTo('room_master_relation', 'user_id', 'user_id and is_current = 1', '', 'left')->setEagerlyType(0);
//    }

}
