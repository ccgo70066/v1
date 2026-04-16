<?php

namespace app\api\controller;


use app\common\exception\ApiException;
use app\common\model\UserBusiness as UserBusinessModel;
use app\common\service\MallService;
use app\common\service\UserService;
use think\Db;
use think\Log;
use util\Minio;


/**
 * 会员接口
 * @ApiWeigh    (9998)
 */
class UserInfo extends Base
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = '*';

    /**
     * @ApiTitle (私聊-获取用户简介)
     * @ApiMethod   (get)
     * @ApiParams   (name="user_id", type="int", required=false, rule="", description="用户id--当前登录用户不用传")
     */
    public function get_base_info()
    {
        try {
            $userId = input('user_id') ?: $this->auth->id;
            $data = UserService::getUserBaseInfo($userId, $this->auth->id);
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
        $this->success('ok', $data);
    }

    /**
     * @ApiTitle (派对-获取用户简介)
     * @ApiMethod   (get)
     * @ApiParams   (name="user_id", type="int", required=false, rule="", description="用户id--当前登录用户不用传")
     */
    public function get_roomuser_info()
    {
        try {
            $userId = input('user_id') ?: $this->auth->id;
            $data = UserService::getUserBaseInfo($userId, $this->auth->id);
            $data['wall'] = UserService::getWallInfo($userId);
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
        $this->success('ok', $data);
    }

    /**
     * @ApiTitle (获取用户-头像、形象照审核状态)
     * @ApiSector (会员接口)
     * @ApiMethod   (get)
     * @ApiReturnParams   (name="avatar", type="array", description="头像")
     * @ApiReturnParams   (name="image", type="array", description="形象照")
     * @ApiReturnParams   (name="url", type="string", description="图片地址")
     * @ApiReturnParams   (name="status", type="string", description="审核状态：1通过、0审核中")
     */
    public function get_audit_image()
    {
        try {
            $userId = $this->auth->id;
            $images = Db::name('user_audit_image')->field('id,img_type,url,status')->where('user_id', $userId)->whereIn('status', [0, 1])->select();
            $data['avatar'] = null;
            $data['image'] = [];
            if (count($images)) {
                foreach ($images as $image) {
                    if ($image['img_type'] == 'avatar') {
                        $data['avatar'] = ['id' => $image['id'], 'url' => $image['url'], 'status' => $image['status'],];
                    } elseif ($image['img_type'] == 'image') {
                        $data['image'][] = ['id' => $image['id'], 'url' => $image['url'], 'status' => $image['status'],];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error($e->getMessage());
        }
        $this->success('ok', $data);
    }

    /**
     * @ApiTitle (删除形象照)
     * @ApiMethod   (post)
     * @ApiParams   (name="id", type="string", required=true, rule="", description="图片ID")
     */
    public function del_image()
    {
        $images = Db::name('user_audit_image')
            ->field('id,img_type,url,status')
            ->where('user_id', $this->auth->id)
            ->where('id', input('id'))
            ->where('img_type', 'image')
            ->find();
        if (!$images) {
            $this->error(__('No results were found'));
        }
        Db::name('user_audit_image')->where('id', input('id'))->delete();
        $this->success('ok');
    }


    /**
     * @ApiTitle    (添加需要审核的形象照)
     * @ApiMethod   (post)
     * @ApiParams   (name="url", type="string", required=true, rule="", description="图片URL")
     */
    public function add_image()
    {
        $user_id = $this->auth->id;
        $imageStr = input('url');

        $where = ['user_id' => $user_id, 'img_type' => 'image'];
        $images1 = db('user_audit_image')->where($where)->where('status', 1)->count();
        $images0 = db('user_audit_image')->where($where)->where('status', 0)->count();
        if ($images1 < 9 && ($images1 + $images0) >= 9) {
            $this->error(__('You can only upload a maximum of %s files', 9));
        }

        Db::startTrans();
        try {
            $deleteImages = [];
            $where = ['user_id' => $user_id, 'img_type' => 'image', 'status' => 1];
            if (get_site_config('user_image_audit') == 1) {
                if ($images1 >= 9) {
                    //删除待审核图片
                    $deleteImages = db('user_audit_image')->where($where)->order('update_time asc')->limit(1)->column('url');
                    db('user_audit_image')->where($where)->order('update_time asc')->limit(1)->delete();
                }
                $insertData[] = [
                    'url'         => $imageStr,
                    'status'      => 0,
                    'user_id'     => $user_id,
                    'img_type'    => 'image',
                    'create_time' => datetime()
                ];
                db('user_audit_image')->insertAll($insertData);
            } else {
                if ($images1 >= 9) {
                    //删除待审核图片
                    $deleteImages = db('user_audit_image')->where($where)->order('update_time asc')->limit(1)->column('url');
                    db('user_audit_image')->where($where)->order('update_time asc')->limit(1)->delete();
                }

                $insertData[] = [
                    'url'         => $imageStr,
                    'status'      => 1,
                    'user_id'     => $user_id,
                    'img_type'    => 'image',
                    'create_time' => datetime()
                ];
                db('user_audit_image')->insertAll($insertData);

                $images = db('user_audit_image')->where('user_id', $user_id)->where('status', 1)->column('url');
                if (count($images)) {
                    $imagesStr = implode(',', $images);
                    $updateImage = trim($imagesStr, ',');
                } else {
                    $updateImage = $imageStr;
                }
                db('user')->where('id', $user_id)->update(['image' => $updateImage, 'create_time' => datetime()]);
            }

            if ($deleteImages) {
                $minio = new Minio();
                foreach ($deleteImages as $deleteImage) {
                    $minio->deleteObject($deleteImage);
                }
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            $this->error($e->getMessage());
        }
        $this->success();
    }


    /**
     * @ApiTitle (更新形象照)
     * @ApiMethod   (post)
     * @ApiParams   (name="id", type="string", required=true, description="需替换的图片ID")
     * @ApiParams   (name="new_image", type="string", required=true, description="新的形象照url")
     */
    public function update_image()
    {
        $image = Db::name('user_audit_image')
            ->field('id,img_type,url,status')
            ->where('user_id', $this->auth->id)
            ->where('id', input('id'))
            ->where('img_type', 'image')
            ->find();
        if (!$image) {
            $this->error(__('No results were found'));
        }
        $update['status'] = 1;
        $update['url'] = input('new_image');
        $update['auditor'] = 0;
        $update['auditor_time'] = null;
        if (get_site_config('user_image_audit') == 1) {
            $update['status'] = 0;
            $update['url_origin'] = $image['url'];
            $update['create_time'] = datetime();
        }
        Db::name('user_audit_image')->where('id', $image['id'])->update($update);
        $this->success('ok');
    }


}
