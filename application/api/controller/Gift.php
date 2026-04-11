<?php

namespace app\api\controller;

use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\common\exception\ApiException;
use app\common\library\rabbitmq\GiveGiftMQ;
use app\common\model\Gift as GiftModel;
use app\common\model\GiftSendStatistic;
use app\common\model\Room as RoomModel;
use app\common\service\GiftService;
use app\common\service\ImService;
use app\common\service\RedisService;
use app\common\service\RoomService;
use think\Db;
use think\Log;
use think\Request;
use Throwable;

/**
 * 礼物
 * @ApiWeigh    (100)
 */
class Gift extends Base
{
    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];
    protected $service;
    protected GiftModel $model;

    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->service = new GiftService();
        $this->model = new GiftModel();
    }


    /**
     * @ApiTitle    (获取房间礼物分类)
     * @ApiSummary  (根据code做数据类型解析,panel为普通礼物,bag为背包礼物,noble为特权礼物)
     * @ApiMethod   (get)
     */
    public function room_gift_cate()
    {
        $data = [
            ['code' => 'bag', 'cate' => GiftModel::GIFT_CATE_BAG, 'name' => '背包'],
            ['code' => 'panel', 'cate' => GiftModel::GIFT_CATE_HOT, 'name' => '礼物'],
            ['code' => 'special', 'cate' => GiftModel::GIFT_CATE_SPECIAL, 'name' => '专场'],
            //['code' => 'box', 'cate' => GiftModel::GIFT_CATE_BOX, 'name' => '魔法'],
            //['code' => 'noble', 'cate' => GiftModel::GIFT_CATE_PRIVILEGE, 'name' => '特权'],
            // ['code' => 'panel', 'cate' => GiftModel::GIFT_CATE_JET, 'name' => '土豪'],
        ];
        $this->success('', $data);
    }

    /**
     * @ApiTitle    (房间礼物列表)
     * @ApiParams   (name="cate",    type="int", required=true,  rule="min:0", description="礼物类别,通过room_gift_cate获取")
     * @ApiMethod   (get)
     */
    public function room_gift_list()
    {
        $cate = input('cate');
        $result = [];
        $userId = $this->auth->id;
        if (array_key_exists($cate, GiftModel::GIFT_TYPE_BOARD_CATES)) {
            $result = db('gift')->where(['status' => 1, 'type' => 1, 'cate' => $cate])
                ->field('id as gift_id,name,image,price,animate,noble_limit')->order('weigh asc')->select();

            if ($cate == GiftModel::GIFT_CATE_PRIVILEGE) {
                $noble_id = db('user_noble')->where('user_id', $userId)->value('noble_id') ?: 0;
                foreach ($result as &$value) {
                    $value['noble_name'] = '';
                    $value['noble_badge'] = '';
                    if ($value['noble_limit']) {
                        $noble = Db::name('noble')->where('id', $value['noble_limit'])->field('name,badge')->find();
                        $value['noble_name'] = $noble['name'];
                        $value['noble_badge'] = $noble['badge'];
                        $value['noble_available'] = ($noble_id >= $value['noble_limit']) ? 1 : 0;
                    }
                }
            }
        }
        if ($cate == GiftModel::GIFT_CATE_BAG) {
            $bag = db('gift g')
                ->join('user_bag ub', 'g.id = ub.gift_id')
                ->where(['ub.user_id' => $userId, 'ub.count' => ['>', 0]])
                ->field('ub.gift_id,g.name,ub.count,g.image,g.price,g.animate,0 as `is_box`')
                ->order('g.weigh asc')
                ->select() ?: [];
            $result = array_merge($result, $bag);
        }

        $this->success('', $result);
    }

    /**
     * @ApiTitle    (獲取下载资源)
     * @ApiMethod   (get)
     */
    public function get_animate()
    {
        $result = db('gift')->where('animate', 'neq', '')->order('price asc')->column('animate');
        $this->success('', $result);
    }

    /**
     * @ApiTitle    (聊天室赠送礼物)
     * @ApiParams   (name="to_user_ids",   type="string", required=true,  rule="min:0", description="收禮人Id:多個以逗號隔開")
     * @ApiParams   (name="gift_id",    type="int", required=true,  rule="min:0", description="禮物ID")
     * @ApiParams   (name="gift_count", type="int", required=true,  rule="min:0", description="每个用户禮物數量")
     * @ApiParams   (name="source",     type="int", required=true, rule="", description="送禮來源:-1:背包单个礼物全送,1=背包選擇數量送,2=面板送禮")
     * @ApiParams   (name="room_id",    type="int", required=true, rule="min:0", description="房間ID")
     * @throws
     */
    public function room_give_gift()
    {
        $user_id = $this->auth->id;
        $this->operate_check('give_gift:' . $user_id, 1);
        $gift_id = input('gift_id');
        $count = abs((int)input('gift_count'));

        list($gift, $to_user_ids_arr, $room_id) = $this->service->checkRoomGiveParam($gift_id, $user_id, input('to_user_ids'), input('room_id'));
        $total_amount = $gift['price'] * $count * count($to_user_ids_arr);
        Db::startTrans();
        try {
            $gift_count = count($to_user_ids_arr) * $count;
            if ((input('source') == GiftModel::GIVE_TYPE_BAG || input('source') == GiftModel::GIVE_TYPE_BAG_ONE_ALL)) {
                if (input('source') == GiftModel::GIVE_TYPE_BAG_ONE_ALL && count($to_user_ids_arr) > 1)
                    throw new ApiException(__('Only one recipient can be selected for full gift delivery'));
                $result = db('user_bag')->where(['user_id' => $user_id, 'gift_id' => $gift_id, 'count' => ['>=', $gift_count]])
                    ->setDec('count', $gift_count);
                if (!$result) throw new ApiException(__('Insufficient gifts in backpack'));
            } else {
                $note = '打赏礼物:' . $gift['name'] . '×' . ($gift_count);
                user_business_change($user_id, 'amount', $total_amount, 'decrease', $note, 4);
            }
            Db::commit();
        } catch (ApiException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (Throwable $e) {
            Db::rollback();
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error(__('Network busy'));
        }
        $this->service->giveGiftByRoom($user_id, $to_user_ids_arr, $gift_id, $count, $room_id ?: 0, input('source'));

        //更新热力值
        $redis = redis();
        $hot = $redis->hIncrBy(RedisService::ROOM_HOT_KEY, $room_id, 10 * $total_amount);

        //麦上打赏统计更新
        $pause = db('room')->where('id', $room_id)->value('pause');
        if ($pause == RoomModel::RoomPauseOn) {
            $roomService = new RoomService();
            //获取在座用户
            $seat_user = $roomService->getSeatUserId($room_id);
            $key_value_update = [];

            //匹配收礼人是否在座上,记录麦上打赏明细
            foreach ($to_user_ids_arr as $to_user_id) {
                $seat_no = array_search($to_user_id, $seat_user);
                if (!$seat_no) {
                    continue;
                }
                $key_value_update[$seat_no] = $gift['price'] * $count;
                update_seat_gift_val($room_id, $seat_no, $user_id, $gift['price'] * $count);
            }
            //在座则增加麦上打赏额统计
            if ($key_value_update) {
                $roomService->incrSeatGiftValue($room_id, $key_value_update);
            }
        }
        $imService = new ImService();
        //聊天室送礼通知,前端刷新热力值和麦上打赏统计
        $imService->roomGiveGiftNotice($room_id, $hot);
        //聊天室送礼消息,聊天公屏送礼消息
        $imService->roomGiveGiftMessage($room_id, $user_id, $to_user_ids_arr, $gift_id, $count);
        $mq_gift = [['gift_id' => $gift_id, 'count' => $count, 'price' => $gift['price'], 'type' => $gift['type']]];
        mq_publish(GiveGiftMQ::instance(), [
            'user_id'     => $user_id,
            'to_user_ids' => $to_user_ids_arr,
            'gifts'       => $mq_gift,
            'room_id'     => $room_id,
        ]);
        //飘屏
        if ($gift['screen_show'] == GiftModel::ScreenShowOn ||
            ($gift['screen_show'] == GiftModel::ScreenShowPrice && $total_amount >= get_site_config('gift_value'))) {
            foreach ($to_user_ids_arr as $to_user_id) {
                $to_nickname = RedisService::getUserCache($to_user_id, 'nickname');
                //$this->service->screenShow(
                //    Message::CMD_SHOW_GIFT_GLOBAL,
                //    $this->auth->nickname,
                //    $gift['name'],
                //    $count,
                //    $gift['price'],
                //    $gift['image'],
                //    $to_nickname,
                //    $gift['cate'] == GiftModel::GIFT_CATE_SPECIAL,
                //    $room_id
                //);
            }
        }
        $this->success();
    }

    /**
     * @ApiTitle    (一鍵清包)
     * @ApiParams   (name="to_user_id",   type="string", required=true,  rule="min:0", description="收禮人Id")
     * @ApiParams   (name="room_id",    type="int", required=false, rule="min:0", description="雲信房間ID")
     * @ApiParams   (name="seat",    type="int", required=false, rule="min:0", description="座位號,1-9,不在座位上則不傳")
     */
    public function give_gift_all()
    {
        $user_id = $this->auth->id;
        $this->operate_check('give_gift_all:' . $user_id, 1);
        $receiver = input('to_user_id');
        $receivers = explode(',', input('to_user_id'));
        $roomService = new RoomService();
        if (count($receivers) <> 1) {
            throw new ApiException(__('Quick gift delivery is limited to one person'));
        }
        if ($user_id == $receiver) {
            throw new ApiException(__('Cannot send gifts to yourself'));
        }

        $bag_gift_count = db('user_bag')->where(['user_id' => $user_id])->sum('count');
        if ($bag_gift_count < 1) {
            throw new ApiException(__('Insufficient gifts in backpack'));
        }
        $room_id = input('room_id', 0);
        $seat_num = input('seat');
        if (!$seat_num) {
            $seat_user = $roomService->getSeatUserId($room_id);
            //匹配收礼人是否在座上,记录麦上打赏明细
            $seat_num = array_search($receiver, $seat_user) ?: 0;
        }

        try {
            Db::startTrans();
            $bag_info = db('gift g')
                ->join('user_bag ub', 'g.id = ub.gift_id')
                ->where(['ub.user_id' => $user_id, 'ub.count' => ['>', 0]])
                ->field('ub.count,ub.gift_id,g.price,g.animate,g.type,g.name,price*count as gift_val,g.image')
                ->order('g.price', 'desc')
                ->select();
            $delete = db('user_bag')->where(['user_id' => $user_id])->delete();
            if (!$delete) {
                throw new ApiException(__('Insufficient gifts in backpack'));
            }

            $redis = redis();
            if ($room_id) {
                $redis_room = redis()->hGet(RedisService::USER_NOW_ROOM_KEY, $user_id);
                if ($room_id <> $redis_room) {
                    Log::error('用户' . $user_id . 'redis房间号:' . $redis_room . '与传参room_id:' . $room_id . '不一致');
                    throw new ApiException(__('Network error, please re-enter the room'));
                }

                $room = db('room')->where('id', $room_id)->find();
                $sum_gift_val = array_sum(array_column($bag_info, 'gift_val'));
            }

            $gift_log = [];

            $send_text = '';
            foreach ($bag_info as $item) {
                if ($send_text) {
                    $send_text .= ',';
                }
                $gift_log[] = [
                    'user_id'           => (int)$user_id,
                    'to_user_id'        => (int)$receiver,
                    'gift_id'           => (int)$item['gift_id'],
                    'gift_val'          => (int)$item['gift_val'],
                    'count'             => (int)$item['count'],
                    'type'              => GiftModel::GIVE_TYPE_BAG_ALL,
                    'room_id'           => (int)$room_id,
                    'union_id'          => (int)($room['union_id']),
                    'union_reward_rate' => config('app.gift_union_owner'),
                    'create_time'       => datetime()
                ];
                $item['price'] = (int)$item['price'];
                $send_text .= $item['name'] . '(' . $item['price'] . ')×' . $item['count'];
            }
            [$user_rate, $union_rate] = $this->service->receiveGiftsRate($room['id'], $receiver);
            user_business_change($receiver, 'reward_amount', $sum_gift_val * $user_rate, 'increase', '收获礼物:' . $send_text, 4);
            db('gift_log')->insertAll($gift_log);

            if ($room_id) {
                foreach ($bag_info as $key => $value) {
                    $mq_gift_info[$key] = $value;
                    unset($mq_gift_info[$key]['animate']);
                    unset($mq_gift_info[$key]['name']);
                    unset($mq_gift_info[$key]['gift_val']);
                    unset($mq_gift_info[$key]['image']);
                    $msg_gift_info[$key] = $value;
                    $msg_gift_info[$key]['price'] = (string)($msg_gift_info[$key]['price'] + 0);
                    unset($msg_gift_info[$key]['gift_id']);
                }

                $union_reward_val = $union_rate * $sum_gift_val;
                $room['union_id'] && union_profit_statistics($room['union_id'], $sum_gift_val, $union_reward_val, $receiver);

                GiftSendStatistic::count_up($user_id, $receiver, $room_id, $sum_gift_val);

                $hot = $redis->hIncrBy(RedisService::ROOM_HOT_KEY, $room_id, $sum_gift_val * 10);
                //麦上打赏统计
                if ($room['pause'] == RoomModel::RoomPauseOn && $seat_num) {
                    (new RoomService())->incrSeatGiftValue($room_id, [$seat_num => $sum_gift_val]);
                    update_seat_gift_val($room_id, $seat_num, $user_id, $sum_gift_val);
                }
                $imService = new ImService();
                //聊天室通知
                $imService->roomGiveGiftAllMessage($room_id, $user_id, $receiver, $msg_gift_info);
                $imService->roomGiveGiftNotice($room_id, $hot);
                mq_publish(GiveGiftMQ::instance(), [
                    'user_id'     => $user_id,
                    'to_user_ids' => $receivers,
                    'gifts'       => $mq_gift_info,
                    'room_id'     => $room_id
                ]);
            }
            Db::commit();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            Db::rollback();
            error_log_out($e);
            $this->error(show_error_notify($e));
        }

        $this->success();
    }


    /**
     * @ApiTitle    (我的礼物墙)
     * @ApiSummary  (礼物墙)
     * @ApiMethod   (get)
     * @ApiParams   (name="user_id", type="int", required=false,  rule="", description="默认为当前登录用户")
     * @ApiParams   (name="light", type="int", required=false,  rule="", description="是否点亮：1=已点亮，0=未点亮")
     * @ApiParams   (name="cate", type="int", required=false,  rule="", description="面板礼物-二级分类：10=热门,11=专场,12=特权,14=盲盒")
     *
     * @ApiParams   (name="page", type="int", required=false,  rule="", description="当前页码，默认1")
     * @ApiParams   (name="size", type="int", required=false,  rule="", description="页码大小，默认15")
     */
    public function wall()
    {
        $user_id = input('user_id') ?: $this->auth->id;
        $light = input('light', '');
        $cate = input('cate') ?: 0;
        $page = input('page') ?: 1;
        $size = input('size') ?: 15;

        $giftIds = db('gift_wall l')->join('gift g', 'g.id=l.gift_id', 'left')->where('l.user_id', $user_id)->column('g.id');
        $where = [];
        if ($light !== '') $where['id'] = [$light == 1 ? 'in' : 'not in', $giftIds];
        $cate && $where['cate'] = $cate;
        $cate == 14 && $where['cate'] = ['in', [GiftModel::GIFT_CATE_WISH_BOTTLE, GiftModel::GIFT_CATE_SPRING, GiftModel::GIFT_CATE_KOI_BOTTLE]];
        //获取礼物墙数据
        $data = GiftService::getGiftsWall($where, $page, $size);
        foreach ($data as &$v) {
            $v['light'] = in_array($v['id'], $giftIds) ? 1 : 0;
        }

        $this->success('ok', $data);
    }

    /**
     * @ApiTitle    (礼物墙-分类)
     * @ApiSummary  (礼物墙)
     * @ApiMethod   (get)
     * @ApiParams   (name="user_id", type="int", required=false,  rule="", description="默认为当前登录用户")
     * @ApiReturnParams (name="cate", type="array", description="面板礼物-二级分类数组")
     * @ApiReturnParams (name="cate.id", type="int", description="分类id，传值用cate")
     * @ApiReturnParams (name="cate.name", type="string", description="分类名称")
     * @ApiReturnParams (name="type", type="object", description="幸运礼物")
     * @ApiReturnParams (name="type.id", type="int", description="分类id，传值用type")
     * @ApiReturnParams (name="type.name", type="string", description="分类名称")
     */
    public function get_wall_type()
    {
        $user_id = input('user_id') ?: $this->auth->id;

        $boardCate = GiftModel::GIFT_TYPE_BOARD_CATES;
        $cateArr = [];
        foreach ($boardCate as $key => $value) {
            $cateArr[$key]['id'] = $key;
            $cateArr[$key]['name'] = $value;
        }
        $data['cate'] = array_values($cateArr);
        [$data['gift_count'], $data['gain_count']] = GiftService::getGiftsWallCount($user_id);
        $this->success('ok', $data);
    }

    /**
     * @ApiTitle    (送礼记录)
     * @ApiMethod   (get)
     * @ApiParams   (name="size", type="int", required=false,  rule="", description="页码大小，默认20")
     * @ApiParams   (name="start_id", type="int", required=false,  rule="", description="起始id，默认0")
     */
    public function send_gift_logs()
    {
        $userId = $this->auth->id;
        $size = input('size') ?: 15;
        $start_id = input('start_id') ?: 0;

        $where = [];
        if ($start_id) {
            $where['id'] = ['<', $start_id];
        }
        $data = db('gift_log')
            ->where('user_id', $userId)
            ->where($where)
            ->field('id,to_user_id,gift_id,count,create_time')
            ->order('id desc')
            ->page(1, $size)
            ->select();

        if ($data) {
            //从缓存获取礼物、用户信息
            foreach ($data as &$item) {
                $item['name'] = RedisService::getGiftCache($item['gift_id'], 'name');
                $item['image'] = RedisService::getGiftCache($item['gift_id'], 'image');
                $item['price'] = RedisService::getGiftCache($item['gift_id'], 'price');

                $item['nickname'] = RedisService::getUserCache($item['to_user_id'], 'nickname');
                $item['avatar'] = RedisService::getUserCache($item['to_user_id'], 'avatar');
            }
        }
        $this->success('ok', $data);
    }

}
