<?php

namespace app\admin\controller\prop;

use app\common\controller\Backend;

/**
 * 贵族特权管理
 *
 * @icon fa fa-circle-o
 */
class NoblePrivilege extends Backend
{

    /**
     * NoblePrivilege模型对象
     * @var \app\admin\model\NoblePrivilege
     */
    protected $model = null;
    protected $noNeedRight = ['privilegeList'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\NoblePrivilege;
        $this->view->assign("hasSwitchList", $this->model->getHasSwitchList());
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    public function privilegeList()
    {
        //主键值
        $primaryvalue = $this->request->request("keyValue");
        if($primaryvalue){
            $where = ['id'=> ['in',$primaryvalue]];
        }else{
            $where = [];
        }
        $privilege = db('noble_privilege')->where($where)->field('id,name')->order('weigh asc')->select();

        return json(['list' => $privilege, 'total' => count($privilege)]);
    }



}
