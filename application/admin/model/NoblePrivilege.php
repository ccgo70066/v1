<?php

namespace app\admin\model;

use think\Model;


class NoblePrivilege extends Model
{

    

    

    // 表名
    protected $name = 'noble_privilege';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'has_switch_text'
    ];
    

    protected static function init()
    {
        self::afterInsert(function ($row) {
            if (!$row['weigh']) {
                $pk = $row->getPk();
                $row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
            }
        });
    }

    
    public function getHasSwitchList()
    {
        return ['1' => __('Has_switch 1'), '0' => __('Has_switch 0')];
    }


    public function getHasSwitchTextAttr($value, $data)
    {
        $value = $value ?: ($data['has_switch'] ?? '');
        $list = $this->getHasSwitchList();
        return $list[$value] ?? '';
    }




}
