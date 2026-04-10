<?php

namespace app\admin\validate;

use think\Validate;

class WheelConfigSingle extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'box_type' => 'unique:wheel_config_single'
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
        'add'  => ['box_type'],
        'edit' => [''],
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
