<?php

namespace app\common\service;


/**
 * 主播服务类
 */
class AnchorService extends BaseService
{
    protected static self $instance;

    public static function instance(): static
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }

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
