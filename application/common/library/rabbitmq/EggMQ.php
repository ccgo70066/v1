<?php

declare(strict_types=1);

namespace app\common\library\rabbitmq;

use app\common\service\EggService;
use think\Db;

class EggMQ extends BaseHandler
{
    // 同时消费者数量
    public static $consumes_count = 2;

    // 消费回调
    public function handler(array $message): bool
    {
        Db::startTrans();
        try {
            EggService::process_mq($message);
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
