<?php

namespace app\admin\controller\channel;

use app\common\controller\Backend;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\response\Json;
use think\Validate;

/**
 * 渠道管理
 *
 * @icon fa fa-circle-o
 */
class Channel extends Backend
{

    /**
     * Channel模型对象
     * @var \app\admin\model\Channel
     */
    protected $model = null;
    protected $noNeedRight = ['get_appid_by_name', 'searchList', 'channelList'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Channel;
        $this->view->assign("platformList", $this->model->getPlatformList());
        $this->view->assign("statusList", $this->model->getStatusList());
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
        $payway = db('channel_payway')->column('id,pay_name');

        $list = $this->model
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);

        foreach ($list as &$item) {
            $i = explode(',', $item['payway']);
            $item['payway_name'] = '';
            foreach ($i as $v) {
                $item['payway_name'] .= ($payway[$v] ?? '') . ',';
            }
        }

        $result = [
            'total' => $list->total(),
            'rows'  => $list->items()
        ];
        return json($result);
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
            $this->view->assign("row", null);
            $this->view->assign("companyList",
                Db::name('channel_company')->order('weigh desc')
                    ->where('status', 1)->field('id,name')->select());
            $this->view->assign('paywayList', []);
            return $this->view->fetch('edit');
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        if ($params['payway'] && is_array($params['payway'])) {
            $params['payway'] = implode(',', $params['payway']);
        }

        if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
            $params[$this->dataLimitField] = $this->auth->id;
        }
        $result = false;
        Db::startTrans();
        try{
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                $this->model->validateFailException()->validate($validate);
            }
            $validate = new Validate([
                'appid' => 'require|regex:\w{1,30}|unique:channel',
            ]);
            if (!$validate->check($params)) {
                $this->error($validate->getError());
            }
            $result = $this->model->allowField(true)->save($params);
            Db::commit();
        }catch (ValidateException|PDOException|Exception $e){
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
            //根据支付渠道的支付路径找到关联支付公司
            $company_ids = Db::name('channel_payway')
                ->where('id', 'in', $row['payway'])->group('company_id')->column('company_id');
            $company_ids = implode(',', $company_ids) ?: -1; //-1为防空报错
            $companyList = Db::name('channel_company')
                ->where('status', 1)->orderRaw('is_checked desc')->fieldRaw('id,name,if(id in (' . $company_ids . '),1,0) as `is_checked`')->select();
            //根据支付公司找到其全部的支付方式
            $this->view->assign("companyList", $companyList);
            $selected_payway = $row['payway'] ?: -1;
            $payway_by_company = Db::name('channel_payway')
                ->where('company_id', 'in', $company_ids)
                ->orderRaw('id in (' . $selected_payway . ') desc')
                ->order('weigh desc')
                ->field('id,pay_name,company_id,0 as `is_checked`')->select();
            foreach ($payway_by_company as &$value) {
                if (in_array($value['id'], explode(',', $row['payway']))) {
                    $value['is_checked'] = 1;
                }
            }

            foreach ($payway_by_company as &$value) {
                if (in_array($value['id'], explode(',', $row['payway']))) {
                    $value['is_checked'] = 1;
                }
            }
            $this->view->assign('paywayList', $payway_by_company);

            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        if (isset($params['payway']) && $params['payway'] && is_array($params['payway'])) {
            $params['payway'] = implode(',', $params['payway']);
        }
        $result = false;
        Db::startTrans();
        try{
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $row->validateFailException()->validate($validate);
            }
            $validate = new Validate([
                'appid' => 'require|regex:\w{1,30}|unique:channel,appid,' . $row->id,
            ]);
            if (!$validate->check($params)) {
                $this->error($validate->getError());
            }
            $result = $row->allowField(true)->save($params);
            Db::commit();
        }catch (ValidateException|PDOException|Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        if (false === $result) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }

    /**
     * 删除
     *
     * @param $ids
     * @return void
     * @throws DbException
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     */
    public function del($ids = null)
    {
        if (false === $this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ?: $this->request->post("ids");
        if (empty($ids)) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }
        $pk = $this->model->getPk();
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $this->model->where($this->dataLimitField, 'in', $adminIds);
        }
        $list = $this->model->where($pk, 'in', $ids)->select();

        $count = 0;
        Db::startTrans();
        try{
            foreach ($list as $item) {
                $count += $item->delete();
            }
            Db::commit();
        }catch (PDOException|Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($count) {
            $this->success();
        }
        $this->error(__('No rows were deleted'));
    }

    /**
     * 真实删除
     *
     * @param $ids
     * @return void
     */
    public function destroy($ids = null)
    {
        if (false === $this->request->isPost()) {
            $this->error(__("Invalid parameters"));
        }
        $ids = $ids ?: $this->request->post('ids');
        if (empty($ids)) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }
        $pk = $this->model->getPk();
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $this->model->where($this->dataLimitField, 'in', $adminIds);
        }
        $this->model->where($pk, 'in', $ids);
        $count = 0;
        Db::startTrans();
        try{
            $list = $this->model->onlyTrashed()->select();
            foreach ($list as $item) {
                $count += $item->delete(true);
            }
            Db::commit();
        }catch (PDOException|Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($count) {
            $this->success();
        }
        $this->error(__('No rows were deleted'));
    }


    public function searchList()
    {
        $key = input('key') ?? 'appid';
        $name = input('name') ?? 'name';
        return json($this->model->where('status', 1)->column($name, $key));
    }


    //AJAX请求
    public function get_appid_by_name()
    {
        $name = input('name');
        $res = db('channel')->where('name', $name)->value('appid');
        return $res;
    }


    public function channelList()
    {
        //主键值
        $primaryvalue = $this->request->request("keyValue");
        if ($primaryvalue) {
            $where = ['id' => $primaryvalue];
        }else {
            $where = [];
        }
        $privilege = db('channel')->where('status', 1)->where($where)->field('id,name')->order('weigh asc')->select();

        return json(['list' => $privilege, 'total' => count($privilege)]);
    }


}
