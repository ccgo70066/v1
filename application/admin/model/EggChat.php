<?php

namespace app\admin\model;

use think\Model;


class EggChat extends Model
{

    

    

    // 表名
    protected $name = 'egg_chat';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'chat_type_text'
    ];
    

    
    public function getChatTypeList()
    {
        return ['1' => __('Chat_type 1'), '2' => __('Chat_type 2')];
    }


    public function getChatTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['chat_type']) ? $data['chat_type'] : '');
        $list = $this->getChatTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }




}
