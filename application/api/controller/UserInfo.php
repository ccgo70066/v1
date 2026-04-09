<?php

namespace app\api\controller;


use app\api\library\MallService;
use app\api\library\UserService;
use app\common\library\ApiException;
use app\common\model\UserBusiness as UserBusinessModel;
use think\Db;
use think\Log;


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
    public function getBaseInfo()
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
    public function getRoomUserInfo()
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
     * @ApiTitle (用户领取升级奖励)
     */
    public function vip_reward()
    {
        try {
            Db::startTrans();
            $userId = $this->auth->id;
            $level = db('user_business')
                ->where('id', $userId)
                ->value('level');
            $levels = db('user_level_reward')
                ->where('user_id', $userId)
                ->column('user_level');
            $where = [];
            count($levels) && $where['grade'] = ['not in', $levels];

            $data = db('level')->where('reward_json', '<>', '')
                ->where('reward_json', '<>', '[]')
                ->where('grade', '<=', $level)
                ->where($where)
                ->field('grade,reward_json,icon')
                ->order('grade', 'asc')
                ->select();

            if (!$data) {
                throw new ApiException(__('No rewards available'));
            }

            //获取所有：聊天气泡数组、坐骑数组、头像框数组
            $bubbleArr = MallService::getBubbleArr();
            $carArr = MallService::getCarArr();
            $adornmentArr = MallService::getAdornmentArr();
            foreach ($data as &$value) {
                $reward_info = [];
                $level_reward[] = [
                    'user_id'    => $userId,
                    'user_level' => $value['grade'],
                ];
                $reward_items = json_decode($value['reward_json'], true);
                foreach ($reward_items as $item) {
                    switch ($item['type']) {
                        case 'amount':
                            $reward_info[] = [
                                'icon'  => 'assets/icon/diamond.png',
                                'name'  => '金幣',
                                'count' => $item['count'],
                            ];
                            break;
                        case 'bubble':
                            $bubble_info = $bubbleArr[$item['id']];
                            $reward_info[] = [
                                'icon'  => $bubble_info['image'],
                                'name'  => $bubble_info['name'],
                                'count' => $item['count'],
                            ];
                            break;
                        case 'car':
                            $car_info = $carArr[$item['id']];
                            $reward_info[] = [
                                'icon'  => $car_info['image'],
                                'name'  => $car_info['name'],
                                'count' => $item['count'],
                            ];
                            break;
                        case 'adornment':
                            $adornment_info = $adornmentArr[$item['id']];
                            $reward_info[] = [
                                'icon'  => $adornment_info['image'],
                                'name'  => $adornment_info['name'],
                                'count' => $item['count'],
                            ];
                            break;
                    }
                }

                UserBusinessModel::reward_give($reward_items, $userId, '升级奖励');
                other_log_add($userId, 6, $reward_info);
                $value['reward'] = $reward_info;
                unset($value['reward_json']);
            }
            array_values($data);
            db('user_level_reward')->insertAll($level_reward);
            Db::commit();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error(show_error_notify($e));
        }
        $this->success(__('Operation completed'), $data ?: []);
    }


    /**
     * @ApiTitle (用户领取升级奖励,待删除)
     */
    public function receiveVipReward()
    {
        try {
            $userId = $this->auth->id;
            $level = db('user_business')
                ->where('id', $userId)
                ->value('level');
            $reward_info = $this->vipRewardSend($this->auth->id, $level);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error(show_error_notify($e));
        }
        $this->success(__('Operation completed'), $reward_info ?: []);
    }

    //升级领取奖励
    private function vipRewardSend($userId, $level)
    {
        try {
            Db::startTrans();

            $levels = db('user_level_reward')
                ->where('user_id', $userId)
                ->column('user_level');
            $where = [];
            count($levels) && $where['grade'] = ['not in', $levels];

            $data = db('level')->where('reward_json', '<>', '')
                ->where('grade', '<=', $level)
                ->where($where)
                ->field('grade,reward_json')
                ->select();

            if (!$data) {
                throw new ApiException(__('No rewards available'));
            }

            //获取所有：聊天气泡数组、坐骑数组、头像框数组
            $bubbleArr = MallService::getBubbleArr();
            $carArr = MallService::getCarArr();
            $adornmentArr = MallService::getAdornmentArr();
            $reward_info = [];
            foreach ($data as $value) {
                $level_reward[] = [
                    'user_id'    => $userId,
                    'user_level' => $value['grade'],
                ];
                $reward_items = json_decode($value['reward_json'], true);
                foreach ($reward_items as $item) {
                    switch ($item['type']) {
                        case 'amount':
                            $reward_info[] = [
                                'icon'  => 'assets/icon/diamond.png',
                                'name'  => '金币',
                                'count' => $item['count'],
                            ];
                            break;
                        case 'bubble':
                            $bubble_info = $bubbleArr[$item['id']];
                            $reward_info[] = [
                                'icon'  => $bubble_info['image'],
                                'name'  => $bubble_info['name'],
                                'count' => $item['count'],
                            ];
                            break;
                        case 'car':
                            $car_info = $carArr[$item['id']];
                            $reward_info[] = [
                                'icon'  => $car_info['image'],
                                'name'  => $car_info['name'],
                                'count' => $item['count'],
                            ];
                            break;
                        case 'adornment':
                            $adornment_info = $adornmentArr[$item['id']];
                            $reward_info[] = [
                                'icon'  => $adornment_info['image'],
                                'name'  => $adornment_info['name'],
                                'count' => $item['count'],
                            ];
                            break;
                    }
                }

                UserBusinessModel::reward_give($reward_items, $userId, '升级奖励');
                other_log_add($userId, 6, $reward_info);
            }
            db('user_level_reward')->insertAll($level_reward);
            Db::commit();
        } catch (\Exception  $e) {
            Db::rollback();
            Log::error($e->getMessage());
            throw $e;
        }
        return $reward_info;
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
    public function getAuditImage()
    {
        try {
            $userId = $this->auth->id;
            $images = Db::name('user_audit_image')
                ->field('id,img_type,url,status')
                ->where('user_id', $userId)
                ->whereIn('status', [0, 1])
                ->select();
            $data['avatar'] = null;
            $data['image'] = [];
            if (count($images)) {
                foreach ($images as $image) {
                    if ($image['img_type'] == 'avatar') {
                        $data['avatar'] = [
                            'id'     => $image['id'],
                            'url'    => $image['url'],
                            'status' => $image['status'],
                        ];
                    } elseif ($image['img_type'] == 'image') {
                        $data['image'][] = [
                            'id'     => $image['id'],
                            'url'    => $image['url'],
                            'status' => $image['status'],
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error(show_error_notify($e));
        }
        $this->success('ok', $data);
    }

    /**
     * @ApiTitle (删除形象照)
     * @ApiMethod   (post)
     * @ApiParams   (name="id", type="string", required=true, rule="", description="图片ID")
     */
    public function delImage()
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
     * @ApiTitle (更新形象照)
     * @ApiMethod   (post)
     * @ApiParams   (name="id", type="string", required=true, description="需替换的图片ID")
     * @ApiParams   (name="new_image", type="string", required=true, description="新的形象照url")
     */
    public function updateImage()
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
        trace(getLastSql());
        $this->success('ok');
    }

}
