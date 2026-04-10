<?php

namespace app\admin\controller\moment;

use app\common\service\RedisService;
use app\common\controller\Backend;

/**
 * 动态管理
 *
 * @icon fa fa-circle-o
 */
class Moment extends Backend
{

    /**
     * Moment模型对象
     * @var \app\admin\model\Moment
     */
    protected $model = null;
    protected $noNeedLogin = ['batch_audit'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Moment;
        $this->view->assign("publishList", $this->model->getPublishList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("blockStatusList", $this->model->getBlockStatusList());
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
            if (input('ids')) {
                $row = $this->model->get(input('ids'));
                if ($row['audit_admin']) {
                    $this->error('该条记录已经审核,请刷新');
                }

                if (input('option') == 'reply') {
                    $row->rebut_flag = 1;
                    $row->audit_admin = $_SESSION['think']['admin']['id'];
                    $row->auditor_time = date('Y-m-d H:i:s');
                    $row->status = -1;
                    $row->save();
                    send_im_msg_by_system_with_lang($row['user_id'], '您提交的动态已被驳回!');
                    $this->success();
                }
                if (input('option') == 'ok') {
                    if (!$row) {
                        $this->error('请刷新网页重试');
                    }
                    $row->status = 1;
                    $row->audit_admin = $_SESSION['think']['admin']['id'];
                    $row->auditor_time = date('Y-m-d H:i:s');
                    $row->save();
                    send_im_msg_by_system_with_lang($row['user_id'], '您提交的动态已通过!');
                    $this->success();
                }
            }

            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $with = [
                'business' => function ($query) {
                    $query->withField('role');
                }
            ];
            $total = $this->model
                ->with($with)
                ->where($where)
                ->count();

            $list = $this->model
                ->with($with)
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();


            //缓存中取用户昵称
            if (count($list)) {
                $list = collection($list)->toArray();
                foreach ($list as $key => $value) {
                    $list[$key]['user']['nickname'] = RedisService::getUserCache($value['user_id'], 'nickname');
                    $list[$key]['admin']['nickname'] = RedisService::getAdminCache($value['audit_admin'], 'nickname');
                }
            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }

        if (input('?param.ids')) {
            $row = $this->model->get(input('ids'))->toArray();
            return $this->view->fetch('common/detail', ['row' => $row]);
        }
        return $this->view->fetch();
    }

    public function batch_audit()
    {
        $ids = input('ids/a');
        if ($ids && input('option') == 'ok') {
            foreach ($ids as $id) {
                $row = $this->model->get($id);
                if ($row['audit_admin']) {
                    continue;
                };
                $row->status = 1;
                $row->audit_admin = $_SESSION['think']['admin']['id'];
                $row->auditor_time = date('Y-m-d H:i:s');
                $row->save();
                send_im_msg_by_system_with_lang($row['user_id'], "您提交的动态已通过!");
            }
            $this->success();
        }

        if ($ids && input('option') == 'reply') {
            foreach ($ids as $id) {
                $row = $this->model->get($id);
                if ($row['audit_admin']) {
                    continue;
                };
                $row->rebut_flag = 1;
                $row->audit_admin = $_SESSION['think']['admin']['id'];
                $row->auditor_time = date('Y-m-d H:i:s');
                $row->status = -1;
                $row->save();
                send_im_msg_by_system_with_lang($row['user_id'], '您提交的动态已被驳回!');
                $this->success();
            }
            $this->success();
        }
    }


}
