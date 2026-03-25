<?php

//上传配置
use think\Env;

return [
    /**
     * 上传地址,默认是本地上传
     */
    'uploadurl'  => 'ajax/upload_to_minio',
    /**
     * CDN地址
     */
    'cdnurl'     => Env::get('app.cdn_url'),
    /**
     * 文件保存格式
     */
    'savekey'    => '/uploads/{year}{mon}{day}/{filemd5}{.suffix}',
    /**
     * 最大可上传大小
     */
    'maxsize'    => '800mb',
    /**
     * 可上传的文件类型
     * 如配置允许 pdf,ppt,docx,svg 等可能含有脚本的文件时，请先从服务器配置此类文件直接下载而不是预览
     */
    'mimetype'   => 'jpg,png,bmp,jpeg,gif,webp,zip,rar,xls,xlsx,wav,mp4,mp3,webm,webp,pdf,svga,svg,apk,ipa,pag',
    /**
     * 是否支持批量上传
     */
    'multiple'   => false,
    /**
     * 上传超时时长，这里仅用于JS上传超时控制
     */
    'timeout'  => 60000,
    /**
     * 是否支持分片上传
     */
    'chunking'   => false,
    /**
     * 默认分片大小
     */
    'chunksize'  => 2097152,
    /**
     * 完整URL模式
     */
    'fullmode'   => false,
    /**
     * 缩略图样式
     */
    'thumbstyle' => '',
];
