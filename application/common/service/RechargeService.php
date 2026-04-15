<?php

namespace app\common\service;

use app\common\exception\ApiException;
use app\common\model\UserBusiness;
use fast\Http;
use think\Collection;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Log;

class RechargeService extends BaseService
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
     * 获取充值项及对应的充值渠道
     * @param $appid
     * @return array|bool|Collection|string|void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getCard($appid, $system)
    {
        $version = request()->header('version');
        $versionThreshold = '1.0.5';
        $showStoreFlag = $version >= $versionThreshold;
        $channel = db('channel')->where(['appid' => $appid])->find();
        if (!$channel) {
            return;
        }
        $payway = db('channel_payway')->alias('p')
            ->join('channel_company c', 'p.company_id = c.id', 'left')
            ->join('pay_way pw', 'pw.id = p.pay_way_id', 'left')
            ->field('p.*,c.code as company_code,pw.code as pay_way_code,pw.image as payway_image')
            ->where(['p.id' => ['in', $channel['payway']]])
            ->where('p.status', 1)
            ->order('p.weigh asc')
            ->select();
        trace($payway);
        $open = [1 => 'H5', 2 => 'H5I', 3 => 'SDK'];
        foreach ($payway as $k => $item) {
            $payway[$k]['payway'] = $item['pay_way_code'];
            $payway[$k]['code'] = implode(
                '_',
                [$item['company_code'], $item['pay_way_code'], $open[$item['open_way']]]
            );
            // $pay_way[$item['pay_way_code']] = $item['payway_image'];
        }
        $cards = db('channel_card')
            ->field('id,code,price,unit,amount,give_amount,bage,item_code')
            ->where('system', $system)
            ->where('status', 1)
            ->where('id', 'in', implode(',', array_column($payway, 'card_ids')))
            ->order('weigh asc')
            ->select();
        if (!$cards) {
            return;
        }
        // $this->auth = Auth::instance();
        $isFirstCharge = 0;
        foreach ($cards as $k => &$card) {
            foreach ($payway as $item) {
                if (in_array($card['id'], explode(',', $item['card_ids']))) {
                    if ($item['code'] == 'XSSTORE_STORE_H5' && !$showStoreFlag) {
                        continue;
                    }
                    $card['payway'][] = [
                        'code'  => $item['code'],
                        'name'  => $item['app_pay_name'],
                        'image' => $item['payway_image']
                    ];
                }
            }
            if ($card['bage'] == 3 && !$isFirstCharge) {
                $card['bage'] = 0;
            }
            $card['bage_text'] = $this->bageMap()[$card['bage']] ?? '';
            $card['unit'] = [1 => 'USD', 2 => 'NTD'][$card['unit']];
            if (empty($card['payway'])) {
                unset($cards[$k]);
            }
        }

        return $cards;
    }

    public function bageMap()
    {
        return ['0' => '', '1' => '推荐', '2' => '超值', '3' => '首充'];
    }

    /**
     * 风控处理
     * @param     $user_id
     * @param int $agent_flag
     * @param int $amount
     * @throws ApiException
     */
    public function riskControl($user_id, int $agent_flag = 0, int $amount = 0, $card_id, $payway): void
    {
        // 黑名单
        $inBlacklist = db('recharge_blacklist')->where(['user_id' => $user_id])->count();
        if ($inBlacklist) {
            throw new ApiException(__('Payment failure'), 441);
        }
        return;
        // 支付频率超限，请稍后再试或选择其它金额
        // 120 秒时间段只能下单2次，每次隔间不能小于30秒。
        $count = db('user_recharge')->where(['user_id' => $user_id, 'status' => 1])
            ->whereTime('create_time', '-' . get_site_config('recharge_frequent_range') . 'second')->count(1);
        if ($count >= get_site_config('recharge_frequent_limit')) {
            throw new ApiException('支付频率超限，请稍后再试或选择其它面额', 446);
        }
        $count = db('user_recharge')
            ->where(['user_id' => $user_id, 'card_id' => $card_id, 'payway' => $payway, 'status' => 1])
            ->whereTime('create_time', '-' . get_site_config('recharge_interval_limit') . 'second')->count(1);
        if ($count) {
            throw new ApiException('支付频率超限，请稍后再试或选择其它面额', 447);
        }
        // 1个小时内调起未支付的订单超过5则将用户加入黑名单
        if (get_site_config('recharge_failed_limit') > 0) {
            $range = get_site_config('recharge_failed_check_range');
            $exist = db('user_recharge')->where([
                'user_id'     => $user_id,
                'create_time' => ['egt', datetime(strtotime("-{$range}minute"))],
                'status'      => ['neq', 1],
            ])->count();
            if ($exist >= get_site_config('recharge_failed_limit')) {
                db('recharge_blacklist')->insert(
                    ['user_id' => $user_id, 'admin_id' => 0, 'comment' => '系统判定'],
                    true
                );
                // Enigma::send_check_message("订单中心--->充值黑名单  - 用户: $user_id 被系统判定进入, 请核对!");
                throw new ApiException('支付故障', 444);
            }
        }

        return;
        // if (Env::get('app.server') == 'test') {
        //     return;
        // }
        // 黑名单检测
        $user = db('user')->where('id', $user_id)->field('loginip,imei')->find();
        $exist = db('recharge_blacklist')
            ->where("(type=1 and number = '{$user_id}') OR (type = 2 and number = '" . ($user['loginip']) . "') or (type=3 and number='" . ($user['imei']) . "')")
            ->order('id desc')->find();
        if ($exist) {
            throw new ApiException($exist['form'], 445);
        }
        // 单个用户单日累计充值成功总额不超过2w
        $total = db('user_recharge')->where(['user_id' => $user_id, 'status' => 1])
            ->whereTime('create_time', 'd')->sum('pay_amount');
        if (($total + $amount) >= 10000) {
            throw new ApiException('超出当日限额，请明日再试', 440);
        }

        if ($agent_flag) {  // 代充 检测到此为止
            return;
        }
        // 检测充值IP是否与登录IP一致
        if (get_site_config('recharge_ip_limit')) {
            $ip = db('user')->where('id', $user_id)->value('loginip');
            if ($ip != request()->ip()) {
                throw new ApiException('支付故障', 442);
            }
        }


    }

    /**
     * 苹果支付凭证校验
     * @param string $order_no 订单号
     * @param string $receipt  支付凭证
     * @return void
     * @throws
     */
    public function appleValidation(string $order_no, string $receipt): void
    {
        $package_name = 'com.repo.voopea';  // app包名, 做验证使用
        $receipt = urldecode(urlencode($receipt));
        $receipt = preg_replace('/\s/', '+', $receipt);
        $url_sandbox = "https://buy.itunes.apple.com/verifyReceipt";
        if (!$receipt || $receipt == '') {
            throw new ApiException('缺少购买凭证');
        }
        //  在verifyWithRetry方法中，首先向向真实环境验证票据，如果是21007则向沙盒环境验证；==但是在消耗品类型的测试中，使用沙盒票据在真实环境中验证票据得到返回码：21002.所以下面代码在真实环境运行时，沙盒测试消耗型商品得不到正确的验证结果==。
        //  https://blog.csdn.net/Leemin_ios/article/details/77714784
        $post_data = json_encode(['receipt-data' => $receipt]);
        $response = Http::post($url_sandbox, $post_data, '');
        $res = json_decode($response, true);
        if ($res['status'] == '21007') {   // 生产环境校验不成功的情况下, 追加一次沙盒校验
            $url_sandbox = "https://sandbox.itunes.apple.com/verifyReceipt";
            $response = Http::post($url_sandbox, $post_data, '');
            $res = json_decode($response, true);
        }

        $err_msg = [
            '21000' => 'App Store不能读取你提供的JSON对象',
            '21002' => 'receipt-data域的数据有问题',
            '21003' => 'receipt无法通过验证',
            '21004' => '提供的shared secret不匹配你账号中的shared secret',
            '21005' => 'receipt服务器当前不可用',
            '21006' => 'receipt合法，但是订阅已过期。服务器接收到这个状态码时，receipt数据仍然会解码并一起发送',
            '21007' => 'receipt是Sandbox receipt，但却发送至生产系统的验证服务',
            '21008' => 'receipt是生产receipt，但却发送至Sandbox环境的验证服务',
        ];

        if ((int)($res['status']) === 0 && $res['receipt']['bundle_id'] == $package_name) {
            $order = db('user_recharge')->where('order_no', $order_no)->find();
            //一定要进行去重验证，一个订单号只能加一次款
            $trade_no = $res['receipt']['in_app'][0]['original_transaction_id'];
            $exist_count = db('user_recharge')->where(['trade_no' => $trade_no])->count();
            if ($exist_count > 0) {
                throw new ApiException('凭证无效', 461);
            }
            //对应产品id，自己做一个金额的映射就行，对应到具体的金额，建议命名要有规律
            $card = db('channel_card')->find($order['card_id']);
            $product_id = $res['receipt']['in_app'][0]['product_id'];
            if ($product_id != $card['item_code']) {
                throw new ApiException('凭证无效', 462);
            }
            UserBusiness::order_success($order_no, $trade_no);
        } else {
            $str = '支付失败:' . $res['status'] . ' - ' . ($err_msg[$res['status']] ?? 'no message');
            Log::error('苹果支付失败');
            Log::error($str);
            Log::error($res);
            throw new Exception($str, $res['status']);
        }
    }

    /**
     * 获取订单支付链接[信息]
     * @param array $order 订单
     * @return array|void
     * @throws ApiException
     */
    public function getOrderInfo(array $order)
    {
        $order_no = $order['order_no'];
        [$companyCode, $payWay, $openWay,] = explode('_', $order['payway']);
        $result = ['order_no' => $order_no, 'pay_way' => $order['payway'], 'source' => $openWay,];
        //获取支付配置信息
        $config = db('channel_company')->where('code', $companyCode)->find();
        if (!$config) {
            throw new ApiException('该支付方式已停止');
        }
        if (strtoupper($payWay) == 'ALIPAY') {
            if (in_array(
                strtoupper($companyCode),
                ['SHANGXUEP', 'RONGKAIP', 'QINGSHENGP', 'TUYOUP', 'YIFANP', 'XINZHIYUNP', 'LYMMSZP']
            )) {  // 支付宝原生
                $pay = new AliPay($config);
                if ($openWay == 'SDK') {
                    $result['pay_url'] = $pay->sdk_payment($order_no);
                } elseif ($openWay == 'H5I' || $openWay == 'H5') {
                    $result['pay_url'] = $pay->h5_payment($order_no);
                }
            } elseif (in_array(strtoupper($companyCode), ['SHANGXUEXS', 'XINZHIYUNXS'])) {  //新生支付
                if ($openWay == 'H5I' || $openWay == 'H5') {
                    $pay = new NewPay($config);
                    $result['pay_url'] = $pay->h5_payment($order_no);
                }
            } elseif (in_array(strtoupper($companyCode), ['SHANGXUEHF'])) {
                $pay = new AdaPay($config);
                $result['pay_url'] = $pay->payment($order);
            } elseif (in_array(strtoupper($companyCode), ['QINGSHENGHM'])) {
                $pay = new HmPay($config);

                if ($openWay == 'H5I' || $openWay == 'H5') {
                    $result['pay_url'] = $pay->h5_payment($order_no);
                } else {
                    $result['pay_way'] = implode('_', [$companyCode, 'HM', $openWay]);
                    $result['pay_url'] = $pay->sdk_payment($order_no);
                }
            } elseif (in_array(strtoupper($companyCode), ['SANDERSX'])) {
                $pay = new SanderPay($config);
                $result['pay_way'] = implode('_', [$companyCode, 'SAND', $openWay]);
                $result['pay_url'] = $openWay == 'SDK' ? $pay->sdk_payment($order_no) : $pay->h5_payment($order_no);
            } elseif (in_array(strtoupper($companyCode), [
                'SHANGXUETC',
                'TUYOUTC',
                'YIFANTC',
                'QINGSHENGTC',
                'XINZHIYUNTC'
            ])) {  // TC
                $pay = new TcPay($config);
                $result['pay_url'] = $pay->payment($order_no, 1);
            } elseif (in_array(strtoupper($companyCode), ['RANGSHANXHD'])) {
                $pay = new XhdPay($config);
                $result['pay_url'] = $pay->alipay_h5($order_no);
            } elseif (in_array(strtoupper($companyCode), ['TUYOUQTK'])) {  // 图游-七淘卡
                $result['pay_url'] = url('/pay/goback/qtk_jump', ['order_no' => $order_no], '', true);
            }
        } elseif (strtoupper($payWay) == 'WX') {
            if (in_array(strtoupper($companyCode), ['SHANGXUESN'])) {  // 殇雪-苏宁
                $pay = new SuningPay($config);
                $result['pay_url'] = $pay->payment($order_no);
            } elseif (in_array(strtoupper($companyCode), ['LYNSN', 'YIFANSN', 'QINGSHENGSN', 'TUYOUSN'])) {  // 乐佑宁-苏宁
                $pay = new SuningPay($config);
                $result['pay_url'] = $pay->payment($order_no);
            } elseif (in_array(strtoupper($companyCode), [
                'SHANGXUETC',
                'TUYOUTC',
                'YIFANTC',
                'QINGSHENGTC',
                'XINZHIYUNTC'
            ])) {  // TC
                $pay = new TcPay($config);
                $result['pay_url'] = $pay->payment($order_no, 2);
            } elseif (in_array(strtoupper($companyCode), ['SHANGXUEHF2'])) {  // 殇雪-汇付2
                $pay = new HuifuPay($config);
                $result['pay_url'] = url('pay/goback/huifu_jump', ['order_no' => $order_no], '', true);
            } elseif (in_array(strtoupper($companyCode), ['SHANGXUE'])) {  // 殇雪 微信sdk
                $pay = new WechatPayV3();
                $result['pay_url'] = $pay->payment_sdk($order_no);
            } elseif (in_array(strtoupper($companyCode), ['SHANGXUEXS'])) {  // 殇雪 新生微信
                $result['pay_url'] = url('/pay/goback/newpay_jump', ['order_no' => $order_no], '', true);
            } elseif (in_array(strtoupper($companyCode), ['SHANGXUELH'])) {  // 殇雪 联合
                $result['pay_url'] = url('/pay/goback/umfpay_jump', ['order_no' => $order_no], '', true);
            }
        } elseif (strtoupper($payWay) == 'BANK') {
            if (in_array(strtoupper($companyCode), [
                'SHANGXUEKJ',
                'QINGSHENGKJ',
                'YIFANKJ',
            ])) {  // 杉德 殇雪-快捷 清晟-快捷 test
                $result['pay_url'] = url('/pay/goback/sand_fast', ['order_no' => $order_no], '', true);
            }
        } elseif (strtoupper($payWay) == 'UNIONPAY') {
            if (in_array(strtoupper($companyCode), ['SHANGXUESDKJ'])) {  // 杉德 殇雪-快捷-银联
                $result['pay_url'] = url('/pay/goback/sand_h5', ['order_no' => $order_no], '', true);
            }
        } elseif (strtoupper($payWay) == 'GOOGLE') {
            $result['pay_url'] = '123';
        } elseif (strtoupper($payWay) == 'APPLE') {
            $result['pay_url'] = '123';
        }
        if (empty($result['pay_url'])) {
            throw new ApiException('不支持的方式');
        }

        return $result;
    }

    /**
     * 是否首充(可领首充奖励)
     * @param $user_id
     * @return bool true=首充存在,false=无首充奖励
     */
    public
    static function isFirstRecharge(
        $user_id
    ) {
        return !db('user_recharge')->where('user_id', $user_id)->where('status', 1)->find();
    }
}
