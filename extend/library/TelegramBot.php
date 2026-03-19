<?php

namespace library;

use fast\Http;

/**
 * 创建 Bot 并获取 Token
 * 与 @BotFather 对话，发送 /newbot，按指引命名并获取 Token（格式：123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11）
 * 获取 Chat ID（接收者标识）
 * 向你的 Bot 发送任意消息（如 /start），确保 Bot 能获取对话记录。
 * 访问 https://api.telegram.org/bot<YOUR_TOKEN>/getUpdates，在返回的 JSON 中找到 chat.id（如 123456789）。
 * 备用方法：使用 @userinfobot 直接获取个人 Chat ID。
 */
class TelegramBot
{
    private static $botToken = '8196950416:AAEKapSHH60N0FLBJK2IOTzxoBMC2amLsP8';
    private static $chatId = '7773568679';

    /**
     * 发送文字消息
     * @param $message
     * @return void
     */
    public static function sendMessage($message)
    {
        $botToken = self::$botToken;
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $params = [
            'chat_id'              => self::$chatId,
            'text'                 => $message,
            'disable_notification' => false // 开启通知声音
        ];
        Http::post($url, $params);
    }

    /**
     * 发送图片消息
     * @param $imagePath
     * @param $message
     * @return void
     */
    public static function sendPhoto($imagePath, $message = '')
    {
        $botToken = self::$botToken;
        $url = "https://api.telegram.org/bot{$botToken}/sendPhoto";
        $params = [
            'chat_id'              => self::$chatId,
            'caption'              => $message,
            'photo'                => new \CURLFile(realpath($imagePath)),
            'disable_notification' => false // 开启通知声音
        ];
        Http::post($url, $params);
    }
}
