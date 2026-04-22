<?php


namespace app\api\controller;

use think\Log;

/**
 * 举报与反馈
 * @ApiWeigh
 */
class UserFeedback extends Base
{
    protected $noNeedLogin = ['blacklist', 'blacklist_h5'];
    protected $noNeedRight = ['*'];

    /**
     * @ApiTitle    (获取举报与反馈类型)
     * @ApiParams   (name="type", type="int",  required=true, rule="", description="对象类型1=举报,2=反馈")
     * @ApiMethod   (get)
     */
    public function get_type()
    {
        if (input('type') == 1) {
            $tab = [
                ['id' => '1', 'name' => __('欺诈')],
                ['id' => '2', 'name' => __('政治敏感')],
                ['id' => '3', 'name' => __('侮辱诽谤')],
                ['id' => '4', 'name' => __('色情低俗')],
                ['id' => '5', 'name' => __('广告引流')],
                ['id' => '6', 'name' => __('侵权')],
                ['id' => '7', 'name' => __('赌博')],
                ['id' => '8', 'name' => __('破坏游戏环境')],
            ];
        } else {
            $tab = [
                ['id' => '1', 'name' => __('功能异常')],
                ['id' => '2', 'name' => __('体验问题')],
                ['id' => '3', 'name' => __('产品建议')],
                ['id' => '4', 'name' => __('其他')],
            ];
        }

        $this->success('', $tab);
    }


    /**
     * @ApiTitle    (提交举报和反馈)
     * @ApiMethod   (post)
     * @ApiParams   (name="form", type="int",  required=true, rule="", description="类型1=举报,2=反馈")
     * @ApiParams   (name="tag", type="string",  required=true, rule="", description="get_type获取的文本")
     * @ApiParams   (name="type", type="int",  required=false, rule="", description="對象類型1=个人,2=房间")
     * @ApiParams   (name="comment", type="string",  required=true, rule="", description="举报文本内容")
     * @ApiParams   (name="target_id", type="int",  required=false, rule="", description="目标ID")
     * @ApiParams   (name="image", type="string",  required=false, rule="", description="证据截图/视频")
     *
     */
    public function add()
    {
        $user_id = $this->auth->id;
        $this->operate_check('feedback_lock:' . $user_id, 2);
        $form = input('form');
        $tag = input('tag');
        $type = input('type');
        $target_id = input('target_id');
        $comment = input('comment');
        $image = input('image');
        try {
            db('user_feedback')->insert([
                'form'      => $form,
                'user_id'   => $user_id,
                'type'      => $type,
                'comment'   => $comment,
                'image'     => $image,
                'target_id' => $target_id,
                'tag'       => $tag
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            error_log_out($e);
            $this->error($e->getMessage());
        }
        //Enigma::send_check_message("会员中心-->用户举报与反馈  - 用户: {$user_id} 提交了新的记录，需要审核！");
        $this->success(__('Operation completed'));
    }

    /**
     * @ApiTitle    (违规账号（原小黑屋）)
     * @ApiParams   (name="page",      type="int",  required=false, rule="", description="页码,默认1")
     * @ApiParams   (name="size",      type="int",  required=false, rule="", description="分页大小,默认20")
     */
    public function blacklist()
    {
        $page = input('page') ?: 1;
        $size = input('size') ?: 20;

        $res = db('blacklist b')
            ->join('user u', 'b.number = u.id')
            ->where('type = 1')
            ->field('b.number as user_id,u.nickname,u.avatar,u.gender,end_time')
            ->order('b.id desc')
            ->page($page, $size)
            ->select();
        foreach ($res as $k => &$v) {
            $v['days'] = date_diff(date_create(date('Y-m-d')), date_create($v['end_time']))->days;
            if ($v['days'] > 365) {
                $v['days'] = '永久封禁';
            } elseif ($v['days'] == 0) {
                $v['days'] = '封禁1天';
            } else {
                $v['days'] = '封禁' . $v['days'] . '天';
            }
            //$v['days'] = __('%s',$v['days']);
        }
        $this->success('', $res);
    }

    /**
     * @ApiTitle    (违规账号（H5接口）)
     * @ApiParams   (name="page",      type="int",  required=false, rule="", description="页码,默认1")
     * @ApiParams   (name="size",      type="int",  required=false, rule="", description="分页大小,默认20")
     */
    public function blacklist_h5()
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
        header("Access-Control-Allow-Headers: Content-Type");
        $page = input('page') ?: 1;
        $size = input('size') ?: 20;

        $res = db('blacklist b')
            ->join('user u', 'b.number = u.id')
            ->where('type = 1')
            ->field('u.nickname,u.avatar,u.gender,end_time')
            ->order('b.id desc')
            ->page($page, $size)
            ->select();
        foreach ($res as $k => &$v) {
            $v['days'] = date_diff(date_create(date('Y-m-d')), date_create($v['end_time']))->days;
            if ($v['days'] > 365) {
                $v['days'] = '永久封禁';
            } elseif ($v['days'] == 0) {
                $v['days'] = '封禁1天';
            } else {
                $v['days'] = '封禁' . $v['days'] . '天';
            }
            //$v['days'] = __('%s',$v['days']);
        }
        $this->success('', $res);
    }
}

