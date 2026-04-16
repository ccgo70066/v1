<?php

namespace util;

/**
 * 星座转换
 * @package util
 */
class Constellation
{
    protected static $name_zh = [
        '摩羯座',
        '水瓶座',
        '雙魚座',
        '白羊座',
        '金牛座',
        '雙子座',
        '巨蟹座',
        '獅子座',
        '處女座',
        '天秤座',
        '天蠍座',
        '射手座',
    ];
    protected static $name_en = [
        'capricorn',
        'aquarius',
        'pisces',
        'aries',
        'taurus',
        'gemini',
        'cancer',
        'leo',
        'virgo',
        'libra',
        'scorpio ',
        'sagittarius',
    ];

    /**
     * 获取星座
     * @param string $date
     * @param string $lang 语言:en=英,zh=繁中
     * @return string
     */
    public static function getConstellation(string $date, string $lang = 'en'): string
    {
        $name = $lang == 'en' ? self::$name_en : self::$name_zh;
        $date = date('Y-m-d H:i:s', strtotime($date));
        $month = substr($date, 5, 2);
        $day = substr($date, 8, 2);
        $res = $name[0];
        switch ($month) {
            case "01":
                $res = $day < 21 ? $name[0] : $name[1];
                break;
            case "02":
                $res = $day < 20 ? $name[1] : $name[2];
                break;
            case "03":
                $res = $day < 21 ? $name[2] : $name[3];
                break;
            case "04":
                $res = $day < 20 ? $name[3] : $name[4];
                break;
            case "05":
                $res = $day < 21 ? $name[4] : $name[5];
                break;
            case "06":
                $res = $day < 22 ? $name[5] : $name[6];
                break;
            case "07":
                $res = $day < 23 ? $name[6] : $name[7];
                break;
            case "08":
                $res = $day < 23 ? $name[7] : $name[8];
                break;
            case "09":
                $res = $day < 23 ? $name[8] : $name[9];
                break;
            case "10":
                $res = $day < 24 ? $name[9] : $name[10];
                break;
            case "11":
                $res = $day < 22 ? $name[10] : $name[11];
                break;
            case "12":
                $res = $day < 22 ? $name[11] : $name[0];
                break;
        }
        return $res;
    }
}
