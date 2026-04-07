<?php


namespace app\common\model;


use app\common\service\UserBaseStatisticsService;
use think\Db;
use think\Exception;
use think\Log;
use think\Model;

class UserGuest extends Model
{
    // 表名
    protected $name = 'user_guest';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [];

    /**
     * 添加访问记录
     * @param int $userId
     * @param int $objId
     * @return bool
     */
    public static function addGuestLog( $userId, $objId)
    {
        try {
            $user           = new User();
            $userGuestModel = new UserGuest();
            $isExists       = $user->find($objId);
            if (!$isExists) {
                throw new Exception('访问的用户不存在', 406);
            }
            Db::startTrans();
            $data      = [
                'user_id'      => $userId,
                'to_user_id'   => $objId,
            ];
            $today  = date('Y-m-d');
            $userGuest = $userGuestModel->where($data)->find();
            if($userGuest){
                if($today > $userGuest['last_guest_time']){
                    $data['last_guest_time'] = $today;
                    $data['count'] = $userGuest['count']+1;
                    $userGuestModel->where('id', $userGuest['id'])->setField($data);
                }
            }else{
                $data['last_guest_time'] = $today;
                $userGuestModel->insert($data);
                UserBaseStatisticsService::setUserStatistics($objId, 'guest_num', 'increase');

            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            error_log_out($e);
            Log::error($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * 是否访问过对方
     * @param $userId
     * @param $toUserId
     * @return int|string
     * @throws \think\Exception
     */
    public static function isGuest($userId, $toUserId)
    {
        return self::where([
            'user_id' => $userId,
            'to_user_id' => $toUserId,
            'is_mutual' => 1,
        ])->count();
    }
}
