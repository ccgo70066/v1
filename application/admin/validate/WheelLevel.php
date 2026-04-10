<?php

namespace app\admin\validate;

use think\Validate;

class WheelLevel extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'range_start'      => 'elt:2147483647|egt:0',
        'range_end'        => 'elt:2147483647|gt:0',
        'pool_sys_percent' => 'between:0,150',
        'pool_pub_percent' => 'between:0,150',
        'pool_per_percent' => 'between:0,150',
        'jump_percent_1'   => 'between:0,150',
        'jump_percent_2'   => 'between:0,150',
        'jump_percent_3'   => 'between:0,150',
        'jump_percent_4'   => 'between:0,150',
    ];
    /**
     * 提示消息
     */
    protected $message = [
    ];
    /**
     * 验证场景
     */
    protected $scene = [
        'add'  => [],
        'edit' => [],
    ];

    public function __construct(array $rules = [], $message = [], $field = [])
    {
        if (empty($field)) {
            foreach ($this->rule as $k => $item) {
                $field[$k] = __($k);
            }
        }
        parent::__construct($rules, $message, $field);
    }
}
