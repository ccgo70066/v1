<?php

namespace app\admin\model;

use think\Model;


class RoomThemeCate extends Model
{





    // 表名
    protected $name = 'room_theme_cate';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'room_type_text',
        'create_time_text',
        'status_text'
    ];



    public function getRoomTypeList()
    {
        return ['1' => __('Room_type 1'), '2' => __('Room_type 2')];

    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '0' => __('Status 0')];
    }


    public function getRoomTypeTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['room_type'] ?? '');
        $list = $this->getRoomTypeList();
        return $list[$value] ?? '';
    }


    public function getCreateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : ($data['create_time'] ?? '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setCreateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
