<?php

namespace app\admin\model\general;

use app\admin\model\Admin;
use app\admin\model\User;
use think\Model;


class AdminOptionLog extends Model
{





    // 表名
    protected $name = 'admin_option_log';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'option_text'
    ];



    public function getOptionList()
    {
        return ['1' => __('Option 1'), '2' => __('Option 2')];
    }


    public function getOptionTextAttr($value, $data)
    {
        $value = $value ?: ($data['option'] ?? '');
        $list = $this->getOptionList();
        return $list[$value] ?? '';
    }



    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }


}
