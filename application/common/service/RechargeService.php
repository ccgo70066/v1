<?php

namespace app\common\service;

use app\common\exception\ApiException;
use app\common\model\UserBusiness;
use app\pay\library\AliPay;
use fast\Http;
use think\Collection;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\Log;

class RechargeService extends BaseService
{


    /**
     * 获取充值项及对应的充值渠道
     * @param $appid
     * @throws
     */
    public function getCard($appid, $system)
    {
        $version = request()->header('version');
        $versionThreshold = '1.0.5';
        $showStoreFlag = $version >= $versionThreshold;
        $channel = db('channel')->where(['appid' => $appid])->find();
        if (!$channel) throw new ApiException(__('Channel not found'));
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
            ->field('id,code,price,amount,give_amount,bage,item_code')
            ->where('system', $system)
            ->where('status', 1)
            ->where('id', 'in', implode(',', array_column($payway, 'card_ids')))
            ->order('weigh asc')
            ->select();
        if (!$cards) throw new ApiException(__('Card not found'));
        // $this->auth = Auth::instance();
        $isFirstCharge = 0;
        foreach ($cards as $k => &$card) {
            foreach ($payway as $item) {
                if (in_array($card['id'], explode(',', $item['card_ids']))) {
                    if ($item['code'] == 'XSSTORE_STORE_H5' && !$showStoreFlag) {
                        continue;
                    }
                    $card['payway'][] = [
                        'code' => $item['code'],
                        'name' => $item['app_pay_name'],
                        'image' => $item['payway_image']
                    ];
                }
            }
            if ($card['bage'] == 3 && !$isFirstCharge) {
                $card['bage'] = 0;
            }
            $card['bage_text'] = $this->bageMap()[$card['bage']] ?? '';
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
     * @throws
     */
    public function riskControl($user_id, int $agent_flag = 0, int $amount = 0, $card_id, $payway): void
    {
        // 黑名单
        $inBlacklist = db('recharge_blacklist')->where(['user_id' => $user_id])->count();
        if ($inBlacklist) {
            throw new ApiException(__('Payment failure'), 441);
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
        $result['pay_url'] = 'http://baidu.com';
        return $result;
        //获取支付配置信息
        $config = db('channel_company')->where('code', $companyCode)->find();
        if (!$config) {
            throw new ApiException('该支付方式已停止');
        }
        if (strtoupper($payWay) == 'ALIPAY') {
            if (in_array(strtoupper($companyCode), ['ALI'])) {  // 支付宝原生
                $pay = new AliPay($config);
                if ($openWay == 'SDK') {
                    $result['pay_url'] = $pay->sdk_payment($order_no);
                } elseif ($openWay == 'H5I' || $openWay == 'H5') {
                    $result['pay_url'] = $pay->h5_payment($order_no);
                }
            }
        } elseif (strtoupper($payWay) == 'WX') {
            if (in_array(strtoupper($companyCode), ['WX'])) {  // 微信
                $result['pay_url'] = url('/pay/goback/pay_jump', ['order_no' => $order_no], '', true);
            }
        } elseif (strtoupper($payWay) == 'BANK') {
            $result['pay_url'] = url('/pay/goback/pay_jump', ['order_no' => $order_no], '', true);
        } else {
            $result['pay_url'] = 'http://baidu.com';
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
    public static function isFirstRecharge($user_id)
    {
        return !db('user_recharge')->where('user_id', $user_id)->where('status', 1)->find();
    }
}
