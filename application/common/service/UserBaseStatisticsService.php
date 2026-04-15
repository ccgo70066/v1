<?php

namespace app\common\service;

use app\common\model\UserBaseStatistics as UserBaseStatisticsModel;
use think\Db;

/**
 * 用户基础数据统计类
 */
class UserBaseStatisticsService extends BaseService
{
    protected static $instance = null;

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * 获取用户基础统计
     * @param int $userId 用户ID
     */
    public static function getUserStatistics(int $userId)
    {
        $data = Db::name('user_base_statistics')
            ->field('fan_num,follow_num,guest_num,blacklist_num,browse_num')
            ->find($userId);
        if ($data) {
            return $data;
        }else {
            $userInfo['fan_num'] = Db::name('user_follow')->alias('f')
                ->join('user u', 'f.user_id = u.id and u.status ="normal"')
                ->where(['to_user_id' => $userId, 'u.status' => 1])->count();
            $userInfo['follow_num'] = Db::name('user_follow')->alias('f')
                ->join('user u', 'f.to_user_id = u.id  and u.status ="normal"')
                ->where(['user_id' => $userId, 'u.status' => 1])->count();
            $userInfo['guest_num'] = Db::name('user_guest')->alias('g')
                ->join('user u', 'g.to_user_id = u.id  and u.status ="normal"')
                ->where(['user_id' => $userId, 'u.status' => 1])->count();
            $userInfo['blacklist_num'] = Db::name('user_blacklist')->alias('b')
                ->join('user u', 'b.to_user_id = u.id  and u.status ="normal"')
                ->where(['user_id' => $userId, 'u.status' => 1])->count();
            $userInfo['id'] = $userId;
            UserBaseStatisticsModel::create($userInfo);
            return $userInfo;
        }
    }

    /**
     * 更新用户基础统计
     * @param int    $userId 用户ID
     * @param string $field  更新字段
     * @param string $type   更新类型：increase=增长,decrease=减少
     */
    public static function setUserStatistics(int $userId, string $field, string $type = 'increase')
    {
        try{
            Db::startTrans();
            $data = Db::name('user_base_statistics')
                ->field('fan_num,follow_num,guest_num,blacklist_num,browse_num,like_num')
                ->find($userId);
            if (!$data) {
                $userInfo = [
                    'fan_num'       => 0,
                    'follow_num'    => 0,
                    'guest_num'     => 0,
                    'blacklist_num' => 0,
                    'like_num'      => 0,
                ];
                // $userInfo['fan_num'] = Db::name('user_follow')->alias('f')
                //     ->join('user u', 'f.user_id = u.id and u.status =1')
                //     ->where(['to_user_id' => $userId, 'u.status' => 1])->count();
                // $userInfo['follow_num'] = Db::name('user_follow')->alias('f')
                //     ->join('user u', 'f.to_user_id = u.id  and u.status =1')
                //     ->where(['user_id' => $userId, 'u.status' => 1])->count();
                // $userInfo['guest_num'] = Db::name('user_guest')->alias('g')
                //     ->join('user u', 'g.to_user_id = u.id  and u.status =1')
                //     ->where(['user_id' => $userId, 'u.status' => 1])->count();
                // $userInfo['blacklist_num'] = Db::name('user_blacklist')->alias('b')
                //     ->join('user u', 'b.to_user_id = u.id  and u.status =1')
                //     ->where(['user_id' => $userId, 'u.status' => 1])->count();
                // $userInfo['like_num']  = count(Db::name('moment_like')->alias('l')
                //     ->join('moment m', 'l.moment_id = m.id')
                //     ->where('m.user_id', $userId)
                //     ->group('l.user_id')->column('l.user_id'));

                if ($type == 'decrease') {
                    if ($userInfo[$field] > 0) {
                        $userInfo[$field] = bcsub($userInfo[$field], 1);
                    }else {
                        $userInfo[$field] = 0;
                    }
                }else {
                    $userInfo[$field] = bcadd($userInfo[$field], 1);
                }
                $userInfo['id'] = $userId;
                UserBaseStatisticsModel::create($userInfo);
            }else {
                $_origin = $data[$field];
                if ($type == 'decrease') {
                    $_later = $_origin - 1;
                    if ($_later > 0) {
                        Db::name('user_base_statistics')->where(['id' => $userId])->dec($field)->update();
                    }else {
                        Db::name('user_base_statistics')->where(['id' => $userId])->update([$field => 0]);
                    }
                }else {
                    Db::name('user_base_statistics')->where(['id' => $userId])->inc($field)->update();
                }
            }

            Db::commit();
        }catch (\Exception $exception){
            Db::rollback();
            throw $exception;
        }
    }
}
