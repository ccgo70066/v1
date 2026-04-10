<?php

namespace app\admin\validate;

use think\Validate;

class UserCreate extends Validate
{
    /**
     * 验证规则
     */
    protected $rule = [
        'mobile|手机号'=>'require|min:11|regex:1\d{10}',
        'password|密码'=>'require|min:6',
        'nickname|昵称'=>'require|max:30'
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
        'add'  => ['mobile','password', 'nickname'],
        'edit' => [],
    ];
    
}
