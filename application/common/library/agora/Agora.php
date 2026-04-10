<?php
namespace app\common\library\agora;

use DateTime;
use DateTimeZone;
use think\Env;

/**
 * 声网接口
 */
class Agora
{

    protected $appID;    //在Agora 控制台创建项目时生成的 App ID。
    protected $appCertificate;   //你的 App 证书。
    protected $timestamp = null;

    public function __construct()
    {
        if (Env::get('app.server') == 'prod') {
            $this->appID = Env::get('prod.agoraAppID');
            $this->appCertificate = Env::get('prod.agoraAppCertificate');
        }else{
            $this->appID = Env::get('test.agoraAppID');
            $this->appCertificate = Env::get('test.agoraAppCertificate');
        }
    }

    //获取进入频道的token
    public function get_token($room_id, $user_id)
    {
        $role = RtcTokenBuilder::RolePublisher; //
        $expireTimeInSeconds = 86400;
        // $expireTimeInSeconds = 300;
        $currentTimestamp = (new DateTime("now", new DateTimeZone('UTC')))->getTimestamp();
        $privilegeExpiredTs = $currentTimestamp + $expireTimeInSeconds;
        $token = RtcTokenBuilder::buildTokenWithUid($this->appID, $this->appCertificate, "$room_id", $user_id, $role, $privilegeExpiredTs);
        return $token;
    }

}


