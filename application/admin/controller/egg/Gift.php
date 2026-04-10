<?php

namespace app\admin\controller\egg;

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
     * EggGift模型对象
     * @var \app\admin\model\EggGift
     */
    protected $model = null;
    protected $noNeedRight = ['*'];
    protected $modelValidate = true;
    protected $searchFields = 'id';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\EggGift;
        $this->view->assign("boxTypeList", $this->model->getBoxTypeList());
        $this->view->assign("broadcastList", $this->model->getBroadcastList());
        $this->view->assign("roomNoticeList", $this->model->getRoomNoticeList());
        $this->view->assign("lightLevelList", $this->model->getLightLevelList());
        $this->view->assign("voiceList", $this->model->getVoiceList());
        $this->view->assign("showAgainList", $this->model->getShowAgainList());
        $this->view->assign("lastStatusList", $this->model->getLastStatusList());
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
                try {
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
                    Cache::clear('small_data_egg');
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    if ($e->getCode() == '10501') {
                        $this->error('礼物已经存在,请不要重复添加');
                    }
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
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
                try {
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
                    !($params['last_status'] ?? 0) && $params['last_time'] = null;
                    if ($params['gift_id'] != $row['gift_id']) {
                        $this->update_config_gift($row['box_type'], $row['gift_id'], $params['gift_id']);
                    }
                    Db::commit();
                    Cache::clear('small_data_egg');

                    $key = 'egg:gift:list_1';
                    Cache::tag('small_data_egg', $key);
                    $box_type = $params['box_type'];
                    $gift = db('egg_gift e')
                        ->join('gift g', 'e.gift_id=g.id', 'left')
                        ->where(['e.box_type' => $box_type, 'e.status' => 1])
                        ->order('g.price desc')
                        ->column('e.*,g.name,g.image,g.price,"0" as count', 'gift_id');
                    foreach ($gift as &$item) {
                        unset($item['id'], $item['box_type'], $item['weigh'], $item['status']);
                    }
                    $data = $gift;
                    Cache::set($key, $data);
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
                    if ($e->getCode() == '10501') {
                        $this->error('礼物已经存在,请不要重复添加');
                    }
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
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
            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                }
                Db::commit();
                Cache::clear('small_data_egg');
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($count) {
                $this->success();
            } else {
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
        try {
            $list = $this->model->onlyTrashed()->select();
            foreach ($list as $k => $v) {
                $count += $v->delete(true);
            }
            Db::commit();
            Cache::clear('small_data_egg');
        } catch (PDOException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($count) {
            $this->success();
        } else {
            $this->error(__('No rows were deleted'));
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    private $tables = [
        'egg_config_back',
        'egg_config_base',
        'egg_config_def',
        'egg_config_per',
        'egg_config_pub',
        'egg_config_single',
        'egg_config_sys',
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
            $box_gift = db('egg_gift eg')
                ->join('gift g', 'eg.gift_id=g.id', 'left')
                ->where(['eg.box_type' => $box_type,])
                ->column('g.id,g.name,g.image,g.price', 'g.id');

            $tables = $this->tables;

            foreach ($tables as $table) {
                $list = db($table)->where(['box_type' => $box_type])->select();
                foreach ($list as $item) {
                    $json_data = json_decode($item['config'], true);
                    foreach ($json_data as $kk => &$json_datum) {
                        $json_datum = array_merge((array)$json_datum, (array)$box_gift[$json_datum['id']]);
                    }
                    array_multisort(array_column($json_data, 'price'), SORT_ASC, $json_data);
                    db($table)->where('id', $item['id'])->setField([
                        'config' => json_encode($json_data, JSON_UNESCAPED_UNICODE)
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
        $list = db('egg_gift eg')
            ->join('gift g', 'eg.gift_id=g.id', 'left')
            ->where($where)
            ->where($where_name)
            ->where($where_key)
            ->field('g.id,concat(g.name,"[",g.price,"]") as name')
            ->select();
        return json(['list' => $list, 'total' => count($list)]);
    }

}

