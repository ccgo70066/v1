<?php

namespace app\api\controller;

/**
 * 广播交友
 */
class Broadcast extends Base
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * @ApiTitle    (获取广告图)
     * @ApiParams   (name="show_type",type="int",  required=true, rule="require", description="展示位置:1=首页,2=房间,5=钱包,6=首页活动")
     * @ApiReturnParams   (name="Action", type="int", description="1=内部H5,5=充值页,7=外部H5,8=首充活动")
     * @ApiMethod   (get)
     **/
    public function get_billboard()
    {
        $show_type = input('show_type');
        $sel = db('channel_billboard')
            ->where('status', 1)
            ->where("find_in_set({$show_type}, `position`)")
            ->where([
                'show_start_time' => ['lt', datetime(time())],
                'show_end_time'   => ['gt', datetime(time())]
            ])
            ->field("id,title,image,action_url,action,type")
            ->order('weigh desc')
            ->select();
        $this->success('', $sel);
    }


    /**
     * @ApiTitle    (获取官方公告列表)
     * @ApiMethod   (get)
     * @ApiParams   (name="show_type",type="int",  required=true, rule="require", description="悬浮展示位置:2=房间")
     * @ApiParams   (name="size",    type="int",  required=false, rule="", description="获取条数,默认10")
     * @ApiParams   (name="start_id",    type="int",  required=false, rule="", description="起始id")
     * @ApiReturnParams   (name="Action", type="int", description="1=内部H5,5=充值页,7=外部H5,8=首充活动")
     **/
    public function notice_list()
    {
        $size = input('size') ?: 10;
        $start_id = input('start_id') ?: 0;
        $show_type = input('show_type');

        $where = ['show_start_time' => ['lt', datetime(time())], 'show_end_time' => ['gt', datetime(time())]];
        if ($start_id) {
            $where['id'] = ['<', $start_id];
        }
        if ($show_type) {
            $where['show_type'] = $show_type;
            $where['icon'] = ['<>', ''];
        }
        $result = db('channel_notice')
            ->field("id,title,cover_image,icon,content,action,action_url,create_time")
            ->where('status', 1)
            ->where($where)
            ->order('id', 'desc')
            ->page(1, $size)
            ->select();

        $this->success('', $result);
    }


}
