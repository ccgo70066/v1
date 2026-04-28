<?php

declare(strict_types=1);

namespace app\common\library\rabbitmq;

use app\common\service\LuckyMoneyService;
use think\Db;

class LuckyMoneyMQ extends BaseHandler
{
    // 消费回调
    public function handler(array $message): bool
    {
        tt($message);
        Db::startTrans();
        try {
            if ($message['type'] == 'push')
                LuckyMoneyService::instance()->push($message['id']);
            else
                LuckyMoneyService::instance()->timeout($message['id']);
            Db::commit();
            return true;
        } catch (\Throwable|\Exception $e) {
            Db::rollback();
            error_log_out($e);
            self::InsertMqLog(__LINE__ . $e->getMessage());
            return false;
        }
    }

}
