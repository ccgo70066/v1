<?php


namespace app\common\model;

use addons\socket\library\GatewayWorker\Applications\App\Message;
use think\Cache;
use think\Db;
use think\Model;

class UserBusiness extends Model
{

    // 表名
    protected $name = 'user_business';
    // 追加属性
    protected $append = [];

    //用户身份:1=用户,2=主播,3=家族成员,4=族长
    const ROLE_USER = 1;
    const ROLE_ANCHOR = 2;
    const ROLE_UNION = 3;
    const ROLE_UNION_MASTER = 4;

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
     * 获取某个用户等级信息
     * @param $userId
     * @return bool|float|mixed|string|null
     */
    public static function getUserLevelInfoById($userId)
    {
        return db('user_business')->alias('ub')
            ->join('level uil', 'ub.level = uil.grade', 'left')
            ->where('ub.id', $userId)
            ->value('uil.icon');
    }


    /**
     * 获取用户贵族信息
     * @param  $userId
     * @return array
     */
    public static function getUserNobleInfoById($userId)
    {
        $noble = db('user_noble')->alias('un')
            ->join('noble unl', 'un.noble_id = unl.id', 'left')
            ->where('un.user_id', $userId)
            ->where('start_time', '<=', date('Y-m-d H:i:s', time()))
            ->where('end_time', '>=', date('Y-m-d H:i:s', time() - config('app.noble_protection_time')))
            ->field('un.id,un.user_id,un.start_time,un.end_time,unl.id as noble_id,unl.name,unl.badge,unl.shop_badge as noble_shop_badge,un.name_color,un.union_room_hide,unl.weigh')
            ->find();
        if ($noble) {
            $noble['is_protection_time'] = strtotime($noble['end_time']) < time() ? 1 : 0;//是否在过期保护期中
        }
        return $noble;
    }

    /*
     * 获取用户贵族
     * @param  $userId
     * @return string
     */
    public static function getUserNobleBadgeById($userId)
    {
        $res = db('user_noble')->alias('un')
            ->join('noble unl', 'un.noble_id = unl.id', 'left')
            ->where('un.user_id', $userId)
            ->whereTime('start_time', '<=', date('Y-m-d H:i:s', time()))
            ->whereTime('end_time', '>=', date('Y-m-d H:i:s', time() - config('app.noble_protection_time')))
            ->field('unl.name,un.name_color,unl.badge,un.noble_id,un.end_time')
            ->find();
        if ($res) {
            $res['end_time'] = date('Y-m-d', strtotime($res['end_time']));
        }
        return $res;
    }

    /*
     * 获取用户贵族图片
     * @param  $userId
     * @return string
     */
    public static function getNobleInfo($user_ids)
    {
        return db('user_noble')->alias('un')
            ->join('noble unl', 'un.noble_id = unl.id', 'left')
            ->where('un.user_id', 'in', $user_ids)
            ->whereTime('start_time', '<=', date('Y-m-d H:i:s', time()))
            ->whereTime('end_time', '>=', date('Y-m-d H:i:s', time() - config('app.noble_protection_time')))
            ->column('unl.badge,un.name_color,un.noble_id', 'user_id');
    }

    /**
     * 获取用户穿戴的头像框
     * @param int $userId
     */
    public static function getWearAdornmentImage(int $userId)
    {
        $res = db('user_adornment')
            ->alias('ua')
            ->join('adornment a', 'a.id=ua.adornment_id', 'left')
            ->where('ua.user_id', $userId)
            ->where('ua.use_status', 1)
            ->where('ua.is_wear', 1)
            ->value('a.cover');
        return $res;
    }

    /**
     * 获取用户穿戴的坐骑
     * @param int $userId
     */
    public static function getWearCarImage(int $userId)
    {
        $res = db('user_car')
            ->alias('ua')
            ->join('car a', 'a.id=ua.car_id', 'left')
            ->where('ua.user_id', $userId)
            ->where('ua.use_status', 1)
            ->where('ua.is_wear', 1)
            ->value('a.cover');
        return $res;
    }

    /**
     * 获取用户穿戴的聊天气泡
     * @param int $userId
     */
    public static function getWearBubbleImage(int $userId)
    {
        $res = db('user_bubble')
            ->alias('ua')
            ->join('bubble a', 'a.id=ua.bubble_id', 'left')
            ->where('ua.user_id', $userId)
            ->where('ua.use_status', 1)
            ->where('ua.is_wear', 1)
            ->field('a.face_image,a.color')
            ->find();
        return $res;
    }


    /**
     * 获取用户穿戴的铭牌
     * @param int $userId
     */
    public static function getWearTailImage(int $userId)
    {
        $res = db('user_tail')
            ->alias('ua')
            ->join('tail a', 'a.id=ua.tail_id', 'left')
            ->where('ua.user_id', $userId)
            ->where('ua.use_status', 1)
            ->where('ua.is_wear', 1)
            ->value('a.face_image');
        return $res;
    }


    public static function getLevelInfo($user_ids)
    {
        return db('user u')
            ->join('user_business s', 'u.id = s.id')
            ->join('level l', 's.level=l.grade', 'left')
            ->where(['u.id' => ['in', $user_ids]])
            ->column('u.nickname,u.avatar,l.icon,u.gender', 'u.id');
    }


    /**
     * 支付成功后续
     * @param string $order_no 商户订单号
     * @param string $trade_no 交易流水号
     * @return void
     * @throws
     */
    public static function order_success(string $order_no, string $trade_no = '', $reorder_flag = false)
    {
        $order = db('user_recharge')->where(['order_no' => $order_no, 'status' => ['gt', 1]])->find();
        if (!$order) {
            return;
        }
        Db::startTrans();
        try {
            $data = ['status' => 1, 'call_time' => datetime()];
            $trade_no && $data['trade_no'] = $trade_no;
            if (!(db('user_recharge')->where('id', $order['id'])->setField($data))) {
                return;
                throw new Exception('充值更新失败' . $order_no);
            }
            $amount = $order['amount'] + ($order['give_amount'] ?: 0);

            user_business_change($order['user_id'], 'amount', $amount, 'increase', '充值', 3, 'recharge');
            db('user_business')->where('id', $order['user_id'])->setInc('recharge_amount', $order['pay_amount']);

            // 首充礼包
            UserBusiness::order_first($order);
            UserBusiness::vip_scope($order['user_id'], $order['amount']);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            error_log_out($e);
            throw new \Exception('充值回调报错');
        }
        send_im_msg_by_system1($order['user_id'], '您于%s充值成功，到账%s金幣，请到钱包查看余额。');
    }

    /** 首充礼包 */
    public static function order_first($order)
    {
        $count = db('user_recharge')->where('user_id', $order['user_id'])->where('id', '<>', $order['id'])->where('status', 1)->count();
        if ($count == 0) {
            $parcel = db('parcel')->where('type', 2)->where('recharge_amount', '=', $order['amount'])->order('recharge_amount', 'desc')->find();
            if (!$parcel) return;
            UserBusiness::reward_give(json_decode($parcel['reward_data'], true), $order['user_id'], '首充礼包');
            db('user_parcel_log')->insert(['user_id' => $order['user_id'], 'parcel_id' => $parcel['id']]);
        }
    }

    /** vip成长 */
    public static function vip_scope($user_id, $amount)
    {
        $vip = db('user_vip')->where('id', $user_id)->find();
        if (!$vip) return;
        $add_amount = bcdiv($amount, 100, 2);
        business_log_add($user_id, 8, 'increase', $vip['scope'], $vip['scope'] + $add_amount, $add_amount, '充值获取');
        $vip['scope'] += $add_amount;
        if ($vip['grade'] > 3) {
            business_log_add($user_id, 9, 'increase', $vip['experience'], $vip['experience'] + $add_amount, $add_amount, '充值获取');
            $vip['experience'] += $add_amount;
        }
        // 检测经验是否可以升级
        for ($i = 0; $i < 5; $i++) {
            $nextGrade = min($vip['grade'] + 1, 6);
            if ($vip['grade'] < $nextGrade) {
                $nextVip = db('vip')->where('grade', $nextGrade)->find();
                if ($vip['scope'] >= $nextVip['scope']) {
                    $vip['grade'] = $nextVip['grade'];
                    $vip['protect'] = 0;
                    $vip['next_protect_time'] = datetime('+1month');
                    $vip['expire_time'] = datetime('+1month');
                    $comment = 'VIP等级升级至' . $nextGrade;
                    db('user_vip_log')->insert(['user_id' => $user_id, 'type' => 2, 'comment' => $comment]);
                    db('user_business')->where('id', $user_id)->setField(['level' => $nextGrade]);
                    UserBusiness::reward_give(json_decode($nextVip['reward_json'], true) ?? [], $user_id, $comment);
                    if ($nextVip['grade'] >= 4) board_notice_delay(
                        Message::CMD_SHOW_VIP_LEVEL_UP,
                        array_index_filter($nextVip, 'name,grade,icon') + ['nickname' => Db::name('user')->where('id', $user_id)->value('nickname')]
                    );
                    send_im_msg_by_system($user_id, '恭喜,您的VIP升級至%s,已解鎖更多專屬特權!');
                }
            }
        }
        db('user_vip')->update($vip);
    }

    /**
     * 奖励发放
     * @param array  $rewardJson 奖励数据
     * @param int    $userId     用户
     * @param string $remark     备注说明
     * @return bool
     */
    public static function reward_give(array $rewardJsonArr, int $userId, string $remark = '')
    {
        if (empty($rewardJsonArr)) {
            return false;
        }
        foreach ($rewardJsonArr as $v) {
            switch ($v['type']) {
                case 'amount'://金幣
                    user_business_change($userId, 'amount', $v['count'], 'increase', $remark, 2);
                    break;
                case 'gift':  //礼物
                    user_gift_add($userId, $v['id'], $v['count']);
                    break;
                case 'adornment': //头像框
                    user_adornment_add($userId, $v['id'], $v['count'], 2);
                    break;
                case 'bubble': //聊天气泡
                    user_bubble_add($userId, $v['id'], $v['count'], 2);
                    break;
                case 'car': //坐骑
                    user_car_add($userId, $v['id'], $v['count'], 2);
                    break;
                case 'vip': // vip
                    user_vip_add($userId, $v['id'], $v['count']);
                    break;
                case 'tail': //尾巴
                    user_tail_add($userId, $v['id'], $v['count'], 2);
                    break;
            }
        }
        return true;
    }

    /**
     * @param $user_id
     * @param $page
     * @param $size
     * @return mixed   status:0=审核中,1=已完成,-1=驳回
     */
    public static function withdraw_log($user_id, $page = 1, $size = 20)
    {
        $list = db('user_withdraw')
            ->field('id,create_time,amount,status,reject_comment')
            ->where('user_id', $user_id)->page($page, $size)
            ->order('id desc')->select();
        foreach ($list as &$item) {
            in_array($item['status'], [0, 1, 2]) && $item['status'] = 0;
            in_array($item['status'], [3]) && $item['status'] = 1;
            in_array($item['status'], [5, -1, -2]) && $item['status'] = -1;
        }
        return $list;
    }

    /**
     * 获取红包权限
     * @info  红包白名单, VIP用户
     * @param $user_id
     */
    public static function getRedPacketAuth($user_id)
    {
        if (get_site_config('red_envelope') != 1) {
            return 1;
        }
        if (db('union_user')->where('user_id', $user_id)->where('status', 'in', [2, 3, 6])->count(1)) {
            return 1;
        }
        if (db('room_admin a')->join('room r', 'a.room_id = r.id')
            ->where(['a.user_id' => $user_id, 'a.role' => 1, 'r.type' => 1])->find()) {
            return 1;
        }
        if (db('red_packet_whitelist')->where('user_id', $user_id)->count(1)) {
            return 1;
        }
        if (db('user_vip')->where('id', $user_id)->where('expire_time', '>', datetime())->count(1)) {
            return 1;
        }

        return 0;
    }

    public static function bind_userinfo(&$res)
    {
        $userInfo = UserBusiness::getLevelInfo(array_column($res, 'user_id'));
        $userNoble = UserBusiness::getNobleInfo(array_column($res, 'user_id'));
        foreach ($res as $key => $v) {
            $data = array_merge($userInfo[$v['user_id']] ?? [], $userNoble[$v['user_id']] ?? []);
            unset($data['id']);
            $res[$key] = array_merge($v, $data);
        }
    }

    /**
     * 统计访问人数
     * @param int $userId
     * @return int|string
     * @throws
     */
    public static function getGuestNumCount(int $userId)
    {
        $total = db('user_guest a')
            ->where('to_user_id', $userId)
            ->count();
        return $total;
    }

    /**
     * 统计关注人数
     * @param int $userId
     * @return int|string
     * @throws
     */
    public static function getFollowNumCount(int $userId)
    {
        $total = db('user_follow')
            ->where('user_id', $userId)
            ->count();
        return $total;
    }

    /**
     * 统计粉丝人数
     * @param int $userId
     * @return int|string
     * @throws
     */
    public static function getFansNumCount(int $userId)
    {
        $total = db('user_follow')
            ->where('to_user_id', $userId)
            ->count();
        return $total;
    }

    /**
     * 统计拉黑人数
     * @param int $userId
     * @return int|string
     * @throws
     */
    public static function getBlackNumCount(int $userId)
    {
        $total = db('user_blacklist')
            ->where('user_id', $userId)
            ->count();
        return $total;
    }

}
