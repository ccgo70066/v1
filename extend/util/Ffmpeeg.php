<?php

namespace util;

/**
 * ffmpeg 工具类
 */
class Ffmpeeg
{
    /**
     * @param string $video 视频地址
     * @param string $cover 封面保存地址
     * @param int    $time
     * @param int    $width
     * @param int    $height
     * @return bool
     */
    function generateVideoCover(string $video, string $cover, int $time = 1, int $width = 0, int $height = 0): bool
    {
        if (!file_exists($video)) {
            return false;
        }
        if ($width != 0) {
            //多一个-s 指定图片的宽高比
            $command = "ffmpeg -i {$video} -y -f mjpeg -ss $time -t 1 -s {$width}x{$height} $cover";
        }else {
            $command = "ffmpeg -i {$video} -y -f mjpeg -ss $time -t 1 $cover";
        }

        exec($command, $output, $return_val);
        if ($return_val == 0) {
            return true;
        }
        return false;
    }

    /**
     * 获得视频文件的总长度时间
     */
    function getDuration($video)
    {
        $vtime = exec("ffmpeg -i " . $video . " 2>&1 | grep 'Duration' | cut -d ' ' -f 4 | sed s/,//");//总长度
        $duration = explode(":", $vtime);
        $duration_in_seconds = (int)$duration[0] * 3600 + (int)$duration[1] * 60 + (int)$duration[2];//转化为秒

        return $duration_in_seconds;
    }


    function Mp4typeToH264($video)
    {
//        $vtime = exec("ffmpeg -i ".$video." -vcodec libx264 ".$video);//转码
        $vtime = exec("ffmpeg -i ".$video." -vcodec h264 ".$video);
        return $vtime;
    }

}
