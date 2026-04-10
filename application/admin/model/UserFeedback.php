<?php

namespace app\admin\model;

use think\Model;


class UserFeedback extends Model
{

    

    

    // 表名
    protected $name = 'user_feedback';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text',
        'form_text',
        'audit_status_text'
    ];
    

    
    public function getTypeList()
    {
        return ['1' => __('Type 1'), '2' => __('Type 2'), '3' => __('Type 3')];
    }

    public function getFormList()
    {
        return ['1' => __('Form 1'), '2' => __('Form 2')];
    }

    public function getAuditStatusList()
    {
        return ['1' => __('Audit_status 1'), '2' => __('Audit_status 2'), '3' => __('Audit_status 3')];
    }


    public function getNewAuditStatusList()
    {
        return ['2' => __('Audit_status 2'), '3' => __('Audit_status 3'),'4' => __('Audit_status 4')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getFormTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['form']) ? $data['form'] : '');
        $list = $this->getFormList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getAuditStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['audit_status']) ? $data['audit_status'] : '');
        $list = $this->getAuditStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function admin()
    {
        return $this->belongsTo('Admin', 'audit_admin', 'id', [], 'LEFT')->setEagerlyType(0);
    }




}
