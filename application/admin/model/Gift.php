<?php

namespace app\admin\model;

use think\Model;

class Gift extends Model
{

    // 表名
    protected $name = 'gift';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text',
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
            '1' => __('Type 1'),
            //'2' => __('Type 2'),
            '3' => __('Type 3'),
            '4' => __('Type 4'),
            //'6' => __('Type 6'),
            // '8' => __('Type 8'),

        ];
    }

    public function getPriceTypeList()
    {
        return [
            '1' => __('Price_type 1'),
            //'2' => __('Price_type 2'),
            //'3' => __('Price_type 3')
        ];
    }

    public function getScreenShowList()
    {
        return ['0' => __('Screen_show 0'), '1' => __('Screen_show 1'), '2' => __('Screen_show 2')];
    }

    public function getNoticeList()
    {
        return ['1' => __('Notice 1'), '0' => __('Notice 0')];
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

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getPriceTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['price_type']) ? $data['price_type'] : '');
        $list = $this->getPriceTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getScreenShowTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['screen_show']) ? $data['screen_show'] : '');
        $list = $this->getScreenShowList();
        return isset($list[$value]) ? $list[$value] : '';
    }

}
