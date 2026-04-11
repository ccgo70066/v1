<?php

namespace app\admin\controller\room;

use app\common\controller\Backend;
use app\common\service\RoomService;
use Exception;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 派对成员
 *
 * @icon fa fa-circle-o
 */
class Admin extends Backend
{

    /**
     * Admin模型对象
     * @var \app\admin\model\room\Admin
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\room\Admin;
        $this->view->assign("roleList", $this->model->getRoleList());
        $this->view->assign("statusList", $this->model->getStatusList());
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将 application/admin/library/traits/Backend.php 中对应的方法复制到当前控制器,然后进行修改
     * @link  \app\admin\library\traits\Backend
     */


    /** 加入审核 */
    public function check_join($ids = null)
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
        $params = input();
        $result = false;
        Db::startTrans();
        try {
            (new RoomService())->member_check($row['room_id'], $row['user_id'], $params['agree'] == 1 ? 1 : -1);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        $this->success();
    }

    /** 退出审核 */
    public function check_leave($ids = null)
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
        $params = input();
        Db::startTrans();
        try {
            (new RoomService())->member_check($row['room_id'], $row['user_id'], $params['agree'] == 1 ? -2 : 1);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        $this->success();
    }


}
