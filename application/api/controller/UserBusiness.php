<?php

namespace app\api\controller;

use app\common\exception\ApiException;
use app\common\library\Sms;
use app\common\model\ShopItem as ShopModel;
use app\common\model\UserBusiness as UserBusinessModel;
use app\common\model\UserBusinessLog;
use app\common\service\NobleService;
use app\common\service\RedisService;
use app\common\service\UserBusinessLogService;
use app\common\service\UserBusinessService;
use think\Db;
use think\Exception;
use think\Log;

/**
 * 会员业务
 */
class UserBusiness extends Base
{
    protected $noNeedLogin = [''];
    protected $noNeedRight = '*';

    /**
     * @ApiTitle    (獲取會員業務信息)
     * @ApiSummary  ("返回值中role（用户身份:1=用户,2=主播,3=家族成员,4=族长）")
     * @ApiMethod   (get)
     * @ApiReturn   ({"code":1,"msg":"","data":{"id":"100002221","proom_no":null,"integral":"0","recharge_amount":"0","reward_amount":"0","lock_amount":"0","rewarded_amount":"0","amount":"1","skill_order_count":null,"rob":"0","safe_code":null,"createtime":"2020-12-12 16:08:52","updatetime":"2020-12-12 16:08:52","amount_total":"1"}})
     */
    public function get()
    {
        try {
            $uid = $this->auth->id;
            $business = UserBusinessService::getInfo($uid);
            $this->success('', $business);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error($e->getMessage());
        }
    }

    /**
     * 账目明细类目
     * @ApiMethod   (get)
     * @ApiReturnParams  (name="special", type="int", description="红包模型标识")
     */
    public function get_from_list()
    {
        $list = UserBusinessService::getBillLables();
        $this->success('', $list);
    }

    /**
     * 账目明细
     * @ApiMethod   (get)
     * @ApiParams   (name="type", type="str", required=true, rule="", description="從get_from_list接口獲取")
     * @ApiParams   (name="from", type="str", required=true, rule="", description="從get_from_list接口獲取")
     * @ApiParams   (name="date_start", type="str", required=true, rule="", description="区间始")
     * @ApiParams   (name="date_end", type="str", required=true, rule="", description="区间终")
     *
     * @ApiParams   (name="page", type="int", required=false, rule="", description="頁碼")
     * @ApiParams   (name="size", type="int", required=false, rule="", description="每頁數量")
     */
    public function get_log()
    {
        $type = input('type');
        $from = input('from');
        $page = input('page') ?: 1;
        $size = input('size') ?: 20;
        $date_start = input('date_start');

        $where = ['user_id' => $this->auth->id, 'type' => $type, 'from' => $from];
        $start = date('Y-m-01', strtotime($date_start));
        $end = datetime(strtotime('+1month -1second', strtotime($date_start)));
        $where['create_time'] = ['between time', [$start, $end]];

        $redis = redis();
        $cache_key = 'get_log_page_id:' . $this->auth->id . ':' . $type . ':' . $from;
        if ($page == 1 && $this->auth->id) {
            $redis->del($cache_key);
            $result['list'] = db('user_business_log')->where($where)
                ->field("comment,create_time,change_amount,id")
                ->order('create_time desc,id desc')
                ->page($page, $size)->select();
            $redis->set($cache_key, $result['list'][0]['id'], 6000);
        } else {
            //最多只拉取到200条
            $id = 99999999999;
            $page_id = $redis->get($cache_key) ?: $id;
            $result['list'] = db('user_business_log')
                ->where($where)
                ->where('id', '<=', $page_id)
                ->field("comment,create_time,change_amount,id")
                ->order('create_time desc,id desc')
                ->page($page, $size)->select();
        }

        $result['sum'] = db('user_business_log')->where($where)->sum('change_amount');
        if ($type == 2 && in_array($from, [6, 7])) {
            $result['total']['in'] = db('user_business_log')->where($where)->where('cate', 1)->sum('change_amount');
            $result['total']['out'] = db('user_business_log')->where($where)->where('cate', 0)->sum('change_amount');
        }

        $this->success('', $result);
    }

    /**
     * 账目明细-其它
     * @ApiSummary  (礼包, 任务, 活动)
     * @ApiMethod   (get)
     * @ApiParams   (name="page", type="int", required=false, rule="", description="頁碼")
     * @ApiParams   (name="size", type="int", required=false, rule="", description="每頁數量")
     */
    public function get_other_log()
    {
        $page = input('page') ?: 1;
        $size = input('size') ?: 10;

        $list = db('user_other_log')
            ->where('user_id', $this->auth->id)
            ->order('create_time desc')
            ->page($page, $size)
            ->select();

        foreach ($list as &$item) {
            $item['image'] = "assets/icon/other_cate/{$item['type']}.png";
        }

        $this->success('', $list);
    }


    /**
     * 获取用户装饰
     * @ApiSector (商城)
     * @ApiMethod   (get)
     * @ApiSummary   ("data=类型:2=頭像框,3=坐騎,6=氣泡,8=铭牌")
     * @ApiParams   (name="system", type="string", required=false, rule="in:1,2", description="平台:1=IOS,2=ANDROID")
     *
     * @ApiReturnParams (name="list.adornment", type="array", description="头像-数组")
     * @ApiReturnParams (name="list.car", type="array", description="坐骑-数组")
     * @ApiReturnParams (name="list.bubble", type="array", description="气泡-数组")
     * @ApiReturnParams (name="list.tail", type="array", description="铭牌-数组")
     * @ApiReturnParams (name="use_status", type="string", description="狀態:0=未使用,1=已使用,2=已過期")
     */
    public function get_bag_decoration()
    {
        try {
            $userId = $this->auth->id;
            $list['adornment'] = UserBusinessService::getBagData($userId, ShopModel::TYPE_ADORNMENT) ?? [];
            $list['car'] = UserBusinessService::getBagData($userId, ShopModel::TYPE_CAR) ?? [];
            $list['bubble'] = UserBusinessService::getBagData($userId, ShopModel::TYPE_BUBBLE) ?? [];
            $list['tail'] = UserBusinessService::getBagData($userId, ShopModel::TYPE_TAIL) ?? [];
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error($e->getMessage());
        }
        $this->success('', $list);
    }


    /**
     * 会员可提现收益兑换钻石
     * @ApiMethod   (post)
     * @ApiParams (name="amount",  type="int",  required=true, rule="between:1,9999999", description="兌換數量")
     */
    public function reward_to_amount()
    {
        $this->operate_check('reward_amount_lock:' . $this->auth->id, 2);

        $userId = $this->auth->id;
        $amount = intval(input('amount'));
        $originAmount = db("user_business")->where('id', $userId)->value('reward_amount');

        if (bccomp($originAmount, $amount, 2) == -1) {
            $this->error(__('Insufficient exchange of earnings for diamonds'));
        }

        try {
            Db::startTrans();
            user_business_change($userId, 'reward_amount', $amount, 'decrease', '收益兑换钻石到余额', 8);
            user_business_change($userId, 'amount', $amount, 'increase', '收益兑换钻石到余额', 8);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error($e->getMessage());
        }
        $this->success();
    }


    /**
     * 获取收益兑换钻石配置
     * @ApiMethod   (get)
     * @ApiSummary  ("reward_any_amount=是否可自定义兑换额度，user_reward_amount=用户可提现收益")
     */
    public function reward_config()
    {
        $data = [];

        //是否支持自定义提现额度
        $data['reward_any_amount'] = get_site_config('reward_any_amount') ?: "0";
        //用户可提现的收益
        $data['user_reward_amount'] = db('user_business')->where('id', $this->auth->id)->value('reward_amount');
        $list = explode(',', get_site_config('list_reward_amount'));
        foreach ($list as $item) {
            $data['list'][] = ['amount' => $item];
        }
        $data['explain'] = [
            '1. 兑换额度只能是整数',
            '2. 起兑额度>=1',
        ];

        $this->success('', $data);
    }

    /**
     * 设置安全码
     * @ApiParams (name="safe_code",  type="string",  required=true, description="安全碼")
     * @ApiParams (name="captcha", type="string", required=true, rule="", description="驗證碼:safe_code")
     */
    public function set_code()
    {
        $user_id = $this->auth->id;
        $mobile = db('user')->where(['id' => $user_id])->value('mobile');
        !$mobile && $this->error(__('Please bind your phone first'));
        $safe_code = input('safe_code');
        (mb_strlen($safe_code) < 6) && $this->error(__('The length of the security code must be 6 digits'));
        $captcha = input('captcha');
        if ($mobile && !Sms::check($mobile, $captcha, 'safe_code')) {
            $this->error(__('The verification code is incorrect'));
        }
        Sms::flush($mobile, 'safe_code');
        db('user_business')->where(['id' => $user_id])->setField(['safe_code' => $safe_code]);

        $this->success(__('Operation completed'));
    }

    /**
     * 是否設置安全碼与登錄密碼
     *
     * @ApiReturnParams (name="data", type="int", description="是否設置安全碼:1=已設置,0=未設置")
     */
    public function check_code()
    {
        $safe_code = db('user_business')->where('id', $this->auth->id)->value('safe_code');
        $password = db('user')->where('id', $this->auth->id)->value('password');
        $this->success('', ['safe' => $safe_code ? 1 : 0, 'password' => $password ? 1 : 0]);
    }


    /**
     * 设置青少年模式密码
     * @ApiParams (name="teenager_code",  type="string",  required=true, description="青少年密码")
     */
    public function set_teenager()
    {
        $user_id = $this->auth->id;
        (mb_strlen(input('teenager_code')) < 4) && $this->error(__('The password length for teenagers must be 4 digits'));
        db('user_business')->where(['id' => $user_id])->setField(['teenager_code' => input('teenager_code')]);
        $this->success(__('Operation completed'));
    }


    /**
     * 是否设置青少年密码
     *
     * @ApiReturnParams (name="data", type="int", description="设置青少年密码:1=已设置,0=未设置")
     */
    public function check_teenager()
    {
        $code = db('user_business')->where('id', $this->auth->id)->value('teenager_code');
        $this->success('', $code ? 1 : 0);
    }


    /**
     * 获取会员背包礼物
     * @ApiSector (商城)
     * @ApiSummary   ("type類型:1=普通禮物")
     * @ApiParams   (name="system", type="string", required=false, rule="in:1,2", description="平台:1=IOS,2=ANDROID")
     * @ApiMethod   (get)
     */
    public function get_bag_gift()
    {
        try {
            $userId = $this->auth->id;
            $where = ['user_id' => $userId,];

            $data = [];
            $data['gift'] = db('user_bag')->alias('ubag')
                ->join('gift', 'gift.id=ubag.gift_id', 'left')
                ->field("gift.id,gift.name,gift.image,gift.price_type,gift.price,ubag.*,1 as type")
                ->where($where)
                ->where('count', '>', 0)
                ->order('gift.price desc')
                ->select();

            $list = [];
            //type类型:1=普通礼物
            //use_status状态:0=未使用,1=已使用,2=已过期'
            if ($data['gift']) {
                foreach ($data['gift'] as $val) {
                    if ($val['price_type'] == 1) {
                        $price = $val['price'];
                    } else {
                        $price = $val['price'];
                    }
                    switch ($val['type']) {
                        case 1:
                        case 4:
                        case 5:
                    }
                    $list[] = [
                        'id'           => $val['id'],
                        'gift_id'      => $val['gift_id'],
                        'type'         => 0,
                        'price_type'   => $val['price_type'],
                        'type_text'    => '',
                        'name'         => $val['name'],
                        'image'        => $val['image'],
                        'price'        => $price,
                        'explain'      => $val['explain'] ?? '',
                        'count'        => $val['count'],
                        'expired_time' => $val['expired_time'] ?? '',
                        'create_time'  => $val['create_time'] ?? null,
                    ];
                }
            }
            unset($data);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            $this->error($e->getMessage());
        }
        $this->success('', $list);
    }

    /**
     * 获取第三方平台绑定信息
     * @ApiMethod   (get)
     * @ApiReturnParams   (name="wx", type="string",  description="微信-昵称，为空未绑定")
     * @ApiReturnParams   (name="qq", type="string",  description="qq-昵称，为空未绑定")
     * @ApiReturnParams   (name="ap", type="string",  description="苹果-昵称，为空未绑定")
     */
    public function get_third_info()
    {
        $userId = $this->auth->id;
        $nicknames = db('user_third')->where('user_id', $userId)->column('1', 'platform');

        $data = [
            'facebook' => '',
            'google'   => '',
            'apple'    => '',
            'line'     => '',
            'wechat'   => '',
        ];

        $this->success('', $nicknames + $data);
    }

    /**
     * 第三平台信息-绑定
     * @ApiMethod   (post)
     * @ApiParams   (name="platform", type="string", required=true, rule="", description="第三方平台类型: wechat,facebook,google,apple,line")
     * @ApiParams   (name="code", type="string", required=true, rule="", description="平台code")
     * @ApiParams   (name="nickname", type="string", required=false, rule="", description="平台-昵称")
     */
    public function third_bind()
    {
        $userId = $this->auth->id;
        $platform = input('platform');
        $code = input('code');
        $nickname = input('nickname');

        $data = db('user_third')
            ->where('user_id', $userId)
            ->where('platform', $platform)->find();
        if (!$data) {
            Db::startTrans();
            try {
                $insertData = [
                    'user_id'  => $this->auth->id,
                    'platform' => $platform,
                    'code'     => $code,
                    'nickname' => $nickname
                ];
                $result = db('user_third')->insert($insertData);
                if (!$result) {
                    throw new ApiException(__('Binding failed'));
                }
                Db::commit();
            } catch (\Exception $exception) {
                Db::rollback();
                $this->error($exception->getMessage());
            }
        } else {
            $this->error(__('Do not repeat the operation'));
        }
        $this->success(__('Operation completed'));
    }

    /**
     * 第三平台信息-解绑
     * @ApiMethod   (post)
     * @ApiParams   (name="platform", type="string", required=true, rule="in:wx,qq,ap", description="第三方平台类型：wechat,facebook,google,apple,line")
     */
    public function third_unbind()
    {
        Db::startTrans();
        try {
            $userId = $this->auth->id;
            $platform = input('platform');
            $result = db('user_third')
                ->where('user_id', $userId)
                ->where('platform', $platform)->delete();
            if (!$result) {
                throw new ApiException(__('Unbinding failed'));
            }
            Db::commit();
        } catch (\Exception $exception) {
            Db::rollback();
            $this->error($exception->getMessage());
        }
        $this->success(__('Operation completed'));
    }

    /**
     * 获取用户权限
     * @ApiMethod   (get)
     */
    public function get_user_auth()
    {
        $userId = $this->auth->id;
        $user = db('user')->where('id', $userId)->field('hidden_level,hidden_noble')->find();
        $this->success('', $user);
    }

    /**
     * 设置用户权限
     * @ApiMethod   (post)
     * @ApiParams   (name="type", type="string", required=true, rule="", description="hidden_level=隐藏等级、hidden_noble=隐藏等级")
     */
    public function set_user_auth()
    {
        Db::startTrans();
        try {
            $userId = $this->auth->id;
            $field = input('type');
            if (!in_array($field, ['hidden_level', 'hidden_noble'])) {
                throw new ApiException(__('Parameter error'));
            }
            $user = db('user')->where('id', $userId)->field($field)->find();
            if ($user[$field]) {
                $data[$field] = 0;
            } else {
                $data[$field] = 1;
            }
            $result = db('user')
                ->where('id', $userId)
                ->update($data);
            if (!$result) {
                throw new ApiException(__('Setting failed'));
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error($e->getMessage());
        }
        $this->success(__('Operation completed'), '');
    }

    /**
     * @ApiTitle    (获取充值记录)
     * @ApiMethod   (get)
     * @ApiParams   (name="start_id",    type="int",  required=false, rule="", description="起始id")
     * @ApiParams   (name="start_time", type="str", required=false, rule="", description="开始时间")
     * @ApiParams   (name="end_time", type="str", required=false, rule="", description="结束时间")
     * @ApiParams   (name="size", type="int", required=false, rule="", description="每頁數量")
     *
     * @ApiReturnParams    (name="comment", type="str", description="描述")
     * @ApiReturnParams    (name="create_time", type="str", description="时间")
     * @ApiReturnParams    (name="val", type="str", description="金额")
     * @ApiReturnParams    (name="cate", type="int", description="变化类型:1=增加,0=减少")
     */
    public function rechargeLogs()
    {
        $start_time = input('start_time');
        $end_time = input('end_time');
        $size = input('size') ?: 20;
        $start_id = input('start_id') ?: 0;
        $userId = $this->auth->id;

        $where['type'] = ['in', [UserBusinessLog::TYPE_DIAMOND]];
        $where['from'] = ['in', [UserBusinessLog::FROM_RECHARGE]];
        $where['cate'] = UserBusinessLog::CATE_ADD;
        if ($start_time && $end_time && ($start_time < $end_time)) {
            $where['create_time'] = ['between', [$start_time, $end_time]];
        }
        if ($start_id) {
            $where['id'] = ['<', $start_id];
        }

        $data = UserBusinessLogService::getBaseLogs($userId, $where, $size);
        $this->result('', $data, 1);
    }

    /**
     * @ApiTitle    (获取红豆流水)
     * @ApiMethod   (get)
     * @ApiParams   (name="start_id", type="int", required=false, rule="", description="起始id")
     * @ApiParams   (name="start_time", type="str", required=false, rule="", description="开始时间")
     * @ApiParams   (name="end_time", type="str", required=false, rule="", description="结束时间")
     * @ApiParams   (name="size", type="int", required=false, rule="", description="每頁數量")
     *
     * @ApiReturnParams    (name="comment", type="str", description="描述")
     * @ApiReturnParams    (name="create_time", type="str", description="时间")
     * @ApiReturnParams    (name="val", type="str", description="金额")
     * @ApiReturnParams    (name="cate", type="int", description="变化类型:1=增加,0=减少")
     */
    public function redBeansLogs()
    {
        $start_time = input('start_time');
        $end_time = input('end_time');
        $size = input('size') ?: 20;
        $start_id = input('start_id') ?: 0;
        $userId = $this->auth->id;

        $where['type'] = ['in', [UserBusinessLog::TYPE_RED_BEANS]];
        if ($start_time && $end_time && ($start_time < $end_time)) {
            $where['create_time'] = ['between', [$start_time, $end_time]];
        }
        if ($start_id) {
            $where['id'] = ['<', $start_id];
        }

        $data = UserBusinessLogService::getBaseLogs($userId, $where, $size);
        $this->result('', $data, 1);
    }

    /**
     * @ApiTitle    (获取收益流水)
     * @ApiMethod   (get)
     * @ApiParams   (name="type", type="int", required=true, rule="in:0,1,2", description="收益类型：0=全部、1=收入、2=支出")
     * @ApiParams   (name="start_id", type="int", required=false, rule="", description="起始id")
     * @ApiParams   (name="start_time", type="str", required=false, rule="", description="开始时间")
     * @ApiParams   (name="end_time", type="str", required=false, rule="", description="结束时间")
     * @ApiParams   (name="size", type="int", required=false, rule="", description="每頁數量")
     *
     * @ApiReturnParams    (name="comment", type="str", description="描述")
     * @ApiReturnParams    (name="create_time", type="str", description="时间")
     * @ApiReturnParams    (name="val", type="str", description="金额")
     * @ApiReturnParams    (name="cate", type="int", description="变化类型:1=增加,0=减少")
     */
    public function incomeLogs()
    {
        $type = input('type') ?? 0;
        $start_time = input('start_time');
        $end_time = input('end_time');
        $size = input('size') ?: 20;
        $start_id = input('start_id') ?: 0;
        $userId = $this->auth->id;

        $where['type'] = ['in', [UserBusinessLog::TYPE_INCOME]];
        switch ($type) {
            case 1: //收入
                // $fromArr = [
                //     UserBusinessLog::FROM_REWARD,
                //     UserBusinessLog::FROM_GUARD_REWARD,
                //     UserBusinessLog::FROM_UNION_REWARD,
                //     UserBusinessLog::FROM_TURNOVER_REWARD,
                // ];
                $cateArr = [UserBusinessLog::CATE_ADD];
                break;
            case 2: //支出
                // $fromArr = [
                //     UserBusinessLog::FROM_CHANGE_DIAMOND,
                //     UserBusinessLog::FROM_INCOME_WITHDRAWAL,
                // ];
                $cateArr = [UserBusinessLog::CATE_SUB];
                break;
            default:  //全部
                // $fromArr = [
                //     UserBusinessLog::FROM_REWARD,
                //     UserBusinessLog::FROM_GUARD_REWARD,
                //     UserBusinessLog::FROM_UNION_REWARD,
                //     UserBusinessLog::FROM_TURNOVER_REWARD,
                //     UserBusinessLog::FROM_CHANGE_DIAMOND,
                //     UserBusinessLog::FROM_INCOME_WITHDRAWAL,
                // ];
                $cateArr = [UserBusinessLog::CATE_ADD, UserBusinessLog::CATE_SUB];
                break;
        }
        // $where['from'] = ['in', $fromArr];
        $where['cate'] = ['in', $cateArr];
        if ($start_time && $end_time && ($start_time < $end_time)) {
            $where['create_time'] = ['between', [$start_time, $end_time]];
        }
        if ($start_id) {
            $where['id'] = ['<', $start_id];
        }

        $data = UserBusinessLogService::getBaseLogs($userId, $where, $size);
        $this->result('', $data, 1);
    }

    /**
     * @ApiTitle    (获取钻石流水)
     * @ApiMethod   (get)
     * @ApiParams   (name="type", type="int", required=true, rule="in:0,1,2", description="收益类型：0=全部、1=收入、2=支出")
     * @ApiParams   (name="start_id", type="int", required=false, rule="", description="起始id")
     * @ApiParams   (name="start_time", type="str", required=false, rule="", description="开始时间")
     * @ApiParams   (name="end_time", type="str", required=false, rule="", description="结束时间")
     * @ApiParams   (name="size", type="int", required=false, rule="", description="每頁數量")
     *
     * @ApiReturnParams    (name="comment", type="str", description="描述")
     * @ApiReturnParams    (name="create_time", type="str", description="时间")
     * @ApiReturnParams    (name="val", type="str", description="金额")
     * @ApiReturnParams    (name="cate", type="int", description="变化类型:1=增加,0=减少")
     */
    public function diamondLogs()
    {
        $type = input('type') ?? 0;
        $start_time = input('start_time');
        $end_time = input('end_time');
        $size = input('size') ?: 20;
        $start_id = input('start_id') ?: 0;
        $userId = $this->auth->id;


        switch ($type) {
            case 1: //收入
                $fromArr = [
                    UserBusinessLog::FROM_TASK,
                    UserBusinessLog::FROM_RECHARGE,
                    UserBusinessLog::FROM_IM_LUCKY_MONEY,
                    UserBusinessLog::FROM_ROOM_LUCKY_MONEY,
                    UserBusinessLog::FROM_UNION_REWARD,
                ];
                $cateArr = [UserBusinessLog::CATE_ADD];
                break;
            case 2: //支出
                $fromArr = [
                    UserBusinessLog::FROM_MALL_EXCHANGE,
                    UserBusinessLog::FROM_REWARD,
                    UserBusinessLog::FROM_IM_LUCKY_MONEY,
                    UserBusinessLog::FROM_ROOM_LUCKY_MONEY,
                    UserBusinessLog::FROM_CHANGE_DIAMOND,
                    UserBusinessLog::FROM_GAME_ARCH,
                ];
                $cateArr = [UserBusinessLog::CATE_SUB];
                break;
            default:  //全部
                $fromArr = [
                    UserBusinessLog::FROM_TASK,
                    UserBusinessLog::FROM_RECHARGE,
                    UserBusinessLog::FROM_REWARD,
                    UserBusinessLog::FROM_IM_LUCKY_MONEY,
                    UserBusinessLog::FROM_ROOM_LUCKY_MONEY,
                    UserBusinessLog::FROM_MALL_EXCHANGE,
                    UserBusinessLog::FROM_CHANGE_DIAMOND,
                    UserBusinessLog::FROM_GAME_ARCH,
                    UserBusinessLog::FROM_UNION_REWARD,
                ];
                $cateArr = [UserBusinessLog::CATE_ADD, UserBusinessLog::CATE_SUB];
                break;
        }
        $where['type'] = ['=', UserBusinessLog::TYPE_DIAMOND];
        // $where['from'] = ['in', $fromArr];
        $where['cate'] = ['in', $cateArr];
        if ($start_time && $end_time && ($start_time < $end_time)) {
            $where['create_time'] = ['between', [$start_time, $end_time]];
        }
        if ($start_id) {
            $where['id'] = ['<', $start_id];
        }

        $data = UserBusinessLogService::getBaseLogs($userId, $where, $size);
        $this->result('', $data, 1);
    }

    /**
     * @ApiTitle    (获取收益兑换钻石流水)
     * @ApiMethod   (get)
     * @ApiParams   (name="start_id", type="int", required=false, rule="", description="起始id")
     * @ApiParams   (name="start_time", type="str", required=false, rule="", description="开始时间")
     * @ApiParams   (name="end_time", type="str", required=false, rule="", description="结束时间")
     * @ApiParams   (name="size", type="int", required=false, rule="", description="每頁數量")
     *
     * @ApiReturnParams    (name="comment", type="str", description="描述")
     * @ApiReturnParams    (name="create_time", type="str", description="时间")
     * @ApiReturnParams    (name="val", type="str", description="消耗-收益数量")
     * @ApiReturnParams    (name="cate", type="int", description="变化类型:1=增加,0=减少")
     * @ApiReturnParams    (name="diamond_val", type="str", description="得到-钻石数量")
     */
    public function incomeExchangeLogs()
    {
        $start_time = input('start_time');
        $end_time = input('end_time');
        $size = input('size') ?: 20;
        $start_id = input('start_id') ?: 0;
        $userId = $this->auth->id;

        $where['type'] = ['in', [UserBusinessLog::TYPE_INCOME]];

        $fromArr = [
            UserBusinessLog::FROM_CHANGE_DIAMOND,
        ];
        $cateArr = [UserBusinessLog::CATE_SUB];
        $where['from'] = ['in', $fromArr];
        $where['cate'] = ['in', $cateArr];

        if ($start_time && $end_time && ($start_time < $end_time)) {
            $where['create_time'] = ['between', [$start_time, $end_time]];
        }
        if ($start_id) {
            $where['id'] = ['<', $start_id];
        }

        $data = UserBusinessLogService::getBaseLogs($userId, $where, $size);
        if (count($data)) {
            foreach ($data as &$item) {
                $item['diamond_val'] = "+" . bcsub(0, $item['val']);
            }
        }
        $this->result('', $data, 1);
    }


    /**
     * 财富等级
     */
    public function level()
    {
        $my = db('user_business')->field('level,score')->where('id', $this->auth->id)->find();
        $list = db('level')->field('name,grade,scope,icon')->order('grade asc')->select();

        $this->success('', [
            'my'    => $my,
            'intro' => '财富等级是您在平台的成长属性，您可以通过获取经验值提升等级，每打赏1钻可增加1点经验，累计获得经验越高，财富等级越高。',
            'list'  => $list,
        ]);
    }

}
