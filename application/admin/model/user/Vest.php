<?php

namespace app\admin\model\user;

use app\admin\model\User;
use think\Model;


class Vest extends Model
{


    // 表名
    protected $name = 'user_vest';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text'
    ];


    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }


    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver', 'id', [], 'LEFT')->setEagerlyType(0);
    }


}
