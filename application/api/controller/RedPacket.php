<?php

namespace app\api\controller;

use addons\socket\library\GatewayWorker\Applications\App\Message;
use app\common\exception\ApiException;
use app\common\model\Shield;
use app\common\service\ImService;
use think\Db;

/**
 * IM红包
 * @ApiWeigh    (899)
 */
class RedPacket extends Base
{
    protected $noNeedLogin = ['order_by_number', 'get_custom_item'];
    protected $noNeedRight = ['*'];

    /**
     * 发送红包
     * @ApiSummary  ("status:1=待领取,2=已领取")
     * @ApiMethod   (post)
     * @ApiParams   (name="to_user_id", type="string",  required=true, rule="require", description="获赠方")
     * @ApiParams   (name="amount", type="int",  required=true, rule="require", description="赠送金额")
     * @ApiParams   (name="remarks", type="string",  required=false, rule="", description="备注,默认 恭喜发财")
     * @ApiParams   (name="safe_code", type="string",  required=true, rule="", description="安全码")
     */
    public function send()
    {
        //$this->operate_check('redpacket_send:' . $this->auth->id, 3);
        !\app\common\model\UserBusiness::getRedPacketAuth($this->auth->id) && $this->error(__('尚未开放'));
        $to_user_id = input('to_user_id');
        //安全码
        $safe_code = db('user_business')->where('id', $this->auth->id)->value('safe_code');
        $safe_code == false && $this->error(__('Please set a security code'), null, 308);
        $safe_code != input('safe_code') && $this->error(__('Code error'), 402);

        $user_id = $this->auth->id;

        $to_user = db('user')->where('id', $to_user_id)->field('nickname,avatar')->find();
        if (!$to_user) {
            $this->error(__('User does not exist'));
        }
        if ($to_user_id == $user_id) {
            $this->error(__('Cannot operate on yourself'));
        }

        $amount = floor(input('amount'));
        $this->operate_check('red_packet_send:' . $this->auth->id, 2);
        if ($amount < 10) {
            $this->error(__('The amount shall not be less than %s', 10));
        }
        Db::startTrans();
        try {
            user_business_change($user_id, 'amount', $amount, 'decrease', '赠送私聊红包', 6);
            user_business_change($to_user_id, 'amount', $amount, 'increase', '收到私聊红包', 6);

            $data = [
                'user_id'    => $user_id,
                'to_user_id' => $to_user_id,
                'amount'     => $amount,
                'remarks'    => Shield::sensitive_filter(input('remarks')) ?: '恭喜发财，大吉大利',
                'status'     => 2,
            ];
            db('red_packet_log')->insert($data);
            Db::commit();
        } catch (ApiException $e) {
            Db::rollback();
            $this->error($e->getMessage(), null, $e->getCode());
        } catch (\Exception $e) {
            Db::rollback();
            error_log_out($e);
            $this->error($e->getMessage());
        }

        $im = new ImService();
        $result = $im->sendRedPacketMessage($this->auth->id, $to_user_id, $amount, $data['remarks']);
        if (!$result) {
            throw new ApiException(__('Send failed'));
        }
        send_im_msg_by_system_with_lang($to_user_id, sprintf('%s给你发了%s钻石红包', $this->auth->nickname, $amount));
        board_notice_delay(Message::CMD_REFRESH_USER, ['user_id' => $to_user_id]);
        $this->success('', $data);
    }

    /**
     * 获取红包信息
     * @ApiMethod   (post)
     * @ApiSummary  ("status:1=待领取,2=已领取")
     * @ApiParams   (name="id", type="string",  required=true, rule="require", description="红包id")
     */
    public function get()
    {
        $id = input('id');
        $sel = db('red_packet_log r')
            ->join('user u', 'r.user_id = u.id')
            ->join('user u2', 'r.to_user_id = u2.id')
            ->where('r.id', $id)
            ->field('r.*,u.nickname,u.avatar,u2.nickname as to_nickname,u2.avatar as to_avatar')
            ->find();
        $this->success('', $sel);
    }

    /**
     * 开启权限
     * @ApiMethod   (post)
     * @ApiParams   (name="to_user_id", type="string",  required=true, rule="require", description="目标ID")
     */
    public function open_auth()
    {
        $exist = db('room_admin')->where('user_id', $this->auth->id)->where('role', 1)->find();
        if (!$exist) {
            $this->error(__('You have no permission'));
        }
        $has = db('red_packet_whitelist')->insert(['user_id' => input('to_user_id')]);
        if ($has) {
            board_notice(Message::CMD_REFRESH_USER, ['user_id' => input('to_user_id')]);
        }
        $this->success();
    }

}

