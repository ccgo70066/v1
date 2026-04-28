<?php

namespace app\common\service;

use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\common\exception\ApiException;
use app\common\library\Auth;
use app\common\library\ChinaName;
use app\common\library\rabbitmq\Game1MQ;
use app\common\model\AnchorRecommend as AnchorRecommendModel;
use app\common\model\Gift as GiftModel;
use app\common\model\User;
use app\common\model\UserBlacklist;
use app\common\model\UserBusiness;
use fast\Http;
use fast\Random;
use think\Cache;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Env;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Log;
use think\Model;
use util\Util;

/**
 * 厅红包
 */
class LuckyMoneyService extends BaseService
{

    /**
     * @param     $id
     * @param     $user_id
     * @param int $room_id
     * @return int|mixed
     * @throws
     */
    public function open($id, $user_id, $room_id = 0)
    {
        $redis = redis();
        $key = 'lucky_money:' . $id;
        $money = $redis->hGetAll($key);
        if (!$money) {
            $money = db('lucky_money')->where('id', $id)->find();
            if (!$money || $money['status'] == 1) throw new  ApiException(__('has ended'));
            if ($money['open_time'] > datetime() || datetime() > $money['end_time']) throw new ApiException(__('The time is not right'));
            $redis->hMSet($key, (array)$money);
            $redis->expire($key, strtotime($money['end_time']) - time());
        }
        if ($money['remain_amount'] <= 0) throw new ApiException(__('No money left'));
        $exist = db('lucky_money_log')->where(['lucky_money_id' => $id, 'user_id' => $user_id,])->count();
        if ($exist) throw new ApiException(__('You have opened'));
        if ($money['max_count'] > 0 && $money['max_count'] == $money['count'])
            throw new ApiException(__('The number of red packets is full'));

        $amount = random_int($money['min_amount'], $money['max_amount']);
        if ($amount > $money['remain_amount']) {
            $amount = $money['remain_amount'];
        }
        $result = $redis->hIncrBy($key, 'remain_amount', -$amount);
        $redis->hIncrBy($key, 'count', 1);
        db('lucky_money_log')->insert(['lucky_money_id' => $id, 'user_id' => $user_id, 'amount' => $amount,]);
        user_business_change($user_id, 'amount', $amount, 'increase', '开红包', 7, $room_id);

        if ($result == 0 || $redis->hGet($key, 'count') == $money['max_count']) {
            $money = $redis->hGetAll($key);
            $money['status'] = 1;
            db('lucky_money')->update($money);
            $redis->del($key);
        }

        return $amount;
    }

    public function get($user_id)
    {
        $result = [];
        $money = db('lucky_money')
            ->field('id,end_time')
            ->where(['status' => 0, 'open_time' => ['<=', datetime()], 'end_time' => ['>', datetime()]])->find();
        if ($money) {
            $exist = db('lucky_money_log')->where(['lucky_money_id' => $money['id'], 'user_id' => $user_id,])->count();
            if (!$exist) {
                $money['second'] = strtotime($money['end_time']) - time();
                return $money;
            }
        }

        return $result;
    }

    /** 推送 */
    public function push(mixed $id)
    {
        $money = db('lucky_money')->where('id', $id)->find();
        if (!$money) return;
        board_notice(Message::CMD_LUCKY_MONEY, ['id' => $money['id'], 'second' => strtotime($money['end_time']) - time()]);
        tt('push');
    }

    public function timeout(mixed $id)
    {
        db('lucky_money')->where('id', $id)->setField(['status' => 1]);
    }
}
