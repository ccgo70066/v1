<?php

namespace app\common\service;


/**
 * 用户业务流水-服务
 */
class UserBusinessLogService
{

    /**
     * 业务流水基础信息
     * @param int    $userId  用户ID
     * @param string $comment 备注
     * @param array  $where   查询条件
     * @param int    $size    页码大小
     * @return array
     */
    public static function getBaseLogs($userId, $where, $size = 20): array
    {
        $query = db('user_business_log')->where('user_id', $userId);
        $data = $query->where($where)
            ->field("id,cate,comment,create_time,change_amount as val,cate")
            ->order('id desc')
            ->limit($size)
            ->select();
        foreach ($data as &$v) {
            $v['val'] = (string)($v['val'] + 0);
            if ($v['cate'] == 1) {
                $v['val'] = (string)(' +' . $v['val']);
            }
            $v['cate'] = (string)$v['cate'];
            $v['id'] = (string)$v['id'];
            $v['comment'] = UserBusinessLogService::getLoadLang($v['comment']);
        }

        return $data;
    }

    public static function getLoadLang($string): string
    {
        $array = explode(':', $string);
        $comment = $array[0];
        if (count($array) > 1) {
            $params = $array[1];
            $params = explode(',', $params);
            $giftArr = [];
            foreach ($params as $param) {
                [$gift, $count] = explode('×', $param);
                $giftArr[] = $gift . '×' . $count;
            }
            $comment .= ':' . implode(',', $giftArr);
        }
        return $comment;
    }

}
