<?php

namespace app\admin\model;

use think\Model;


class ChannelPayway extends Model
{


    // 表名
    protected $name = 'channel_payway';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'Platform_text',
        'status_text',
        'pay_way_text',
    ];


    protected static function init()
    {
        self::afterInsert(function ($row) {
            $pk = $row->getPk();
            $row->getQuery()->where($pk, $row[$pk])->where('weigh', 0)->update(['weigh' => $row[$pk]]);
        });
    }


    public function getPlatformList()
    {
        return ['iOS' => __('Platform iOS'), 'Android' => __('Platform Android'), 'Web' => __('Platform Web')];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '0' => __('Status 0')];
    }


    public function getPlatformTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['Platform']) ? $data['Platform'] : '');
        $list = $this->getPlatformList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function getPayWayList()
    {
        return [
            'AP' => __('Pay_way ap'),
            'WC' => __('Pay_way wc'),
            'AE' => __('Pay_way ae'),
            'GG' => __('Pay_way gg'),
            'BK' => __('Pay_way bk'),
            'CC' => __('Pay_way cc')
        ];
    }

    public function getOpenWayList()
    {
        return [
            '1' => __('Open_way 1'),
            '2' => __('Open_way 2'),
            '3' => __('Open_way 3'),
        ];
    }


    public function getPayWayTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['pay_way']) ? $data['pay_way'] : '');
        $list = $this->getPayWayList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    public function company()
    {
        return $this->belongsTo('channel_company', 'company_id', 'id', [], 'left')->setEagerlyType(0);
    }

    public function payway()
    {
        return $this->belongsTo('pay_way', 'pay_way_id', 'id', [], 'left')->setEagerlyType(0);
    }


}
