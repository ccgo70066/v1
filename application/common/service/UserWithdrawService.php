<?php

namespace app\common\service;


/**
 * 用户提现类
 */
class UserWithdrawService extends BaseService
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
     * 获取账户信息
     * @param $user_id
     * @return array ['bank_account'=>[...],'alipay_account'=>[...]]
     */
    public function getAccountInfo($user_id)
    {
        $account = db('user_account')->where('user_id', $user_id)->find();
        $data['bank_account'] = $account ? array_index_filter($account, ['account_name', 'bank_name', 'bank_number', 'bank_logo', 'branch_name', 'province_city', 'card_number', 'mobile', 'alipay_card_number']) : null;
        $data['alipay_account'] = $account ? array_index_filter($account, ['alipay_name', 'alipay_number', 'alipay_card_number']) : null;

        if ($account) {
            $data['bank_account']['is_default'] = (int)($account['default_type'] == 1);
            $data['alipay_account']['is_default'] = (int)($account['default_type'] == 2);
        }
        return $data;
    }
}
