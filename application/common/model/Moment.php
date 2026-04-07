<?php

namespace app\common\model;

use think\Model;


class Moment extends Model
{

    // 表名
    protected $name = 'moment';

    // 追加属性
    protected $append = [
        'publish_text',
        'status_text',
        'block_status_text'
    ];

    //隐私:0=公开,1=粉丝,2=关注,3=访客,4=自己
    const Publish = 0;
    const PublishOfFans = 1;
    const PublishOfFollow = 2;
    const PublishOfVisitors = 3;
    const PublishOnlyMe = 4;

    //状态:1=显示,0=屏蔽,-1=驳回,2=待审核
    const StatusOff = 0;
    const StatusOn = 1;
    const StatusReject = -1;
    const StatusAudit = 2;


    public function getPublishList()
    {
        return ['1' => __('Publish 1'), '2' => __('Publish 2'), '3' => __('Publish 3'), '4' => __('Publish 4'), '0' => __('Publish 0')];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '0' => __('Status 0'), '2' => __('Status 2')];
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

    public function admin()
    {
        return $this->belongsTo('admin', 'audit_admin', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
