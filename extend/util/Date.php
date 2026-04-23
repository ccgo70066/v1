<?php

namespace util;

class Date
{
    /**
     * 传入时间戳,计算距离现在的时间
     * @param number $timestamp 时间戳
     * @return string     返回多少以前
     */
    public function human_time($timestamp, $lang = 'zh'): string
    {
        $timestamp = (int)substr($timestamp, 0, 10);
        $int = time() - $timestamp;
        if ($int < 60) {
            $str = __('%s seconds ago', $int);
        } elseif ($int < 3600) {
            $str = __('%s minutes ago', floor($int / 60));
        } elseif ($int < 86400) {
            $str = __('%s hours ago', floor($int / 3600));
        } elseif ($int < 2678400) {             //31天
            $today_time = strtotime(datetime(time(), 'Y-m-d 0:0:0'));
            $int = $today_time - $timestamp;
            $str = __('%s days ago', ceil($int / 86400));
        } else {
            $str = date('Y-m-d H:i:s', $timestamp);
        }
        return $str;
    }

    public static function format_second($second, $lang = 'zh'): string
    {
        if ($second <= 60) {
            $str = sprintf('%d' . __('seconds'), $second);
        } elseif ($second < 3600) {
            $str = sprintf('%.2f ' . __('minutes'), ($second / 60));
        } elseif ($second < 86400) {
            $str = sprintf('%.2f' . __('hours'), ($second / 3600));
        } elseif ($second < 1728000) {
            $str = sprintf('%.2f' . __('days'), ($second / 86400));
        } else {
            $str = sprintf('%.2f' . __('months'), ($second / 86400 * 30));
        }
        return $str;
    }

    /**
     * 获取当前时间到下一个整点时间的秒数
     * @param int $interval 整点间隔(分钟)
     * @return int
     */
    public static function get_next(int $interval = 30): int
    {
        $minute = (intval(date('i') / $interval) + 1) * 30;
        $date = strtotime("+{$minute}minute", strtotime(date('Y-m-d H:00:00')));
        return $date - time();
    }
}
