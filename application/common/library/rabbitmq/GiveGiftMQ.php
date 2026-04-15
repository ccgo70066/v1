<?php

declare(strict_types=1);

namespace app\common\library\rabbitmq;

use app\common\service\GiftService;
use think\Db;
use think\Log;

class GiveGiftMQ extends BaseHandler
{
    // 同时消费者数量
    //public static $consumes_count = 2;

    // 消费回调
    public function handler(array $message): bool
    {
        Db::startTrans();
        try {
            GiftService::instance()->give_gift(
                $message['user_id'],
                $message['to_user_ids'],
                $message['gifts'],
                $message['room_id'],
                $message['source']
            );
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
