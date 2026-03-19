<?php

namespace app\admin\controller\apilog;

use addons\apilog\model\Apilog;
use app\common\controller\Backend;
use think\Cache;

class Index extends Backend
{


    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new Apilog;
        $this->view->assign("methodList", $this->model->getMethodList());
    }

    public function index()
    {
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $k => $v) {
                $v['banip'] = Cache::has('banip:' . $v['ip']);
            }
            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }


    public function detail($ids)
    {
        $row = $this->model->get(['id' => $ids]);
        if (!$row)
            $this->error(__('No Results were found'));
        $this->view->assign("row", $row->toArray());
        return $this->view->fetch();
    }


    public function banip($status, $ip, $time = 0)
    {
        if ($status == 0) {
            Cache::set('banip:' . $ip, 1, $time * 60);
        } else {
            Cache::rm('banip:' . $ip);
        }
        $this->success('succ', null, Cache::has('banip:' . $ip));
    }
}
