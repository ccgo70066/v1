<?php

namespace app\common\service;

use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\common\exception\ApiException;
use app\common\library\Auth;
use app\common\library\ChinaName;
use app\common\library\rabbitmq\EggMQ;
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
use think\Log;
use think\Model;
use util\Util;

/**
 * 厅红包
 */
class LuckyMoneyService extends BaseService
{

    /**
     * @param $id
     * @param $user_id
     * @return int|mixed
     * @throws
     */
    public function open($id, $user_id)
    {
        $redis = redis();
        $key = 'lucky_money:' . $id;
        $money = $redis->hGetAll($key);
        if (!$money) {
            $money = db('lucky_money')->where('id', $id)->find();
            if ($money['status'] == 1) throw new  ApiException(__('has ended'));
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

        if ($result == 0 || $redis->hGet($key, 'count') == $money['max_count']) {
            $money = $redis->hGetAll($key);
            $money['status'] = 1;
            db('lucky_money')->update($money);
            $redis->del($key);
        }

        return $amount;
    }
}
