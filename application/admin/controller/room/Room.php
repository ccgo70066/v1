<?php

namespace app\admin\controller\room;

use app\common\service\ImService;
use app\common\service\RedisService;
use app\common\controller\Backend;
use app\common\model\Room as RoomModel;
use app\common\service\RoomService;
use Exception;
use fast\Random;
use think\Db;
use think\exception\PDOException;
use think\exception\ValidateException;
use think\Log;


/**
 * 房间管理
 *
 * @icon fa fa-circle-o
 */
class Room extends Backend
{

    /**
     * Room模型对象
     * @var \app\admin\model\Room
     */
    protected $model = null;
    protected $searchFields = 'id';
    protected $noNeedRight = ['index', 'delete'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Room;
        $this->view->assign("cateList", $this->model->getCateList());
        $this->view->assign("isLockList", $this->model->getIsLockList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("wayList", $this->model->getWayList());
        $this->view->assign("isCloseList", $this->model->getIsCloseList());
        $this->view->assign("pauseList", $this->model->getPauseList());
        $this->view->assign("isShowList", $this->model->getIsShowList());
        $this->view->assign("isRankList", $this->model->getIsRankList());
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
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();


            $total = $this->model
                ->with(['roomthemecate'])
                ->where('room.status', '<>', 0)
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->with(['roomthemecate'])
                ->where('room.status', '<>', 0)
                ->where($where)
                ->orderRaw('case room.status when 1 then 1 when 3 then 2 when 2 then 3 when 0 then 4 else 5 end')
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
            foreach ($list as $row) {
                $row->visible([
                    'beautiful_id',
                    'id',
                    'name',
                    'theme_id',
                    'owner_id',
                    'cate',
                    'cover',
                    'hot',
                    'password',
                    'bg_img',
                    'music',
                    'is_lock',
                    'status',
                    'is_close',
                    'create_time',
                    'is_show',
                    'show_sort',
                    'audit_admin'
                ]);
                $row->visible(['roomthemecate']);
                $row->getRelation('roomthemecate')->visible(['name', 'type']);
            }
            $list = collection($list)->toArray();
            foreach ($list as $key => $value) {
                $list[$key]['admin_name'] = RedisService::getAdminCache($value['audit_admin'], 'nickname');
            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }

        if (input('?param.ids')) {
            $row = $this->model->get(input('ids'))->toArray();
            return $this->view->fetch('common/detail', ['row' => $row]);
        }
        $this->assignconfig('forbidden_auth', $this->auth->check('room/room/delete'));
        return $this->view->fetch();
    }

    public function create()
    {
        if ($this->request->isPost()) {
            try {
                $params = $this->request->post("row/a");
                $beautiful_id = $params['room_id'];
                if (!$params['name'] || !$params['cover']) {
                    throw new \think\Exception('房间名称和封面不能为空');
                }
                $name = $params['name'];
                $cover = $params['cover'];
                $count = db('room')->where(['status' => ['in', '1,2,3']])->count();
                $roomService = new RoomService();
                db()->startTrans();
                db()->commit();
            } catch (\Exception $e) {
                db()->rollback();
                Log::error($e->getMessage());
                $this->error($e->getMessage());
            }
            $this->success();
        }

        return $this->view->fetch();
    }

    //删除房间
    public function delete()
    {
        $id = input('ids');
        $sel = db('room')->where('id', $id)->find();
        if ($sel == false) {
            $this->error('房间不存在');
        }

        try {
            db()->startTrans();
            //先关闭房间
            $roomService = new RoomService();
            $roomService->closeRoom($id);

            db('room')->where('id', $id)->setField([
                'is_close'     => 1,
                'status'       => 0,
                'beautiful_id' => 0,
                'owner_id'     => 0,
                'name'         => $sel['name'] . Random::alnum(), // 新建房间重名 fix
            ]);
            db('room_admin')->where('room_id', $id)->delete();
            db()->commit();
        } catch (\Throwable $exception) {
            db()->rollback();
            error_log_out($exception);
            Log::error($exception->getMessage());
            $this->error('操作失败');
        }
        $this->success('删除成功');
    }

    public function reject_delete()
    {
        $id = input('ids');
        $sel = db('room')->where('id', $id)->find();
        if ($sel == false) {
            $this->error('房间不存在');
        }

        try {
            db()->startTrans();
            db('room')->where('id', $id)->setField(['status' => 2,]);
            db()->commit();
        } catch (\Throwable $exception) {
            db()->rollback();
            error_log_out($exception);
            Log::error($exception->getMessage());
        }
        $this->success();
    }


    //房主变更记录
    public function master_log()
    {
        $room_id = input('ids');
        $sel = db('room_master_log l')
            ->join('user u1', 'l.user_id = u1.id')
            ->join('user u2', 'l.to_user_id = u2.id')
            ->where('l.room_id', $room_id)
            ->field('u1.nickname,u2.nickname as to_nickname,l.*')
            ->order('id', 'desc')
            ->select();
        $this->assign('row', $sel);
        return $this->view->fetch('master_log');
    }

    public function update_beautiful()
    {
        $room_id = input('ids');
        if ($this->request->isPost()) {
            try {
                $params = $this->request->post("row/a");
                $room_id = $params['id'];
                $new_beautiful_id = $params['new_beautiful_id'];
                $sel = db('room')->where('id', $room_id)->find();
                if ($sel == false) {
                    $this->error('房间不存在');
                }
                db()->startTrans();
                $exist = db('room')->where('beautiful_id', $new_beautiful_id)->find();
                if ($exist) {
                    throw new \think\Exception('靓号已存在');
                }
                if (!preg_match('/^[0-9]{4,5}$/i', $new_beautiful_id)) {
                    throw new \think\Exception('房间靓号为4~5位');
                }

                $update = db('room')->where('id', $room_id)->setField('beautiful_id', $new_beautiful_id);
                if (!$update) {
                    throw new \think\Exception('更新失败');
                }
                db()->commit();
            } catch (Exception $e) {
                db()->rollback();
                $this->error($e->getMessage());
            }
            $this->success();
        }
        $row = db('room')->where('id', $room_id)->find();
        $this->assign('row', $row);
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        $update_before = Db::name('room')->where('id', $ids)->field('id,status,name')->find();
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
                    if ($update_before['status'] == RoomModel::ROOM_STATUS_AUDIT &&
                        in_array($params['status'], [RoomModel::ROOM_STATUS_IDLE, RoomModel::ROOM_STATUS_PLAYING])) {
                        //审核通过
                        $isset = db('room_admin')->where('room_id', $row['id'])->where('role', 1)->find();
                        if (!$isset) {
                            db('room_admin')->insert([
                                'room_id' => $row['id'],
                                'user_id' => $row['owner_id'],
                                'role'    => 1
                            ]);
                        }
                        //首次审核通过
                        $res = Db::name('room')->where('id', $row['id'])->where('audit_admin', 0)->setField(['audit_admin' => session('admin.id'), 'audit_time' => datetime()]);
                        if ($res) {
                            $sysMessage = '您的派对%s已审核通过，快去管理派对吧！';
                        }
                    } else {
                        //状态由休息中或者开播中 变更为休息中(审核通过)时,不做更新
                        if ($update_before['status'] != RoomModel::ROOM_STATUS_AUDIT
                            && $params['status'] != RoomModel::ROOM_STATUS_AUDIT) {
                            unset($params['status']);
                        }
                    }

                    $result = $row->allowField(true)->save($params);

                    if (isset($params['status']) && in_array(
                            $update_before['status'],
                            [RoomModel::ROOM_STATUS_IDLE, RoomModel::ROOM_STATUS_PLAYING]
                        )
                        && $params['status'] == RoomModel::ROOM_STATUS_AUDIT) {
                        $imService = new ImService();
                        $redis = redis();
                        //把房间关了再开
                        $imService->roomSetSwitch($row['id'], false);
                        $rUserAll = $redis->hGetAll(RedisService::USER_NOW_ROOM_KEY);
                        if ($rUserAll && is_array($rUserAll)) {
                            foreach ($rUserAll as $key => $value) {
                                if ($value == $row['id']) {
                                    $redis->hDel(RedisService::USER_NOW_ROOM_KEY, $key);
                                }
                            }
                        }
                        $redis->del(RedisService::ROOM_USER_KEY_PRE . $row['id']);
                        $imService->roomSetSwitch($row['id'], true);
                    }

                    Db::commit();
                    if (isset($params['is_show']) && $params['is_show'] == 0) {
                        $redis = redis();
                        //运营设置为歇业的房间,app内无法操作为营业
                        if ($update_before['status'] == 1 && $params['status'] == 2) {
                        } else {
                            $redis->hset(RedisService::ADMIN_SET_ROOM_NOT_SHOW, $ids, 1);
                        }
                        $im = new ImService();
                        $im->roomSendNotice(
                            $row['id'],
                            [
                                'type'            => 'room_op',
                                'type_op'         => 'room_change_state',
                                'type_op_content' => $params['is_show']
                            ]
                        );
                    }
                    if (isset($params['is_show']) && $params['is_show'] == 1) {
                        $redis = redis();
                        //运营设置为歇业的房间,app内无法操作为营业
                        $redis->hdel(RedisService::ADMIN_SET_ROOM_NOT_SHOW, $ids);
                        $im = new ImService();
                        $im->roomSendNotice(
                            $row['id'],
                            [
                                'type'            => 'room_op',
                                'type_op'         => 'room_change_state',
                                'type_op_content' => $params['is_show']
                            ]
                        );
                    }
                    !empty($sysMessage) && send_im_msg_by_system_with_lang($row['owner_id'], $sysMessage, $row['name']);
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
                    Db::rollback();
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
}
