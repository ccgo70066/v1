<?php

namespace app\admin\model;

use think\Model;


class EggGift extends Model
{


    // 表名
    protected $name = 'egg_gift';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'box_type_text',
        'broadcast_text',
        'room_notice_text',
        'light_level_text',
        'voice_text',
        'show_again_text',
        'last_status_text',
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

    public function getBroadcastList()
    {
        return ['1' => __('Broadcast 1'), '0' => __('Broadcast 0')];
    }

    public function getRoomNoticeList()
    {
        return ['0' => __('Room_notice 0'), '1' => __('Room_notice 1'), '2' => __('Room_notice 2')];
    }

    public function getLightLevelList()
    {
        return ['0' => __('Light_level 0'), '1' => __('Light_level 1'), '2' => __('Light_level 2')];
    }

    public function getVoiceList()
    {
        return ['0' => __('Voice 0'), '1' => __('Voice 1'), '2' => __('Voice 2')];
    }

    public function getShowAgainList()
    {
        return ['0' => __('Show_again 0'), '1' => __('Show_again 1')];
    }

    public function getLastStatusList()
    {
        return ['1' => __('Last_status 1'), '0' => __('Last_status 0')];
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


    public function getBroadcastTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['broadcast']) ? $data['broadcast'] : '');
        $list = $this->getBroadcastList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getRoomNoticeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['room_notice']) ? $data['room_notice'] : '');
        $list = $this->getRoomNoticeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getLightLevelTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['light_level']) ? $data['light_level'] : '');
        $list = $this->getLightLevelList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getVoiceTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['voice']) ? $data['voice'] : '');
        $list = $this->getVoiceList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getShowAgainTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['show_again']) ? $data['show_again'] : '');
        $list = $this->getShowAgainList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getLastStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['last_status']) ? $data['last_status'] : '');
        $list = $this->getLastStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function gift()
    {
        return $this->belongsTo('gift', 'gift_id', 'id', '', 'left')->setEagerlyType(0);
    }
}
