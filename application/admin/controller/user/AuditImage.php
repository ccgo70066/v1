<?php

namespace app\admin\controller\user;

use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\common\service\ImService;
use app\common\service\RedisService;
use app\common\controller\Backend;
use think\Db;
use think\exception\PDOException;
use think\Log;
use util\Minio;

/**
 * 图片审核
 *
 * @icon fa fa-circle-o
 */
class AuditImage extends Backend
{

    /**
     * UserAuditImage模型对象
     * @var \app\admin\model\UserAuditImage
     */
    protected $model = null;
    protected $relationSearch = true;
    protected $noNeedLogin = ['batch_audit'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\UserAuditImage;
        $this->view->assign("imgTypeList", $this->model->getImgTypeList());
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
                ->with([
                    'business' => function ($query) {
                        $query->withField('role');
                    }
                ])
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            //缓存中取用户昵称
            foreach ($list->items() as $k => $v) {
                $list->items()[$k]['user_nickname'] = RedisService::getUserCache($v['user_id'], 'nickname');
                $list->items()[$k]['admin_nickname'] = RedisService::getAdminCache($v['auditor'], 'nickname');
            }

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 审核
     */
    public function audit($ids)
    {
        if ($this->request->isAjax()) {
            $option = input('option');

            $row = db('user_audit_image')->where('id', $ids)->find();
            !$row && $this->error('记录找不到');
            $row['status'] != 0 && $this->error('该条记录已经审核,请刷新');

            if ($option == 'ok') {
                $user_field = ['avatar', 'image'];
                $update = ['auditor' => session('admin.id'), 'auditor_time' => date('Y-m-d H:i:s'),];
                if (in_array($row['img_type'], $user_field)) {
                    $user_old_info = db('user')->where('id', $row['user_id'])->field('avatar')->find();
                    if ($row['img_type'] == 'avatar') {
                        db('user')->where('id', $row['user_id'])->setField([$row['img_type'] => $row['url']]);
                    } else {
                        //形象照多条处理
                        $images = db('user_audit_image')
                            ->where('user_id', $row['user_id'])
                            ->where('status', 1)
                            ->column('url');
                        if (count($images)) {
                            array_push($images, $row['url']);
                            $imagesStr = implode(',', $images);
                            $updateImage = trim($imagesStr, ',');
                        } else {
                            $updateImage = $row['url'];
                        }
                        db('user')->where('id', $row['user_id'])->setField([$row['img_type'] => $updateImage]);
                    }

                    $update['status'] = 1;
                    $update['url_origin'] = '';
                    db('user_audit_image')->where('id', $ids)->setField($update);

                    if ($row['img_type'] == 'avatar') {
                        $im = new ImService();
                        $res = $im->updateUser($row['user_id'], '', $row['url']);
                        if ($res['code'] != 200) {
                            Log::error('图片审核调用云信更新用户:' . json_encode($res));
                        }
                        $third = parse_url($user_old_info['avatar']);
                        if (!(strpos($user_old_info['avatar'], '/assets/avatar') === 0 ||
                            (isset($third['scheme']) && isset($third['host'])))) {
                            $minio = new Minio();
                            $minio->deleteObject($user_old_info['avatar']);
                        }
                    }
                    $info = $row['img_type'] == 'avatar' ? '您上传的头像已审核通过' : '您上传的个人主页封面已审核通过';
                    send_im_msg_by_system_with_lang($row['user_id'], $info);
                    board_notice(Message::CMD_REFRESH_USER, ['user_id' => $row['user_id']]);
                    \app\common\model\UserBusiness::clear_cache($row['user_id']);
                    $this->success();
                }
            }

            if ($option == 'reply') {
                (new Minio())->deleteObject($row['url']);
                if ($row['url_origin']) {
                    $update = ['auditor' => session('admin.id'), 'auditor_time' => date('Y-m-d H:i:s'), 'status' => 1, 'url_origin' => '', 'url' => $row['url_origin']];
                } else {
                    $update = ['auditor' => session('admin.id'), 'auditor_time' => date('Y-m-d H:i:s'), 'status' => 2,];
                }
                db('user_audit_image')->where('id', $ids)->setField($update);
                $info = $row['img_type'] == 'avatar' ? '您上传的头像未通过审核' : '您上传的个人主页封面未通过审核';
                send_im_msg_by_system_with_lang($row['user_id'], $info);
                $this->success();
            }
        }
    }


    public function batch_audit()
    {
        try {
            $this->operate_check('auditImage_batch_audit' . session('admin.id'), 3);
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
        $ids = input('ids/a');
        if ($ids && input('option') == 'ok') {
            foreach ($ids as $id) {
                $row = $this->model->get($id);
                if (!$row || $row['auditor']) {
                    continue;
                };
                $user_field = ['avatar', 'image'];
                $update = ['auditor' => session('admin.id'), 'auditor_time' => date('Y-m-d H:i:s'),];
                if (in_array($row['img_type'], $user_field)) {
                    $user_old_info = db('user')->where('id', $row['user_id'])->field('avatar')->find();
                    if ($row['img_type'] == 'avatar') {
                        db('user')->where('id', $row['user_id'])->setField([$row['img_type'] => $row['url']]);
                    } else {
                        //形象照多条处理
                        $images = db('user_audit_image')
                            ->where('user_id', $row['user_id'])
                            ->where('status', 1)
                            ->column('url');
                        if (count($images)) {
                            array_push($images, $row['url']);
                            $imagesStr = implode(',', $images);
                            $updateImage = trim($imagesStr, ',');
                        } else {
                            $updateImage = $row['url'];
                        }
                        db('user')->where('id', $row['user_id'])->setField([$row['img_type'] => $updateImage]);
                    }
                    $update['status'] = 1;
                    db('user_audit_image')->where('id', $id)->setField($update);

                    if ($row['img_type'] == 'avatar') {
                        $im = new ImService();
                        $res = $im->updateUser($row['user_id'], '', $row['url']);
                        if ($res['code'] != 200) {
                            Log::error('图片审核调用云信更新用户:' . json_encode($res));
                        }
                        $third = parse_url($user_old_info['avatar']);
                        if (!(strpos($user_old_info['avatar'], '/assets/avatar') === 0 ||
                            (isset($third['scheme']) && isset($third['host'])))) {
                            // $minio = new Minio();
                            // $minio->deleteObject($user_old_info['avatar']);
                        }
                    }
                    $info = $row['img_type'] == 'avatar' ? '您上传的头像已审核通过' : '您上传的个人主页封面已审核通过';
                    send_im_msg_by_system_with_lang($row['user_id'], $info);
                    board_notice(Message::CMD_REFRESH_USER, ['user_id' => $row['user_id']]);
                    \app\common\model\UserBusiness::clear_cache($row['user_id']);
                }
            }
            $this->success();
        }

        if ($ids && input('option') == 'reply') {
            foreach ($ids as $id) {
                $row = $this->model->get($id);
                if ($row['auditor']) {
                    continue;
                };
                $update = ['auditor' => session('admin.id'), 'auditor_time' => date('Y-m-d H:i:s'), 'status' => 2];
                db('user_audit_image')->where('id', $id)->setField($update);
                $info = $row['img_type'] == 'avatar' ? '您上传的头像未通过审核' : '您上传的个人主页封面未通过审核';
                send_im_msg_by_system_with_lang($row['user_id'], $info);
            }
            $this->success();
        }
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
            $data = Db::name('user_audit_image')->where('id', $ids)->find();
            if (!$data) {
                $this->error('该记录状态已变更,请刷新');
            }
            $user_id = $data['user_id'];
            $count = 0;
            Db::startTrans();
            try {
                $count = Db::name('user_audit_image')->where('id', $ids)->delete();
                if (!(strpos($data['url'], '/assets/avatar') === 0)) {
                    $minio = new Minio();
                    $minio->deleteObject($data['url']);
                }
                //头像的话需要单独处理
                if (Db::name('user')->where('id', $user_id)->where('avatar', $data['url'])->find()) {
                    $avatar = config('app.default_avatar_gender');
                    $update['avatar'] = $avatar[array_rand($avatar)]['avatar'];
                    $im = new ImService();
                    $im->updateUser($user_id, '', $update['avatar']);
                    db('user')->where('id', $user_id)->update($update);
                    send_im_msg_by_system_with_lang($user_id, '您的头像涉嫌违规,已重置处理');
                }
                Db::commit();
                board_notice(Message::CMD_REFRESH_USER, ['user_id' => $user_id]);
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (\Exception $e) {
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


}
