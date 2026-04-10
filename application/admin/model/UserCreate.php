<?php

namespace app\admin\model;

use think\Model;


class UserCreate extends Model
{

    

    

    // 表名
    protected $name = 'user_create';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];


    //随机获取头像
    public static function getAvatar(){
        $gender_avatars = config('app.default_avatar_gender');
        $avatars = array_values(array_filter(array_map(function ($value){
            if ($value['gender'] == 1) {
                return $value['avatar'];
            }
        }, $gender_avatars)));

        return $avatars[array_rand($avatars)];
    }
    

    







}
