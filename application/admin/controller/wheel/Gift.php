<?php

namespace app\admin\controller\wheel;

use app\common\controller\Backend;
use think\Cache;
use think\Db;
use Exception;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 奖池礼物
 *
 * @icon fa fa-circle-o
 */
class Gift extends Backend
{

    /**
     * WheelGift模型对象
     * @var \app\admin\model\WheelGift
     */
    protected $model = null;
    protected $searchFields = 'id';
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\WheelGift;
        $this->view->assign("boxTypeList", $this->model->getBoxTypeList());
        $this->view->assign("broadcastList", $this->model->getBroadcastList());
        $this->view->assign("roomNoticeList", $this->model->getRoomNoticeList());
        $this->view->assign("lightLevelList", $this->model->getLightLevelList());
        $this->view->assign("voiceList", $this->model->getVoiceList());
        $this->view->assign("showAgainList", $this->model->getShowAgainList());
        $this->view->assign("statusList", $this->model->getStatusList());
    }



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
            $with = 'gift';
            $total = $this->model
                ->with($with)
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with($with)
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list = collection($list)->toArray();
            $result = array("total" => $total, "rows" => $list);

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

                    $result = $this->model->allowField(true)->save($params);
                    if ($params['status'] == 1) {
                        $this->update_config($params['box_type'], $params['gift_id'], 'add');
                    }
                    Db::commit();
                    Cache::clear('small_data_wheel');
                }catch (ValidateException $e){
                    Db::rollback();
                    $this->error($e->getMessage());
                }catch (PDOException $e){
                    Db::rollback();
                    if ($e->getCode() == '10501') {
                        $this->error('礼物已经存在,请不要重复添加');
                    }
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
                    $result = $row->allowField(true)->save($params);
                    if ($params['status'] == 1 && $row['status'] != 1) {
                        $this->update_config($row['box_type'], $row['gift_id'], 'add');
                    }
                    if ($params['status'] == 0 && $row['status'] != 0) {
                        $this->update_config($row['box_type'], $row['gift_id'], 'remove');
                    }
                    if ($params['gift_id'] != $row['gift_id']) {
                        $this->update_config_gift($row['box_type'], $row['gift_id'], $params['gift_id']);
                    }
                    Db::commit();
                    Cache::clear('small_data_wheel');
                }catch (ValidateException $e){
                    Db::rollback();
                    $this->error($e->getMessage());
                }catch (PDOException $e){
                    Db::rollback();
                    if ($e->getCode() == '10501') {
                        $this->error('礼物已经存在,请不要重复添加');
                    }
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

    private $tables = [
        'wheel_config_back',
        'wheel_config_base',
        'wheel_config_def',
        'wheel_config_per',
        'wheel_config_pub',
        'wheel_config_pubn',
        'wheel_config_single',
        'wheel_config_sys',
    ];

    private function update_config($box_type, $gift_id, $option = 'add')
    {
        $gift = db('gift')->field('id,name,image,price')->find($gift_id);
        $tables = $this->tables;

        foreach ($tables as $table) {
            $list = db($table)->where(['box_type' => $box_type])->select();
            foreach ($list as $item) {
                $json_data = json_decode($item['config'], true);
                if ($option == 'add') {
                    $gift['weight'] = 0;
                    $json_data[] = $gift;
                }
                if ($option == 'remove') {
                    foreach ($json_data as $kk => $json_datum) {
                        if ($json_datum['id'] == $gift_id) {
                            unset($json_data[$kk]);
                            break;
                        }
                    }
                }
                array_multisort(array_column($json_data, 'price'), SORT_ASC, $json_data);
                db($table)->where('id', $item['id'])->setField([
                    'config' => json_encode($json_data, JSON_UNESCAPED_UNICODE)
                ]);
            }
        }
    }


    private function update_config_gift($box_type, $gift_id, $new_gift_id)
    {
        $new_gift = db('gift')->field('id,name,image,price')->find($new_gift_id);
        $tables = $this->tables;

        foreach ($tables as $table) {
            $list = db($table)->where(['box_type' => $box_type])->select();
            foreach ($list as $item) {
                $json_data = json_decode($item['config'], true);
                foreach ($json_data as $kk => &$json_datum) {
                    if ($json_datum['id'] == $gift_id) {
                        $json_datum['id'] = $new_gift_id;
                        $json_datum['name'] = $new_gift['name'];
                        $json_datum['image'] = $new_gift['image'];
                        $json_datum['price'] = $new_gift['price'];
                        break;
                    }
                }
                array_multisort(array_column($json_data, 'price'), SORT_ASC, $json_data);
                db($table)->where('id', $item['id'])->setField([
                    'config' => json_encode($json_data, JSON_UNESCAPED_UNICODE)
                ]);
            }
        }
    }

    public function refresh_config()
    {
        for ($i = 1; $i <= 2; $i++) {
            $box_type = $i;
            $box_gift = db('wheel_gift eg')
                ->join('gift g', 'eg.gift_id=g.id', 'left')
                ->where(['eg.box_type' => $box_type,])
                ->order('price asc')
                ->field('g.id,g.name,g.image,g.price,"0" as weight')
                ->select();

            $tables = $this->tables;

            foreach ($tables as $table) {
                $list = db($table)->where(['box_type' => $box_type])->select();
                foreach ($list as $item) {
                    $json_data = json_decode($item['config'], true);
                    $result = $box_gift;
                    foreach ($json_data as $kk => $json_datum) {
                        isset($result[$kk]) && $result[$kk]['weight'] = $json_datum['weight'] ?: 0;
                    }
                    db($table)->where('id', $item['id'])->setField([
                        'config' => json_encode($result, JSON_UNESCAPED_UNICODE)
                    ]);
                }
            }
        }

        $this->success();
    }


    public function get_gift()
    {
        $where = input('custom/a');
        $where_name = input('name') ? ['g.name' => ['like', '%' . input('name') . '%']] : [];
        $where_key = input('keyValue') ? ['g.id' => ['in', input('keyValue')]] : [];
        $list = db('wheel_gift eg')
            ->join('gift g', 'eg.gift_id=g.id', 'left')
            ->where($where)
            ->where($where_name)
            ->where($where_key)
            ->field('g.id,concat(g.name,"[",g.price,"]") as name')
            ->select();
        return json(['list' => $list, 'total' => count($list)]);

    }

}

