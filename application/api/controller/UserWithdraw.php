<?php


namespace app\api\controller;

use app\common\service\UserWithdrawService;
use think\Db;
use think\Log;

/**
 * 会员提现申请
 */
class UserWithdraw extends Base
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';

    public function __construct()
    {
        parent::__construct();
        $this->service = new UserWithdrawService();
    }


    /**
     * 设置提现账户
     * @ApiMethod   (post)
     * @ApiParams   (name="account_name",    type="str",  required=false, description="真实姓名")
     * @ApiParams   (name="bank_number",  type="str",  required=false, description="银行卡号")
     * @ApiParams   (name="bank_name",  type="str",  required=false, description="银行名称")
     * @ApiParams   (name="branch_name", type="str",  required=false, description="开户支行名称")
     *
     * @ApiParams   (name="alipay_number",  type="str",  required=false, description="支付宝账号")
     * @ApiParams   (name="alipay_name",  type="str",  required=false, description="支付宝姓名")
     */
    public function set_account()
    {
        $user_id = $this->auth->id;
        $this->operate_check('account_lock:' . $user_id, 2);
        $data = input();
        $data['user_id'] = $user_id;
        $exist = db('user_account')->where('user_id', $user_id)->count();
        if (!$exist) db('user_account')->strict(false)->insert($data);
        else db('user_account')->where('user_id', $user_id)->strict(false)->update($data);

        $this->success();
    }

    /**
     * 获取提现账户详情
     */
    public function get_account()
    {
        $data = db('user_account')->where(['user_id' => $this->auth->id])->find();
        if (!$data) $this->success();
        $bank = $data['bank_name'] ? array_index_filter($data, ['account_name', 'bank_name', 'bank_number', 'branch_name',]) : new \ArrayObject();
        $alipay = $data['alipay_name'] ? array_index_filter($data, ['alipay_name', 'alipay_number']) : new \ArrayObject();

        $this->success('', compact('alipay', 'bank'));
    }


    /**
     * @ApiTitle    (获取提现申请列表)
     * @ApiMethod   (get)
     * @ApiParams   (name="page",    type="int",  required=false, rule="", description="页码")
     * @ApiParams   (name="size", type="int",  required=false, rule="", description="每页数量")
     * @ApiReturnParams    (name="status", type="int", description="状态:0=审核中,1=一审通过,2=已打款,3=打款失败,-1=驳回,-2=用户取消")
     * @ApiReturnParams    (name="account_type", type="int", description="打款方式:1=银行卡,2=支付宝")
     */
    public function list()
    {
        $user_id = $this->auth->id;
        $list = db('user_withdraw')->field('id,payment_amount,status,account_data,create_time')
            ->where('user_id', $user_id)->page(input('page', 1), input('size', 10))->order('id desc')->select();
        foreach ($list as &$value) {
            if (in_array($value['status'], [0, 1])) {
                $value['status'] = 0;
                $value['comment'] = '审核中';
            }
            if ($value['status'] == 2) {
                $value['status'] = 1;
                $value['comment'] = '成功';
            }
            if ($value['status'] == -1) $value['comment'] = '驳回';
            $info = json_decode($value['account_data'], true);
            $value['account_info'] = '';
            isset($info['bank_name']) && $info['bank_name'] && $value['account_info'] = $info['bank_name'] . '(' . substr($info['bank_number'], -4) . ')';
            unset($value['account_data']);
        }
        $this->success(__('Operation completed'), $list);
    }


    /**
     * @ApiTitle    (新增會員提現申請)
     * @ApiMethod   (post)
     * @ApiParams   (name="account_type", type="int",  required=true, rule="", description="提现账户类型:1=支付宝,2=银行卡")
     * @ApiParams   (name="amount", type="string",  required=true, rule="", description="金额")
     */
    public function add()
    {
        $user_id = $this->auth->id;
        $amount = input('amount');
        $this->operate_check('withdraw_lock:' . $user_id, 2);

        $business = db('user_business')->where('id', $user_id)->find();
        if ($amount > $business['reward_amount']) $this->error(__('Insufficient withdrawal limit'));
        $account = db('user_account')->where('user_id', $user_id)->find();
        if (empty($account)) $this->error(__('Withdrawal account does not exist'));
        $account_info = input('account_type') == 2 ?
            array_index_filter($account, ['account_name', 'bank_name', 'bank_number', 'branch_name',]) :
            array_index_filter($account, ['alipay_name', 'alipay_number',]);
        //$list_user = explode(',', get_site_config('list_withdraw_user')); //不受限制提现次数用户
        //if (in_array($user_id, $list_user) === false) {
        //    $withdraw = db('user_withdraw')->where([
        //        'user_id' => $user_id,
        //        'status'  => ['in', [0, 1, 2]],
        //        //                's_flag'  => 0,
        //    ])->whereTime('create_time', 'd')->count(1);
        //    $num = get_site_config('withdraw_num') ?: "0";   //个人每天的提现次数
        //    if ($withdraw >= $num) {
        //        $this->error(__('The number of withdrawals for that day has been exhausted'));
        //    }
        //}
        $min_withdraw_amount = get_site_config('min_withdraw_amount') ?: "1";   //最低提现(收益)
        $max_withdraw_amount = get_site_config('max_withdraw_amount') ?: "1";   //最高提现(收益)
        $amount < $min_withdraw_amount && $this->error(__('The withdrawal limit cannot be less than the minimum withdrawal (profit)'));
        $amount > $max_withdraw_amount && $this->error(__('The withdrawal limit cannot exceed the maximum withdrawal (profit)'));
        $withdraw_config = get_site_config('withdraw_fee') ?: "0"; //获取手续费

        $fee = bcmul($amount, $withdraw_config, 2);
        $less_amount = bcsub($business['reward_amount'], $amount, 2);
        $payment_amount = bcdiv(bcsub($amount, $fee, 2), 10, 2);
        try {
            Db::startTrans();
            $data = [
                'withdraw_no'    => date('YmdHis') . random_int(1000, 9999),
                'user_id'        => $user_id,
                'account_id'     => $account['id'],
                'amount'         => $amount,
                'less_amount'    => $less_amount,
                'payment_amount' => $payment_amount,
                'fee'            => $fee,
                'status'         => 0,
                'account_data'   => json_encode($account_info),
            ];
            db('user_withdraw')->insert($data);
            user_business_change($user_id, 'reward_amount', $amount, 'decrease', '用户申请提现', 13);

            Db::commit();
            //Enigma::send_check_message("订单中心--->用户提现申请  - 用户: {$user_id} 提交了新的记录，需要审核！");
        } catch (\Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error($e->getMessage());
        }
        $this->success(__('Operation completed'));
    }

    /**
     * @ApiTitle    (會員取消提現申請)
     * @ApiMethod   (get)
     * @ApiParams   (name="id", type="int",  required=true, rule="", description="id")
     *
     */
    public function cancel()
    {
        $user_id = $this->auth->id;
        $id = input('id');
        $withdraw = db('user_withdraw')->where('id', $id)->find();
        if (!$withdraw) {
            $this->error(__('No results were found'));
        }
        if ($withdraw['status'] != 0) {
            $this->error(__('Incorrect status'));
        }
        $less_amount = bcadd($withdraw['less_amount'], $withdraw['amount'], 2);
        try {
            Db::startTrans();
            $data = [
                'id'             => $id,
                'status'         => -2,
                'less_amount'    => $less_amount,
                'payment_amount' => 0,
            ];
            db('user_withdraw')->update($data);
            user_business_change($user_id, 'reward_amount', $withdraw['amount'], 'increase', '用户申请提现', 13);
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
     * 获取提现额度配置
     * @ApiMethod   (get)
     * @ApiSummary  ("withdraw_any_amount=是否可自定义提现金额，user_reward_amount=用户可提现收益（不含手续费）")
     */
    public function config()
    {
        $data = [];
        //提现手续费
        $data['withdraw_fee'] = get_site_config('withdraw_fee') ?: "0";
        //是否支持自定义提现额度
        $data['withdraw_any_amount'] = get_site_config('withdraw_any_amount') ?: "0";
        $data['withdraw_amount_min'] = get_site_config('min_withdraw_amount') ?: "1";   //最低提现(收益)
        $data['withdraw_amount_max'] = get_site_config('max_withdraw_amount') ?: "1";   //最低提现(收益)
        //用户可提现的收益
        $data['user_reward_amount'] = db('user_business')->where('id', $this->auth->id)->value('reward_amount');
        $data['account'] = null;
        $account = db('user_account')->where('user_id', $this->auth->id)->where('is_default', 1)->find();
        if ($account) {
            $data['account'] = [
                'id'   => $account['id'],
                'name' => $account['bank_name'] . '(' . substr($account['bank_number'], -4) . ')',
                'logo' => @$this->bankLogo[$account['bank_name']],
            ];
        }
        $data['explain'] = [
            '1. 請選擇提領的收益數量',
            '2. 提領申請提交24小時内完成審核，節假日順延',
            '3. 每筆提領需收取3%服務費',
            '4. 如有其他問題，請咨詢客服',
        ];


        $this->success('', $data);
    }

}
