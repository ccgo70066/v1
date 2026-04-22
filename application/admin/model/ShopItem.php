<?php

namespace app\admin\model;

use think\Model;


class ShopItem extends Model
{





    // 表名
    protected $name = 'shop_item';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text',
        'cate_text',
        'show_text',
        'status_text'
    ];


    protected static function init()
    {
        self::afterInsert(function ($row) {
            $pk = $row->getPk();
            //$row->getQuery()->where($pk, $row[$pk])->update(['weigh' => $row[$pk]]);
        });
    }


    public function getTypeList()
    {
        return [
//            '1' => __('Type 1'),
            '2' => __('Type 2'),
            '3' => __('Type 3'),
            //'4' => __('Type 4'),
//            '5' => __('Type 5'),
            '6' => __('Type 6'),
            //'8' => __('Type 8'),
        ];
    }

    public function getCateList()
    {
        return ['1' => __('Cate 1')];
    }

    public function getShowList()
    {
        return ['1' => __('Show 1'), '2' => __('Show 2')];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '0' => __('Status 0')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getCateTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['cate']) ? $data['cate'] : '');
        $list = $this->getCateList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getShowTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['show']) ? $data['show'] : '');
        $valueArr = explode(',', $value);
        $list = $this->getShowList();
        return implode(',', array_intersect_key($list, array_flip($valueArr)));
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setShowAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }


}
