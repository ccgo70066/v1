<?php

namespace app\api\controller;

use app\common\exception\ApiException;

/**
 * 红包雨
 * @ApiWeigh    (2000)
 */
class LuckyMoney extends Base
{
    protected $noNeedLogin = [];
    protected $noNeedRight = '*';


    /**
     * 领取红包
     * @ApiMethod   (post)
     * @ApiParams   (name="id", type="int",  required=true, rule="", description="红包id")
     *
     * @ApiReturnParams   (name="open_amount", type="string", description="红包id")
     * @ApiReturnParams   (name="next", type="object", description="下一个红包")
     */
    public function open()
    {
        $id = input('id');
        $user_id = $this->auth->id;
        $redis = redis();
        if (!$redis->set('lucky_money:' . $user_id, 1, ['nx', 'ex' => 1])) {
            throw new ApiException(__('Your operation is too fast'));
        }
        try {
            $money = db('lucky_money')->find($id);
            if ($money['remain_amount'] < $money['min_amount'] ||
                !$money['open_time'] ||
                time() - strtotime($money['open_time']) > 20) {
                throw new ApiException(__('All red packets have been claimed'));
            }

            $count = db('lucky_money_log')->where([
                'lucky_money_id' => $id,
                'user_id'        => $user_id,
            ])->count();

            if ($count >= $money['max_count']) {
                throw new ApiException(__('Reached the upper limit of claim attempts'));
            }
            $open_amount = $this->get_money($money['min_amount'], $money['max_amount'], $money['remain_amount']);
            $update['open_count'] = $money['open_count'] + 1;
            $update['open_count'] == $money['count'] && $update['status'] = 0;
            $update = db('lucky_money')->where('id', $id)->where(
                'remain_amount',
                '>=',
                $open_amount
            )->setDec('remain_amount', $open_amount);

            if (!$update) {
                throw new ApiException(__('Claim failed'));
            }
            $update = db('lucky_money_log')->insert([
                'lucky_money_id' => $id,
                'user_id'        => $user_id,
                'amount'         => $open_amount,
            ]);
            if ($update) {
                user_business_change($user_id, 'amount', $open_amount, 'increase', "领取红包雨", 7);
            }
        } catch (ApiException $exception) {
            $redis->del('lucky_money:' . $user_id);
            $this->error(__('Operation failed'));
        } catch (\Throwable $exception) {
            $redis->del('lucky_money:' . $user_id);
            error_log_out($exception);
            $this->error(__('Operation failed'));
        }
        $redis->del('lucky_money:' . $user_id);
        $this->success(__('Operation completed'), ['open_amount' => $open_amount]);
    }

    /**
     * 随机开红包的金额
     * @param $remain_count
     * @param $remain_amount
     * @return int
     */
    public function get_money($min_amount, $max_amount, $remain_amount)
    {
        if ($remain_amount < $min_amount * 2 && $remain_amount > $max_amount) {
            return $remain_amount;
        }
        $min = $min_amount;
        $max_amount = min($remain_amount, $max_amount);
        $max = ($max_amount - $min_amount);
        return $min + rand(0, $max);
    }

    // /**
    //  * 随机开红包的金额
    //  * @param $remain_count
    //  * @param $remain_amount
    //  * @return int
    //  */
    // public function get_money($remain_count, $remain_amount)
    // {
    //     if ($remain_count == 1) {
    //         return $remain_amount;
    //     }
    //     $min = $this->min_amount;
    //     if ($remain_amount / $remain_count == $min) {
    //         return $min;
    //     }
    //     $max = ($remain_amount - $min * $remain_count) / $remain_count * 2;
    //     return $min + rand(0, $max);
    // }
}

