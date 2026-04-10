<?php

namespace app\admin\controller\moment;

use app\common\service\RedisService;
use app\common\controller\Backend;

/**
 * 动态评论管理
 *
 * @icon fa fa-circle-o
 */
class Comment extends Backend
{

    /**
     * MomentComment模型对象
     * @var \app\admin\model\MomentComment
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\MomentComment;
        $this->view->assign("typeList", $this->model->getTypeList());
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
        $this->request->filter(['strip_tags']);
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

            //缓存中取用户昵称
            if (count($list)) {
                foreach ($list as &$item) {
                    $item['user']['nickname'] = RedisService::getUserCache($item['user_id'], 'nickname');
                    $item['touser']['nickname'] = RedisService::getUserCache($item['to_user_id'], 'nickname');
                }
            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }


}
