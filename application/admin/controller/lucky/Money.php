<?php

namespace app\admin\controller\lucky;

use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\common\controller\Backend;
use app\common\exception\ApiException;
use think\Db;
use Exception;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 红包雨管理
 *
 * @icon fa fa-circle-o
 */
class Money extends Backend
{

    /**
     * LuckyMoney模型对象
     * @var \app\admin\model\LuckyMoney
     */
    protected $model = null;
    protected $searchFields = 'id';
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\LuckyMoney;
        $this->view->assign("statusList", $this->model->getStatusList());
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
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            if (input('option') == 'switch') {
                $status = $this->model->where('id', input('ids'))->value('status');
                $this->model->where('id', input('ids'))->setField('status', $status == -1 ? 1 : -1);
                $this->success();
            }
            //如果发送的来源是Selectpage，则转发到Selectpage
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

            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }

        if (input('?param.ids')) {
            $row = $this->model->get(input('ids'))->toArray();
            return $this->view->fetch('common/detail', ['row' => $row]);
        }
        return $this->view->fetch();
    }


    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);

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
                        $this->model->validateFailException(true)->validate($validate);
                    }
                    if (!$params['open_time']) {
                        $params['open_time'] = null;
                    }
                    if ($params['open_time'] && $params['open_time'] < datetime(strtotime('+60 seconds'))) {
                        throw new ApiException('此红包雨开始时间必须大于当前时间一分钟以上');
                    }
                    if ((int)$params['min_amount'] < 1 ||
                        (int)$params['max_amount'] < 1 ||
                        (int)$params['max_amount'] < (int)$params['min_amount'] ||
                        $params['amount'] < (int)$params['max_amount']) {
                        throw new ApiException('参数有误');
                    }
                    $params['remain_amount'] = $params['amount'];
                    $result = $this->model->allowField(true)->save($params);
                    Db::commit();
                }catch (ValidateException $e){
                    Db::rollback();
                    $this->error($e->getMessage());
                }catch (PDOException $e){
                    Db::rollback();
                    $this->error($e->getMessage());
                }catch (Exception $e){
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                }else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", null);
        return $this->view->fetch('edit');
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();
                try{
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }
                    $data = Db::name('lucky_money')->where('id', $ids)->find();
                    if ($data['status'] == 1 || $data['status'] == 2) {
                        throw new ApiException('此红包雨已开始或结束,禁止更新');
                    }
                    if (!$params['open_time']) {
                        $params['open_time'] = null;
                    }
                    if ($params['open_time'] && $params['open_time'] < datetime(strtotime('+60 seconds'))) {
                        throw new ApiException('此红包雨开始时间必须大于当前时间一分钟以上');
                    }
                    if ((int)$params['min_amount'] < 1 ||
                        (int)$params['max_amount'] < 1 ||
                        (int)$params['max_amount'] < (int)$params['min_amount'] ||
                        (int)$params['amount'] < (int)$params['max_amount']) {
                        throw new ApiException('参数有误');
                    }
                    $params['remain_amount'] = $params['amount'];
                    $result = $row->allowField(true)->save($params);
                    Db::commit();
                }catch (ValidateException $e){
                    Db::rollback();
                    $this->error($e->getMessage());
                }catch (PDOException $e){
                    Db::rollback();
                    $this->error($e->getMessage());
                }catch (Exception $e){
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                }else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * 删除
     */
    public function del($ids = "")
    {
        if ($ids) {
            $pk = $this->model->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->model->where($pk, 'in', $ids)->select();

            $count = 0;
            Db::startTrans();
            try{
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                }
                Db::commit();
            }catch (PDOException $e){
                Db::rollback();
                $this->error($e->getMessage());
            }catch (Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($count) {
                $this->success();
            }else {
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    /**
     * 真实删除
     */
    public function destroy($ids = "")
    {
        $pk = $this->model->getPk();
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $this->model->where($this->dataLimitField, 'in', $adminIds);
        }
        if ($ids) {
            $this->model->where($pk, 'in', $ids);
        }
        $count = 0;
        Db::startTrans();
        try{
            $list = $this->model->onlyTrashed()->select();
            foreach ($list as $k => $v) {
                $count += $v->delete(true);
            }
            Db::commit();
        }catch (PDOException $e){
            Db::rollback();
            $this->error($e->getMessage());
        }catch (Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($count) {
            $this->success();
        }else {
            $this->error(__('No rows were deleted'));
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    /**
     * 手动发放
     */
    public function send()
    {
        $id = input('ids');
        $data = Db::name('lucky_money')
            ->where('status', 0)
            ->where('id',$id)
            ->field('open_time,id')
            ->find();
        if (!$data) {
            $this->error('记录状态有误,请刷新重试');
        }
        $update = Db::name('lucky_money')->where('id', $id)->where('status',0)->update(['status'=> 1,'open_time'=>datetime(),'open_type'=>2]);
        if ($update) {
            board_notice(Message::CMD_LUCKY_MONEY_RAIN_START, $data, '红包雨开始');
        }else{
            $this->error('记录状态有误,请刷新重试');
        }
        $this->success('发送成功');
    }
}
