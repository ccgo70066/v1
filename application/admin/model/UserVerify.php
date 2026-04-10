<?php

namespace app\admin\model;

use think\Model;


class UserVerify extends Model
{

    

    

    // 表名
    protected $name = 'user_verify';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'check_status_text'
    ];
    

    
    public function getCheckStatusList()
    {
        return ['-1' => __('Check_status -1'), '0' => __('Check_status 0'), '1' => __('Check_status 1')];
    }


    public function getCheckStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['check_status']) ? $data['check_status'] : '');
        $list = $this->getCheckStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function admin()
    {
        return $this->belongsTo('admin', 'checker', 'id', [], 'LEFT')->setEagerlyType(0);
    }


    /**
     * 获取会员审核状态
     * @param $user_id
     */
    public static function getCheckStatus($user_id)
    {
        $verify = self::where('user_id', '=', $user_id)->find();
        if($verify){
            return $verify['check_status'];
        }else{
            return 2;
        }
    }




}
