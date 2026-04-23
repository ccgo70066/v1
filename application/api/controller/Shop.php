<?php

namespace app\api\controller;

use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\common\model\ShopItem as ShopItemModel;
use app\common\model\UserBusiness;
use app\common\service\RedisService;
use think\Db;
use think\Exception;
use think\Log;
use util\Sign;

/**
 * 商城
 */
class Shop extends Base
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    /**
     * 获取商城物品
     * @ApiParams   (name="type", type="int",  required=false, rule="", description="类型:2=头像框,3=坐骑,6=聊天气泡,8=铭牌")
     * @ApiParams   (name="page", type="int",  required=false, rule="", description="页码")
     * @ApiParams   (name="size", type="int",  required=false, rule="", description="每頁數量")
     *
     * @ApiReturnParams  (name="cate", type="string", description="分类:1=钻石,2=红豆")
     * @ApiReturnParams  (name="show", type="string", description="显示系统:1=iOS,2=Android")
     * @ApiReturnParams  (name="is_buy", type="string", description="是否已购买:0=未购买,1=已购买")
     */
    public function get()
    {
        $system = $this->system ?: 2;
        $page = input('page') ?: 1;
        $size = input('size') ?: 10;

        $where = ['status' => 1];
//        input('cate') && $where['cate'] = input('cate');
        input('type') && $where['type'] = input('type');
        $data = db('shop_item')
            ->field('create_time,update_time', true)
            ->where('type', 'in', [2, 3, 6])  //2=头像框,3=坐骑,6=聊天气泡
            ->where("find_in_set({$system}, `show`)")
            ->where($where)
            ->order('type asc,weigh asc')
            ->page($page, $size)
            ->select();
        $itemArr = [];
        foreach ($data as $item) {
            $itemArr[$item['type']][] = $item['item_id'];
        }
        $data = array_values($data);

        $itemImageInfo = $this->getImageByIdType($itemArr);
        foreach ($data as $key => $value) {
            $data[$key]['price'] = (float)($value['price']);
            $info = $itemImageInfo["{$value['type']}:{$value['item_id']}"];
            $data[$key]['image'] = $info['image'] ?? $value['image'];
            $data[$key]['face_image'] = $info['face_image'] ?? $value['image'];
            if (isset($info['color'])) {
                $data[$key]['color'] = $info['color'];
            }
            if (isset($info['gift_price'])) {
                $data[$key]['gift_price'] = $info['gift_price'];
            }
            if (isset($info['is_buy'])) {
                $data[$key]['is_buy'] = $info['is_buy'];
            }
        }

        $this->success('', $data);
    }

    /**
     * 商城购买下单
     * @ApiSummary  ("code 410=跳充值頁面,411=跳支付鏈接")
     * @ApiParams   (name="item_id", type="int",  required=true, rule="", description="物品")
     * @ApiParams   (name="count", type="int",  required=true, rule="", description="數量")
     * @ApiParams   (name="room_id", type="int",  required=false, rule="", description="房間id")
     * @ApiParams   (name="extend_data", type="int",  required=false, rule="", description="擴展參數")
     */
    public function order()
    {
        $this->operate_check('shop_order_lock:' . $this->auth->id, 2);
        $system = $this->system ?: 2;
        $sign_data = input('param.');
        $count = abs((int)input('count'));
        unset($sign_data['sign'], $sign_data['room_id'], $sign_data['extend_data']);
        $sign = Sign::generate($sign_data, config('app.sign_key'));
//        if (input('sign') != $sign) {
//            $this->error('签名错误', config('app_debug') ? $sign : null, 403);
//        }

        $data = input('param.');
        if (isset($data['extend_data'])) {
            $data['extend_data'] = htmlspecialchars_decode($data['extend_data']);
        }
        unset($data['sign']);
        $data = array_merge($data, [
            'user_id' => $this->auth->id,
            'status'  => 0,
            'system'  => $system
        ]);
        $item = db('shop_item')
            ->where("find_in_set({$system}, `show`)")
            ->cache(true, '600', 'shop_data')
            ->find(input('item_id'));

        if (!$item) {
            $this->error(__('No results were found'));
        }
        //status = -1为特殊商品,这种东西不能续费或者购买
        if ($item['status'] == -1) {
            $this->error(__('This product cannot be purchased or renewed'));
        }


        $user_business = db('user_business')->find($this->auth->id);
        $data['price_type'] = $item['cate'];
        $data['price'] = $item['price'];
        $data['orig_amount'] = $item['price'] * $count;
        $data['diss_amount'] = $data['diss_amount'] ?? 0;
        $data['amount'] = $data['orig_amount'] - $data['diss_amount'];

        // 类型:2=头像框,3=坐骑,4=贵族,6=聊天气泡
        if ($item['type'] == 4) {
            //购买贵族
            $noble_info = UserBusiness::getUserNobleInfoById($this->auth->id);
            if (isset($noble_info['noble_id']) && $noble_info['noble_id'] > $item['item_id']) {
                $this->error(__('You are already a noble, you can only start with a higher level noble!'));
            }
            //续费价格优惠
            if (isset($noble_info['noble_id']) && $noble_info['noble_id'] == $item['item_id']) {
                $data['diss_amount'] = ($item['price'] - round($item['price'] * get_site_config('renewal_noble'))) * $count;
                $data['price'] = ceil($item['price'] * $count * get_site_config('renewal_noble'));
                $data['amount'] = $data['orig_amount'] - $data['diss_amount'];
                $extend['is_renew'] = 1;
                $data['extend_data'] = json_encode($extend);
            }
        }

        if ($item['cate'] == 1) {  // 钻石
            if ($user_business['amount'] >= $data['amount']) {
                user_business_change($this->auth->id, 'amount', $data['amount'], 'decrease', '商城兑换', 1);
                $data['status'] = 1;
                $data['pay_way'] = 1;
            } else {
                $this->error(__('Insufficient balance'));
            }
        }
        $id = db('shop_order')->strict(false)->insertGetId($data);
        shop_order_success($id, $data['pay_way'] ?? 2);

        $this->success(__('Operation completed'));
    }


    /**
     * 装扮续费
     * @ApiParams   (name="type", type="string", required=false, rule="in:2,3,6,8", description="類型:2=頭像框,3=坐騎,6=氣泡,8=铭牌")
     * @ApiParams   (name="id",    type="int",  required=true,  rule="require", description="裝扮ID")
     */
    public function renew()
    {
        $this->operate_check('shop_renew_lock:' . $this->auth->id, 2);
        $userId = $this->auth->id;
        $item_id = input('id');
        $type = input('type');
        $system = $this->system;

        switch ($type) {
            case ShopItemModel::TYPE_ADORNMENT:
                $user_item = db('user_adornment')->where(['user_id' => $userId, 'adornment_id' => $item_id])->find();
                $query = db('adornment');
                break;
            case ShopItemModel::TYPE_CAR:
                $user_item = db('user_car')->where(['user_id' => $userId, 'car_id' => $item_id])->find();
                $query = db('car');
                break;
            case ShopItemModel::TYPE_BUBBLE:
                $user_item = db('user_bubble')->where(['user_id' => $userId, 'bubble_id' => $item_id])->find();
                $query = db('bubble');
                break;
            case ShopItemModel::TYPE_TAIL:
                $user_item = db('user_tail')->where(['user_id' => $userId, 'tail_id' => $item_id])->find();
                $query = db('tail');
                break;
            default:
                $this->error(__('No results were found'));
        }

        $item_info = $query
            ->alias('a')
            ->join('shop_item s', 'a.id = s.item_id and s.type = ' . $type)
            ->where("find_in_set({$system}, `show`)")
            ->where(['a.id' => $item_id, 'a.status' => 1])
            ->field("s.id,s.cate as price_type,s.price as price,a.name,s.days")
            ->find();

        if (!$item_info || !$user_item) {
            $this->error(__('Expired validity period'), '', 404);
        }
        $business = db('user_business')->field('amount,reward_amount')->where(['id' => $userId])->find();
        if ($item_info['price_type'] == 1 && bccomp(
                $business['amount'],
                $item_info['price'],
                2
            ) == -1) {
            $this->error(__('Insufficient balance'), '', 404);
        }
        try {
            Db::startTrans();
            $new_order = [
                'user_id'       => $this->auth->id,
                'item_id'       => $item_info['id'],
                'price_type'    => $item_info['price_type'],
                'count'         => 1,
                'price'         => $item_info['price'],
                'orig_amount'   => $item_info['price'] * 1,
                'diss_amount'   => 0,
                'amount'        => $item_info['price'],
                'status'        => 1,  //支付成功
                'system'        => $this->system,
                'room_id'       => 0,
                'pay_way'       => $item_info['price_type'],
                'split_percent' => 0,
                'extend_data'   => null,
            ];
            db('shop_order')->insertGetId($new_order);

            switch ($type) {
                case ShopItemModel::TYPE_ADORNMENT:
                    user_adornment_add($userId, $item_id, $item_info['days']);
                    break;
                case ShopItemModel::TYPE_CAR:
                    user_car_add($userId, $item_id, $item_info['days']);
                    break;
                case ShopItemModel::TYPE_BUBBLE:
                    user_bubble_add($userId, $item_id, $item_info['days']);
                    break;
                case ShopItemModel::TYPE_TAIL:
                    user_tail_add($userId, $item_id, $item_info['days']);
                    break;
            }


            if ($item_info['price_type'] == 1) {
                user_business_change($userId, 'amount', $item_info['price'], 'decrease', '续费裝扮:' . $item_info['name'], 1);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error($e->getMessage());
        }
        $this->success(__('Operation completed'));
    }


    /**
     * @ApiTitle    (激活裝扮)
     * @ApiParams   (name="id",    type="int",  required=true,  rule="require", description="裝扮ID")
     * @ApiParams   (name="is_wear",    type="int",  required=true,  rule="require", description="是否穿戴:0=取消穿戴,1=穿戴裝扮")
     * @ApiParams   (name="type", type="string", required=false, rule="in:2,3,6,8", description="類型:2=頭像框,3=坐騎,6=氣泡,8=尾巴")
     */
    public function active()
    {
        $this->operate_check('shop_active_lock:' . $this->auth->id, 2);
        $userId = $this->auth->id;
        $item_id = input('id');
        $isWear = input('is_wear');
        $type = input('type');

        $where = ['user_id' => $userId, 'use_status' => ['in', [0, 1]]];
        switch ($type) {
            case ShopItemModel::TYPE_ADORNMENT:
                $user_item = db('user_adornment')->where($where)->where(['adornment_id' => $item_id])->find();
                $query_item = db('adornment');
                $query_user = db('user_adornment');
                break;
            case ShopItemModel::TYPE_CAR:
                $user_item = db('user_car')->where($where)->where(['car_id' => $item_id])->find();
                $query_item = db('car');
                $query_user = db('user_car');

                break;
            case ShopItemModel::TYPE_BUBBLE:
                $user_item = db('user_bubble')->where($where)->where(['bubble_id' => $item_id])->find();
                $query_item = db('bubble');
                $query_user = db('user_bubble');
                break;
            case ShopItemModel::TYPE_TAIL:
                $user_item = db('user_tail')->where($where)->where(['tail_id' => $item_id])->find();
                $query_item = db('tail');
                $query_user = db('user_tail');
                break;
            default:
                $this->error(__('Network busy'));
        }

        $item_info = $query_item->where(['id' => $item_id, 'status' => 1])->find();
        if (!$item_info || !$user_item) {
            $this->error(__('Expired validity period'), '', 404);
        }
        try {
            Db::startTrans();
            if ($user_item) {
                switch ($isWear) {
                    case 0:
                        $data = [
                            'id'      => $user_item['id'],
                            'is_wear' => 0,
                        ];
                        break;
                    case 1:
                        $data = [
                            'id'      => $user_item['id'],
                            'is_wear' => 1,
                        ];
                        if ($user_item['use_status'] == 0) {
                            $data['use_status'] = 1;
                            if ($user_item['expired_days'] == -1) {
                                $data['expired_time'] = null;
                            } else {
                                $data['expired_time'] = date(
                                    'Y-m-d H:i:s',
                                    strtotime(" +{$user_item['expired_days']} days")
                                );
                            }
                            $data['use_time'] = date('Y-m-d H:i:s');
                        }

                        $query_user->where('user_id', $userId)->where('id', '<>', $user_item['id'])
                            ->setField('is_wear', 0);
                        break;
                }
                $query_user->update($data);
                UserBusiness::clear_cache($userId);

                //更新云信用户等级贵族装扮相关信息
                board_notice(Message::CMD_REFRESH_USER, ['user_id' => $userId]);
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error($e->getMessage());
        }
        $this->success(__('Operation completed'));
    }


    /*
     * @ApiInternal
     * 根据类型与id 获取相应物品的展示图标
     * @param int $type 类型:1=礼物,2=头像框,3=坐骑,4=贵族,5=守护,6=聊天气泡,8铭牌
     * @param int $itemId
     */
    protected function getImageByIdType(array $itemIdsArr): array
    {
        $data = [];
        if (empty($itemIdsArr)) {
            return $data;
        }
        foreach ($itemIdsArr as $type => $item_ids) {
            $list = [];
            switch ($type) {
                case ShopItemModel::TYPE_GIFT:
                    $list = db('gift')->whereIn('id', $item_ids)->field('id,image,price as gift_price')->select();
                    break;
                case ShopItemModel::TYPE_ADORNMENT:
                    $list = db('adornment')->whereIn('id', $item_ids)->field('id,cover as image,face_image')->select();
                    foreach ($list as $k => $v) {
                        $list[$k]['is_buy'] = 0;
                        $amp = [
                            'user_id'      => $this->auth->id,
                            'adornment_id' => $v['id'],
                            'expired_days' => -1,
                        ];
                        $item1 = db('user_adornment')->where($amp)->find();
                        if ($item1) {
                            $list[$k]['is_buy'] = 1;
                        }
                    }
                    break;
                case ShopItemModel::TYPE_CAR:
                    $list = db('car')->whereIn('id', $item_ids)->field('id,cover as image,face_image')->select();
                    foreach ($list as $k => $v) {
                        $list[$k]['is_buy'] = 0;
                        $amp = [
                            'user_id'      => $this->auth->id,
                            'car_id'       => $v['id'],
                            'expired_days' => -1,
                        ];
                        $item1 = db('user_car')->where($amp)->find();
                        if ($item1) {
                            $list[$k]['is_buy'] = 1;
                        }
                    }
                    break;
                case ShopItemModel::TYPE_NOBLE:
                    $list = db('noble')->whereIn('id', $item_ids)->field('id,badge as image,shop_image as face_image')->select();
                    break;
                case ShopItemModel::TYPE_BUBBLE:
                    $list = db('bubble')->whereIn(
                        'id',
                        $item_ids
                    )->field('id,face_image as image,face_image,color')->select();
                    foreach ($list as $k => $v) {
                        $list[$k]['is_buy'] = 0;
                        $amp = [
                            'user_id'      => $this->auth->id,
                            'bubble_id'    => $v['id'],
                            'expired_days' => -1,
                        ];
                        $item1 = db('user_bubble')->where($amp)->find();
                        if ($item1) {
                            $list[$k]['is_buy'] = 1;
                        }
                    }
                    break;
                case ShopItemModel::TYPE_TAIL:
                    $list = db('tail')->whereIn('id', $item_ids)->field('id,cover as image,face_image')->select();
                    foreach ($list as $k => $v) {
                        $list[$k]['is_buy'] = 0;
                        $amp = [
                            'user_id'      => $this->auth->id,
                            'tail_id'      => $v['id'],
                            'expired_days' => -1,
                        ];
                        $item1 = db('user_tail')->where($amp)->find();
                        if ($item1) {
                            $list[$k]['is_buy'] = 1;
                        }
                    }
                    break;
            }
            if (!empty($list)) {
                foreach ($list as $item) {
                    $data["{$type}:{$item['id']}"]['image'] = $item['image'];
                    $data["{$type}:{$item['id']}"]['face_image'] = $item['face_image'];
                    $data["{$type}:{$item['id']}"]['is_buy'] = $item['is_buy'];
                    isset($item['color']) && $data["{$type}:{$item['id']}"]['color'] = $item['color'];
                    isset($item['gift_price']) && $data["{$type}:{$item['id']}"]['gift_price'] = $item['gift_price'];
                }
            }
        }
        return $data;
    }

    /**
     * 获取商城购买数量配置
     * @ApiMethod   (get)
     */
    public function config()
    {
        $data = [];
        $list = explode(',', get_site_config('shop_buy_count'));
        foreach ($list as $item) {
            $data[] = $item;
        }
        $this->success('', $data);
    }

}
