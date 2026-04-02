<?php

namespace app\common\library;

use think\Env;
use InvalidArgumentException;

class ApiEnhance
{
    protected static $instance;
    protected $requestKey = '';
    protected $responseKey = '';

    // AES-256-GCM 常量
    private const KEY_LENGTH = 32;
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;
    private const CIPHER = 'aes-256-gcm';

    protected function __construct($options = [])
    {
        $this->requestKey = Env::get('api.request_encode_key');
        $this->responseKey = Env::get('api.response_encode_key');

        foreach ($options as $name => $item) {
            if (property_exists($this, $name)) {
                $this->$name = $item;
            }
        }
    }

    public static function instance($options = []): static
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($options);
        }
        return self::$instance;
    }

    public function requestEncode(string $string): string
    {
        return $this->encode($string, $this->requestKey);
    }

    public function requestDecode(string $string): string
    {
        return $this->decode($string, $this->requestKey);
    }

    public function responseEncode(string $string): string
    {
        return $this->encode($string, $this->responseKey);
    }

    public function responseDecode(string $string): string
    {
        return $this->decode($string, $this->responseKey);
    }

    /**
     * AES-256-GCM 加密
     * @param string $plaintext 明文
     * @param string $key       密钥
     * @return string Base64 编码的密文（IV + Ciphertext + Tag）
     * @throws InvalidArgumentException 当加密失败时抛出
     */
    public function encode(string $plaintext, string $key): string
    {
        $key = substr(hash('sha256', $key, true), 0, self::KEY_LENGTH);
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);

        return base64_encode($iv . $ciphertext . $tag);
    }

    /**
     * AES-256-GCM 解密
     * @param string $encrypted Base64 编码的密文
     * @param string $key       密钥
     * @return string 解密后的明文
     * @throws InvalidArgumentException 当数据格式错误或解密失败时抛出
     */
    public function decode(string $encrypted, string $key): string
    {
        $data = base64_decode($encrypted, true);
        $key = substr(hash('sha256', $key, true), 0, self::KEY_LENGTH);
        $iv = substr($data, 0, self::IV_LENGTH);
        $tag = substr($data, -self::TAG_LENGTH);
        $ciphertext = substr($data, self::IV_LENGTH, -self::TAG_LENGTH);

        return openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
    }
}
