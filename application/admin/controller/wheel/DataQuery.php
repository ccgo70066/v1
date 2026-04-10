<?php

namespace app\admin\controller\wheel;

use app\common\controller\Backend;

/**
 * 游戏数据查询
 *
 */
class DataQuery extends Backend
{
    protected $noNeedRight = ['*'];

    /**
     * 查看
     */
    public function index()
    {

        if ($this->request->isAjax()) {
            $type = input('type');
            $value = input('input_value');
            if (!in_array($type, ['user_id', 'room_id', 'imei', 'ip']) || $value == false) {
                $this->error('请填写有效参数');
            }

            $today = db('wheel_intact_log')
                ->where($type, $value)
                ->whereTime('create_time', 'today')
                ->field('sum(amount) as amount,sum(used_amount) as use_amount')
                ->find();
            $all = db('wheel_intact_log')
                ->where($type, $value)
                ->field('sum(amount) as amount,sum(used_amount) as use_amount')
                ->find();
            if ($type == 'user_id') {
                $current = db('user')->where('id', $value)->field('loginip as ip,imei')->find();
            }
            if ($type == 'ip') {
                $ip_sum = db('user')->where('loginip', $value)->count();
            }
            if ($type == 'imei') {
                $imei_sum = db('user')->where('imei', $value)->count();
            }
            $res = [
                'today_amount'     => $today['amount'] ?? 0,
                'today_use_amount' => $today['use_amount'] ?? 0,
                'today_percent'    => $today['use_amount'] ? bcdiv($today['amount'], $today['use_amount'], 4) * 100 . '%' : '0%',
                'today_reward'     => $today['use_amount'] ? number_format(($today['amount'] * 0.85 - $today['use_amount']) , 2,'.','') : 0,
                'all_amount'       => $all['amount'] ?? 0,
                'all_use_amount'   => $all['use_amount'] ?? 0,
                'all_percent'      => $all['use_amount'] ? bcdiv($all['amount'], $all['use_amount'], 4) * 100 . '%' : '0%',
                'all_reward'       => $all['use_amount'] ? number_format(($all['amount'] * 0.85 - $all['use_amount']) , 2,'.','') : 0,
                'current_ip'       => isset($current) ? $current['ip'] : '',
                'current_imei'     => isset($current) ? $current['imei'] : '',
                'ip_sum'           => $ip_sum ?? '',
                'imei_sum'         => $imei_sum ?? '',

            ];
            $this->success('', '', $res);
        }
        return $this->view->fetch();
    }
}
