<?php

namespace app\admin\controller\shop;

use app\common\service\RedisService;
use app\common\controller\Backend;
use think\exception\DbException;
use think\response\Json;

/**
 * 商场订单
 *
 * @icon fa fa-circle-o
 */
class Order extends Backend
{

    /**
     * ShredShopLog模型对象
     * @var \app\admin\model\ShopOrder
     */
    protected $model = null;
    protected $relationSearch = true;
    protected $noNeedRight = ['total'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\ShopOrder;
        $this->view->assign("priceTypeList", $this->model->getPriceTypeList());
        $this->view->assign("payWayList", $this->model->getPayWayList());
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
        //如果发送的来源是 Selectpage，则转发到 Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        $with = [
//            'user' => function ($query) {
//                $query->withField('nickname');
//            },
            'shop' => function ($query) {
                $query->withField('name,type');
            },
        ];
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model
            ->with($with)
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);

        //缓存中取用户昵称
        $items = $list->items();
        foreach ($items as $k => $v) {
            $items[$k]['user']['nickname'] = RedisService::getUserCache($v['user_id'], 'nickname');
        }

        $result = [
            'total'  => $list->total(),
            'rows'   => $items,
            "extend" => [],
        ];
        return json($result);
    }

    /**
     * 统计查询
     */
    public function total()
    {
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        $total = $this->request->get('total_count') ?: 0;
        list($where, $sort, $order, $offset, $limit) = $this->buildparams();

        //已支付订单总数
        $pay_count = $this->model->where(['status' => 1])->count();
        //钻石支付成功金额总计
        $money_pay_sum = $this->model->where(['pay_way' => 1, 'status' => 1,])->sum('amount');

        $result = [
            'pay_count'     => $pay_count, //付款单数
            'money_pay_sum' => $money_pay_sum,  //付款人数
        ];

        return json($result);
    }


}
