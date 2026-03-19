<?php

namespace util;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use think\Env;
use think\Log;

//文档地址:https://www.bookstack.cn/read/MinioCookbookZH/52.md
//官方文档:https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_installation.html
//sdk-composer: composer require aws/aws-sdk-php
class Minio
{
    private $access_key = '';
    private $secret_key = '';
    private $bucket = '';
    private $endpoint = '';
    private $region = '';
    private $S3Client;

    public function __construct($bucket = '')
    {
        //IM聊天存在指定的bucket里，除了IM聊天图片上传，其他不填
        if ($bucket) {
            $this->bucket = $bucket;
        }
        if (Env::get('app.server') == 'prod') {
            $this->bucket = Env::get('prod.minio_bucket');
            $this->endpoint = Env::get('prod.minio_endpoint');
            $this->region = Env::get('prod.minio_region');
            $this->access_key = Env::get('prod.minio_access_key');
            $this->secret_key = Env::get('prod.minio_secret_key');
        } else {
            $this->bucket = Env::get('test.minio_bucket');
            $this->endpoint = Env::get('test.minio_endpoint');
            $this->region = Env::get('test.minio_region');
            $this->access_key = Env::get('test.minio_access_key');
            $this->secret_key = Env::get('test.minio_secret_key');
        }

        $this->S3Client = new S3Client([
            'version'                 => 'latest',
            'region'                  => $this->region,
            'endpoint'                => $this->endpoint,
            'use_path_style_endpoint' => true,
            'credentials'             => [
                'key'    => $this->access_key,
                'secret' => $this->secret_key,
            ],
        ]);
    }

    /** 上传文件到minio
     * @param $file_Path string 本地文件路径
     * @param $filename  string 保存在minio的路径 指定目录方式:目录/文件名(目录不存在会自动创建) 例:avatar/123.png或根目录123.png
     * @return mixed|string|null 返回上传成功后的访问地址:例:http://4p1383640h.zicp.vip/kt-file/test/test1315.png
     */
    public function putObject($file_Path, $filename, $contentType = '')
    {
        try {
            $result = $this->S3Client->putObject([
                'Bucket'      => $this->bucket,  //
                'Key'         => $filename,   //上传之后的文件名
                'Body'        => fopen($file_Path, 'r'), //文件内容
                'ACL'         => 'public-write',
                'ContentType' => $contentType ?: mime_content_type($file_Path)
            ]);
            return $result->get('ObjectURL');
        } catch (S3Exception $e) {
            error_log_out($e);
            Log::error($e->getMessage());
            return $e->getMessage();
        }
    }

    /** 从minio下载文件
     * @param $filename       string minio上的文件地址 例:avatar/123.png或根目录123.png
     * @param $local_filename string 保存在本地的路径以及文件名  例:'./uploads/20210101/aaa.jpg'
     * @return mixed|string|null
     */
    public function getObject($filename, $local_filename)
    {
        try {
            $result = $this->S3Client->getObject([
                'Bucket' => $this->bucket,
                'Key'    => $filename,
                'SaveAs' => $local_filename
            ]);
            return $result;
        } catch (S3Exception $e) {
            Log::error($e->getMessage());
            return $e->getMessage();
        }
    }

    /** 从minio上删除文件
     * @param string $filename minio上的文件地址 例:avatar/123.png或根目录123.png
     * @return mixed
     */
    public function deleteObject($filename)
    {
        if (strpos($filename, 'assets') === 0 || strpos($filename, '/assets') === 0) {
            return;
        }
        try {
            $result = $this->S3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $filename,
            ]);
            return $result;
        } catch (S3Exception $e) {
            Log::error($e->getMessage());
            return $e->getMessage();
        }
    }

}
