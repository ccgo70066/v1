<?php

namespace app\pay\controller;

use app\pay\library\AdaPay;
use app\pay\library\WechatPay;
use app\pay\library\wechatpay_sdk\WechatAppPay;
use app\pay\library\WechatPayV3;
use think\Controller;
use think\Log;

/**
 * 支付异步回调
 */
class Callback extends Controller
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

    public function test()
    {
        echo 'test success';
    }

    /**
     * 通用代收回调
     * @return void
     * @throws
     */
    public function payment_common()
    {
        $data = input('');
        trace($data, 'sql');
        if (!is_array($data)) {
            $data = json_decode($data, true);
        }
        $pay_service = new TopPay();
        if (!$pay_service->check_sign($data)) {
            trace('验签不通过', 'sql');
            trace($data, 'sql');
            return;
        }
        $info = $data;
        if ($info['status'] == 1) {
            $order = db('user_recharge')->where(['order_no' => $info['out_trade_no']])->find();
            if ($order['status'] != 1) {
                \app\common\model\UserBusiness::order_success($info['out_trade_no'], $info['trade_no']);
            }
        }
        echo 'success';
    }

    /**
     * 通用代付回调
     * @return void
     * @throws
     */
    public function payout_common()
    {
        $data = input('');
        trace($data, 'sql');
        if (!is_array($data)) {
            $data = json_decode($data, true);
        }
        $pay_service = new TopPay();
        if (!$pay_service->check_sign($data)) {
            trace('toppay代付回调验签不通过', 'sql');
            trace($data, 'sql');
            return;
        }
        $info = $data;
        $withdraw_no = $info['out_trade_no'];
        if ($info['status'] == 1) {//success
            $order = db('user_withdraw')->where(['withdraw_no' => $withdraw_no])->find();
            if (!$order) {
                return;
            }
            if ($order['status'] != 3) {
                Withdraw::order_success($withdraw_no);
            }
        }
        if ($info['status'] == 2) {
            Withdraw::order_fail($withdraw_no, 5, '打款失败');
        }
        echo 'success';
    }


    /**
     * 支付宝回调
     * @return void
     */
    public function notify_alipay()
    {
        $data = $this->request->param();
        $order_no = $data['out_trade_no']; //订单号
        $trade_no = $data["trade_no"]; //交易流水号
        $pay_amount = $data["total_amount"]; //支付金额

        if ($data['trade_status'] == "TRADE_SUCCESS" && $data['out_trade_no']) {
            \app\common\model\UserBusiness::order_success($order_no, $trade_no);
            echo 'success';
        } else {
            echo "fail";
        }
    }


    /** 微信支付回调 */
    public function wechat_payment()
    {
        $wechatAppPay = (new WechatPay())->get_handler();
        $data = $wechatAppPay->getNotifyData();
        if ($data['out_trade_no']) {
            $order_no = $data['out_trade_no'];
            $order = db('user_recharge')->where('order_no', $order_no)->find();
            if ($order && $order['status'] > 1) {
                \app\common\model\UserBusiness::order_success($order_no, $data['transaction_id']);
                $wechatAppPay->replyNotify();
            }
        }
    }

    public function wechatv3_payment()
    {
        trace(input(), 'm');
        trace(file_get_contents('php://input'), 'm');
        $callData = input();
        $data = (new WechatPayV3())->decrypted($callData['resource']);
        $order_no = $data['out_trade_no'];
        $trade_no = $data['transaction_id'];
        if ($data['trade_state'] == "SUCCESS" && $data['out_trade_no']) {
            $order = db('user_recharge')->where('order_no', $order_no)->find();
            if ($order && $order['status'] > 1) {
                \app\common\model\UserBusiness::order_success($order_no, $trade_no);
            }
            echo json_encode(['code' => 'SUCCESS']);
        } else {
            echo json_encode(['code' => 'FAIL', 'message' => 'fail']);
        }
    }

}
