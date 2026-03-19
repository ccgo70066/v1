<?php

namespace util;

class File
{

    /**
     * 返回文件格式 imei
     * @param mixed $file_extension 文件名
     * @return string      文件格式
     */
    public static function file_format($file_extension)
    {
        // 取文件后缀名
        $file_extension = strtolower(pathinfo($file_extension, PATHINFO_EXTENSION));
        // 图片格式
        $image = array('webp', 'jpg', 'png', 'ico', 'bmp', 'gif', 'tif', 'pcx', 'tga', 'bmp', 'pxc', 'tiff', 'jpeg', 'exif', 'fpx', 'svg', 'psd', 'cdr', 'pcd', 'dxf', 'ufo', 'eps', 'ai', 'hdri');
        // 视频格式
        $video = array('mp4', 'avi', '3gp', 'rmvb', 'gif', 'wmv', 'mkv', 'mpg', 'vob', 'mov', 'flv', 'swf', 'mp3', 'ape', 'wma', 'aac', 'mmf', 'amr', 'm4a', 'm4r', 'ogg', 'wav', 'wavpack');
        // 压缩格式
        $zip = array('rar', 'zip', 'tar', 'cab', 'uue', 'jar', 'iso', 'z', '7-zip', 'ace', 'lzh', 'arj', 'gzip', 'bz2', 'tz');
        // 文档格式
        $text = array('exe', 'doc', 'ppt', 'xls', 'wps', 'txt', 'lrc', 'wfs', 'torrent', 'html', 'htm', 'java', 'js', 'css', 'less', 'php', 'pdf', 'pps', 'host', 'box', 'docx', 'word', 'perfect', 'dot', 'dsf', 'efe', 'ini', 'json', 'lnk', 'log', 'msi', 'ost', 'pcs', 'tmp', 'xlsb');

        $arr = compact('image', 'video', 'zip', 'text');
        foreach ($arr as $k => $v) {
            if (in_array($file_extension, $v)) {
                return $k;
            }
        }
        return 'image';
    }
}
