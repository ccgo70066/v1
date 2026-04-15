<?php

namespace app\common\service;

use app\common\model\ShopItem as ShopModel;
use think\Cache;
use think\Db;
use think\Exception;


/**
 * 用户业务类
 */
class UserBusinessService extends BaseService
{
    protected static $instance = null;

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }
    /**
     * 获取所有用户穿戴的头像框
     */
    public static function getWearAdornmentImages($user_ids)
    {
        $res = db('user_adornment')
            ->alias('ua')
            ->join('adornment a', 'a.id=ua.adornment_id', 'left')
            ->where('ua.user_id', 'in', $user_ids)
            ->where('ua.use_status', 1)
            ->where('ua.is_wear', 1)
            ->column('ua.user_id,a.face_image');
        return $res;
    }

    /**
     * 清空用户的信息相关缓存
     * @param string $user_id 为0清空所有用户的信息相关缓存
     * @return void
     */
    public static function clear_cache($user_id)
    {
        $user_id && cache('user:icon_info:' . $user_id, null);
        !$user_id && Cache::clear('small_data_user');
    }

    /**
     * 获取用户钱包、等级等数据
     * @param int $userId 用户id
     */
    public static function getInfo($userId)
    {
        $data = db('user_business')
            ->field('payword,safe_code,create_time,update_time', true)
            ->where('id', $userId)
            ->find();

        if (!$data) {
            throw new Exception('用户不存在');
        }
        $data['level_name'] = Db::name('level')->where('grade', $data['level'])->value('name') ?: $data['level'];
        $data['amount'] = (int)$data['amount'];
        return $data;
    }

    /**
     * 获取用户账单标签数据
     */
    public static function getBillLables()
    {
        $data = [
            ['name' => '充值记录', 'sub' => [['type' => 2, 'form' => '3', 'name' => '充值记录']]],
            [
                'name' => '打赏记录',
                'sub'  => [
                    ['type' => 2, 'form' => '4', 'name' => '打赏礼物'],
                ]
            ],
            [
                'name' => '收益记录',
                'sub'  => [
                    ['type' => 4, 'form' => '4', 'name' => '打赏礼物'],
                    ['type' => 4, 'form' => '13', 'name' => '收益提现'],
                    ['type' => 4, 'form' => '8', 'name' => '兑换金幣'],
                    ['type' => 4, 'form' => '10', 'name' => '个人守护分成'],
                    ['type' => 4, 'form' => '11', 'name' => '家族收益取领'],
                    ['type' => 4, 'form' => '12', 'name' => '流水奖励'],
                    ['type' => 4, 'form' => '0', 'name' => '其他'],
                ]
            ],
            ['name' => '商城兑换', 'sub' => [['type' => 2, 'form' => '1', 'name' => '商城兑换'],]],
            [
                'name' => '红包记录',
                'sub'  => [
                    ['type' => 2, 'form' => '6', 'name' => 'IM红包', 'special' => 1],
                    ['type' => 2, 'form' => '7', 'name' => '房间红包', 'special' => 1],
                ]
            ],
            [
                'name' => '兑换记录',
                'sub'  => [
                    ['type' => 2, 'form' => '8', 'name' => '兑换金幣'],
                    ['type' => 2, 'form' => '9', 'name' => '兑换游戏券'],
                ]
            ],
            [
                'name' => '礼包活动',
                'sub'  => [
                    ['type' => 2, 'form' => '2', 'name' => '活动奖励'],
                    ['type' => 2, 'form' => '0', 'name' => '其它'],
                ]
            ],
            [
                'name' => '红豆记录',
                'sub'  => [
                    ['type' => 3, 'form' => '1', 'name' => '商城兑换'],
                    ['type' => 3, 'form' => '2', 'name' => '活动奖励'],
                    ['type' => 3, 'form' => '0', 'name' => '其它'],
                ]
            ],

        ];
        return $data;
    }

    /**
     * 获取用户背包数据
     * @param int $userId 用户ID
     * @param int $type   背包数据类型
     */
    public static function getBagData($userId, $type)
    {
        $where = ['ua.user_id' => $userId];
        $list = [];
        switch ($type) {
            case ShopModel::TYPE_GIFT:
                $list = self::getBagGifts($where);
                break;
            case ShopModel::TYPE_ADORNMENT:
                $list = self::getBagAdornments($where);
                break;
            case ShopModel::TYPE_CAR:
                $list = self::getBagCars($where);
                break;
            case ShopModel::TYPE_BUBBLE:
                $list = self::getBagBubbles($where);
                break;
            case ShopModel::TYPE_TAIL:
                $list = self::getBagTails($where);
                break;
        }
        return $list;
    }

    /**
     * 获取用户背包中的礼物数据
     * @param array $where 查询条件
     */
    public static function getBagGifts($where)
    {
        $data = db('user_bag')->alias('ua')
            ->join('gift', 'gift.id=ua.gift_id', 'left')
            ->field("gift.id,gift.name,'1' as type,gift.image,1 as cate,gift.price,ua.*")
            ->where($where)
            ->where('count', '>', 0)
            ->order('gift.price desc')
            ->select();

        $list = [];
        //use_status状态:0=未使用,1=已使用,2=已过期'
        if (isset($data)) {
            foreach ($data as $val) {
                $list[] = [
                    'gift_id'     => $val['gift_id'],
                    'type'        => ShopModel::TYPE_GIFT,
                    'name'        => $val['name'],
                    'image'       => $val['image'],
                    'cate'        => $val['cate'],
                    'price'       => $val['price'],
                    'explain'     => $val['explain'] ?? '',
                    'count'       => $val['count'],
                    'create_time' => $val['create_time'],
                ];
            }
        }
        unset($data);
        return $list;
    }

    /**
     * 获取用户背包中的头像框数据
     * @param array $where 查询条件
     */
    public static function getBagAdornments($where)
    {
        $data = db('user_adornment')->alias('ua')
            ->join('adornment a', 'a.id=ua.adornment_id')
            ->join('shop_item s', ' a.id = s.item_id and s.type = 2', 'left')
            ->field("a.id,a.name,a.cover,a.face_image,s.price,s.cate,s.days,a.is_renew, ua.*")
            ->where($where)
            ->where('!(ua.use_status = 2 and ua.is_wear = 0)')
            ->orderRaw('use_status = 2 asc')
            ->order('ua.create_time desc')
            ->select();

        $list = [];
        if (isset($data)) {
            foreach ($data as $val) {
                //如果存在过期时间 计算当前装扮的剩余天数
                $days = $val['expired_days'];
                $hours = 0;
                $time = date('Y-m-d H:i:s');
                if ($val['expired_time'] && $val['expired_time'] > $time) {
                    $diff = get_new_expiry_days($val['expired_time']);
                    $days = $diff->days;
                    $hours = $diff->hours;
                }

                $list[] = [
                    'id'            => $val['adornment_id'],
                    'type'          => ShopModel::TYPE_ADORNMENT,
                    'name'          => $val['name'],
                    'image'         => $val['cover'],
                    'face_image'    => $val['face_image'],
                    'cate'          => $val['cate'],
                    'price'         => $val['price'] ?: 0,
                    'explain'       => $val['explain'] ?? '',
                    'use_status'    => $val['use_status'],
                    'is_wear'       => $val['is_wear'],
                    'is_renew'      => $val['is_renew'],
                    'count'         => '1',
                    'days'          => $val['days'] ?? '',
                    'expired_time'  => $val['expired_time'],
                    'expired_days'  => $days,
                    'expired_hours' => $hours,
                    'create_time'   => $val['create_time'],
                ];
            }
        }
        unset($data);
        return $list;
    }

    /**
     * 获取用户背包中的坐骑数据
     * @param array $where 查询条件
     */
    public static function getBagCars($where)
    {
        $data = db('user_car')->alias('ua')
            ->join('car a', 'a.id=ua.car_id')
            ->join('shop_item s', ' a.id = s.item_id and s.type = 3', 'left')
            ->field("a.id,a.name,a.cover,a.face_image,s.price,s.cate,s.days,a.is_renew, ua.*")
            ->where($where)
            ->where('!(ua.use_status = 2 and ua.is_wear = 0)')
            ->orderRaw('use_status = 2 asc')
            ->order('ua.create_time desc')
            ->select();

        $list = [];
        if (isset($data)) {
            foreach ($data as $val) {
                //如果存在过期时间 计算当前的剩余天数
                $days = $val['expired_days'];
                $hours = 0;
                $time = date('Y-m-d H:i:s');
                if ($val['expired_time'] && $val['expired_time'] > $time) {
                    $diff = get_new_expiry_days($val['expired_time']);
                    $days = $diff->days;
                    $hours = $diff->hours;
                }

                $list[] = [
                    'id'            => $val['car_id'],
                    'type'          => ShopModel::TYPE_CAR,
                    'name'          => $val['name'],
                    'image'         => $val['cover'],
                    'face_image'    => $val['face_image'],
                    'cate'          => $val['cate'],
                    'price'         => $val['price'] ?: 0,
                    'explain'       => $val['explain'] ?? '',
                    'use_status'    => $val['use_status'],
                    'is_wear'       => $val['is_wear'],
                    'is_renew'      => $val['is_renew'],
                    'count'         => '1',
                    'days'          => $val['days'] ?? '',
                    'expired_time'  => $val['expired_time'],
                    'expired_days'  => $days,
                    'expired_hours' => $hours,
                    'create_time'   => $val['create_time'],
                ];
            }
        }
        unset($data);
        return $list;
    }

    /**
     * 获取用户背包中的聊天气泡数据
     * @param array $where 查询条件
     */
    public static function getBagBubbles($where)
    {
        $data = db('user_bubble')->alias('ua')
            ->join('bubble a', 'a.id=ua.bubble_id')
            ->join('shop_item s', ' a.id = s.item_id and s.type = 6', 'left')
            ->field("a.id,a.color,a.name,a.cover,a.face_image,s.price,s.cate,s.days,a.is_renew, ua.*")
            ->where($where)
            ->where('!(ua.use_status = 2 and ua.is_wear = 0)')
            ->orderRaw('use_status = 2 asc')
            ->order('ua.create_time desc')
            ->select();

        $list = [];
        if (isset($data)) {
            foreach ($data as $val) {
                //如果存在过期时间 计算当前装扮的剩余天数
                $days = $val['expired_days'];
                $hours = 0;
                $time = date('Y-m-d H:i:s');
                if ($val['expired_time'] && $val['expired_time'] > $time) {
                    $diff = get_new_expiry_days($val['expired_time']);
                    $days = $diff->days;
                    $hours = $diff->hours;
                }

                $list[] = [
                    'id'            => $val['bubble_id'],
                    'type'          => ShopModel::TYPE_BUBBLE,
                    'name'          => $val['name'],
                    'image'         => $val['cover'],
                    'face_image'    => $val['face_image'],
                    'cate'          => $val['cate'],
                    'color'         => $val['color'],
                    'price'         => $val['price'] ?: 0,
                    'explain'       => $val['explain'] ?? '',
                    'use_status'    => $val['use_status'],
                    'is_wear'       => $val['is_wear'],
                    'is_renew'      => $val['is_renew'],
                    'count'         => '1',
                    'days'          => $val['days'] ?? '',
                    'expired_time'  => $val['expired_time'],
                    'expired_days'  => $days,
                    'expired_hours' => $hours,
                    'create_time'   => $val['create_time'],
                ];
            }
        }
        unset($data);
        return $list;
    }


    /**
     * 获取用户背包中的铭牌数据
     * @param array $where 查询条件
     */
    public static function getBagTails($where)
    {
        $data = db('user_tail')->alias('ua')
            ->join('tail a', 'a.id=ua.tail_id')
            ->join('shop_item s', ' a.id = s.item_id and s.type = 8', 'left')
            ->field("a.id,a.name,a.cover,a.face_image,s.price,s.cate,s.days,a.is_renew, ua.*")
            ->where($where)
            ->where('!(ua.use_status = 2 and ua.is_wear = 0)')
            ->orderRaw('use_status = 2 asc')
            ->order('ua.create_time desc')
            ->select();

        $list = [];
        if (isset($data)) {
            foreach ($data as $val) {
                //如果存在过期时间 计算当前的剩余天数
                $days = $val['expired_days'];
                $hours = 0;
                $time = date('Y-m-d H:i:s');
                if ($val['expired_time'] && $val['expired_time'] > $time) {
                    $diff = get_new_expiry_days($val['expired_time']);
                    $days = $diff->days;
                    $hours = $diff->hours;
                }

                $list[] = [
                    'id'            => $val['tail_id'],
                    'type'          => ShopModel::TYPE_TAIL,
                    'name'          => $val['name'],
                    'image'         => $val['cover'],
                    'face_image'    => $val['face_image'],
                    'cate'          => $val['cate'],
                    'price'         => $val['price'] ?: 0,
                    'explain'       => $val['explain'] ?? '',
                    'use_status'    => $val['use_status'],
                    'is_wear'       => $val['is_wear'],
                    'is_renew'      => $val['is_renew'],
                    'count'         => '1',
                    'days'          => $val['days'] ?? '',
                    'expired_time'  => $val['expired_time'],
                    'expired_days'  => $days,
                    'expired_hours' => $hours,
                    'create_time'   => $val['create_time'],
                ];
            }
        }
        unset($data);
        return $list;
    }

    /**
     * 获取等级列表与奖励信息
     */
    public static function getLevelList()
    {
        $lists = db('level')
            ->field('broadcast,reward_json,create_time,update_time', true)
            ->order('grade asc')
            ->select();

        $data['list'] = $lists;
        return $data;
    }

    /**
     * @param     $user_id
     * @param int $role 角色:1=用户,2=厅主,3=主播,4=运营
     * @return void
     */
    public static function set_user_role($user_id, int $role): void
    {
        $where = is_array($user_id) ? ['id' => ['in', $user_id]] : ['id' => $user_id];
        db('user_business')->where($where)->setField('role', $role);
    }

    public function level_scope($user_id, $amount)
    {
        $level = db('level')->column('scope,name', 'grade');
        $user = db('user_business')->where('id', $user_id)->field('level,level_scope')->find();
        $user['level_scope'] += $amount * 10;
        $flag = false;

        while (isset($level[$user['level'] + 1]) && $user['level_scope'] >= $level[$user['level'] + 1]['scope']) {
            $user['level'] += 1;
            $flag = true;
        }
        $flag && send_im_msg_by_system($user_id, sprintf('恭喜,您的财富等级升級至%s,已解鎖更多專屬特權!', $level[$user['level']]['name']));
        db('user_business')->where('id', $user_id)->setField($user);
    }

}
