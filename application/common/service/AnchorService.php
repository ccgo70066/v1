<?php

namespace app\common\service;


/**
 * 主播服务类
 */
class AnchorService
{

    public function getInfo($user_id)
    {
        $user = db('user')->where('id', $user_id)->find();
        return [
            'real_name' => true,
            'avatar'    => !in_array($user['avatar'], array_column(config('app.default_avatar_gender'), 'avatar')),
            'image'     => count(explode(',', $user['image'])) >= 3,
            'voice'     => (bool)$user['voice'],
            'bio'       => (bool)$user['bio'],
            'interest'  => (bool)$user['interest_ids']
        ];
    }

}
