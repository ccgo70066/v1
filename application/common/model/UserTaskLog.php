<?php

namespace app\common\model;

use think\Model;


class UserTaskLog extends Model
{

    /**
     * @var 进度状态—未完成
     */
    const TASK_LOG_COMPLETE_0 = 0;
    /**
     * @var 进度状态—完成
     */
    const TASK_LOG_COMPLETE_1 = 1;
    /**
     * @var 进度状态—待领
     */
    const TASK_LOG_COMPLETE_2 = 2;


    // 表名
    protected $name = 'user_task_log';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];

    public function task()
    {
        return $this->belongsTo('task', 'key', 'key', [], 'left')->setEagerlyType(0);
    }

    public function user()
    {
        return $this->belongsTo('user', 'user_id', 'id', [], 'left')->setEagerlyType(0);
    }


}
