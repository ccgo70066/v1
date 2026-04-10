<?php

namespace app\admin\model;
use think\Log;
use think\Model;

class Union extends Model
{

    // 表名
    protected $name = 'union';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
    ];

    public function getOnlineList()
    {
        return ['1' => __('Online 1'), '0' => __('Online 0')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3')];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function user()
    {
        return $this->belongsTo('User', 'owner_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    /**
     * 新增家族操作日志
     * @param        $union_id     int 家族号
     * @param        $user_id      int 操作人
     * @param        $text         string 内容
     * @param        $to_user_id   int 被操作人
     */
    public function add_union_log($union_id, $user_id, $text = '', $to_user_id = 0)
    {
        db('union_log')->insert([
            'user_id'    => $user_id,
            'union_id'   => $union_id,
            'action'     => $text,
            'to_user_id' => $to_user_id,
        ]);
    }

}
