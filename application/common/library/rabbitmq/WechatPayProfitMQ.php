<?php

declare(strict_types=1);

namespace app\common\library\rabbitmq;

/**
 * 微信分账延时队列
 */
class WechatPayProfitMQ extends BaseHandler
{
    // 是否延迟队列
    protected $delayed = true;
    public static $consumes_count = 1;

    // 消费回调
    public function handler(array $message): bool
    {
        try{
            trace('分帐异步触发' . input('order_no'), 'm');
            trace($message, 'm');
            $data = $message;
            //$order = db('user_recharge')->where('order_no', $data['order_no'])->find();
            //model('common/UserRecharge')->profit_sharing($order);
            // model('common/UserRecharge')->profit_finish($order);
            return true;
        }catch (\Throwable|\Exception $e){
            error_log_out($e);
            self::InsertMqLog(__LINE__ . $e->getMessage());
            return false;
        }
    }
}
