<?php

namespace app\admin\model;

use think\Model;


class Moment extends Model
{


    // 表名
    protected $name = 'moment';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'publish_text',
        'status_text',
        'block_status_text'
    ];


    public function getPublishList()
    {
        return ['1' => __('Publish 1'), '2' => __('Publish 2'), '0' => __('Publish 0')];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '-1' => __('Status -1'), '2' => __('Status 2')];
    }

    public function getBlockStatusList()
    {
        return ['1' => __('Block_status 1'), '0' => __('Block_status 0')];
    }


    public function getPublishTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['publish']) ? $data['publish'] : '');
        $list = $this->getPublishList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getBlockStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['block_status']) ? $data['block_status'] : '');
        $list = $this->getBlockStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function user()
    {
        return $this->belongsTo('user', 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function business()
    {
        return $this->belongsTo('user_business', 'user_id', 'id', [], 'left')->setEagerlyType(0);
    }

    public function admin()
    {
        return $this->belongsTo('admin', 'audit_admin', 'id', [], 'LEFT')->setEagerlyType(0);
    }


}
