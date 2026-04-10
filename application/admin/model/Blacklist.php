<?php

namespace app\admin\model;

use app\common\extend\util\Random;
use think\Model;


class Blacklist extends Model
{


    // 表名
    protected $name = 'blacklist';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text'
    ];

    protected static function init()
    {
        self::afterDelete(static function ($model) {
            $user_ids = [];
            $model['type'] == 1 && $user_ids[] = $model['number'];
            $model['type'] == 2 && $user_ids = array_merge($user_ids, db('user')->where('imei', $model['number'])->column('id'));
            foreach ($user_ids as $user_id) {
                self::userStatusCheck($user_id);
            }
        });
    }

    public static function userStatusCheck($user_id)
    {
        $user = db('user')->where('id', $user_id)->find();
        // $is_black = db('blacklist')->where("(type=1 and number = '{$user_id}') OR (type = 2 and number = '{$user['imei']}') or (type=3 and number='{$user['mobile']}')")
        $is_black = db('blacklist')->where("(type=1 and number = '{$user_id}') or (type=3 and number='{$user['mobile']}')")
            ->where('end_time > ' . time())
            ->count(1);
        if ($is_black == 0) {
            db('user')->where(['id' => $user_id])->setField(['status' => 'normal']);
        }
    }


    public function getTypeList()
    {
        return ['1' => __('Type 1'), '2' => __('Type 2'), '3' => __('Type 3')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function admin()
    {
        return $this->belongsTo('Admin', 'creator', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public static function is_blacklist($user_id, $imei, $mobile)
    {
        $count = db('blacklist')
            ->where(['number' => [['eq', $user_id], ['eq', $imei], ['eq', $mobile], 'or']])
            ->where("number <> ''")
            ->where(['end_time' => ['gt', time()]])
            ->count();
        return $count == 0 ? 0 : 1;
    }


}
