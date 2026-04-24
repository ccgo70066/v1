<?php

declare(strict_types=1);

namespace app\common\library\rabbitmq;

use app\common\service\Game1Service;
use app\common\service\Game2Service;
use think\Db;

class Game2MQ extends BaseHandler
{
    // 同时消费者数量
    public static $consumes_count = 1;

    // 消费回调
    public function handler(array $message): bool
    {
        Db::startTrans();
        try {
            Game2Service::process_mq($message);
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
