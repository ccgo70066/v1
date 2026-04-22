<?php

namespace app\admin\controller\egg;

use app\common\controller\Backend;
use app\common\service\EggService;
use Exception;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use think\Cache;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 系统调控
 *
 * @icon fa fa-circle-o
 */
class ConfigSys extends Backend
{
    // php think crud -t egg_config_sys -m eggConfigSys -c egg/configSys -u 1 -f 1
    /**
     * EggConfigSys模型对象
     * @var \app\admin\model\EggConfigSys
     */
    protected $model = null;
    protected $modelValidate = true;
    protected $searchFields = 'id';
    protected $noNeedRight = ['*'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\EggConfigSys;
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
            $level = db('egg_level')->column('name', 'id');
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
            $key = 'pool:egg:sys_' . input('box_type');

            $gift = [
                'clear_type' => 1,
                'box_type'   => input('box_type'),
                'count_type' => input('count_type'),
            ];
            $gift_ids = db('egg_clear_gift')->where($gift)->value('gift_ids');
            $clear_gift = db('gift')->where('id', 'in', $gift_ids)->column('concat(name, "[", price, "]") as name');

            $result = array(
                "total"  => $total,
                "rows"   => $list,
                'extend' => [
                    'pool'       => cache($key) / 100,
                    'clear_gift' => implode(',', $clear_gift),
                ]
            );
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
                    EggService::weigh_title_unique($params['title'], 'sys', $ids ?? 0);
                    isset($params['range_start']) && $params['range_start'] == '' && $params['range_start'] = null;
                    isset($params['range_end']) && $params['range_end'] == '' && $params['range_end'] = null;
                    $list = db('egg_gift b')
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
                    Cache::clear('small_data_egg');
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
                    EggService::weigh_title_unique($params['title'], 'sys', $ids ?? 0);
                    isset($params['range_start']) && $params['range_start'] == '' && $params['range_start'] = null;
                    isset($params['range_end']) && $params['range_end'] == '' && $params['range_end'] = null;
                    $params['title'] = trim($params['title']);
                    if (trim($params['json_str'])) {
                        $params['json_str'] = preg_replace("/\s(?=\s)/","\\1",trim($params['json_str']));
                        $params['json_str'] = str_replace(["\r","\n","\t"]," ",$params['json_str']);
                        $params['json_str'] = preg_replace("/\s(?=\s)/","\\1",trim($params['json_str']));
                        $params['json_str'] = explode(' ',$params['json_str']);
                        foreach ($params['json_str'] as $k => $v) {
                            if ($k % 2 == '0') {
                                $key = $v;
                                $arr[$key] = '';
                            } else {
                                if (strpos($v, '%')) {
                                    $v = str_replace('%', '', $v);
                                }
                                $arr[$key] = $v;
                            }
                        }
                        $params['enter_json'] = json_encode($arr);
                    }

                    // $enter_json = json_decode($params['enter_json'], true);
                    // array_multisort(array_column($enter_json, 'start'), SORT_ASC, $enter_json);
                    // $params['enter_json'] = json_encode($enter_json, JSON_UNESCAPED_UNICODE);
                    $result = $row->allowField(true)->save($params);
                    Db::commit();
                    Cache::clear('small_data_egg');
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
                Cache::clear('small_data_egg');
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
            Cache::clear('small_data_egg');
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
    public function set($type, $box_type)
    {
        if ($type == 1) {
            $key = 'pool:egg:sys_' . $box_type;
            if ($this->request->isPost()) {
                Cache::inc($key, input('row.diff') * 100);
                $this->success('');
            }
            return $this->view->fetch('', [
                'value' => (1000000 * 100 - cache($key)) / 100,
            ]);
        }
        if ($type == 2) {
            $gift = [
                'clear_type' => 1,
                'box_type'   => input('box_type'),
                'count_type' => input('count_type'),
            ];
            if ($this->request->isPost()) {
                $gift['gift_ids'] = input('gift_ids');
                db('egg_clear_gift')->insert($gift, true);
                $this->success();
            }
            return $this->view->fetch('set1', ['gift_ids' => db('egg_clear_gift')->where($gift)->value('gift_ids')]);
        }
    }



}
