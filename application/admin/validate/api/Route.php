<?php

namespace app\admin\validate\api;

use think\Validate;

class Route extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
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
