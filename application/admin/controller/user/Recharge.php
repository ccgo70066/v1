<?php

namespace app\admin\controller\user;

use app\admin\model\UserRecharge1;
use app\common\service\RedisService;
use app\common\controller\Backend;
use app\common\library\exception\BusinessException;
use app\common\model\UserBusiness;
use think\Db;
use think\exception\PDOException;

/**
 * 充值记录
 *
 * @icon fa fa-circle-o
 */
class Recharge extends Backend
{

    /**
     * UserRecharge模型对象
     * @var \app\admin\model\UserRecharge
     */
    protected $noNeedLogin = ['total', 'get_pay_company', 'get_pay_channel'];
    protected $noNeedRight = ['total', 'get_pay_company', 'get_pay_channel'];
    protected $model = null;
    protected $relationSearch = true;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\UserRecharge;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("systemList", $this->model->getSystemList());
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            if (input('option') == 'load_payway') {
                return json(db('pay_way')->column('name', 'code'));
            }
            $with = [
//                'user' => function ($query) {
//                    $query->withField('nickname');
//                },
// 'agent' => function ($query) {
//     $query->withField('username');
// },
//                'payway' => function ($query) {
//                    $query->withField('pay_name');
//                },
                'card'
            ];
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->with($with)
                ->where($where)
//                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with($with)
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();

            //获取用户昵称
            if (count($list)) {
                $company = Db::name('channel_company')->column('name', 'code');
                $pay_channel = Db::name('channel_payway')->column('pay_name', 'id');

                foreach ($list as $key => $value) {
                    //$list[$key]['pay_amount'] = bcdiv($value['pay_amount'], 100, 4);
                    $list[$key]['pay_amount'] = $value['pay_amount'];

                    $list[$key]['user']['nickname'] = RedisService::getUserCache($value['user_id'], 'nickname');
                    $list[$key]['company'] = $company[$value['company_code']] ?? '';
                    $list[$key]['pay_channel'] = $pay_channel[$value['pay_channel_id']] ?? '';
                }
            }

            $result = array(
                "total"  => $total,
                "rows"   => $list,
                "extend" => [],
            );
            return json($result);
        }

        if (input('?param.ids') && !input('?get.ids')) {
            $row = $this->model->get(input('ids'))->toArray();
            return $this->view->fetch('common/detail', ['row' => $row]);
        }
        return $this->view->fetch();
    }

    /**
     * 统计查询
     */
    public function total()
    {
        list($where, $sort, $order, $offset, $limit) = $this->buildparams();
        $total = $this->model->where($where)->count(1);
        $success_pay_num = $this->model->where($where)->where("status", 1)->count(1);
        // $totalPeople = $this->model->where($where)->group('user_id')->count(1);
        $totalPeople = Db::name('user_recharge')->alias('user_recharge')->where($where)->group('user_id')->count(1);
        $pay_people_num = Db::name('user_recharge')->alias('user_recharge')
            ->where($where)->where('status', 1)->group('user_id')->count(1);
        //$money_pay_amount = $this->model->where($where)->where("status = 1")->sum('pay_amount');
        $money_pay_amount = $this->model->with(['card'])->where($where)->where("user_recharge.status = 1")->group('card.unit')->column('sum(pay_amount)', 'card.unit');
        //trace(collection($money_pay_amount)->toArray());
        $money_text = '';
        foreach ($money_pay_amount as $k => $v) {
            $money_text .= ['1' => '美金', '2' => '台币'][$k] . ':' . $v . ' ';
        }

        if ($success_pay_num == 0 || $total == 0) {
            $pay_rate = '0%';
        } else {
            $pay_rate = number_format($success_pay_num / $total * 100, 2) . '%';
        }
        $result = [
            'total_count'      => $total,
            'success_pay_num'  => $success_pay_num, //付款单数
            'total_people'     => $totalPeople,
            'pay_people_num'   => $pay_people_num,  //付款人数
            'pay_rate'         => $pay_rate,        //付款率
            'money_pay_amount' => $money_text,
        ];

        return json($result);
    }

    /**
     * 手动补单
     */
    public function reorder($ids)
    {
        if ($this->request->isAjax()) {
            $result = Db::name('user_recharge')->where('id', $ids)->find();
            if (!$result) {
                $this->error('参数错误,充值记录不存在');
            }
            if ($result['status'] == 1) {
                $this->error('该订单状态不可手动补单');
            }

            try {
                Db::startTrans();
                $update = Db::name('user_recharge')->where('id', $ids)->where('status', '<>', 1)
                    ->update(['is_reorder' => 1,]);
                if (!$update) {
                    $this->error('请刷新重试');
                }
                UserBusiness::order_success($result['order_no'], $result['trade_no'] ?? '', true);
                Db::commit();
            } catch (\Throwable $e) {
                Db::rollback();
                error_log_out($e);
                //$this->error('补单失败,请刷新重试');
                $this->error('操作失败:' . $e->getMessage());
            }
            $this->success();
        }
    }

    public function get_pay_channel()
    {
        // $company_id = input('company_ids') ?? [];
        // if (!$company_id) {
        //     $this->success('无数据');
        // }
        $key = input('key') ?? 'id';
        $name = input('pay_name') ?? 'pay_name';
        return json(Db::name('channel_payway')->where('status', 1)->order('weigh desc')->column($name, $key));
    }

    public function get_pay_company()
    {
        // $company_id = input('company_ids') ?? [];
        // if (!$company_id) {
        //     $this->success('无数据');
        // }
        $key = input('key') ?? 'code';
        $name = input('pay_name') ?? 'name';
        return json(Db::name('channel_company')->where('status', 1)->order('weigh desc')->column($name, $key));
    }

    /**
     * 删除
     */
    public function delete($ids = "")
    {
        if ($ids) {
            $pk = $this->model->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->model->where($pk, 'in', $ids)->select();

            $count = 0;
            Db::startTrans();
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                }
                Db::commit();
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($count) {
                $this->success();
            } else {
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    /** 退款 */
    public function refund($ids = '')
    {
        //$this->error('暂不支持此功能');
        //$this->success('已申请退款, 请耐心等待支付平台处理');
        if (!request()->isPost()) {
            $this->error('');
        }
        $order = $this->model->where('id', $ids)->find();
        // 直接标识退款
        $order->status = 5;
        $order->save();
        $this->success();

        [$companyCode, $payWay, $openWay,] = explode('_', $order['payway']);
        $refundExist = Db::name('user_recharge_refund')->where('order_no', $order['order_no'])->count();
        // $order['status'] != 1 && $this->error('订单没有付款');
        // $companyCode != 'TUYOUSN'&&  $this->error('暂时只支持[图游-苏宁]的订单');
        // $refundExist > 0 && $this->error('该订单已申请退款');
        try {
            $companyCode = 'TUYOUSN';
            $config = db('channel_company')->where('code', $companyCode)->find();
            $res = (new \app\pay\library\SuningPay($config))->refund($order['order_no']);
        } catch (BusinessException $exception) {
            // $this->error($exception->getMessage());
        }
        db('user_recharge_refund')->insert([
            'order_no'  => $order['order_no'],
            'amount'    => $order['pay_amount'],
            'pay_way'   => $order['payway'],
            'refund_no' => $res['refund_no'] ?? '',
            'operator'  => session('admin.id'),
            'status'    => 2,
        ]);
        $this->success('已申请退款, 请耐心等待支付平台处理');
    }

}
