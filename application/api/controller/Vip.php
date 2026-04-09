<?php


namespace app\api\controller;

use app\common\service\RedisService;
use think\Db;

/**
 * VIP
 * @ApiWeigh
 */
class Vip extends Base
{
    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];

    /**
     * 获取用户VIP信息
     * @throws
     */
    public function info()
    {
        $user_id = $this->auth->id;
        $vip = db('user_vip')->field('create_time,update_time,protect,next_protect_time', true)->where('id', $user_id)->find();
        if ($vip) {
            $vip['switch'] = json_decode($vip['switch'], true);
            $vip = array_merge($vip, db('vip')->field('name,privilege_ids')->where('grade', $vip['grade'])->find());
        }

        $this->success('', $vip);
    }

    /**
     * 开关权限
     * @ApiMethod (post)
     * @ApiParams (name="switch", type="int",  required=true, rule="", description="分类:1=开,2=关")
     * @ApiParams (name="privilege_id", type="int",  required=true, rule="", description="权限Id")
     */
    public function switch()
    {
        $switch = input('switch');
        $privilegeId = input('privilege_id');
        $user_id = $this->auth->id;
        $vip_privilege = db('vip_privilege')->where('id', $privilegeId)->where('has_switch', 1)->find();
        !$vip_privilege && $this->error(__('No results were found'));
        $vip = db('user_vip')->where('id', $user_id)->find();
        $switchObj = json_decode($vip['switch'], true) ?: [];
        $switchObj[$privilegeId] = $switch;
        $vip['switch'] = json_encode($switchObj);
        db('user_vip')->update($vip);
        $this->success();
    }

    /**
     * 获取VIP信息
     * @throws
     */
    public function list()
    {
        $list = Db::name('vip')
            ->field('create_time,update_time,font_color,protect_days,protect_limit,punish_limit,reward_json', true)
            ->where('grade', '>', 0)->select();
        $privilege = Db::name('vip_privilege')->field('id,name,image,has_switch')->select();
        $price = get_site_config('vip_price');
        $days = 30;

        $this->success('', compact('list', 'privilege', 'price', 'days'));
    }

    /**
     * 购买VIP
     * @ApiMethod (post)
     * @throws
     */
    public function buy()
    {
        $price = get_site_config('vip_price');
        $user_id = $this->auth->id;
        $exist = db('user_vip')->where('id', $user_id)->where('expire_time', '>', datetime())->find();
        if ($exist) $this->error(__('You have purchased VIP'));
        user_business_change($user_id, 'amount', $price, 'decrease', '购买VIP');
        user_vip_add($user_id, 2, 30, '购买VIP');

        $this->success();
    }

}
