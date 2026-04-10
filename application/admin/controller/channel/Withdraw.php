<?php

namespace app\admin\controller\channel;

use app\common\controller\Backend;
use app\pay\library\AliPay;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

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
    protected $noNeedRight = ['detail'];
    protected $relationSearch = true;

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
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $with = [
                'user'    => function ($query) {
                    $query->withField('nickname');
                },
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

            $list = $this->model
                ->with($with)
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list->items() as $k => $v) {
                $list->items()[$k]['account_name'] = json_decode($v['account_data'], true)['account_name'];
                $list->items()[$k]['payment_amount'] = bcdiv($v['payment_amount'], 100, 2);
            }
            $withdrawal_finish = $this->model->with($with)->where($where)
                ->where('user_withdraw.status', 2)
                ->sum('payment_amount');
            $fee = $this->model->with($with)->where($where)
                ->where('user_withdraw.status', 2)
                ->sum('user_withdraw.fee');
            $withdrawal_waiting = $this->model->with($with)->where($where)
                ->where('user_withdraw.status', 'in', '0,1')
                ->sum('payment_amount');

            $result = array(
                "total"  => $list->total(),
                "rows"   => $list->items(),
                "extend" => [
                    'fee'                => (float)bcdiv($fee, 100, 2), //已经提现手续费合计
                    'withdrawal_finish'  => (float)bcdiv($withdrawal_finish, 100, 2), //已经提现合计
                    'withdrawal_waiting' => (float)bcdiv($withdrawal_waiting, 100, 2), //未处理提现合计
                ],
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
     * 添加
     *
     * @return string
     * @throws \think\Exception
     */
    public function add()
    {
        if (false === $this->request->isPost()) {
            $this->view->assign("row", null);
            return $this->view->fetch('add');
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        $user = db('user')->where('id', $params['user_id'])->find();
        if (empty($user)) {
            $this->error('该用户不存在');
        }
        $account = db('user_account')->where('user_id', $params['user_id'])->find();
        if ($account) {
            $account_id = $account['id'];
        }else {
            $account_id = 0;
        }
        $amount = round($params['amount'], 2);
        $withdraw_config = get_site_config('withdraw_fee') ?: "0";;
        $params['fee'] = bcmul($amount, $withdraw_config, 2);
        $params['less_amount'] = 0;
        $params['payment_amount'] = bcdiv(bcsub($amount, $params['fee'], 2), 10, 2);
        $params['status'] = 0;
        $params['withdraw_no'] = date('YmdHis', strtotime($params['create_time'])) . random_int(1000, 9999);
        $params['s_flag'] = 1;
        $params['account_id'] = $account_id;
        $params['account_data'] = json_encode($account);
        $params['amount'] = $amount;

        if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
            $params[$this->dataLimitField] = $this->auth->id;
        }
        $result = false;
        Db::startTrans();
        try{
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                $this->model->validateFailException()->validate($validate);
            }
            $result = $this->model->allowField(true)->save($params);
            Db::commit();
        }catch (ValidateException|PDOException|Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        }
        $this->success();
    }


}
