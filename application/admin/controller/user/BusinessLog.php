<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;

/**
 * 用户业务记录管理
 *
 * @icon fa fa-circle-o
 */
class BusinessLog extends Backend
{

    /**
     * UserBusinessLog模型对象
     * @var \app\admin\model\UserBusinessLog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\UserBusinessLog;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("fromList", $this->model->getFromList());
        $this->view->assign("cateList", $this->model->getCateList());
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
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            $with = ['user'=>function($query) {    $query->withField('nickname');}];

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $filter = json_decode($this->request->get('filter'),true);

            $total = $this->model
                ->with($filter ? $with : [])
                ->where($where)
                ->count();

            $list = $this->model
                ->with($with)
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();

            $inc_sum_amount = $this->model
                ->with($with)
                ->where($where)
                ->where('cate = 1')
                ->sum('amount');
            $inc_sum_origin_amount = $this->model
                ->with($with)
                ->where($where)
                ->where('cate = 1')
                ->sum('origin_amount');

            $inc_sum = bcsub($inc_sum_amount,$inc_sum_origin_amount,2);

            $dec_sum_amount = $this->model
                ->with($with)
                ->where($where)
                ->where('cate = 0')
                ->sum('amount');
            $dec_sum_origin_amount = $this->model
                ->with($with)
                ->where($where)
                ->where('cate = 0')
                ->sum('origin_amount');
            $dec_sum =  bcsub($dec_sum_origin_amount,$dec_sum_amount,2);

            $result = [
                "total"  => $total,
                "rows"   => $list,
                'extend' => [
                    'ins_sum' => $inc_sum,
                    'dec_sum' => $dec_sum
                ]
            ];

            return json($result);
        }

        if (input('?param.ids') && !input('?get.ids')) {
            $row = $this->model->get(input('ids'))->toArray();
            return $this->view->fetch('common/detail', ['row' => $row]);
        }
        return $this->view->fetch();
    }


}
