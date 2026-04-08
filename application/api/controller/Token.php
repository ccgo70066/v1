<?php

namespace app\api\controller;

use app\common\library\Token as TokenService;
use fast\Random;
use think\Cache;
use think\Db;

/**
 * Token接口
 */
class Token extends ApiBase
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 检测Token是否过期
     *
     */
    public function check()
    {
        $token = $this->auth->getToken();
        $tokenInfo = TokenService::get($token);
        $res = (isset($tokenInfo) && $tokenInfo && $tokenInfo['token'] && $tokenInfo['expires_in'] > 1) ? 1 : 0;
        if ($res) {
            Db::name('user')->where('id', $tokenInfo['user_id'])->update(['loginip' => request()->ip()]);
        }
        $this->success('', ['ret' => $res]);
    }


    /**
     * 刷新Token
     *
     */
    public function refresh()
    {
        //删除源Token
        $token = $this->auth->getToken();
        TokenService::delete($token);
        //创建新Token
        $token = Random::uuid();
        TokenService::set($token, $this->auth->id, 2592000);
        $tokenInfo = TokenService::get($token);
        $this->success('', ['token' => $tokenInfo['token'], 'expires_in' => $tokenInfo['expires_in']]);
    }


    public function clear()
    {
        $tag = input('tag');
        cache($tag, null);
        $rs = Cache::clear($tag);
        $this->success($tag, $rs);
    }

}
