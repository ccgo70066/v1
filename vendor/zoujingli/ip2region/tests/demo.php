<?php

/**
 * IP2Region 演示脚本
 * 
 * 功能说明：
 * - 展示IP2Region库的基本使用方法
 * - 演示IPv4和IPv6地址查询功能
 * - 展示多种缓存策略和数据库加载
 * - 性能测试和统计信息展示
 * 
 * 演示内容：
 * 1. IPv4地址查询演示 - 使用常见公共DNS服务器
 * 2. IPv6地址查询演示 - 使用主流IPv6公共DNS
 * 3. 详细信息查询 - 展示完整的IP信息结构
 * 4. 自动缓存机制 - 展示缓存文件的使用和管理
 * 5. 性能统计信息 - 内存使用和IO统计
 * 6. 批量查询演示 - 批量处理多个IP地址
 * 
 * 测试IP地址说明：
 * - 61.142.118.231: 中国广东省深圳市普通IP
 * - 202.96.134.133: 中国广东省深圳市普通IP
 * - 180.76.76.76: 中国北京北京市普通IP
 * - 114.114.114.114: 中国江苏省南京市普通IP
 * - 223.5.5.5: 中国浙江省杭州市普通IP
 * - 8.8.8.8: 美国加利福尼亚州普通IP
 * 
 * 运行方式：
 * php tests/demo.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== Ip2Region 演示 ===\n\n";

try {
    // 创建查询器实例
    $searcher = new Ip2Region();

    echo "1. IPv4 查询演示:\n";
    $ipv4Tests = [
        '61.142.118.231'  => '中国广东省深圳市',
        '202.96.134.133'  => '中国广东省深圳市',
        '180.76.76.76'    => '中国北京北京市',
        '114.114.114.114' => '中国江苏省南京市',
        '223.5.5.5'       => '中国浙江省杭州市',
        '8.8.8.8'         => '美国加利福尼亚州'
    ];

    foreach ($ipv4Tests as $ip => $desc) {
        $result = $searcher->simple($ip);
        echo "  {$ip} ({$desc}) => {$result}\n";
    }

    echo "\n2. IPv6 查询演示:\n";
    $ipv6Tests = [
        '2001:4860:4860::8888' => 'Google IPv6公共DNS',
        '2400:3200::1'         => '阿里IPv6公共DNS',
        '2606:4700:4700::1111' => 'Cloudflare IPv6公共DNS',
        '2400:da00::6666'      => '腾讯IPv6公共DNS',
        '::1'                  => '本地回环地址'
    ];

    foreach ($ipv6Tests as $ip => $desc) {
        try {
            $result = $searcher->simple($ip);
            echo "  {$ip} ({$desc}) => {$result}\n";
        } catch (Exception $e) {
            echo "  {$ip} ({$desc}) => 错误: " . $e->getMessage() . "\n";
        }
    }

    echo "\n3. 详细信息查询:\n";
    $testIPs = array('61.142.118.231', '114.114.114.114', '2001:4860:4860::8888');
    foreach ($testIPs as $ip) {
        $info = $searcher->getIpInfo($ip);
        if ($info) {
            echo "  IP: {$info['ip']}\n";
            echo "    国家: {$info['country']}\n";
            echo "    地区: {$info['region']}\n";
            echo "    省份: {$info['province']}\n";
            echo "    城市: {$info['city']}\n";
            echo "    ISP: {$info['isp']}\n";
            echo "    版本: {$info['version']}\n\n";
        }
    }

    echo "4. 数据库加载状态:\n";
    $dbInfo = $searcher->getDatabaseInfo();
    echo "  IPv4已加载: " . ($dbInfo['v4_loaded'] ? '是' : '否') . "\n";
    echo "  IPv6已加载: " . ($dbInfo['v6_loaded'] ? '是' : '否') . "\n";
    echo "  自定义IPv4路径: " . ($dbInfo['custom_v4_path'] ?: '使用默认路径') . "\n";
    echo "  自定义IPv6路径: " . ($dbInfo['custom_v6_path'] ?: '使用默认路径') . "\n";

    echo "\n5. 性能统计:\n";
    $stats = $searcher->getStats();
    foreach ($stats as $key => $value) {
        echo "  {$key}: " . (is_bool($value) ? ($value ? '是' : '否') : $value) . "\n";
    }

    echo "\n6. 内存使用:\n";
    $memory = $searcher->getMemoryUsage();
    foreach ($memory as $key => $value) {
        echo "  {$key}: {$value}\n";
    }

    echo "\n7. 批量查询演示:\n";
    $batchIPs = array_keys($ipv4Tests) + array_keys($ipv6Tests);
    $batchResults = $searcher->batchSearch($batchIPs);

    foreach ($batchResults as $ip => $result) {
        $status = $result ? '成功' : '失败';
        echo "  {$ip} => {$status}\n";
    }
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}

echo "\n=== 演示完成 ===\n";
