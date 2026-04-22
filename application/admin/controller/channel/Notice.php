<?php

namespace app\admin\controller\channel;

use app\common\controller\Backend;

/**
 * 公告管理
 *
 * @icon fa fa-circle-o
 */
class Notice extends Backend
{

    /**
     * ChannelNotice模型对象
     * @var \app\admin\model\ChannelNotice
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\ChannelNotice;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("actionList", $this->model->getActionList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("showTypeList", $this->model->getShowTypeList());
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将 application/admin/library/traits/Backend.php 中对应的方法复制到当前控制器,然后进行修改
     * @link  \app\admin\library\traits\Backend
     */


}
