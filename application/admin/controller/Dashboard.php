<?php

namespace app\admin\controller;

use app\admin\model\Admin;
use app\admin\model\Order;
use app\admin\model\Product;
use app\admin\model\User;
use app\common\controller\Backend;
use app\common\model\Attachment;
use fast\Date;
use think\Cache;
use think\Db;

/**
 * 控制台
 *
 * @icon   fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends Backend
{

    protected $noNeedRight = [''];

    /**
     * 查看
     */
    public function index()
    {
        try {
            \think\Db::execute("SET @@sql_mode='';");
        } catch (\Exception $e) {
        }
        $chart = Cache::remember('dashboard:chart', function () {
            $column = [];
            $starttime = Date::unixtime('day', -7);
            $endtime = Date::unixtime('day', -1, 'end');

            for ($time = $starttime; $time <= $endtime;) {
                $column[] = date("Y-m-d", $time);
                $time += 86400;
            }
            $chart = array_fill_keys($column, []);

            $temp = [];
            $user_reg = Db("user")->where('jointime', 'between time', [$starttime, $endtime])
                ->field('COUNT(*) AS nums, DATE_FORMAT(FROM_UNIXTIME(jointime), "%Y-%m-%d") AS join_date')
                ->group('join_date')
                ->select();
            foreach ($user_reg as $k => $v) {
                $temp[$v['join_date']]['user_reg'] = $v['nums'];
            }

            $user_login = Db("user")->where('logintime', 'between time', [$starttime, $endtime])
                ->field('COUNT(*) AS nums, DATE_FORMAT(FROM_UNIXTIME(logintime), "%Y-%m-%d") AS join_date')
                ->group('join_date')
                ->select();
            foreach ($user_login as $k => $v) {
                $temp[$v['join_date']]['user_login'] = $v['nums'];
            }

            $recharge_usd = Db("user_recharge")->alias('r')->join('channel_card c', 'r.card_id=c.id', 'left')
                ->where('r.create_time', 'between time', [datetime($starttime), datetime($endtime)])
                ->where('r.status', 1)
                ->field('sum(pay_amount) as amount, DATE_FORMAT(r.create_time, "%Y-%m-%d") AS join_date')
                ->group('join_date')
                ->select();
            foreach ($recharge_usd as $k => $v) {
                $temp[$v['join_date']]['recharge'] = ($v['amount']);
            }
            $withdraw = Db("user_withdraw")
                ->where('create_time', 'between time', [datetime($starttime), datetime($endtime)])
                ->where('status', 2)
                ->field('sum(payment_amount) as amount, DATE_FORMAT(create_time, "%Y-%m-%d") AS join_date')
                ->group('join_date')
                ->select();
            foreach ($withdraw as $k => $v) {
                $temp[$v['join_date']]['withdraw'] = ($v['amount']);
            }

            foreach ($chart as $k => $v) {
                $chart[$k]['user_reg'] = $temp[$k]['user_reg'] ?? 0;
                $chart[$k]['user_login'] = $temp[$k]['user_login'] ?? 0;
                $chart[$k]['recharge'] = $temp[$k]['recharge'] ?? 0;
                $chart[$k]['withdraw'] = $temp[$k]['withdraw'] ?? 0;
            }

            return $chart;
        }, 1);

        $this->view->assign([
            'totaluser'      => User::count(),
            'today_user_reg' => User::where(['jointime' => ['>=', strtotime(date('Y-m-d'))]])->count(),
            'total_recharge' => (Db("user_recharge")->alias('r')->where('r.status', 1)->sum('pay_amount')),
            'total_withdraw' => (Db("user_withdraw")->where('status', 2)->sum('payment_amount')),
        ]);

        $this->assignconfig('chart', [
            'column'     => array_keys($chart),
            'user_reg'   => array_column($chart, 'user_reg'),
            'user_login' => array_column($chart, 'user_login'),
            'recharge'   => array_column($chart, 'total_recharge'),
            'withdraw'   => array_column($chart, 'withdraw'),
        ]);

        return $this->view->fetch();
    }

}
