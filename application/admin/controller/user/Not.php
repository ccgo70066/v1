<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;

/**
 * 会员7天未登录管理
 *
 * @icon fa fa-user
 */
class Not extends Backend
{

    protected $relationSearch = true;
    protected $searchFields = 'id,username,nickname';


    /**
     * @var \app\admin\model\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\User;
    }

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
                'business'  => function ($query) {
                    $query->withField('recharge_amount');
                },
            ];
            $list = $this->model
                ->with($with)
                ->where($where)
                ->where('logintime', '<', time() - 7 * 24 * 3600)
                ->order($sort, $order)
                ->paginate($limit);
            foreach ($list as $k => $v) {
                $v->hidden(['password', 'salt']);
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }





}
