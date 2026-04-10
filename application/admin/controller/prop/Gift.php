<?php

namespace app\admin\controller\prop;

use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\common\service\RedisService;
use app\common\controller\Backend;
use think\Db;
use think\Exception;
use think\exception\PDOException;
use think\exception\ValidateException;

/**
 * 礼物管理
 *
 * @icon fa fa-gift
 */
class Gift extends Backend
{

    /**
     * Gift模型对象
     * @var \app\admin\model\Gift
     */
    protected $model = null;
    protected $noNeedRight = ['search_list', 'push', 'select_type'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Gift;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("priceTypeList", $this->model->getPriceTypeList());
        $this->view->assign("screenShowList", $this->model->getScreenShowList());
        $this->view->assign("noticeList", $this->model->getNoticeList());
        $this->view->assign("statusList", $this->model->getStatusList());
    }


    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    public function push($ids)
    {
        $gift = db('gift')->where('id', 'in', $ids)->column('animate');
        board_notice(Message::CMD_GIFT_PUSH, $gift);
        $this->success('推送成功');
    }

    public function search_list()
    {
        return db('gift')->order('price desc')->field('id,concat(name,"(",price,")") as name')->select();
    }

    public function select_type()
    {
        $typeId = $this->request->get('type');
        if ($typeId) {
            $cates = $this->model->getCateList();
            $data = $cates[$typeId];
        } else {
            $data = $this->model->getTypeList();
        }
        $list = [];
        if (count($data)) {
            foreach ($data as $key => $value) {
                $list[$key]['value'] = $key;
                $list[$key]['name'] = $value;
            }
        }
        $this->success('', null, array_values($list));
    }

    /**
     * 添加
     */
    public function add()
    {
        if (request()->isPost()) {
            $params = $this->request->post('row/a');
            if (empty($params)) {
                $this->error(__('Parameter %s can not be empty', ''));
            }
            $params = $this->preExcludeFields($params);

            if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                $params[$this->dataLimitField] = $this->auth->id;
            }
            try {
                Db::startTrans();
                if ($params['cate'] <> \app\common\model\Gift::GIFT_CATE_PRIVILEGE) {
                    $params['noble_limit'] = 0;
                }
                $giftId = db('gift')->insertGetId($params);

                //添加礼物缓存
                $giftHash = [
                    'id'    => $giftId,
                    'name'  => $params['name'],
                    'image' => $params['image'],
                    'price' => $params['price'],
                ];
                redis()->hMSet(RedisService::GIFT_BASE_LISTS_KEY . $giftId, $giftHash);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success();
        }
        return $this->view->fetch('edit');
    }

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
            $cates = $this->model->getCateList();
            $cateList = $cates[$row['type']];
            $this->view->assign('cateList', $cateList);
            return $this->view->fetch();
        }
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if (empty($params)) {
                $this->error(__('Parameter %s can not be empty', ''));
            }
            $params = $this->preExcludeFields($params);
            !isset($params['cate']) && $params['cate'] = null;

            $result = false;
            Db::startTrans();
            try {
                //是否采用模型验证
                if ($this->modelValidate) {
                    $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                    $row->validateFailException()->validate($validate);
                }
                if ($params['image'] <> $row['image'] || $params['name'] <> $row['name'] || $params['price'] <> $row['price']) {
                    $giftHash = [
                        'name'  => $params['name'],
                        'image' => $params['image'],
                        'price' => $params['price'],
                    ];
                    redis()->hMSet(RedisService::GIFT_BASE_LISTS_KEY . $row['id'], $giftHash);
                }
                redis()->del(RedisService::GIFT_BASE_LISTS_KEY . $row['id']);

                $result = $row->allowField(true)->save($params);
                Db::commit();
            } catch (ValidateException|PDOException|Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if (false === $result) {
                $this->error(__('No rows were updated'));
            }
            $this->success();
//
//
            return parent::edit($ids);
        }
    }

    /**
     * 删除
     */
    public function del($ids = "")
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
        try {
            foreach ($list as $item) {
                redis()->del(RedisService::GIFT_BASE_LISTS_KEY . $item['id']);
                $count += $item->delete();
            }
            Db::commit();
        } catch (PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($count) {
            $this->success();
        }
        $this->error(__('No rows were deleted'));
    }
}
