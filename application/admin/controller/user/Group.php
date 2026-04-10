<?php

namespace app\admin\controller\user;

use app\common\controller\Backend;

/**
 * 会员组管理
 *
 * @icon fa fa-users
 */
class Group extends Backend
{

    /**
     * @var \app\admin\model\UserGroup
     */
    protected $model = null;
    protected $noNeedRight = ['searchList'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('UserGroup');
        $this->view->assign("statusList", $this->model->getStatusList());
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        //$nodeList = \app\admin\model\UserRule::getTreeList();
        $this->assign("nodeList", null);
        return parent::add();
    }

    public function edit($ids = null)
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        //$rules = explode(',', $row['rules']);
        //$nodeList = \app\admin\model\UserRule::getTreeList($rules);
        $this->assign("nodeList", null);
        return parent::edit($ids);
    }

    public function searchList()
    {
        $key = input('key') ?? 'id';
        $name = input('name') ?? 'name';
        return json($this->model->where('status', 'normal')->column($name, $key));
    }

}
