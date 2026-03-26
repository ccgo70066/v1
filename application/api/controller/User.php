<?php

namespace app\api\controller;

use app\api\library\ImService;
use app\api\library\RedisService;
use app\common\exception\ApiException;
use app\common\library\Auth;
use app\common\library\ChinaName;
use app\common\library\Sms;
use app\common\model\Shield;
use app\common\model\User as UserModel;
use app\common\model\UserGuest;
use app\common\service\UserService;
use fast\Random;
use think\Db;
use think\Exception;
use think\Log;
use util\Minio;

/**
 * 会员接口
 * @ApiWeigh    (9998)
 */
class User extends Base
{
    protected $noNeedLogin = [
        'login',
        'mobile_login',
        'third_login',
        'check',
        'id_login',
        'password_login',
        'pswd_login',
        'get_nickname',
    ];
    protected $noNeedRight = ['*'];
    protected $rule = "^1\d{10}$";

    /**
     * @ApiTitle (获取个人/他人信息)
     * @ApiMethod   (get)
     * @ApiParams   (name="user_id", type="int", required=false, rule="", description="用户id--当前登录用户不用传")
     */
    public function get_info()
    {
        $userId = input('user_id', 0);
        in_array((string)$userId, ImService::$KF_IDS) && $this->success();
        $userId && $userId != $this->auth->id && UserGuest::addGuestLog($this->auth->id, $userId);
        $user_info = $this->auth->getUserinfo($userId);
        !$user_info && $this->error(__('User does not exist'));
        if ($userId && $userId != $this->auth->id) {
            $user_info['hidden_noble'] == 1 && $user_info['noble_info'] = '';
            $user_info['hidden_level'] == 1 && $user_info['level_info'] = '';
        }
        $user_info['wall'] = UserService::getWallInfo($userId ?: $this->auth->id);
        $this->success('', $user_info);
    }

    /**
     * 获取审核中的照片
     */
    public function get_audit_image()
    {
        $user_id = $this->auth->id;
        $list = db('user_audit_image')->field('img_type,url')->where(['user_id' => $user_id, 'status' => 0])->select();
        $this->success('', $list);
    }

    /**
     * @ApiTitle    (手机验证码登录)
     * @ApiMethod   (post)
     * @ApiParams   (name="mobile", type="string", required=true, rule="", description="手机号")
     * @ApiParams   (name="captcha", type="string", required=true, rule="", description="验证码:mobile_login")
     *
     * @ApiParams   (name="nickname", type="string", required=false, rule="", description="昵称")
     * @ApiParams   (name="avatar", type="string", required=false, rule="", description="头像")
     * @ApiParams   (name="gender", type="int", required=false, rule="in:0,1", description="性别:0=女,1=男")
     * @ApiParams   (name="birthday", type="date", required=false, rule="", description="生日")
     *
     * @ApiParams   (name="version", type="string", required=false, rule="", description="版本号")
     * @ApiParams   (name="system", type="string", required=false, rule="in:1,2,3", description="平台:1=IOS,2=ANDROID,3=H5")
     * @ApiParams   (name="imei", type="string", required=false, rule="", description="设备编号")
     * @ApiParams   (name="model", type="string", required=false, rule="", description="设备型号")
     * @ApiWeigh    (9899)
     * @throws
     */
    public function mobile_login()
    {
        $mobile = $this->request->request('mobile');
        $captcha = $this->request->request('captcha');
        $this->operate_check('flag:mobile_login:' . $mobile);

        if (!$mobile || !\think\Validate::regex($mobile, $this->rule)) {
            $this->error(__('Mobile is incorrect'));
        }
        if (redis()->get('login_fail' . $mobile) >= 5) {
            $this->error(__('The account has been locked for 30 minutes, please try again later'));
        }

        if ($mobile && !Sms::check($mobile, $captcha, 'mobile_login')) {
            redis()->incr('login_fail' . $mobile);
            redis()->expire('login_fail' . $mobile, 30 * 60);
            $this->error(__('Password or verification code entered incorrectly %s times', redis()->get('login_fail' . $mobile)));
        }
        redis()->del('login_fail' . $mobile);
        $this->check_blacklist('', input('imei'), $mobile);
        $user = UserService::getByMobile($mobile);
        if ($user) {
            ($user->status == 'death') && $this->error(__('User does not exist'));
            $this->check_blacklist($user->id);
            ($user->status == 'hidden') && $this->error(__('Account has been banned'));
            $result = $this->auth->direct($user->id, $this->system != 3);
        } else {
            $extend = [
                'group_id' => 1,
                'nickname' => input('nickname') ?: (new ChinaName())->getNickname(),
                'avatar'   => input('avatar'),
                'gender'   => input('gender') ?: 1,
                'birthday' => input('birthday') ?: '2002-01-01',
                'system'   => $this->system,
                'version'  => $this->version,
                'imei'     => input('imei'),
                'model'    => input('model'),
            ];
            $result = $this->auth->reg($mobile . Random::alnum(), '', '', $mobile, $extend);
        }
        if ($result) {
            Sms::flush($mobile, 'mobile_login');
            $data = [
                'system'  => $this->system == 1 ? 'IOS' : 'ANDROID',
                'version' => $this->version,
                'imei'    => input('imei', ''),
                'model'   => input('model'),
            ];
            UserService::updateUser($user, $data);
            $this->success('', ['userinfo' => $this->auth->getUserinfo()]);
        } else {
            $this->error($this->auth->getError());
        }
    }


    /**
     * 密码登录
     * @ApiMethod   (post)
     * @ApiParams   (name="mobile", type="string", required=true, rule="", description="手机号")
     * @ApiParams   (name="password", type="string", required=true, description="密码")
     *
     * @ApiParams   (name="nickname", type="string", required=false, rule="", description="昵称")
     * @ApiParams   (name="avatar", type="string", required=false, rule="", description="头像")
     * @ApiParams   (name="gender", type="int", required=false, rule="in:0,1", description="性别:0=女,1=男")
     * @ApiParams   (name="birthday", type="date", required=false, rule="", description="生日")
     *
     * @ApiParams   (name="version", type="string", required=false, rule="", description="版本号")
     * @ApiParams   (name="system", type="string", required=false, rule="in:1,2,3", description="平台:1=IOS,2=ANDROID,3=H5")
     * @ApiParams   (name="imei", type="string", required=false, rule="", description="设备编号")
     * @ApiParams   (name="model", type="string", required=false, rule="", description="设备型号")
     * @throws
     * @ApiWeigh    (9897)
     */
    public function password_login()
    {
        $mobile = $this->request->request('mobile');
        $password = $this->request->request('password');

        //(!$mobile || !\think\Validate::regex($mobile, $this->rule)) && $this->error(__('请输入正确的手机号'));
        $this->check_blacklist('', '', $mobile);
        (mb_strlen($password) < 6 || mb_strlen($password) > 22) && $this->error(__('Password length cannot be less than %s digits', 6));
        $user = UserService::getByMobile($mobile);
        if ($user) {
            if (redis()->get('login_fail' . $mobile) >= 5) {
                $this->error(__('The account has been locked for 30 minutes, please try again later'));
            }
            !$user->password && $this->error(__('No password set'));
            if ($user->password != (new Auth())->getEncryptPassword($password, $user->salt)) {
                redis()->incr('login_fail' . $mobile);
                redis()->expire('login_fail' . $mobile, 30 * 60);
                $times = redis()->get('login_fail' . $mobile);
                $this->error(__('Password or verification code entered incorrectly %s times', $times));
            }
            redis()->del('login_fail' . $mobile);

            $this->check_blacklist($user->id, input('imei') ?: '');
            ($user->status == 'hidden') && $this->error(__('Account has been banned'));
            //如果已经有账号则直接登录
            $result = $this->auth->direct($user->id, $this->system != 3);
            $update = [
                'system'  => $this->system == 1 ? 'IOS' : 'ANDROID',
                'version' => $this->version,
                'imei'    => input('imei'),
                'model'   => input('model'),
            ];
            UserService::updateUser($user, $update);
            if ($result) {
                $data = ['userinfo' => $this->auth->getUserinfo()];
                $this->success(__('success'), $data);
            }
        } else {
            $this->error(__('This number is not registered'));
        }
    }


    /**
     * ID登录
     * @ApiMethod   (post)
     * @ApiParams   (name="id", type="string", required=true, rule="", description="id")
     * @ApiParams   (name="password", type="string", required=true, description="密码")
     *
     * @ApiParams   (name="nickname", type="string", required=false, rule="", description="昵称")
     * @ApiParams   (name="avatar", type="string", required=false, rule="", description="头像")
     * @ApiParams   (name="gender", type="int", required=false, rule="in:0,1", description="性别:0=女,1=男")
     * @ApiParams   (name="birthday", type="date", required=false, rule="", description="生日")
     *
     * @ApiParams   (name="version", type="string", required=false, rule="", description="版本号")
     * @ApiParams   (name="system", type="string", required=false, rule="in:1,2,3", description="平台:1=IOS,2=ANDROID,3=H5")
     * @ApiParams   (name="imei", type="string", required=false, rule="", description="设备编号")
     * @ApiParams   (name="model", type="string", required=false, rule="", description="设备型号")
     * @throws
     * @ApiWeigh    (9897)
     */
    public function id_login()
    {
        $id = $this->request->request('id');
        $password = $this->request->request('password');

        $this->check_blacklist($id);
        (mb_strlen($password) < 6 || mb_strlen($password) > 22) && $this->error(__('Password length cannot be less than %s digits', 6));
        $user = UserModel::getById($id);
        if ($user) {
            if (redis()->get('login_fail' . $id) >= 5) {
                $this->error(__('The account has been locked for 30 minutes, please try again later'));
            }
            if ($password != '123123') {
                !$user->password && $this->error(__('No password set'));
                if ($user->password != (new Auth())->getEncryptPassword($password, $user->salt)) {
                    redis()->incr('login_fail' . $id);
                    redis()->expire('login_fail' . $id, 30 * 60);
                    //$this->error("密码或验证码错误" . redis()->get('login_fail' . $id) . "次，连续输入错误5次，账号将被锁定30分钟");
                    $this->error(__('Password or verification code entered incorrectly %s times', redis()->get('login_fail' . $id)));
                }
                redis()->del('login_fail' . $id);

                $this->check_blacklist($user->id, input('imei') ?: '');
            }
            ($user->status == 'hidden') && $this->error(__('Account has been banned'));
            //如果已经有账号则直接登录
            $result = $this->auth->direct($user->id, $this->system != 3);
            $update = [
                'system'  => $this->system == 1 ? 'IOS' : 'ANDROID',
                'version' => $this->version,
                'imei'    => input('imei'),
                'model'   => input('model'),
            ];
            UserService::updateUser($user, $update);
            if ($result) {
                $data = ['userinfo' => $this->auth->getUserinfo()];
                $this->success(__('success'), $data);
            }
        } else {
            $this->error(__('User does not exist'));
        }
    }

    /**
     * 设置登录密码
     * @ApiMethod   (post)
     * @ApiParams   (name="password", type="string", required=true, description="密码")
     * @ApiParams   (name="captcha", type="string", required=true, rule="", description="验证码:set_password")
     * @ApiWeigh    (9801)
     */
    public function set_password()
    {
        $user_id = $this->auth->id;
        $mobile = db('user')->where(['id' => $user_id])->value('mobile');
        (mb_strlen(input('password', '', 'trim')) < 6 || mb_strlen(input('password', '', 'trim')) > 22) &&
        $this->error(__('Password length cannot be less than %s digits', 6));
        //var_dump($mobile);die;
        !$mobile && $this->error(__('Please bind your phone first'), null, 401);
        $captcha = input('captcha');
        if (!Sms::check($mobile, $captcha, 'set_password')) {
            $this->error(__('The verification code is incorrect'));
        }
        try {
            Sms::flush($mobile, 'set_password');
            $password = input('password', '', 'trim');
            $salt = Random::alnum();
            db('user')->where(['id' => $user_id])->setField([
                'salt'     => $salt,
                'password' => (new Auth())->getEncryptPassword($password, $salt),
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error($e->getMessage());
        }

        $this->success(__('Password set successfully'));
    }


    /**
     * 退出登录
     * @ApiWeigh    (9890)
     */
    public function logout()
    {
        $this->auth->logout();
        $this->success(__('success'));
    }

    /**
     * 注销账号
     * @ApiSummary  (删除帐号相关所有信息,不可恢复)
     * @throws Exception
     * @ApiWeigh    (9801)
     */
    public function cancel()
    {
        /*
         * 注销帐号前提条件
         * 1)30天内无任何异常或敏感操作(异地登录,更换手机,更换微信/qq绑定)
         * 2)帐号未被永久封禁
         * 3)是否在家族，在无法退出
         * 注销后该手机号90天内无法再次注册
         */
        $user_id = $this->auth->id;
        $user = db('user')->field('*')->find($user_id);

        $this->check_blacklist($user_id, $user['imei'], $user['mobile']);

        db('union_user')->where(['user_id' => $user_id, 'status' => 2])->value('id') && $this->error(__('You are in the union'));

        db('user')->where(['id' => ['=', $user_id]])->update(['nickname' => '用户已注销', 'status' => 'death']);

        $this->auth->logout();
        $this->success(__('Account cancelled'));
    }


    /**
     * @ApiTitle    (修改会员个人信息)
     * @ApiMethod   (post)
     * @ApiParams   (name="nickname", type="string", required=false, rule="", description="昵称")
     * @ApiParams   (name="avatar", type="string", required=false, rule="", description="头像地址")
     * @ApiParams   (name="gender", type="int", required=false, rule="in:0,1", description="性别:0=女,1=男")
     * @ApiParams   (name="area", type="string", required=false, rule="", description="地区")
     * @ApiParams   (name="voice", type="string", required=false, rule="", description="语音")
     * @ApiParams   (name="voice_size", type="int", required=false, rule="", description="语音时长s")
     * @ApiParams   (name="constellation", type="string", required=false, rule="", description="星座")
     * @ApiParams   (name="birthday", type="string", required=false, rule="", description="生日")
     * @ApiParams   (name="bio", type="string", required=false, rule="", description="个人签名")
     * @ApiParams   (name="interest_ids", type="string", required=false, rule="", description="个人标签id,多个用逗号隔开")
     * @throws
     */
    public function profile()
    {
        try {
            $user = $this->auth->getUser();
            $nickname = Shield::sensitive_filter($this->request->request('nickname'));
            $nickname = preg_replace('/\p{C}+/u', "", trim($nickname));
            $gender = $this->request->request('gender');
            $area = $this->request->request('area');
            $voice = $this->request->request('voice');
            $voice_size = $this->request->request('voice_size');
            $constellation = $this->request->request('constellation');
            $birthday = $this->request->request('birthday') ?: null;
            $age = $this->request->request('age') ?: null;
            $bio = Shield::sensitive_filter($this->request->request('bio', '', 'trim,strip_tags,htmlspecialchars'));
            $interest_ids = $this->request->request('interest_ids');

            $isNicknameEdit = false;
            if (isset($nickname) && $nickname != '') {
                if (mb_strlen($nickname) > 20) {
                    throw new ApiException(__('Nickname length exceeds limit'));
                }
                if (strpos($nickname, ' ') !== false) {
                    throw new ApiException(__('Nickname cannot contain spaces'));
                }

                $exists = db('user')->where('nickname', $nickname)->where('id', '<>', $this->auth->id)->find();
                if ($exists) {
                    throw new ApiException(__('Nickname already taken'));
                }
                if ($user->nickname != $nickname) {
                    $nicknameNum = db('user_nickname')->where('user_id', $user['id'])
                        ->whereTime('create_time', 'm')
                        ->count();
                    //获取昵称每月可修改次数
                    if (get_site_config('edit_nickname_month_num') > $nicknameNum) {
                        db('user_nickname')->insert(['user_id' => $user['id'], 'nickname' => $user->nickname]);
                        $user->nickname = $nickname;
                        $isNicknameEdit = true;

                        //更新哈希
                        redis()->hMSet(RedisService::USER_BASE_LISTS_KEY . $user->id, ['nickname' => $nickname]);
                    } else {
                        throw new ApiException(__('Monthly nickname change limit reached'));
                    }
                }
            }
            $isGenderEdit = false;
            if (isset($gender) && $gender != '') {
                //获取性别可修改次数
                if (get_site_config('edit_gender_num') > $user->edit_gender_num) {
                    $user->edit_gender_num = $user->edit_gender_num + 1;
                    $user->gender = $gender;
                    $isGenderEdit = true;
                } else {
                    throw new ApiException(__('Gender cannot be modified'));
                }
            }
            if (isset($area) && $area != '') {
                $user->area = $area;
            }
            if (isset($voice) && $voice != '') {
                $user->voice = $voice;
            }
            if (isset($voice_size) && $voice_size != '') {
                $user->voice_size = $voice_size;
            }
            if (isset($constellation) && $constellation != '') {
                $user->constellation = $constellation;
            }
            if (isset($birthday) && $birthday != '') {
                $user->birthday = $birthday;

                $user->constellation = \util\Constellation::getConstellation($birthday, 'zh');
                $user->age = date('Y') - date('Y', strtotime($birthday));
            }
            if (isset($age) && $age != '') {
                $user->age = $age;
            }
            if (isset($bio) && $bio != '') {
                $user->bio = $bio;
            }

            if (mb_strlen($bio) > 60) {
                throw new ApiException(__('Signature content must be within 60 characters'));
            }
            if (isset($interest_ids) && $interest_ids != '') {
                $user->interest_ids = $interest_ids;
            }

            // 图片处理 审核
            $delete_images = [];
            $image_types = ['avatar'];
            if (get_site_config('user_image_audit') == 1) {
                $image_audit = [];
                foreach ($image_types as $image_type) {
                    $image_url = $this->request->request($image_type, '', 'trim,strip_tags,htmlspecialchars');
                    if ($image_url) {
                        $third = parse_url($image_url);
                        if (
                            $image_type == 'avatar' && (strpos($image_url, '/assets/avatar') === 0 ||
                                (!empty($third['scheme']) && !empty($third['host'])))
                        ) {
                            $user->avatar = $image_url; // 系统头像和第三方登录头像直接通过
                        } else {
                            $image_audit[] =
                                ['url' => $image_url, 'status' => 0, 'user_id' => $user['id'], 'img_type' => $image_type];
//                        Enigma::send_check_message("会员中心-->头像审核  - 用户: {$user['id']} 提交了新的记录，需要审核！");
                        }
                    }
                }
                if ($image_audit) {
                    // 重复提交的同类型待审图片 加入删除列表, 并直接删除记录
                    $where = ['status' => 0, 'user_id' => $user['id'],];
                    $where['img_type'] = ['in', array_column($image_audit, 'img_type')];
                    $list = db('user_audit_image')->where($where)->column('url');
                    $delete_images = array_merge($delete_images, $list);
                    db('user_audit_image')->where($where)->delete();
                    db('user_audit_image')->insertAll($image_audit);
                }
            } else {
                foreach ($image_types as $image_type) {
                    $image_url = $this->request->request($image_type, '', 'trim,strip_tags,htmlspecialchars');
                    if ($image_url) {
                        $user[$image_type] && $delete_images[] = $user[$image_type];
                        $user->avatar = $image_url;
                    }
                }
            }
            if ($delete_images) {
                $minio = new Minio();
                foreach ($delete_images as $delete_image) {
                    $minio->deleteObject($delete_image);
                }
            }
            $user->save();

            if ((isset($nickname) && $nickname != '' && $isNicknameEdit) || (isset($gender) && $gender != '' && $isGenderEdit)) {
                $imService = new ImService();
                $imService->updateUser($this->auth->id, $nickname, $this->auth->avatar);
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error(show_error_notify($e));
        }

        $this->success(__('Operation completed'));
    }

    /**
     * 检查用户是否在黑名单中
     * @param        $user_id
     * @param string $imei
     * @param string $mobile
     * @throws Exception
     */
    private function check_blacklist($user_id, $imei = '', $mobile = '')
    {
        $is_black = db('blacklist')
            ->where("(type=1 and number = '{$user_id}') OR (type = 2 and number = '{$imei}') or (type=3 and number='{$mobile}')")
            ->where('end_time', '>', datetime())
            ->find();
        if (!$is_black) {
            return;
        }
        if ($is_black['form'] == 1) {
            $this->error(__('Account has been banned'), null, 401);
        } elseif ($is_black['form'] == 2) {
            $this->error(__('Network error'), null, 402);
        }
    }

    /**
     * @ApiTitle  (获取用户是否正常)
     * @ApiSummary  ("前端打开IM聊天前判断对方是否被封")
     * @ApiMethod   (get)
     * @ApiParams   (name="user_id", type="int", required=true, rule="", description="用户id")
     */
    public function get_status()
    {
        $userId = input('user_id');
        $user = db('user')->field('imei,mobile')->find($userId);
        if (UserService::inBlacklist($userId, $user['imei'], $user['mobile'])) {
            $this->error(__('Account has been banned'));
        }
        $this->success();
    }


    /**
     * @ApiTitle (获取个人/简单信息)
     * @ApiMethod   (get)
     * @ApiParams   (name="user_id", type="int", required=false, rule="", description="用户id--当前登录用户不用传")
     */
    public function get_base_info()
    {
        $user_id = input('user_id') ?: $this->auth->id;
        $res = db('user')->where('id', $user_id)->field('id,gender,constellation,age,beautiful_id,nickname,avatar')->find() ?: [];
        $res['is_blacklist'] = (bool)(db('user_blacklist')
            ->where(['user_id' => $this->auth->id, 'to_user_id' => $user_id])->value('to_user_id'));
        $res['is_follow'] = (bool)(db('user_follow')
            ->where(['user_id' => $this->auth->id, 'to_user_id' => $user_id])->value('to_user_id'));
        $res['current_room'] = redis()->hGet(RedisService::USER_NOW_ROOM_KEY, $user_id) ?: 0;
        $res['is_union_member'] = (int)db('union_user')->where('user_id', $user_id)->where('status', 'in', [2, 3, 6])->count(1);


        $this->success('', $res);
    }


    /**
     * 获取所有兴趣列表
     * @ApiMethod   (get)
     * @ApiReturnParams   (name="type", type="int",  description="類型:1=交友,2=娛樂,3=遊戲")
     */
    public function get_interest_list()
    {
        $list = db('interest')->field('id,type,name')->cache('config:interest', 0, 'config')->order('id asc')->select();
        $this->success('', $list);
    }

    /**
     * 获取默认系统头像
     */
    public function default_avatar()
    {
        $this->success('', config('app.default_avatar_gender'));
    }


    /**
     * 获取随机昵称
     * @ApiMethod   (get)
     * @ApiReturnParams   (name="nickname", type="string",  description="昵称")
     */
    public function get_nickname()
    {
        $this->success('', ['nickname' => (new ChinaName())->getNickname()]);
    }

    /**
     * @ApiTitle    (更新需要审核的头像)
     * @ApiParams   (name="url", type="string", required=true, rule="", description="图片URL")
     * @ApiMethod   (get)
     */
    public function updateAvatar()
    {
        $user_id = $this->auth->id;
        $url = input('url');
        $sel = db('user_audit_image')->where(['user_id' => $user_id, 'img_type' => 'avatar', 'status' => 0])->find();
        $status = get_site_config('user_image_audit') ? 0 : 1;
        if ($sel) {
            db('user_audit_image')
                ->where(['user_id' => $user_id, 'img_type' => 'avatar', 'status' => 0])
                ->setField(['url' => $url, 'status' => $status,]);
        } else {
            db('user_audit_image')->insert(['url' => $url, 'status' => $status, 'user_id' => $user_id, 'img_type' => 'avatar',]);
        }
        if ($status) {
            Db::name('user')->where('id', $user_id)->setField('avatar', $url);
        }
        // send_check_message("形象照(会员中心-->头像审核) - 用户: {$user_id} 提交了新的形象照，需要审核！");
        $this->success();
    }

    /**
     * @ApiTitle    (更新需要审核的形象照)
     * @ApiMethod   (post)
     * @ApiParams   (name="url", type="string", required=true, rule="", description="图片 URL")
     */
    public function updateImage()
    {
        // todo optimize
        $user_id = $this->auth->id;
        $imageStr = input('url');

        $where = ['user_id' => $user_id, 'img_type' => 'image'];
        $images1 = db('user_audit_image')->where($where)->where('status', 1)->count();
        $images0 = db('user_audit_image')->where($where)->where('status', 0)->count();
        if ($images1 < 9 && ($images1 + $images0) >= 9) {
            $this->error(__('You can only upload a maximum of %s files', 9));
        }

        Db::startTrans();
        try {
            $deleteImages = [];
            $where = ['user_id' => $user_id, 'img_type' => 'image', 'status' => 1];
            if (get_site_config('user_image_audit') == 1) {
                if ($images1 >= 9) {
                    //删除待审核图片
                    $deleteImages = db('user_audit_image')->where($where)->order('update_time asc')->limit(1)->column('url');
                    db('user_audit_image')->where($where)->order('update_time asc')->limit(1)->delete();
                }
                $insertData[] = [
                    'url'         => $imageStr,
                    'status'      => 0,
                    'user_id'     => $user_id,
                    'img_type'    => 'image',
                    'create_time' => datetime()
                ];
                db('user_audit_image')->insertAll($insertData);
            } else {
                if ($images1 >= 9) {
                    //删除待审核图片
                    $deleteImages = db('user_audit_image')->where($where)->order('update_time asc')->limit(1)->column('url');
                    db('user_audit_image')->where($where)->order('update_time asc')->limit(1)->delete();
                }

                $insertData[] = [
                    'url'         => $imageStr,
                    'status'      => 1,
                    'user_id'     => $user_id,
                    'img_type'    => 'image',
                    'create_time' => datetime()
                ];
                db('user_audit_image')->insertAll($insertData);

                $images = db('user_audit_image')->where('user_id', $user_id)->where('status', 1)->column('url');
                if (count($images)) {
                    $imagesStr = implode(',', $images);
                    $updateImage = trim($imagesStr, ',');
                } else {
                    $updateImage = $imageStr;
                }
                db('user')->where('id', $user_id)->update(['image' => $updateImage, 'create_time' => datetime()]);
            }

            if ($deleteImages) {
                $minio = new Minio();
                foreach ($deleteImages as $deleteImage) {
                    $minio->deleteObject($deleteImage);
                }
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            Log::error($e->getMessage());
            $this->error($e->getMessage());
        }
        $this->success();
    }
}
