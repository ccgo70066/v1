<?php

namespace app\common\service;

use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\common\exception\ApiException;
use app\common\library\rabbitmq\GiveGiftMQ;
use app\common\model\Gift as GiftModel;
use app\common\model\GiftSendStatistic;
use app\common\model\Room as RoomModel;
use think\Exception;

/**
 * 礼物服务类
 */
class GiftService extends BaseService
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
        $gift = GiftModel::getGiftById($gift_id);
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
     * @param     $user_id
     * @param     $to_user_ids
     * @param     $gifts
     * @param     $room_id
     * @param int $source 送礼方式：-1=背包单个礼物全送,1=背包选择数量送,2=面板送礼,4=一键清包,5=私聊送礼 GiftModel::GIVE_TYPE_BAG_ALL
     * @return void
     * @throws
     */
    public function give_gift($user_id, $to_user_ids, $gifts, $room_id, $source)
    {
        t('GiveGift mq processing');
        t(func_get_args());
        try {
            $gift_info = db('gift')->where('id', 'in', array_column($gifts, 'gift_id'))->order('price desc')->column('id,name,price,screen_show,image', 'id');
            $max_gift = $gift_info[array_key_first($gift_info)];
            $gift_log = [];
            $total_price = ['total' => 0];
            foreach ($to_user_ids as $to_user_id) {
                [$user_rate, $room_rate] = $this->receiveGiftsRate($room_id, $to_user_id);
                $total_price[$to_user_id] = 0;
                $note = '收获礼物:';
                foreach ($gifts as $gift) {
                    $gift_val = $gift_info[$gift['gift_id']]['price'] * $gift['count'];
                    $total_price['total'] += $gift_val;
                    $total_price[$to_user_id] += $gift_val;
                    $gift_log[] = [
                        'user_id'    => $user_id,
                        'to_user_id' => $to_user_id,
                        'gift_id'    => $gift['gift_id'],
                        'count'      => $gift['count'],
                        'gift_val'   => $gift_val,
                        'type'       => $source,
                        'room_id'    => $room_id,
                    ];
                    $note .= $gift_info[$gift['gift_id']]['name'] . '×' . $gift['count'] . ',';
                    $gift['gift_id'] == $max_gift['id'] && $max_gift['count'] = $gift['count'];
                    GiftService::instance()->wall_add($to_user_id, $gift['gift_id'], $gift['count']);
                }
                user_business_change($to_user_id, 'reward_amount', $total_price[$to_user_id] * $user_rate, 'increase', substr($note, 0, -1), 4);
                room_profit_statistics($room_id, $total_price[$to_user_id], $room_rate * $total_price[$to_user_id], $to_user_id);
                RankService::instance()->count_up($user_id, $to_user_id, $room_id, $total_price[$to_user_id]);
            }
            UserBusinessService::instance()->level_scope($user_id, $total_price['total']);
            db('gift_log')->insertAll($gift_log);

            if ($room_id) {
                //更新热力值
                $redis = redis();
                $hot = $redis->hIncrBy(RedisService::ROOM_HOT_KEY, $room_id, 10 * $total_price['total']);

                //麦上打赏统计更新
                if (db('room')->where('id', $room_id)->value('pause') == RoomModel::RoomPauseOn) {
                    $roomService = RoomService::instance();
                    $seat_user = $roomService->getSeatUserId($room_id);
                    $key_value_update = [];
                    //匹配收礼人是否在座上,记录麦上打赏明细
                    foreach ($to_user_ids as $to_user_id) {
                        $seat_no = array_search($to_user_id, $seat_user);
                        if (!$seat_no) continue;
                        $key_value_update[$seat_no] = $total_price[$to_user_id];
                        update_seat_gift_val($room_id, $seat_no, $user_id, $total_price[$to_user_id]);
                    }
                    if ($key_value_update) $roomService->incrSeatGiftValue($room_id, $key_value_update); //在座则增加麦上打赏额统计
                }
                $imService = ImService::instance();
                $imService->roomGiveGiftNotice($room_id, $hot);
                if ($source == 4) $imService->roomGiveGiftAllMessage($room_id, $user_id, $to_user_ids[0], $gifts);
                else $imService->roomGiveGiftMessage($room_id, $user_id, $to_user_ids, $gifts[0]['gift_id'], $gifts[0]['count']);
                //飘屏
                if ($max_gift['screen_show'] == GiftModel::ScreenShowOn ||
                    ($max_gift['screen_show'] == GiftModel::ScreenShowPrice && $max_gift['price'] * $max_gift['count'] >= get_site_config('gift_value'))) {
                    foreach ($to_user_ids as $to_user_id) {
                        $to_nickname = RedisService::getUserCache($to_user_id, 'nickname');
                        $this->screenShow(
                            Message::CMD_SHOW_GIFT_GLOBAL,
                            db('user')->where('id', $user_id)->value('nickname') ?? '',
                            $max_gift['name'],
                            $max_gift['count'],
                            $max_gift['price'],
                            $max_gift['image'],
                            $to_nickname,
                            false,
                            $room_id
                        );
                    }
                }
            }
        } catch (Exception $e) {
            t('GiveGift mq processing error');
            t($e->getMessage());
        }
        t('GiveGift mq processing end');
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
        $room_id = 0
    ) {
        if ($cmd == Message::CMD_SHOW_GIFT_GLOBAL) {
            //广播
            $board_data = [
                'nickname'      => $nickname,
                'gift_name'     => $gift_name,
                'count'         => $count,
                'price'         => (string)($price + 0),
                'image'         => $image,
                'to_nickname'   => $to_nickname,
                'is_all_server' => $is_all_server,
                'room_id'       => $room_id
            ];
            board_notice(Message::CMD_SHOW_GIFT_GLOBAL, $board_data, '打赏礼物飘屏');
        }
    }

    public function wall_add($user_id, $gift_id, $count)
    {
        $sql = db('gift_wall')->fetchSql()->insert([
            'user_id' => $user_id,
            'gift_id' => $gift_id,
            'count'   => $count,
        ]);
        db()->execute($sql . " on duplicate key update count=count+{$count}");
    }


    /**
     * 根据收礼人和房间所属家族ID获取家族收益提成和个人收益提成
     * @param $room_id  int 房间id
     * @param $user_id  int 用户id
     * @return array ['个人收益比例','家族收益比例', '族长直接分成']
     * @throws Exception
     */
    public function receiveGiftsRate($room_id, $user_id)
    {
        if (!$room_id) {
            return [get_site_config('receive_gifts'), 0, 0];
        }
        return [
            get_site_config('room_receive_gifts'),
            get_site_config('gift_room_profit'),
            get_site_config('gift_room_owner'),
        ];
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
        $typeArr = [GiftModel::GIFT_TYPE_BOARD];
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
        $typeArr = [GiftModel::GIFT_TYPE_BOARD];

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
