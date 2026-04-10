<?php

namespace app\admin\controller\wheel;

use app\common\controller\Backend;
use think\Cache;
use think\Db;
use Exception;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 初始调控
 *
 * @icon fa fa-circle-o
 */
class ConfigPub extends Backend
{
    //php think crud -t wheel_config_pub -m wheelConfigPub -c wheel/configPub -u 1 -f 1
    /**
     * WheelConfigSys模型对象
     * @var \app\admin\model\WheelConfigSys
     */
    protected $model = null;
    protected $modelValidate = true;
    protected $searchFields = 'id';
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\WheelConfigPub();
        $this->view->assign("boxTypeList", $this->model->getBoxTypeList());
        $this->view->assign("countTypeList", $this->model->getCountTypeList());
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
            $level = db('wheel_level')->column('name', 'id');
            foreach ($list as &$item) {
                $item['level_name'] = $level[$item['level_id']];
                if ($item['config']) {
                    $configs = json_decode($item['config'], true);
                    if (!$configs) {
                        continue;
                    }
                    $arr = array_column($configs, 'weight');
                    $item['weigh_config'] = implode(',', $arr);
                    $item['all'] = 0;
                    foreach ($configs as $config) {
                        $item['all'] += $config['price'] * $config['weight'];
                    }
                    $base = array_sum($arr);
                    $base > 0 && $item['all'] = sprintf('%.2f', $item['all'] / $base);
                }
            }
            $key = 'pool:wheel:pub_' . input('box_type');
            $result = array("total" => $total, "rows" => $list, 'extend' => ['pool' => cache($key) / 100,]);
            return json($result);
        }

        if (input('?param.ids') && !input('?get.ids')) {
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
                    $params['range_start'] == '' && $params['range_start'] = null;
                    $params['range_end'] == '' && $params['range_end'] = null;
                    $list = db('wheel_gift b')
                        ->field('g.id,g.image,g.name,g.price,"0" as weight')
                        ->join('gift g', 'g.id=b.gift_id', 'left')
                        ->where('b.box_type', $params['box_type'])
                        ->where('b.status', 1)
                        ->order('g.price asc')->select();
                    $list[0]['weight'] = 1;
                    $params['config'] = json_encode($list, JSON_UNESCAPED_UNICODE);
                    $params['title'] = trim($params['title']);

                    $result = $this->model->allowField(true)->save($params);
                    Db::commit();
                    Cache::clear('small_data_wheel');
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
                    $params['range_start'] == '' && $params['range_start'] = null;
                    $params['range_end'] == '' && $params['range_end'] = null;
                    $params['title'] = trim($params['title']);
                    $result = $row->allowField(true)->save($params);

                    Db::commit();
                    Cache::clear('small_data_wheel');
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
        $data = json_decode($row['config'], true);
        foreach ($data as &$datum) {
            $datum['image_url'] = cdnurl($datum['image']);
            $datum['weight'] = $datum['weight'] ?? 0;
        }
        $row['config'] = json_encode($data);
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
                Cache::clear('small_data_wheel');
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
            Cache::clear('small_data_wheel');
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
     * 调整
     * @param $box_type
     * @return string
     * @throws \think\Exception
     */
    public function set($box_type)
    {
        $key = 'pool:wheel:pub_' . $box_type;
        if ($this->request->isPost()) {
            Cache::inc($key, input('row.diff') * 100);
            $this->success('');
        }
        return $this->view->fetch('', [
            'value' => (1000000 * 100 - cache($key)) / 100,
        ]);
    }
}
