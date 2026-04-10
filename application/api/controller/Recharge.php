<?php

namespace app\api\controller;

use app\common\exception\ApiException;
use think\Db;
use think\Env;
use think\Log;
use util\Sign;

/**
 * 充值
 * @ApiWeigh    (899)
 * @package app\api\controller
 */
class Recharge extends Base
{
    protected $noNeedLogin = ['get_h5_way', 'h5_order', 'profit_sharing_process'];
    protected $noNeedRight = ['*'];

    /**
     * 支付方式:AP=支付宝,WC=微信,AE=苹果,GG=谷歌,BK=银行卡,CC=收银台
     */

    public function __construct()
    {
        parent::__construct();
        $this->service = new RechargeService();
    }

    /**
     * 是否存在首充
     *
     */
    public function is_first_recharge()
    {
        $this->success('', ['is_first' => $this->service->isFirstRecharge($this->auth->id)]);
    }

    /**
     * app获取充值卡
     *
     * @ApiParams   (name="appid", type="string", required=false, rule="", description="渠道号")
     * @ApiParams   (name="system", type="string", required=false, rule="in:1,2,3", description="平台:1=iOS,2=Android,3=Web")
     */
    public function get_card()
    {
        $appid = input('appid') ?? $this->appid;
        $system = $this->system ?? 2;
        $systemArr = [
            '1' => 'iOS',
            '2' => 'Android',
            '3' => 'Web',
        ];
        $card = $this->service->getCard($appid, $systemArr[$system]);
        $this->success('', $card);
    }

    /**
     * 支付下单
     * @ApiMethod   (post)
     * @ApiSummary  (注意: receipt不参与签名)
     * @ApiParams   (name="card_id", type="string",  required=true, rule="", description="充值项id")
     * @ApiParams   (name="pay_way", type="string",  required=false, rule="", description="支付方式")
     * @ApiParams   (name="appid", type="string", required=true, rule="", description="渠道号")
     * @ApiParams   (name="system", type="string", required=true, rule="in:1,2", description="平台:1=IOS,2=ANDROID")
     *
     * @ApiParams   (name="sign", type="string", required=true, rule="", description="签名")
     */
    public function order()
    {
        $user_id = $this->auth->id;
        $this->operate_check('order:' . $user_id);
        $card_id = input('card_id');
        $pay_way = input('pay_way');
        !input('pay_way') && $this->error(__('Not yet enabled'));
        $sign = Sign::generate(['card_id' => $card_id, 'pay_way' => $pay_way, 'appid' => input('appid'), 'system' => input('system'),], config('app.sign_key'));
        $systemArr = ['1' => 'iOS', '2' => 'Android', '3' => 'Web',];

        if (input('sign') != $sign && Env::get('app.server') != 'test') {
            $this->error(__('Sign incorrect'), config('app_debug') ? $sign : null, 403);
        }
        $card = db('channel_card')->where('system', $systemArr[input('system')])->find($card_id);
        !$card && $this->error(__('Not yet supported'), null, 405);
        $this->service->riskControl($user_id, 0, $card['price'], $card_id, $pay_way);
        Db::startTrans();
        try {
            [$companyCode, $way, $openWay,] = explode('_', $pay_way);
            $open = ['H5' => 1, 'H5I' => 2, 'SDK' => 3];
            $open_way_number = $open[$openWay];
            $pay_channel_id = Db::name('channel_payway')->alias('cp')
                ->join('channel_company c', 'cp.company_id = c.id')
                ->join('pay_way pw', 'cp.pay_way_id = pw.id')
                ->where('cp.card_system', $systemArr[input('system')])
                ->where('pw.code', $way)
                ->where('open_way', $open_way_number)
                ->where('c.code', $companyCode)
                ->value('cp.id');
            $price = $card['price'];
            $order = [
                'order_no'       => date('YmdHis') . random_int(1000, 9999),
                'user_id'        => $user_id,
                'card_id'        => $card_id,
                'status'         => 3,
                'pay_amount'     => $price,
                // 'orig_amount'    => $card['price'],
                'amount'         => $card['amount'],
                'company_code'   => $companyCode,
                'way_code'       => $way,
                'open_way'       => $openWay,
                'payway'         => $pay_way,
                'pay_channel_id' => $pay_channel_id,
                'ip'             => $this->request->ip(),
                'appid'          => input('appid'),
                'system'         => input('system'),
                'give_amount'    => $card['give_amount'] ?: 0
            ];
            $order['id'] = db('user_recharge')->insertGetId($order);
            $result = $this->service->getOrderInfo($order);
            Db::commit();
        } catch (ApiException $exception) {
            $this->error($exception->getMessage(), null, $exception->getCode());
        } catch (\Exception $exception) {
            Db::rollback();
            trace(\util\Util::exceptionFormat($exception), 'error');
            Log::error($exception->getMessage());
            error_log_out($exception);
            $this->error(__('Operation failed'), null, $exception->getCode());
        }
        // $this->success('', $result ?? null);
        $this->result('', $result ?? null, 1);
    }


    /**
     * 苹果支付凭证校验
     * @ApiMethod   (post)
     * @ApiParams   (name="order_no", type="string",  required=true, description="订单号")
     * @ApiParams   (name="receipt", type="string",  required=true, description="苹果购买凭证")
     */
    public function validate_ae()
    {
        $order_no = input('order_no');
        $receipt = input('receipt');
        try {
            $this->service->appleValidation($order_no, $receipt);
        } catch (ApiException $exception) {
            $this->error($exception->getMessage(), null, $exception->getCode());
        } catch (\Exception $exception) {
            trace(\util\Util::exceptionFormat($exception), 'error');
            $this->error($exception->getMessage(), null, $exception->getCode());
        }

        $this->success();
    }

    /**
     * 谷歌凭证处理
     * @ApiMethod   (post)
     * @ApiParams   (name="packagename", type="string",  required=true, description="app包名")
     * @ApiParams   (name="productid", type="string",  required=true, description="商品ID")
     * @ApiParams   (name="token", type="string",  required=true, description="token")
     */
    public function validate_gg()
    {
        $result = (new GooglePay())->query(input(''));
        trace($result, 'sql');
        !$result && $this->error(__('Operation failed'));
        if ($result['purchaseState'] == 0 && $result['obfuscatedExternalAccountId']) {
            \app\common\model\UserBusiness::order_success(
                $result['obfuscatedExternalAccountId'],
                $result['orderId']
            );
            $this->success();
        }
        $this->error();
    }


    /**
     * 账单分帐 异步调用
     * @return void
     * @ApiInternal
     */
    public function profit_sharing_process()
    {
        set_time_limit(0);
        // 延时1分钟.  @link https://blog.csdn.net/yjl544627654/article/details/108492892
        sleep(60);
        trace('分帐异步触发' . input('order_no'), 'm');
        $order = db('user_recharge')->where('order_no', input('order_no'))->find();
        model('common/UserRecharge')->profit_sharing($order);
        model('common/UserRecharge')->profit_finish($order);
    }


    /**
     * 查询
     * @ApiParams   (name="order_no", type="string",  required=true, description="订单号")
     * @return void
     */
    public function query()
    {
        $order_no = input('order_no', 0);
        $order = db('user_recharge')->where('order_no', $order_no)->find();

        $this->success('', $order['status'] == 1 ? 1 : 0);
    }
}
