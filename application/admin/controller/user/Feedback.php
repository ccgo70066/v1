<?php

namespace app\admin\controller\user;

use app\common\service\RedisService;
use app\common\controller\Backend;
use think\Db;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\response\Json;

/**
 * 用户反馈
 *
 * @icon fa fa-circle-o
 */
class Feedback extends Backend
{

    /**
     * UserFeedback模型对象
     * @var \app\admin\model\UserFeedback
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\UserFeedback;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("formList", $this->model->getFormList());
        $this->view->assign("AuditStatusList", $this->model->getAuditStatusList());
        $this->view->assign("NewAuditStatusList", $this->model->getNewAuditStatusList());
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
        //当前是否为关联查询
        $this->relationSearch = false;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }
        //如果发送的来源是 Selectpage，则转发到 Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);
        //缓存中取用户昵称
        foreach ($list->items() as $k => $v) {
            $list->items()[$k]['admin_name'] = RedisService::getAdminCache($v['audit_admin'], 'nickname');
        }
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    /**
     * 编辑
     *
     * @param $ids
     * @return string
     * @throws DbException
     * @throws \think\Exception
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        if ($params['audit_status'] == 2 || $params['audit_status'] == 3) {
            if (empty($params['audit_remark'])) {
                $this->error('审核批注不能为空');
            }
        }
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            $params['audit_admin'] = session('admin.id');
            $result = $row->allowField(true)->save($params);
            if (in_array($params['audit_status'], [2, 3])) {
                if ($row['form'] == 1) {
                    if ($row['type'] == 1) {
                        $info = db('user')->where('id', $row['target_id'])->field('id,nickname')->find();
                        $name = $info['nickname'] . '(' . $info['id'] . ')' . $row['tag'];
                    } else {
                        $info1 = db('room')->where('id', $row['target_id'])->field('id,name')->find();
                        $name = $info1['name'] . '(' . $info1['id'] . ')' . $row['tag'];
                    }
                    send_im_msg_by_system_with_lang($row['user_id'], '您%s提交的关于%s的举报已处理：%s', $row['create_time'], $name, $params['audit_remark']);
                } else {
                    send_im_msg_by_system_with_lang($row['user_id'], '您%s提交的关于%s的反馈已处理：%s', $row['create_time'], $row['tag'], $params['audit_remark']);
                }
            }
            if ($params['audit_status'] == 4) {
                $this->model->where('id', $ids)->delete();
            }

            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }


}
