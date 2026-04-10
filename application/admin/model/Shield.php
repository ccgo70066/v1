<?php

namespace app\admin\model;

use DfaFilter\SensitiveHelper;
use think\Cache;
use think\Model;


class Shield extends Model
{

    

    

    // 表名
    protected $name = 'shield';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];


    /**
     * 敏感词屏蔽
     */
    public static function sensitive_filter($content)
    {
        if (!$content) {
            return $content;
        }
        return SensitiveHelper::init()->setTree(self::get_illegal_word())->replace($content, '*', true);
    }


    public static function get_illegal_word()
    {
        $word = Cache::remember('sensitive_dictionary', function () {
            return self::order('create_time desc')->column('name');
        }, 0);
        Cache::tag('small_data_filter', 'sensitive_dictionary');

        return $word;
    }

    

    







}
