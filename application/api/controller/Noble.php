<?php

namespace app\api\controller;

use app\common\service\NobleService;
use Exception;
use think\Db;
use think\Log;

/**
 * 贵族
 */
class Noble extends Base
{
    protected $noNeedLogin = '';
    protected $noNeedRight = '*';

    /**
     * @ApiTitle    (获取贵族信息)
     * @ApiSummary  ("privilege权限:1=拥有权限,0无此权限")
     * @throws
     */
    public function get()
    {
        try {
            $userId = $this->auth->id;
            $system = $this->system ?? 2;
            $data = NobleService::getNobleInfo($userId, $system);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            $this->error($e->getMessage());
        }
        $this->success('', $data);
    }

    /**
     * 贵族权限打开或关闭
     * @ApiParams   (name="switch", type="int",  required=true, rule="", description="分类:1=开,2=关")
     * @ApiParams   (name="privilege_id", type="int",  required=true, rule="", description="权限Id")
     */
    public function switch()
    {
        Db::startTrans();
        try {
            $userId = $this->auth->id;
            $privilegeId = input('privilege_id');
            $switch = input('switch');
            NobleService::setSwitch($userId, $privilegeId, $switch);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            error_log_out($e);
            $this->error($e->getMessage());
        }
        $this->success(__('Operation completed'));
    }

}
