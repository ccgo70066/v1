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

    public function handler11(array $message): bool
    {
        try {
            Db::startTrans();

            $data = $message;
            if (!isset($data['user_id']) || !isset($data['gifts']) || !isset($data['to_user_ids'])) {
                Log::error('give_gift_async_handle参数有误:');
                Log::error($data);
                throw new \Exception('give_gift_async_handle参数有误');
            }
            $user_id = $data['user_id'];
            $gifts = $data['gifts'];
            $to_user_ids_arr = $data['to_user_ids'];
            $gift_value_sum = 0;
            foreach ($to_user_ids_arr as $to_user_id) {
                $gift_value = 0;
                foreach ($gifts as $gift) {
                    $gift_value += $gift['price'] * $gift['count']; //单个用户收到的礼物价值
                    $gift_value_sum += $gift['price'] * $gift['count']; //单个用户收到的礼物价值合计
                    if (in_array($gift['type'], [1, 2])) {
                        //礼物墙
                        gift_wall_add($to_user_id, $gift['gift_id'], $gift['count']);
                    }
                }
            }
            //$integral = get_noble_integral_mark_up($user_id, $gift_value_sum);
            //user_business_change($user_id, 'integral', $integral, 'increase', '赠送礼物', 4);
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
