<?php

namespace app\admin\controller\general;

use app\common\service\RedisService;
use app\common\controller\Backend;
use think\Cache;
use think\Db;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 打款方式管理
 *
 * @icon fa fa-circle-o
 */
class PaymentWay extends Backend
{

    /**
     * PaymentWay模型对象
     * @var \app\admin\model\PaymentWay
     */
    protected $model = null;
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\PaymentWay;
        $this->view->assign("statusList", $this->model->getStatusList());
    }

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
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            $companyList = Db::name('channel_company')->column('name','code');
            $paywayList = Db::name('pay_way')->column('name','code');
            //缓存中取用户昵称
            foreach ($list as &$items) {
                $items['company_name'] = $companyList[$items['company_code']];
                $items['payway_name'] = $paywayList[$items['pay_way_code']];
            }


            $result = array(
                "total"  => $list->total(),
                "rows"   => $list->items(),
                "extend" => [],
            );

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     *
     * @return string
     * @throws \think\Exception
     */
    public function add()
    {
        if (false === $this->request->isPost()) {
            $companyList = Db::name('channel_company')->field('id,name,code')->select();
            $this->view->assign('companyList',$companyList);
            $paywayList = Db::name('pay_way')->field('id,code,name')->select();
            $this->view->assign('paywayList',$paywayList);
            $this->view->assign("row", null);
            return $this->view->fetch('edit');
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
            $params[$this->dataLimitField] = $this->auth->id;
        }
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                $this->model->validateFailException()->validate($validate);
            }
            $exist = $this->model->where([
                'name'  => $params['name'],
            ])->count();
            if ($exist) {
                $this->error('名称不能重复');
            }
            $result = $this->model->allowField(true)->save($params);
            Cache:: clear('small_data_filter');
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        }
        $this->success();
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
        $companyList = Db::name('channel_company')->field('id,name,code')->select();
        $this->view->assign('companyList',$companyList);
        $paywayList = Db::name('pay_way')->field('id,code,name')->select();
        $this->view->assign('paywayList',$paywayList);
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
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            if($params['name'] != $row['name']){
                $exist1 = $this->model->where(['name'  => $params['name'],])->count();
                if ($exist1) {
                    $this->error('名称不能重复');
                }
            }
            $result = $row->allowField(true)->save($params);
            Cache:: clear('small_data_filter');
            \app\admin\model\Shield:: get_illegal_word();
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
