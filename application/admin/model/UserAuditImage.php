<?php

namespace app\admin\model;

use think\Model;


class UserAuditImage extends Model
{





    // 表名
    protected $name = 'user_audit_image';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'img_type_text',
        'status_text'
    ];



    public function getImgTypeList()
    {
        return ['avatar' => __('Img_type avatar'), 'image' => __('Img_type image')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2')];
    }


    public function getImgTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['img_type']) ? $data['img_type'] : '');
        $list = $this->getImgTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function user()
    {
        return $this->belongsTo('user', 'user_id', 'id', '', 'left')->setEagerlyType(0);
    }
    public function admin()
    {
        return $this->belongsTo('admin', 'auditor', 'id', '', 'left')->setEagerlyType(0);
    }


    public function business()
    {
        return $this->belongsTo('user_business', 'user_id', 'id', '', 'left')->setEagerlyType(0);
    }



}
