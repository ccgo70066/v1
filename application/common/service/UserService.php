<?php

namespace app\common\service;

use app\common\model\AnchorRecommend as AnchorRecommendModel;
use app\common\model\Gift as GiftModel;
use app\common\model\User;
use app\common\model\UserBlacklist;
use app\common\model\UserBusiness;
use think\Db;

/**
 * 用户业务类
 */
class UserService
{
    /**
     * 通过ID获取用户信息
     * @param $id
     */
    public static function getById($id)
    {
        return User::where('id', $id)->find();
    }

    /**
     * 通过用户名获取用户信息
     * @param $username
     */
    public static function getByUsername($username)
    {
        return User::where('username', $username)->find();
    }

    /**
     * 通过昵称获取用户信息
     * @param $nickname
     */
    public static function getByNickname($nickname)
    {
        return User::where('nickname', $nickname)->find();
    }

    /**
     * 通过邮箱获取用户信息
     * @param $email
     */
    public static function getByEmail($email)
    {
        return User::where('email', $email)->find();
    }

    /**
     * 通过手机号获取用户信息
     * @param $mobile
     */
    public static function getByMobile($mobile)
    {
        return User::where('mobile', $mobile)->find();
    }


    /**
     * 更新用户信息
     * @param $user
     */
    public static function updateUser($user, $data)
    {
        $update = [];
        if ($user) {
            $user->first_login = $user->first_login ?? 0;
            $user->imei = $user->imei ?? '';
            $user->system = $user->system ?? '';
            $user->version = $user->version ?? '';
            $update['first_login'] = 0;
        }

        if ($user && $data['imei'] != '' && $data['imei'] != $user->imei) {
            $update['imei'] = $data['imei'];
            if (input('system')) {
                $update['system'] = input('system');
            }
            $update['model'] = $data['model'];
            db('user_imei_log')->insert([
                'user_id'     => $user['id'],
                'orig_imei'   => $user->imei,
                'orig_system' => $user->system,
                'orig_model'  => $user['model'],
                'imei'        => $data['imei'],
                'system'      => $data['system'],
                'model'       => $data['model'],
                'create_time' => datetime(time()),
            ]);
        }
        if (isset($data['version']) && isset($user->version) && $data['version'] != '' && $data['version'] != $user->version) {
            $update['version'] = $data['version'];
        }

        if ($user && $update) {
            db('user')->where(['id' => $user->id])->setField($update);
        }
    }

    /**
     * 获取推荐主播
     * @param int $limit
     */
    public static function getAnchorRecommend(int $limit, $userId)
    {
        $anchorArr = AnchorRecommendModel::where('user_id', '<>', $userId)->column('user_id');
        if (count($anchorArr) > $limit) {
            $userIds = array_rand($anchorArr, $limit);
        } else {
            $userIds = $anchorArr;
        }

        $list = db('user u')
            ->join('blacklist b', 'b.type=1 and b.number=u.id and b.end_time > ' . time(), 'left')
            ->join('user_business ub', 'ub.id=u.id', 'left')
            ->field('u.id,u.nickname as name,u.avatar as cover,gender,age,is_online')
            ->where(['b.id' => ['exp', Db::raw('is null')]])
            ->whereIn('u.id', $anchorArr)
            ->limit($limit)
            ->order('u.id desc')
            ->select();

        if (count($list)) {
            foreach ($list as &$item) {
                $item['room_id'] = redis()->hGet(RedisService::USER_NOW_ROOM_KEY, $item['id']) ?: 0;
            }
        }

        return $list;
    }

    /**
     * 更新用户表字段
     * @param $user_id
     * @param $field
     * @param $value
     * @return void
     */
    public static function updateField($user_id, $field, $value)
    {
        Db::name('user')->where('id', $user_id)->update([$field => $value]);
        if (in_array($field, ['avatar', 'nickname'])) {
            $user = Db::name('user')->where('id', $user_id)->find();
            $imService = new ImService();
            $imService->updateUser($user_id, $user['nickname'], $user['avatar']);
            if ($user['is_online']) {
                //board_notice(Message::CMD_REFRESH_USER, ['user_id' => $user_id]);
            }
        }
    }

    /**
     * 获取会员基本信息
     */
    public static function getUserBaseInfo($userId, $authUserId)
    {
        $data = User::field('id,nickname,avatar,beautiful_id,gender,age,bio,constellation,interest_ids,hidden_level,hidden_noble,imei,mobile')
            ->find($userId);
        if (!$data) {
            return null;
        }
        $userinfo = $data->toArray();
        $userinfo['is_follow'] = 0;
        if ($userId <> $authUserId) {
            $isExits = db('user_follow')->where(['user_id' => $authUserId, 'to_user_id' => $userId])->count();
            if ($isExits) {
                $userinfo['is_follow'] = 1;
            }
        }
        $userinfo['follow_count'] = db('user_follow')->where(['user_id' => $userId])->count();
        $userinfo['fans_count'] = db('user_follow')->where(['to_user_id' => $userId])->count();

        //个性标签
        $userinfo['interest_text'] = db('interest')->whereIn('id', $userinfo['interest_ids'])->column('name');
        unset($userinfo['interest_ids']);

        $userBusiness = UserBusiness::field('union_id,safe_code,level')->find($userinfo['id']);
        $userinfo['union_id'] = $userBusiness['union_id'];

        $roomStatus = redis()->hGet(RedisService::USER_NOW_ROOM_KEY, $userinfo['id']);
        $userinfo['is_on_room'] = $roomStatus ?: 0;
        if ($authUserId == $userId) {
            $userinfo['be_blocked'] = 0;
        } else {
            $userinfo['be_blocked'] = UserBlacklist::is_blacklist($authUserId, $userId);
        }
//        $userinfo['red_packet_auth'] = UserBusiness::getRedPacketAuth($userinfo['id']);
        unset($userinfo['imei'], $userinfo['mobile']);

        //获取贵族头像信息
        $noble = UserBusiness::getUserNobleBadgeById($userinfo['id']);
        $userinfo['noble_img'] = isset($noble['badge']) ? $noble['badge'] : '';
        $userinfo['level_img'] = RedisService::getLevelCache($userBusiness['level']) ?: '';
        $userinfo['user_tail'] = UserBusiness::getWearTailImage($userinfo['id']);

        if (!empty($userId) && $userId != $authUserId) {
            if ($userinfo['hidden_noble'] == 1) {
                $userinfo['noble_img'] = '';
            }
            if ($userinfo['hidden_level'] == 1) {
                $userinfo['level_img'] = RedisService::getLevelCache(0) ?: '';
            }
        }
        unset($userinfo['hidden_noble'], $userinfo['hidden_level'], $userinfo['url']);
        //$userinfo['is_official'] = (int)Db::name('sys_user_role')->where('user_id',$userinfo['id'])->where('role',1)->find();
        //$userinfo['is_union_master'] = (int)Db::name('union')->where('owner_id',$userinfo['id'])->where('status',1)->find();
        return $userinfo;
    }

    /**
     * 获取会员礼物墙信息
     */
    public static function get_wall_info($userId)
    {
        $giftIds = db('gift_wall')->where('user_id', $userId)->column('gift_id');
        $where = [];
        if ($giftIds) {
            $where['id'] = ['in', $giftIds];
        }

        $box_ids = GiftModel::getGiftBoxIds();
        $box_ids = array_filter($box_ids, function ($value) {
            return $value !== 0 && $value !== null;
        });
        $typeArr = [GiftModel::GIFT_TYPE_BOARD, GiftModel::GIFT_TYPE_BOX];
        $query = db('gift')->where('status', GiftModel::STATUS_ON)
            ->whereIn('type', $typeArr)
            ->where('id', 'not in', $box_ids)
            ->order('price desc');

        $data = [];
        $data['list'] = [];
        $data['hasNum'] = 0;
        $data['totalNum'] = $query->count();
        if ($giftIds) {
            $data['list'] = $query->where($where)->field('id,name,image,price_type,price')->order('price desc')->limit(5)->select();
            $data['hasNum'] = count($giftIds);
        }
        return $data;
    }


    /**
     * 是否在黑名单中
     * @param int    $user_id
     * @param string $imei
     * @param string $mobile
     * @return bool
     * @throws null
     */
    public static function inBlacklist(int $user_id, string $imei, string $mobile): bool
    {
        $is_black = db('blacklist')
            ->where("(type=1 and number = '{$user_id}') OR (type = 2 and number = '{$imei}') or (type=3 and number='{$mobile}')")
            ->where('end_time > ' . time())
            ->count(1);

        return $is_black > 0;
    }


    public static function getWallInfo($userId)
    {
        $giftIds = db('gift_wall')->where('user_id', $userId)->column('gift_id');
        $where = [];
        if ($giftIds) {
            $where['id'] = ['in', $giftIds];
        }

        $typeArr = [GiftModel::GIFT_TYPE_BOARD, GiftModel::GIFT_TYPE_BOX];
        $query = db('gift')->where('status', GiftModel::STATUS_ON)
            ->whereIn('type', $typeArr)
            ->order('price desc');

        $data = [];
        $data['list'] = [];
        $data['hasNum'] = 0;
        $data['totalNum'] = $query->count();
        if ($giftIds) {
            $data['list'] = $query->where($where)->field('id,name,image,price_type,price')->order('price desc')->limit(5)->select();
            $data['hasNum'] = count($giftIds);
        }
        return $data;
    }


}
