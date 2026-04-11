<?php


namespace app\admin\model;
use think\Model;

class Room extends Model
{
    // 表名
    protected $name = 'room';

    // 追加属性
    protected $append = [
        'cate_text',
        'status_text',
        'way_text',
        'is_close_text',
        'create_time_text',
        'pause_text',
        'is_show_text',
    ];

    public function getCateList()
    {
        return ['1' => __('Cate 1'), '3' => __('Cate 3')];
    }

    public function getIsLockList()
    {
        return ['1' => __('Is_lock 1'), '0' => __('Is_lock 0')];
    }

    public function getIsGiveBoxList()
    {
        return ['1' => __('Is_give_box 1'), '0' => __('Is_give_box 0')];
    }

    public function getIsOpenBoxList()
    {
        return ['1' => __('Is_open_box 1'), '0' => __('Is_open_box 0')];
    }

    public function getIsOpenTreasureList()
    {
        return ['1' => __('Is_open_treasure 1'), '0' => __('Is_open_treasure 0')];
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3'), '0' => __('Status 0'), '-1' => __('Status -1'), '-2' => __('Status -2'), '-3' => __('Status -3')];
    }

    public function getWayList()
    {
        return ['1' => __('Way 1'), '2' => __('Way 2')];
    }

    public function getIsCloseList()
    {
        return ['1' => __('Is_close 1'), '0' => __('Is_close 0')];
    }


    public function getPauseList()
    {
        return ['1' => __('Pause 1'), '2' => __('Pause 2'), '3' => __('Pause 3')];
    }

    public function getIsShowList()
    {
        return ['1' => __('Is_show 1'), '0' => __('Is_show 0')];
    }

    public function getIsHostSeatList()
    {
        return ['0' => __('Is_host_seat 0'), '1' => __('Is_host_seat 1')];
    }

    public function getIsReceiveSeatList()
    {
        return ['0' => __('Is_receive_seat 0'), '1' => __('Is_receive_seat 1')];
    }

    public function getIsBossSeatList()
    {
        return ['0' => __('Is_boss_seat 0'), '1' => __('Is_boss_seat 1')];
    }

    public function getIsRankList()
    {
        return ['0' => __('Is_rank 0'), '1' => __('Is_rank 1')];
    }

    public function getIsScreenList()
    {
        return ['0' => __('Is_screen 0'), '1' => __('Is_screen 1')];
    }



    public function getCateTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['cate']) ? $data['cate'] : '');
        $list = $this->getCateList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsLockTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_lock']) ? $data['is_lock'] : '');
        $list = $this->getIsLockList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsGiveBoxTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_give_box']) ? $data['is_give_box'] : '');
        $list = $this->getIsGiveBoxList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsOpenBoxTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_open_box']) ? $data['is_open_box'] : '');
        $list = $this->getIsOpenBoxList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsOpenTreasureTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_open_treasure']) ? $data['is_open_treasure'] : '');
        $list = $this->getIsOpenTreasureList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getWayTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['way']) ? $data['way'] : '');
        $list = $this->getWayList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsCloseTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_close']) ? $data['is_close'] : '');
        $list = $this->getIsCloseList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getCreateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['create_time']) ? $data['create_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getUpdateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['update_time']) ? $data['update_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getPauseTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['pause']) ? $data['pause'] : '');
        $list = $this->getPauseList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsShowTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_show']) ? $data['is_show'] : '');
        $list = $this->getIsShowList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsHostSeatTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_host_seat']) ? $data['is_host_seat'] : '');
        $list = $this->getIsHostSeatList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsReceiveSeatTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_receive_seat']) ? $data['is_receive_seat'] : '');
        $list = $this->getIsReceiveSeatList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsBossSeatTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_boss_seat']) ? $data['is_boss_seat'] : '');
        $list = $this->getIsBossSeatList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsRankTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_rank']) ? $data['is_rank'] : '');
        $list = $this->getIsRankList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsScreenTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_screen']) ? $data['is_screen'] : '');
        $list = $this->getIsScreenList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getCountdownTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['countdown_time']) ? $data['countdown_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }



    protected function setCreateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setUpdateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setCountdownTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function roomthemecate()
    {
        return $this->belongsTo('app\admin\model\RoomThemeCate', 'theme_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }

    public function roomadmin()
    {
        return $this->belongsTo('app\admin\model\RoomAdmin', 'id', 'room_id and roomadmin.role = 1', [], 'LEFT')->setEagerlyType(0);
    }
}
