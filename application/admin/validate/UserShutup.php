<?php

namespace app\admin\validate;

use think\Validate;

class UserShutup extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'user_id' => 'unique:user_shutup'
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
        'add'  => ['user_id'],
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
