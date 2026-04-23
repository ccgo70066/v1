<?php

namespace app\admin\controller\user;

use app\common\service\RedisService;
use app\common\controller\Backend;
use app\pay\library\AliPay;
use think\Db;

/**
 * 用户提现申请管理
 *
 * @icon fa fa-circle-o
 */
class Withdraw extends Backend
{

    /**
     * UserWithdraw模型对象
     * @var \app\admin\model\UserWithdraw
     */
    protected $model = null;
    protected $noNeedRight = ['detail', 'total', 'batch_examine', 'batch_payment'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\UserWithdraw;
        $this->view->assign("statusList", $this->model->getStatusList());
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
        //当前是否为关联查询
        $this->relationSearch = false;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            //缓存中取用户昵称
            foreach ($list as &$items) {
                $items['user_nickname'] = RedisService::getUserCache($items['user_id'], 'nickname');
                $account = json_decode($items['account_data'], true);
                $items['account_name'] = $account['account_name'] ?? $account['alipay_name'];
                //$items['payment_amount'] = bcdiv($items['payment_amount'], 100, 2);
                //$items['payment_amount'] = $items['payment_amount'];

                $items['operate_username'] = RedisService::getAdminCache($items['operate_id'], 'nickname');
                $items['finance_username'] = RedisService::getAdminCache($items['finance_id'], 'nickname');
                $items['payment_name'] = $items['payment_way_id'] ? RedisService::getPaymentWayCache($items['payment_way_id'], 'name') : '';
                $items['fee'] /= 15;
            }


            $result = array(
                "total"  => $list->total(),
                "rows"   => $list->items(),
                "extend" => [],
            );

            return json($result);
        }
        return $this->view->fetch();
    }


    /**
     * 賬戶詳情
     * @param $ids
     */
    public function detail($ids)
    {
        $row = db('user_withdraw')->where('id', $ids)->find();
        if (!$row) {
            $this->error(__('信息不存在'));
        }
        $account_data = json_decode($row['account_data'], true);
        $this->view->assign("row", $account_data);
        return $this->view->fetch('detail');
    }


    /**
     * 一审
     * @param $ids
     * @return string
     * @throws
     */
    public function examine($ids)
    {
        $row = $this->model->with(['user'])->where('user_withdraw.id', $ids)->find();
        $account = json_decode($row['account_data'], true);
        $row = $row->toArray();
        $row = array_merge($row, $account);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            $row['status'] != 0 && $this->error('状态错误');
            $params = $this->request->post("row/a");
            if ($params) {
                $data = [
                    'id'           => $ids,
                    'status'       => 1,
                    'comment'      => $params['comment'],
                    'operate_id'   => session('admin.id'),
                    'operate_time' => date('Y-m-d H:i:s'),
                ];
                $result = db('user_withdraw')->update($data);
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch('examine');
    }


    /**
     * 批量一审
     * @param $ids
     * @return string
     * @throws
     */
    public function batch_examine($ids)
    {
        $list_ids = Db::name('user_withdraw')->where('id', 'in', $ids)->where('status', 0)->column('id');
        if (!$list_ids) {
            $this->error(__('没有符合的数据'));
        }
        $data = [
            'status'       => 1,
            'comment'      => '批量审核',
            'operate_id'   => session('admin.id'),
            'operate_time' => date('Y-m-d H:i:s'),
        ];
        $result = db('user_withdraw')->where('id', 'in', $list_ids)->update($data);
        $this->success(__('操作成功,更新符合数据' . $result . '条'));
    }

    /**
     * 批量打款
     * @param $ids
     * @return string
     * @throws
     */
    public function batch_payment($ids)
    {
        $list_ids = Db::name('user_withdraw')->where('id', 'in', $ids)->where('status', 1)->column('id');

        if ($this->request->isPost()) {
            if (!$list_ids) {
                $this->error(__('没有符合的数据'));
            }

            $list = Db::name('user_withdraw')->where('id', 'in', $ids)->where('status', 1)->select();
            $result = 0;
            $params = $this->request->post("row/a");
            $params['status'] = 2;
            $params['payment_way_id'] = Db::name('payment_way')->whereNull('company_code')->value('id');
            $params['finance_id'] = session('admin.id');
            $params['finance_time'] = datetime();

            foreach ($list as $value) {
                $update = db('user_withdraw')->where('id', $value['id'])->where('status', 1)->setField($params);
                if ($update) {
                    $result++;
                    db('user_business')->where(['id' => $value['user_id']])->setInc(
                        'rewarded_amount',
                        $value['amount']
                    );
                    send_im_msg_by_system_with_lang($value['user_id'], sprintf('您%s提交的%s收益提现申請，已经打款，请注意查收！', $value['create_time'], $value['amount']));
                }
            }

            $this->success(__('操作成功,更新数据' . $result . '条'));
        }
        return $this->view->fetch('batch_payment');
    }

    /**
     * 审核完成-打款
     * @param $ids
     * @return string
     * @throws
     */
    public function payment($ids)
    {
        $row = $this->model->with(['user'])->where('user_withdraw.id', $ids)->find();
        $account = json_decode($row['account_data'], true) ?? [];
        $row = $row->toArray();
        $row = array_merge($row, $account);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            $row['status'] != 1 && $this->error('订单错误');
            $params = $this->request->post("row/a");
            !isset($params['payment_way_id']) && $this->error('请选择打款方式');
            if ($params) {
                $params = $this->preExcludeFields($params);
                $params['finance_id'] = session('admin.id');
                $params['finance_time'] = datetime();
                $params['comment'] = $row['comment'] . '\n' . $params['comment'];

                $data = db('user_withdraw w')->where('w.id', $ids)->find();
                $payment_way = db('payment_way')->find($params['payment_way_id']);
                if (!$payment_way) {
                    $this->error('打款方式-错误');
                }

                //todo 线下打款
                if (!$payment_way['company_code']) {
                    $params['status'] = 2;
                    db('user_withdraw')->where('id', $ids)->setField($params);
                    db('user_business')->where(['id' => $row['user_id']])->setInc(
                        'rewarded_amount',
                        $data['amount']
                    );

                    $data['amount'] = (int)$data['amount'];
                    send_im_msg_by_system_with_lang($row['user_id'], sprintf('您%s提交的%s收益提现申請，已经打款，请注意查收！', $data['create_time'], $data['amount']));
                    $this->success();
                } else {
                    //线上打款
                    $config = db('channel_company')->where('code', $payment_way['company_code'])->find();
                    if (!$config) {
                        $this->error('选择的支付公司-配置信息缺失');
                    }
                    $payWay = db('pay_way')->where('code', $payment_way['pay_way_code'])->find();
                    $params['status'] = 2;
                    switch (strtoupper($payWay['code'])) {
                        //支付宝原生
                        case "ALIPAY":
                            $account = json_decode($data['account_data'], true);
                            (!$account['alipay_name'] || !$account['alipay_number']) && $this->error('用户支付宝信息未完善');
                            $pay = new AliPay($config);
                            [$status, $msg] = $pay->payout(
                                $data['withdraw_no'],
                                $account['alipay_number'],
                                $account['alipay_name'],
                                $data['payment_amount'],
                                ''
                            );
                            $status == 0 && $this->error($msg);
                            break;
                        default:
                            $this->error('支付方式不存在');
                    }

                    $result = $this->model->where('id', $ids)->setField($params);

                    if ($result !== false) {
                        db('user_business')->where(['id' => $row['user_id']])->setInc(
                            'rewarded_amount',
                            $data['amount']
                        );
                        $data['amount'] = (int)$data['amount'];
                        send_im_msg_by_system_with_lang($row['user_id'], sprintf('您%s提交的%s收益提现申請，已经打款，请注意查收！', $data['create_time'], $data['amount']));
                        $this->success();
                    } else {
                        $this->error(__('No rows were updated'));
                    }
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        $this->view->assign('str', '财务打款');
        return $this->view->fetch();
    }


    /**
     * 驳回
     * @param $ids
     * @return string
     * @throws \think\Exception
     * @throws \think\exception\DbException
     */
    public function rebut($ids)
    {
        $row = $this->model->with(['user'])->where('user_withdraw.id', $ids)->find();
        $account = json_decode($row['account_data'], true) ?? [];
        $row = $row->toArray();
        $row = array_merge($account, $row);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            $row['status'] == 2 && $this->error('已打款无法操作.');
            in_array($row['status'], [-1, 2, -2]) && $this->error('订单已经处理过, 请不要重复操作');
            $less_amount = bcadd($row['less_amount'], $row['amount'], 2);
            $params = $this->request->post("row/a");
            if ($params) {
                $data = [
                    'id'             => $ids,
                    'status'         => -1,
                    'less_amount'    => $less_amount,
                    'reject_comment' => $params['reject_comment'],
                ];
                // if ($row['s_flag'] == 1) {
                //     db('user_withdraw')->where('id', $ids)->setField($data);
                //     $this->success();
                // }else {
                $result = db('user_withdraw')->update($data);
                user_business_change($row['user_id'], 'reward_amount', $row['amount'], 'increase', '提现申请被驳回', 13, 0);
                $row['amount'] = (int)$row['amount'];
                send_im_msg_by_system_with_lang($row['user_id'], sprintf('您于%s提交的%s收益提现申请未通过,订单号:%s,原因: %s', $row['create_time'], $row['amount'], $row['withdraw_no'], $params['reject_comment']));
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
                // }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch('rebut');
    }

    /**
     * 统计查询
     */
    public function total()
    {
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        list($where, $sort, $order, $offset, $limit) = $this->buildparams();

        $with = [
            'payment' => function ($query) {
                $query->withField('name');
            },
            'operate' => function ($query) {
                $query->withField('username');
            },
            'finance' => function ($query) {
                $query->withField('username');
            },
        ];

        $withdrawal_finish = $this->model->with($with)->where($where)
            ->where('user_withdraw.status', 2)
            ->sum('payment_amount');
        $fee = $this->model->with($with)->where($where)
            ->where('user_withdraw.status', 2)
            ->sum('user_withdraw.fee');
        $withdrawal_waiting = $this->model->with($with)->where($where)
            ->where('user_withdraw.status', 'in', '0,1')
            ->sum('payment_amount');

        //$divisor = 100;
        $divisor = 1;
        $result = [
            'fee'                => (float)bcdiv($fee, 15, 2), //已经提现手续费合计
            'withdrawal_finish'  => (float)bcdiv($withdrawal_finish, $divisor, 2), //已经提现合计
            'withdrawal_waiting' => (float)bcdiv($withdrawal_waiting, $divisor, 2), //未处理提现合计
        ];

        return json($result);
    }
}
