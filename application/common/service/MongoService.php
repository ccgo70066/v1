<?php

namespace app\common\service;

use think\Db;

class MongoService
{
    //解决mongodb在mysql事务中存储的数据不能跟随mysql的回滚事务而保持一致性的问题
    public static $logData = [];

    protected function __construct()
    {
    }

    /**
     * 保存数据，用于后面存储
     * @param array $data
     * @return void
     */
    public static function dataStore(array $data)
    {
        self::$logData = $data;
    }

    /**
     * 将保存数据入库
     * @param array $data
     * @param bool  $insertAll 批量插入
     * @return void
     */
    public static function dataInsert(string $tableName, $insertAll = false)
    {
        try {
            $data = self::$logData;
            if (!$data) {
                throw new \Exception('数据为空');
            }

            self::$logData = [];
            if ($insertAll) {
                Db::connect('mongodb')->table($tableName)->insertAll($data);
            } else {
                Db::connect('mongodb')->table($tableName)->insert($data);
            }
        } catch (\Throwable $e) {
            error_log_out($e);
        }
    }

    public static function clean($tableName)
    {
        Db::connect('mongodb')->name($tableName)->where('_id', '<>', '')->delete();
    }

}
