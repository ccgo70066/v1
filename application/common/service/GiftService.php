<?php

namespace app\common\service;

use app\common\exception\ApiException;
use app\common\model\Gift as GiftModel;
use app\common\model\GiftSendStatistic;
use think\Exception;

/**
 * 礼物服务类
 */
class GiftService
{
    protected GiftModel $model;

    /**
     * 参数验证
     * @param  $gift_id
     * @param  $user_id
     * @param  $to_user_ids
     * @param  $room_id
     * @return array [$gift, $to_user_ids_arr, $room_id ?? 0]
     * @throws
     */
    public function checkRoomGiveParam($gift_id, $user_id, $to_user_ids, $room_id = 0): array
    {
        $gift = GiftModel::getGiftById($gift_id, '*', ['type' => ['not in', array_keys(GiftModel::GIFT_CATE_SPECIAL_CATES)]]);
        if (!$gift) throw new ApiException(__('Gift does not exist'));
        if (!$to_user_ids) throw new ApiException(__('User does not exist'));
        $to_user_ids_arr = explode(',', $to_user_ids);
        if (in_array($user_id, $to_user_ids_arr)) throw new ApiException(__('Cannot send gifts to yourself'));
        $to_user_count = db('user')->where('id', 'in', $to_user_ids)->column('id');
        if (count($to_user_ids_arr) != count($to_user_count)) throw new ApiException(__('User does not exist'));
        if ($room_id) {
            if (!db('room')->where('id', $room_id)->value('id')) throw new ApiException(__('Failed to get room'));
        }
        return [$gift, $to_user_ids_arr, $room_id ?? 0];
    }

    /**
     * 房间送礼业务
     * @param $giver_id
     * @param $to_user_ids_arr
     * @param $gift_id
     * @param $count
     * @param $room_id
     * @param $from_type int 送礼方式:-1=背包全送,1=背包选择数量送,2=面板送礼,4=一键清包
     * @return bool
     * @throws
     */
    public function giveGiftByRoom($giver_id, $to_user_ids_arr, $gift_id, $count, $room_id, $from_type = 0)
    {
        $gift = GiftModel::getGiftById($gift_id);
        $room = db('room')->where('id', $room_id)->field('name,union_id,pause')->find();
        if (!$room) {
            throw new ApiException(__('Failed to retrieve room'));
        }
        $gift_log = [];

        foreach ($to_user_ids_arr as $receiver_id) {
            //根据收礼人是否是本房间所属家族成员获取个人提成比例和家族提成比例
            [$user_rate, $union_rate] = $this->receiveGiftsRate($room['union_id'], $receiver_id);
            $gift_log[] = [
                'user_id'     => $giver_id,
                'to_user_id'  => $receiver_id,
                'gift_id'     => $gift_id,
                'gift_val'    => $gift['price'] * $count,
                'count'       => $count,
                'type'        => $from_type,
                'room_id'     => $room_id,
                'create_time' => datetime()
            ];
            user_business_change($receiver_id, 'reward_amount', $gift['price'] * $count * $user_rate, 'increase', '收获礼物:' . $gift['name'] . '×' . $count, 4);
            //如果收礼人是本房间所属家族成员,家族会获得家族收益
            //$union_reward_val = $union_rate * $gift['price'] * $count;
            //$room['union_id'] && union_profit_statistics($room['union_id'], $gift['price'] * $count, $union_reward_val, $receiver_id);
            //根据送礼人、收礼人、房间做送礼统计
            GiftSendStatistic::count_up($giver_id, $receiver_id, $room_id, $gift['price'] * $count);
        }
        db('gift_log')->insertAll($gift_log);
    }

    /**
     * 飘屏
     * @param $cmd int 消息号
     * @return void
     */
    public function screenShow(
        $cmd,
        $nickname = '',
        $gift_name = '',
        $count = null,
        $price = 0,
        $image = null,
        $to_nickname = null,
        $is_all_server = false,
        $room_id
    ) {
        //if ($cmd == Message::CMD_SHOW_GIFT_GLOBAL) {
        //    //广播
        //    $board_data = [
        //        'nickname'      => $nickname,
        //        'gift_name'     => $gift_name,
        //        'count'         => $count,
        //        'price'         => (string)($price + 0),
        //        'image'         => $image,
        //        'to_nickname'   => $to_nickname,
        //        'is_all_server' => $is_all_server,
        //        'room_id'       => $room_id
        //    ];
        //    board_notice(Message::CMD_SHOW_GIFT_GLOBAL, $board_data, '打赏礼物飘屏');
        //}
    }


    /**
     * 根据收礼人和房间所属家族ID获取家族收益提成和个人收益提成
     * @param $union_id int 房间的家族id
     * @param $user_id  int 用户id
     * @return array ['个人收益比例','家族收益比例', '族长直接分成']
     * @throws Exception
     */
    public function receiveGiftsRate($union_id, $user_id)
    {
        if (!$union_id) {
            return [config('app.receive_gifts'), 0, 0];
        }
        //9-16 任何人在房间中送礼族长都有18%收益
        // 在本派对下送礼的，家族都有2%周流水奖励
        return [
            config('app.union_receive_gifts'),
            config('app.gift_union_profit'),
            config('app.gift_union_owner'),
        ];

        //收礼人是否属于本房间家族成员
        // $exist = db('union_user')->where([
        //     'user_id'  => $user_id,
        //     'union_id' => $union_id,
        //     'status'   => ['in', UnionModel::STATUS_JOINED_RANGE]
        // ])->count();
        // if ($exist) {
        //     return [config('app.union_receive_gifts'), config('app.gift_union_owner')];
        // }else {
        //     return [config('app.receive_gifts'), 0];
        // }
    }

    /**
     * 获取礼物墙数据
     * @param array $where 查询条件
     * @param int   $page  当前页码
     * @param int   $size  页码大小
     * @throws Exception
     */
    public static function getGiftsWall($where, $page = 1, $size = 15)
    {
        $typeArr = [GiftModel::GIFT_TYPE_BOARD, GiftModel::GIFT_TYPE_BOX];
        $data = db('gift')->where('status', GiftModel::STATUS_ON)
            ->whereIn('type', $typeArr)
            ->where($where)
            ->field('id,name,image,price_type,price')
            ->order('price desc')
            ->page($page, $size)
            ->select();
        return $data;
    }

    /**
     * 获取礼物墙统计
     * @return [$gift_count,$gain_count]
     */
    public static function getGiftsWallCount($user_id)
    {
        $typeArr = [GiftModel::GIFT_TYPE_BOARD, GiftModel::GIFT_TYPE_BOX];

        $gift_count = db('gift g')->where('status', GiftModel::STATUS_ON)
            ->whereIn('type', $typeArr)
            ->count();

        $gain_count = db('gift_wall l')
            ->join('gift g', 'g.id=l.gift_id', 'left')
            ->whereIn('g.type', $typeArr)
            ->where('l.user_id', $user_id)
            ->count();
        return [$gift_count, $gain_count];
    }

}
