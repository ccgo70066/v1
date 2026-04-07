<?php

namespace app\api\controller;

use app\common\library\ApiEnhance;
use app\common\model\ChannelBlacklist;
use app\common\model\Shield;
use think\Env;
use think\Log;
use util\Ffmpeeg;
use util\Minio;
use util\OpenSSL3DES;

/**
 * APP环境配置
 * @ApiWeigh    (9999)
 */
class App extends ApiBase
{

    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];
    protected $noNeedSign = ['*'];

    /**
     * @ApiInternal
     */
    public function config_bak()
    {
        [$config, $data] = $this->get_config();
        $this->resultWithoutEncode($config, $data, 1);
    }


    /**
     * @ApiInternal
     * @return void
     */
    public function config()
    {
        [$config, $data] = $this->get_config();
        echo $data;
    }


    /**
     * 上传文件
     * @ApiMethod (POST)
     * @ApiParams   (name="scene", type="int", required=true, rule="between:0,100", description="存儲場景:0=默認,1=用戶資料,2=廣場,3=房間和家族,4=聊天,5=签名-主播,6=签名-家族,99=前端日志")
     * @ApiParams   (name="file", type="file", required=false, rule="", description="文件")
     */
    public function upload()
    {
        $file = request()->file('file');
        $scene = input('scene') ?: '0';

        if (empty($file)) {
            $this->error(__('No file upload or server upload limit exceeded'));
        }
        $scene_arr = [
            'app_upload',
            'avatar',
            'moment',
            'room',
            'chat',
            'sign_anchor',
            'sign_union',
        ];
        $uploadDir = $scene_arr[$scene] . '/';

        if ($scene == 99) {
            $uploadDir = 'user_log/' . $this->auth->id . '/';
        }
        $fileInfo = $file->getInfo();
        $suffix = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        $mimetypeArr = ['jpg', 'png', 'bmp', 'jpeg', 'gif', 'webp', 'rar', 'wav', 'mp4', 'mp3', 'webm', 'aac', 'mov', 'txt'];
        //验证文件后缀
        if (!in_array($suffix, $mimetypeArr)) {
            $this->error(__('Uploaded file format is limited'));
        }
        if ($fileInfo['size'] > 10 * 1024 * 1024) {
            $this->error(__('Upload file size exceeds the limit'));
        }
        //验证是否为图片文件
        if (in_array(
                $fileInfo['type'],
                ['image/gif', 'image/jpg', 'image/jpeg', 'image/bmp', 'image/png', 'image/webp']
            ) || in_array(
                $suffix,
                ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'webp']
            )) {
            $imgInfo = getimagesize($fileInfo['tmp_name']);
            if (!$imgInfo || !isset($imgInfo[0]) || !isset($imgInfo[1])) {
                $this->error(__('Uploaded file is not a valid image'));
            }
        }
        $lockName = date('dh');
        $key = incrLock($lockName, 3600);
        $fileName = date('Ymdh') . random_int(1000, 9999) . $key . '.' . $suffix;

        $minio = new Minio();
        $targetFile = $fileInfo['tmp_name'];
        in_array($suffix, ['gif', 'jpg', 'jpeg', 'png', 'webp']) &&
        $targetFile = $this->compressImage($fileInfo['tmp_name']);
        $upload = $minio->putObject($targetFile, $uploadDir . $fileName);
        trace('upload');
        trace($upload);
        if ($fileInfo['type'] == 'video/mp4') {
            $ffmpeeg = new Ffmpeeg();
            $cover_url = $uploadDir . $fileName . '.jpg';
            if ($ffmpeeg->generateVideoCover($file->getRealPath(), $file->getRealPath() . '.jpg')) {
                $upload = $minio->putObject($file->getRealPath() . '.jpg', $cover_url, 'image/jpeg');
            }
        }
        unlink($targetFile);
        $this->success(__('success'), ['url' => '/' . ltrim($uploadDir, '/') . $fileName]);
    }


    private function compressImage($sourcePath, $targetPath = '', $quality = 50)
    {
        $targetPath == '' && $targetPath = $sourcePath . '.jpg';
        $info = getimagesize($sourcePath);
        $extension = image_type_to_extension($info[2], false);
        $createFun = "imagecreatefrom$extension";
        $image = $createFun($sourcePath);
        imagejpeg($image, $targetPath, $quality);
        imagedestroy($image);
        return $targetPath;
    }


    /**
     * 上传文件-H5
     * @ApiMethod (POST)
     * @ApiParams   (name="scene", type="int", required=true, rule="between:0,8", description="存儲場景:0=默認,1=用戶資料,2=廣場,3=房間和家族,4=聊天,5=签名-主播,6=签名-家族")
     * @ApiParams   (name="file", type="file", required=false, rule="", description="文件")
     */
    public function upload_h5()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
        header("Access-Control-Allow-Headers: Content-Type");
        $file = request()->file('file');
        if (empty($file)) {
            $this->error(__('No file upload or server upload limit exceeded'));
        }
        $scene_arr = [
            'app_upload',
            'avatar',
            'moment',
            'room',
            'chat',
            'sign_anchor',
            'sign_union',
        ];
        $scene = input('scene') ?: '0';
        $uploadDir = $scene_arr[$scene] . '/';

        $fileInfo = $file->getInfo();
        $suffix = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        $mimetypeArr = ['jpg', 'png', 'bmp', 'jpeg', 'gif', 'webp', 'rar', 'wav', 'mp4', 'mp3', 'webm', 'aac', 'mov'];
        //验证文件后缀
        if (!in_array($suffix, $mimetypeArr)) {
            $this->error(__('Uploaded file format is limited'));
        }
        if ($fileInfo['size'] > 10 * 1024 * 1024) {
            $this->error(__('Upload file size exceeds the limit'));
        }
        //验证是否为图片文件
        if (in_array(
                $fileInfo['type'],
                ['image/gif', 'image/jpg', 'image/jpeg', 'image/bmp', 'image/png', 'image/webp']
            ) || in_array(
                $suffix,
                ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'webp']
            )) {
            $imgInfo = getimagesize($fileInfo['tmp_name']);
            if (!$imgInfo || !isset($imgInfo[0]) || !isset($imgInfo[1])) {
                $this->error(__('Uploaded file is not a valid image'));
            }
        }
        $lockName = date('dh');
        $key = incrLock($lockName, 3600);
        $fileName = date('Ymdh') . random_int(1000, 9999) . $key . '.' . $suffix;

        $minio = new Minio();
        $upload = $minio->putObject($fileInfo['tmp_name'], $uploadDir . $fileName);
        if ($fileInfo['type'] == 'video/mp4') {
            $ffmpeeg = new Ffmpeeg();
            $cover_url = $uploadDir . $fileName . '.jpg';
            if ($ffmpeeg->generateVideoCover($file->getRealPath(), $file->getRealPath() . '.jpg')) {
                $upload = $minio->putObject($file->getRealPath() . '.jpg', $cover_url, 'image/jpeg');
            }
        }
        $this->success(__('success'), ['url' => '/' . ltrim($uploadDir, '/') . $fileName]);
    }

    /**
     * @ApiTitle    (版本更新)
     * @ApiSummary  (post)
     * @ApiMethod   (get)
     * @ApiParams   (name="system", type="string", required=true, rule="in:1,2", description="平台:1=IOS,2=ANDROID")
     * @ApiParams   (name="version", type="string", required=true, rule="", description="版本号")
     * @ApiReturnParams (name="force", type="string", required=true, rule="", description="强制更新:1=是,0=否")
     * @ApiReturn   ({ "code": 1, "msg": "", "data": { "version": "0.0.2", "download": "https://www.baidu.com", "force": 0, "comment": "" } })
     */
    public function get_new_version()
    {
        $system = $this->request->param('system') == 1 ? 'IOS' : 'ANDROID';
        $version = $this->request->param('version');

        $user = '101164';
        $this->auth->id == $user && $this->success('', new \ArrayObject());

        $data = db('channel_package')->alias('cp')
            ->field('cp.id,cp.channel_id')
            ->where([
                'cp.system'  => $system,
                'cp.version' => $version,
                'cp.status'  => 1,
            ])
            ->find();
        if ($system == 'IOS') {
            Log::error('IOS');
            Log::error($data);
            Log::error(Db()->getLastSql());
        }
        if ($data) {
            $lastData = db('channel_package')->field('version,download,force,comment')->where([
                'channel_id' => $data['channel_id'],
                'system'     => $system,
                'status'     => 1,
            ])->order('version desc')->find();
            if ($system == 'IOS') {
                Log::error('IOS' . __LINE__);
                Log::error($lastData);
                Log::error(Db()->getLastSql());
            }
            if ($lastData && version_compare($lastData['version'], $version) > 0) {
                // 企业签的版本直接更新成App Store的版本
                // $share_url = 'https://wx.ttworknet.com/';
                // $lastData['download'] = $system == 'IOS' ? $share_url : $lastData['download'];

                if ($lastData['force'] == 0) {
                    $between = db('channel_package')->where([
                        'channel_id' => $data['channel_id'],
                        'system'     => $system,
                        'status'     => 1,
                        'version'    => [['>', $version], ['<', $lastData['version']]]
                    ])->column('force');
                    if ($system == 'IOS') {
                        Log::error('IOS' . __LINE__);
                        Log::error($between);
                        Log::error(Db()->getLastSql());
                    }
                    foreach ($between as $item) {
                        if ($item == 1) {
                            $lastData['force'] = 1;
                            break;
                        }
                    }
                }
                $lastData['download'] = cdnurl($lastData['download']);
                $this->success('', $lastData);
            }
        }

        $this->success('', new \ArrayObject());
    }

    /**
     * 获取版本控制
     */
    public function blacklist()
    {
        $appid = $this->appid;
        $system = $this->system;
        $version = $this->version;

        $this->success('', ChannelBlacklist::get_blacklist($appid, $system, $version));
    }


    /**
     * 获取敏感词
     */
    public function get_illegal_word()
    {
        $this->success(__('success'), Shield::get_illegal_word());
    }

    /**
     * @return mixed
     */
    private function get_config()
    {
        $self_url = url('/', '', '', true);
        $config = [
            'api_url'         => Env::get('app.api_url', $self_url),
            'cdn_url'         => Env::get('app.cdn_url', cdnurl('')),
            'ws_url'          => Env::get('app.web_socket_url'),
            'share_url'       => Env::get('app.share_url'), // 分享链接
            'share_text'      => 'hello',
            'kf_id'           => config('app.kf_id'),
            'request_key'     => Env::get('api.request_encode_key'),
            'response_key'    => Env::get('api.response_encode_key'),
            'document_prefix' => Env::get('app.page_url', $self_url),
            'safety_reminder' => get_site_config('safety_reminder') ?: "",   //私聊安全提醒
            'grey_mode'       => get_site_config('grey_mode'),//哀悼灰色模式
            'show_level'      => get_site_config('show_level'), // 飘屏显示等级限制(>=)
            'version'         => get_site_config('base_version'),  // 配置文件版本号
        ];
        $data = ApiEnhance::instance()->encode(json_encode($config), Env::get('api.config_key'));
        return [$config, $data];
    }

    /**
     * 配置推送
     * @ApiInternal
     * @return void
     */
    public function push_config()
    {
        $data = ['version' => get_site_config('base_version')];
        //board_notice(Message::CMD_CONFIG_UPDATE, $data);
        \GatewayClient\Gateway::sendToAll(json_encode($data));
        $this->success(__('operation success'));
    }

}
