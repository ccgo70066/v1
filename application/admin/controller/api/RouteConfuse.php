<?php

namespace app\admin\controller\api;

use app\common\controller\Backend;
use fast\Random;

/**
 * 路由混淆
 *
 * @icon fa fa-circle-o
 */
class RouteConfuse extends Backend
{

    /**
     * RouteConfuse模型对象
     * @var \app\admin\model\api\RouteConfuse
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\api\RouteConfuse;
    }



    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将 application/admin/library/traits/Backend.php 中对应的方法复制到当前控制器,然后进行修改
     * @link  \app\admin\library\traits\Backend
     */


    /**
     * 批量添加
     *
     * @return string
     * @throws \think\Exception
     */
    public function add_batch()
    {
        if (false === $this->request->isPost()) {
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
            $params[$this->dataLimitField] = $this->auth->id;
        }
        $fields = db('api_route')->field('name,memo')->select();
        $exist = db('api_route_confuse')->where('package_name', $params['package_name'])->column('name');
        $rs = [];
        foreach ($fields as $field) {
            if (!in_array($field['name'], $exist)) {
                $field['package_name'] = $params['package_name'];
                $field['encrypt'] = $this->getEncrypt();
                $rs[] = $field;
            }
        }
        $this->model->insertAll($rs, true);
        $this->success();
    }

    /**
     * @return string
     */
    private function getEncrypt(): string
    {
        do {
            $string = strtolower(Random::alpha()) . '/' . strtolower(Random::alpha());
        } while ($this->model->where('encrypt', $string)->count());

        return $string;
    }

}
