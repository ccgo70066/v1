<?php

namespace app\admin\controller\user;

use app\admin\library\GiftService;
use app\common\service\RedisService;
use app\common\controller\Backend;

/**
 * 红包记录管理
 *
 * @icon fa fa-circle-o
 */
class RedPacketLog extends Backend
{

    /**
     * RedPacketLog模型对象
     * @var \app\admin\model\RedPacketLog
     */
    protected $noNeedLogin = ['total'];
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\RedPacketLog;
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
            $total = $this->model
                ->where($where)
//                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();

            if (count($list)) {
                foreach ($list as $key => $value) {
                    //$list[$key]['amount'] = $value['amount'] / 100;

                    $list[$key]['user']['nickname'] = RedisService::getUserCache($value['user_id'], 'nickname');
                    $list[$key]['touser']['nickname'] = RedisService::getUserCache($value['to_user_id'], 'nickname');
                }
            }

//            $amount_sum = $this->model
//                ->with(['user', 'touser'])
//                ->where($where)
//                ->where('red_packet_log.status',2)
//                ->order($sort, $order)
//                ->sum('amount');
//            $service_charge_sum = $this->model
//                ->with(['user', 'touser'])
//                ->where($where)
//                ->where('red_packet_log.status',2)
//                ->order($sort, $order)
//                ->sum('service_charge');

            $result = array(
                "total"  => $total,
                "rows"   => $list,
                "extend" => [
//                    'amount_sum'       => $amount_sum / 100,                              //红包发送金额累计
//                    'service_charge_sum'  => $service_charge_sum /100,                     //红包手续费累计
                ],
            );

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 统计查询
     */
    public function total()
    {
        $this->relationSearch = false;
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        list($where, $sort, $order, $offset, $limit) = $this->buildparams();

        $amount_sum = $this->model
            ->where($where)
            ->where('status', 2)
            ->order($sort, $order)
            ->sum('amount');
        $service_charge_sum = $this->model
            ->where($where)
            ->where('status', 2)
            ->order($sort, $order)
            ->sum('service_charge');

        $result = [
            'amount_sum'         => $amount_sum,                              //红包发送金额累计
            'service_charge_sum' => $service_charge_sum,                     //红包手续费累计
        ];
        return json($result);
    }


}
