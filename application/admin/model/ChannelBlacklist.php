<?php

namespace app\admin\model;

use think\Model;


class ChannelBlacklist extends Model
{


    // 表名
    protected $name = 'channel_blacklist';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'system_text',
        'item_code_text',
        'status_text'
    ];


    public function getSystemList()
    {
        return ['1' => __('System 1'), '2' => __('System 2')];
    }

    public function getItemCodeList()
    {
        return [
            'ITEM_002' => __('Item_code item_002'),
            //'ITEM_003' => __('Item_code item_003'),
            'ITEM_004' => __('Item_code item_004'),
            //'ITEM_005' => __('Item_code item_005'),
            //'ITEM_006' => __('Item_code item_006')
        ];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '0' => __('Status 0')];
    }


    public function getSystemTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['system']) ? $data['system'] : '');
        $list = $this->getSystemList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getItemCodeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['item_code']) ? $data['item_code'] : '');
        $valueArr = explode(',', $value);
        $list = $this->getItemCodeList();
        return implode(',', array_intersect_key($list, array_flip($valueArr)));
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setItemCodeAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }

    public static function get_blacklist($appid, $system, $version)
    {
        $blacklist = self::where(['appid' => $appid, 'system' => $system, 'version' => $version, 'status' => 1])->find();
        $list = $blacklist ? explode(',', $blacklist['item_code']) : [];
        return $list;
    }


    public function channel()
    {
        return $this->belongsTo('channel', 'appid', 'appid', '', 'left')->setEagerlyType(0);
    }


}
