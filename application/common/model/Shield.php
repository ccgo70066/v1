<?php

namespace app\common\model;

use DfaFilter\SensitiveHelper;
use think\Cache;
use think\Db;
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
     * @param $words string 文本
     * @param $type  string 类型:1=屏蔽词,2=替换词
     */
    public static function sensitive_filter($words, $type = 1)
    {
        if (!$words) {
            return $words;
        }

        $tree = self::get_illegal_word();
        if (!$tree) {
            return $words;
        }
        $handle = SensitiveHelper::init()->setTree($tree);
        $isLegal = $handle->islegal($words);
        if ($isLegal) {
            $words = $handle->replace($words, '***');
        }
        return $words;
    }

    public static function get_illegal_word()
    {
        return db('shield')->column('name');
    }

}
