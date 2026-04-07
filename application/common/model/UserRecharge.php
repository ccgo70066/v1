<?php

namespace app\common\model;

use app\pay\library\WechatPay;
use app\pay\library\wechatpay_sdk\WechatAppPay;
use app\pay\library\WechatPayV3;
use think\Model;


class UserRecharge extends Model
{


    // 表名
    protected $name = 'user_recharge';


    public function profit_finish($order)
    {
        $wechatAppPay = (new WechatPay())->get_handler();
        $params = array(
            // 'out_order_no'   => $order['order_no'],  // 商户分账单号
            'out_order_no'   => '300027054020221013373086211001',
            'transaction_id' => $order['trade_no'],  //微信支付订单号
            'description'    => 'finish',
        );
        $wechatAppPay->profit_finish($params);
    }


    // 分帐
    public function profit_sharing($order)
    {
        trace($order, 'm');
        if ($order['status'] != 1 || $order['profit_status'] == 1) {
            return;
        }
        $wechatAppPay = (new WechatPayV3())->get_handler();
        $receivers = [
            [
                'type'        => 'MERCHANT_ID',
                'account'     => '1615165904',
                'name'        => $wechatAppPay->getEncrypt('深圳中星创展资产管理有限公司'),
                'description' => 'sharing'
            ],
        ];
        $total_amount = floor($order['pay_amount'] * 100 * 0.005) ?: 1; // 接口使用分为单位, 分帐比率30%
        $receivers_count = count($receivers);
        foreach ($receivers as $k => $receiver) {
            $amount = floor($total_amount / $receivers_count);
            $receivers[$k]['amount'] = $amount;
            $total_amount -= $amount;
            $receivers_count--;
        }

        $params = array(
            'out_order_no'     => $order['order_no'],  //自己生成的订单号
            'transaction_id'   => $order['trade_no'],  //微信支付订单号
            'receivers'        => $receivers,  //接收方
            'unfreeze_unsplit' => true,
        );

        list($status, $info) = $wechatAppPay->profit_sharing($params);

        db('user_recharge')->where('id', $order['id'])->setField([
            'profit_status' => 1,
            'profit_no'     => $info,
        ]);
    }

}
