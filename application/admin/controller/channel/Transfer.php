<?php

namespace app\admin\controller\channel;

use app\common\controller\Backend;
use app\pay\library\AliPay;
use think\Db;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\response\Json;

/**
 * 渠道自助打款
 *
 * @icon fa fa-circle-o
 */
class Transfer extends Backend
{

    /**
     * ChannelTransfer模型对象
     * @var \app\admin\model\ChannelTransfer
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\ChannelTransfer;
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    /**
     * 查看
     *
     * @return string|Json
     * @throws \think\Exception
     * @throws DbException
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }
        if (input('option') == 'load_way') {
            $list = db('payment_way')->where(['name' => ['not like', '%线下%']])->field('id,name')->select();
            return ['list' => $list];
        }
        if (input('option') == 'search_list') {
            $key = input('key') ?? 'id';
            $name = input('name') ?? 'name';
            return json($this->model->column($name, $key));
        }
        //如果发送的来源是 Selectpage，则转发到 Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        return json([]);
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);
        $way = db('payment_way')->column('id,name');
        $admin = db('admin')->column('id,nickname');
        $items = $list->items();
        foreach ($items as &$item) {
            $item['way_text'] = $way[$item['way']];
            $item['operator_name'] = $admin[$item['operator']];
        }

        $result = ['total' => $list->total(), 'rows' => $items];
        return json($result);
    }

    /**
     * 打款
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);

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
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    $params['order_no'] = date('YmdHis') . substr(microtime(true), -4);
                    $params['operator'] = session('admin.id');
                    $result = $this->model->allowField(true)->save($params);
                    $arr = [
                        '2' => ['2021003145625200', 'alipay_org1'],//支付宝代付
                        '3' => ['2021003155600424', 'alipay_org2'],//克度支付宝代付
                        '4' => ['2021003159636063', 'alipay_org3'],//武汉凡冠科技有限公司
                        '5' => ['2021003157649226', 'alipay_org4'],//武汉申诺科技有限公司
                        '6' => ['2021003174658333', 'alipay_org5'],//临沂鸿世文化传媒有限公司
                        '7' => ['2021003174677611', 'alipay_org6'],//武汉艺眠科技有限公司
                        '8' => ['2021003174679584', 'alipay_org7'],//武汉晚悦科技有限公司
                    ];
                    if (in_array($params['way'], array_keys($arr))) {
                        $pay = new AliPay($arr[$params['way']][0], $arr[$params['way']][1]);
                        [$status, $msg] = $pay->payout($params['order_no'], $params['account'], $params['name'],
                            $params['amount'], '分帐');
                        if ($status == 0) {
                            throw new Exception($msg);
                        }
                    }
                    Db::commit();
                }catch (ValidateException $e){
                    Db::rollback();
                    $this->error($e->getMessage());
                }catch (PDOException $e){
                    Db::rollback();
                    $this->error($e->getMessage());
                }catch (Exception $e){
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                }else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", null);
        return $this->view->fetch('edit');
    }
}
