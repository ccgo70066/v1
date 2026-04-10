<?php

namespace controller;

use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\common\controller\Api;
use app\common\exception\ApiException;
use app\pay\library\AdaPay;
use app\pay\library\HuifuPay;
use app\pay\library\NewPay;
use app\pay\library\UMFPay;
use app\pay\library\WechatPay;
use app\pay\library\wechatpay_sdk\WechatAppPay;
use Exception;
use think\Cache;
use think\Controller;
use think\exception\HttpResponseException;
use think\Log;
use think\Response;

use function app\api\controller\board_notice;
use function app\api\controller\debugLog;

/**
 * 支付回
 */
class Call extends Api
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';


    /**
     * 支付回调
     * @desc key:  5e02657ed5fdcd78a8167486b3e4a04f
     * @ApiMethod   (post)
     * @ApiParams   (name="mch_id", type="string", required=true,  rule="min:0", description="商户ID")
     * @ApiParams   (name="user_id", type="int", required=true,  rule="min:0", description="用户ID")
     * @ApiParams   (name="item", type="int", required=true,  rule="min:0", description="支付项")
     * @ApiParams   (name="amount", type="int", required=true,  rule="min:0", description="金额[带.00]")
     * @ApiParams   (name="out_trade_no", type="int", required=true,  rule="min:0", description="交易流水号")
     * @ApiParams   (name="system", type="int", required=true,  rule="min:0", description="平台:1=ios,2=android")
     * @ApiParams   (name="sign", type="int", required=true,  rule="min:0", description="签名")
     * @return void
     * @throws
     */
    public function back()
    {
        $mch_id = 'a6888802123169';
        $key = '5e02657ed5fdcd78a8167486b3e4a04f';
        $input = file_get_contents('php://input');
        $input = json_decode($input, true);
        debugLog('pay', 'store', '', $input);
        $data = $input;
        $data['amount'] = sprintf('%.2f', $data['amount']);
        unset($data['sign']);
        ksort($data);
        $data['key'] = $key;
        $sign = strtoupper(md5(urldecode(http_build_query($data))));
        // dump($sign);
        // dump($input['sign']);
        $sign != $input['sign'] && $this->error(__('sign error'), $sign);
        $data['mch_id'] != $mch_id && $this->error(__('mch_id error'));
        $user = db('user')->where('id', $input['user_id'])->find();
        !$user && $this->error(__('user id error'));
        $item = db('channel_card')->where('code', $input['item'])->find();
        !$item && $this->error(__('item error'));
        $item['price'] != $input['amount'] && $this->error(__('amount error'));

        $order = [
            'order_no'       => date('YmdHis') . random_int(1000, 9999),
            'trade_no'       => $input['out_trade_no'],
            'user_id'        => $input['user_id'],
            'card_id'        => $item['id'],
            'status'         => 3,
            'pay_amount'     => $item['price'],
            'amount'         => $item['amount'],
            'company_code'   => 'XSSTORE',
            'way_code'       => 'STORE',
            'open_way'       => 'H5',
            'payway'         => 'XSSTORE_STORE_H5',
            'pay_channel_id' => 0,
            'ip'             => $this->request->ip(),
            'appid'          => input('appid'),
            'system'         => input('system'),
        ];
        db('user_recharge')->insert($order);

        $input['status'] == 1 && \app\common\model\UserBusiness::order_success($order['order_no']);

        $this->success('operation success');
    }


    /**
     * huifu * 提供给微信小程序三方下单
     * @ApiParams   (name="order_no", type="string", required=true,  rule="min:0", description="订单号")
     * @ApiParams   (name="code", type="string", required=false,  rule="min:0", description="登录code")
     * @return void
     */
    public function order()
    {
        trace(input(''), 'm');
        $order_no = input('order_no');
        $js_code = input('code');
        $order = db('user_recharge')->where('order_no', $order_no)->find();
        if (!$order) {
            $this->error(__('No results were found'));
        }
        $openid = Cache::remember("huifupay_wemini_openid:{$order['user_id']}", function () use ($js_code) {
            $openid = 'oAimI66E3xNROvYw7SbczxhIHufg';
            return $openid;
            $url = 'https://api.weixin.qq.com/sns/jscode2session';
            $query = [
                'appid'      => 'wx4cd4b137ebd9a1bb',
                'secret'     => 'b475397a8a82aa7e1677546f4e5cfe1e',
                'js_code'    => $js_code,
                'grant_type' => 'authorization_code',
            ];
            $rs = \fast\Http::get($url, $query);
            trace('获取用户openid', 'm');
            trace($rs, 'm');
            $rs = json_decode($rs, true);
            if ($rs['errcode'] != 0) {
                throw new ApiException($rs['errmsg'], $rs['errcode']);
            }
            return $rs['openid'];
        });
        [$companyCode, $payWay, $openWay,] = explode('_', $order['payway']);
        $result = [];
        $config = db('channel_company')->where('code', $companyCode)->find();
        if ($payWay == 'WX') {
            if ($companyCode == 'SHANGXUEHF') {
                $pay = new HuifuPay($config);
                $result = $pay->payment($order_no, $openid);
            }elseif ($companyCode == 'SHANGXUELH') {
                $pay = new UMFPay($config);
                $result = $pay->payment($order_no, $openid);
            }
        }

        $this->success('', $result);
    }

    /**
     * newpay/新生 * 提供给微信小程序三方下单
     * @ApiParams   (name="order_no", type="string", required=true,  rule="min:0", description="订单号")
     * @ApiParams   (name="code", type="string", required=false,  rule="min:0", description="登录code")
     * @return void
     */
    public function order_newpay()
    {
        trace(input(''), 'm');
        $order_no = input('order_no');
        $js_code = input('code');
        $order = db('user_recharge')->where('order_no', $order_no)->find();
        if (!$order) {
            $this->error(__('No results were found'));
        }
        $openid = Cache::remember("newpay_wemini_openid:{$order['user_id']}", function () use ($js_code) {
            $url = 'https://api.weixin.qq.com/sns/jscode2session';
            $query = [
                'appid'      => 'wx4cd4b137ebd9a1bb',
                'secret'     => '794fd4debeabb2363ee47af1fccbab33',
                'js_code'    => $js_code,
                'grant_type' => 'authorization_code',
            ];
            $rs = \fast\Http::get($url, $query);
            trace('获取用户openid', 'm');
            trace($rs, 'm');
            $rs = json_decode($rs, true);
            if (!isset($rs['errcode']) || $rs['errcode'] != 0) {
                throw new ApiException($rs['errmsg'] ?? '', $rs['errcode'] ?? 0);
            }
            return $rs['openid'];
        });
        // $openid = 'oAimI66E3xNROvYw7SbczxhIHufg';
        [$companyCode, $payWay, $openWay,] = explode('_', $order['payway']);
        $config = db('channel_company')->where('code', $companyCode)->find();
        $pay = new NewPay($config);
        $rs = $pay->payment_wechatService($order_no, $openid);

        $this->success('', $rs);
    }


    public function send_log_report_cmd()
    {
        $userId = input('user_id');
        board_notice(Message::CDM_LOG_REPORT, ['user_id' => $userId]);
        $this->success('success');
    }
}
