<?php

namespace app\admin\model;

use think\Model;


class ChannelPackage extends Model
{

    

    

    // 表名
    protected $name = 'channel_package';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'system_text',
        'force_text',
        'status_text'
    ];
    

    protected static function init()
    {
        self::afterInsert(function ($row) {
            if(empty($row['weigh'])){
                $pk = $row->getPk();
                $row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
            }
        });
    }

    
    public function getSystemList()
    {
        return ['ANDROID' => __('System android'), 'IOS' => __('System ios')];
    }

    public function getForceList()
    {
        return ['1' => __('Force 1'), '0' => __('Force 0')];
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


    public function getForceTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['force']) ? $data['force'] : '');
        $list = $this->getForceList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function channel()
    {
        return $this->belongsTo('channel', 'channel_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }




}
