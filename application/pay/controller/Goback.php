<?php

namespace app\pay\controller;

use think\Controller;
use think\Db;

/**
 * 支付返回 h5
 * 或某此支付需要做的中转页面
 */
class Goback extends Controller
{

    protected $noNeedLogin = '*';
    protected $noNeedRight = '*';

    public function pay_return()
    {
        return $this->view->fetch('pay_return', ['flag' => true]);
    }

    /**
     * 支付中转页面
     */
    public function pay_jump($order_no)
    {
        $company_code = Db::name('user_recharge')->where('order_no', $order_no)->value('company_code');
        $config = Db::name('channel_company')->where('code', $company_code)->find();
        $this->assign(['url' => $url ?? '', 'params' => $params ?? [],]);
        return view('pay_jump');
    }

}
