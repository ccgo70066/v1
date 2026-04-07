<?php

namespace app\common\service;
use app\common\library\ApiException;
use app\common\model\NoblePrivilege;
use app\common\model\ShopItem as ShopModel;
use app\common\model\UserBusiness as UserBusinessModel;
use app\common\model\UserNoble;

use function app\api\library\get_expiry_days;

/**
 * 贵族相关类
 */
class NobleService
{

    /**
     * 获取贵族相关信息
     * @param int $userID 用户ID
     * @param int $system
     */
    public static function getNobleInfo($userID, $system)
    {
        if (!$system) {
            exception('缺少参数');
        }
        $nobleInfo = UserBusinessModel::getUserNobleInfoById($userID);
        $data = db('shop_item i')
            ->join('noble n', 'n.id=i.item_id', 'left')
            ->field("i.id,i.name,i.days,i.price,i.price as origin_price,'0' as is_discount,n.privilege_ids,n.speedup,n.shop_badge,n.id as noble_id")
            ->where([
                'i.type' => ShopModel::TYPE_NOBLE,
                'i.status' => ShopModel::STATUS_ON,
                'i.cate' => ShopModel::CATE_RED_DIAMOND
            ])
            ->where("find_in_set($system, `show`)")
            ->order('i.price asc')
            ->select();

        $privilege = db('noble_privilege')
            ->field("id,name,label,has_switch")
            ->select();

        foreach ($data as &$datum) {
            if (isset($nobleInfo['noble_id']) && $nobleInfo['noble_id'] > $datum['noble_id']) {
                $datum['can_buy'] = 0;
            } else {
                $datum['can_buy'] = 1;
            }
            //判断是否开通贵族权限
            if (isset($nobleInfo['noble_id']) && $nobleInfo['noble_id'] == $datum['noble_id']) {
                $datum['is_open'] = 1;
                $datum['end_time'] = substr($nobleInfo['end_time'], 0, 10) ?? '';
                $datum['end_time_hour'] = get_expiry_days($nobleInfo['end_time'])->hours ?? '';
                $datum['price'] = round($datum['price'] * get_site_config('renewal_noble')); //续费价格
                if (get_site_config('renewal_noble') < 1) {
                    $datum['is_discount'] = 1;
                }
            } else {
                $datum['is_open'] = 0;
                $datum['end_time'] = '';
            }

            $datumPrivilege = explode(',', $datum['privilege_ids']);
            foreach ($privilege as $item) {
                if ($datumPrivilege && in_array($item['id'], $datumPrivilege)) {
                    $item['activate'] = 1;
                } else {
                    $item['activate'] = 0;
                }
                $item['switch'] = 0;
                //如果用户开通了贵族等级，显示相应开关状态
                if (isset($nobleInfo['union_room_hide']) && $nobleInfo['union_room_hide'] == UserNoble::UNION_ROOM_HIDE_ON && $item['id'] == 9) {
                    $item['switch'] = 1;
                }
                if (isset($nobleInfo['name_color']) &&
                    $nobleInfo['name_color'] == UserNoble::NAME_COLOR_ON && $item['id'] == 6) {
                    $item['switch'] = 1;
                }
                $item['label'] = str_replace('{X}', $datum['speedup'], $item['label']);
                $item['name'] = str_replace('{X}', $datum['speedup'], $item['name']);
                $datum['intro'][] = $item;
            }

            unset($datum['noble_id']);
            unset($datum['privilege_ids']);
            unset($datum['speedup']);
        }
        return $data;
    }

    /**
     * 权限贵族设置
     * @param int $userID 用户ID
     * @param int $privilegeId
     * @param int $switch 分类:1=开,2=关
     */
    public static function setSwitch($userId, $privilegeId, $switch)
    {
        $userNoble = UserBusinessModel::getUserNobleInfoById($userId);
        if (!$userNoble)  throw new ApiException(__('No permissions'));

        //如果用户开通了贵族等级，显示相应开关状态
        if ($privilegeId == NoblePrivilege::PERMISSION_ID_ROOM_HIDE && $userNoble['union_room_hide']) {
            $result = db('user_noble')->where('id', $userNoble['id'])->setField('union_room_hide', $switch);
        }
        if ($privilegeId == NoblePrivilege::PERMISSION_ID_NAME_COLOR && $userNoble['name_color']) {
            $result = db('user_noble')->where('id', $userNoble['id'])->setField('name_color', $switch);
        }
        if (!$result) {
            throw new ApiException(__('Operation failed'));
        }
    }

    /**
     * 获取贵族-最大等级
     */
    public static function getMaxNoble()
    {
        $data = db('noble')->max('weigh');
        return $data;
    }

}
