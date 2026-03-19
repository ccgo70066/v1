# 自定义数据库使用说明

本文档详细说明如何在 ip2region 项目中使用自定义数据库文件。

## 概述

ip2region 支持使用自定义的 IPv4 和 IPv6 数据库文件，提供更灵活的数据库管理方式。

## 数据库文件格式

### 支持的文件格式

- **IPv4 数据库**：`.xdb` 格式文件
- **IPv6 数据库**：`.xdb` 格式文件
- **压缩文件**：IPv4 支持 `.gz` 和 `.zip` 压缩格式

### 文件大小参考

- **IPv4 数据库**：约 10.5MB（未压缩）
- **IPv6 数据库**：约 617MB（未压缩）
- **IPv4 压缩文件**：约 2-3MB（压缩后）

## 配置方式

### 1. 构造函数配置

```php
<?php
require 'vendor/autoload.php';

try {
    // 使用自定义数据库路径（建议使用绝对路径）
    $ip2region = new \Ip2Region('file', 
        '/path/to/your/ip2region_v4.xdb',  // IPv4 数据库路径
        '/path/to/your/ip2region_v6.xdb'   // IPv6 数据库路径
    );
    
    // 查询示例
    echo $ip2region->simple('61.142.118.231'); // 中国广东省中山市【电信】
    echo $ip2region->simple('2001:4860:4860::8888'); // Google DNS
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
?>
```

### 2. 动态设置数据库路径

```php
<?php
require 'vendor/autoload.php';

try {
    $ip2region = new \Ip2Region();
    
    // 动态设置数据库路径
    $ip2region->setCustomDbPaths('/path/to/v4.xdb', '/path/to/v6.xdb');
    
    // 查询IP
    echo $ip2region->simple('8.8.8.8') . "\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
?>
```

### 3. 检查数据库状态

```php
<?php
require 'vendor/autoload.php';

try {
    $ip2region = new \Ip2Region('file', '/path/to/v4.xdb', '/path/to/v6.xdb');
    
    // 检查是否使用自定义数据库
    $customStatus = $ip2region->isUsingCustomDb();
    echo "IPv4 使用自定义数据库: " . ($customStatus['v4'] ? '是' : '否') . "\n";
    echo "IPv6 使用自定义数据库: " . ($customStatus['v6'] ? '是' : '否') . "\n";
    
    // 获取数据库配置信息
    $dbInfo = $ip2region->getDatabaseInfo();
    echo "IPv4 路径: " . ($dbInfo['custom_v4_path'] ?: '默认压缩') . "\n";
    echo "IPv6 路径: " . ($dbInfo['custom_v6_path'] ?: '需要下载') . "\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
?>
```

## 数据库优先级

系统按以下优先级查找数据库文件：

1. **自定义数据库**：通过构造函数或 `setCustomDbPaths()` 指定的 `.xdb` 文件路径
2. **下载的数据库**：通过 `ip2down` 工具下载的完整数据库文件
3. **IPv4 压缩文件**：仅 IPv4 支持，自动解压压缩文件
4. **IPv6 压缩文件**：不支持，必须使用完整数据库文件

## 获取数据库文件

### 免费版本

从官方仓库下载：

- **IPv4 数据库**：[ip2region_v4.xdb](https://raw.githubusercontent.com/lionsoul2014/ip2region/master/data/ip2region_v4.xdb) (10.5MB)
- **IPv6 数据库**：[ip2region_v6.xdb](https://raw.githubusercontent.com/lionsoul2014/ip2region/master/data/ip2region_v6.xdb) (617MB)

### 商业版本

从 [ip2region 官网](https://www.ip2region.net/) 购买或下载。

## 使用下载工具

### 自动下载

```bash
# 下载 IPv4 数据库
./vendor/bin/ip2down download v4

# 下载 IPv6 数据库
./vendor/bin/ip2down download v6

# 下载所有数据库
./vendor/bin/ip2down download all
```

### 查看已下载文件

```bash
./vendor/bin/ip2down list
```

### 测试数据库功能

```bash
./vendor/bin/ip2down test
```

## 性能优化建议

### 1. 文件缓存策略

```php
// 使用文件缓存，适合大文件
$ip2region = new \Ip2Region('file', '/path/to/v4.xdb', '/path/to/v6.xdb');
```

### 2. 内存缓存策略

```php
// 使用内存缓存，适合小文件或内存充足的环境
$ip2region = new \Ip2Region('content', '/path/to/v4.xdb', '/path/to/v6.xdb');
```

### 3. 向量索引策略

```php
// 使用向量索引，平衡内存和性能
$ip2region = new \Ip2Region('vectorIndex', '/path/to/v4.xdb', '/path/to/v6.xdb');
```

## 常见问题

### 1. 数据库文件不存在

**错误信息**：`数据库文件不存在: /path/to/ip2region_v4.xdb`

**解决方案**：
- 检查文件路径是否正确
- 确保文件存在且可读
- 使用绝对路径避免相对路径问题

### 2. 文件权限问题

**错误信息**：`failed to open xdb file`

**解决方案**：
- 检查文件权限：`chmod 644 /path/to/database.xdb`
- 确保 PHP 进程有读取权限

### 3. 内存不足

**错误信息**：`Fatal error: Allowed memory size exhausted`

**解决方案**：
- 增加 PHP 内存限制：`ini_set('memory_limit', '256M');`
- 使用文件缓存策略：`new Ip2Region('file')`
- 定期清理缓存：`Ip2Region::clearCache()`

### 4. IPv6 数据库问题

**错误信息**：`IPv6 查询需要下载完整数据库文件`

**解决方案**：
- IPv6 不支持压缩文件，必须使用完整 `.xdb` 文件
- 下载 IPv6 数据库：`./vendor/bin/ip2down download v6`
- 或手动下载到指定路径

## 最佳实践

### 1. 路径管理

```php
// 推荐：使用绝对路径
$v4Path = realpath('/path/to/ip2region_v4.xdb');
$v6Path = realpath('/path/to/ip2region_v6.xdb');

$ip2region = new \Ip2Region('file', $v4Path, $v6Path);
```

### 2. 错误处理

```php
try {
    $ip2region = new \Ip2Region('file', $v4Path, $v6Path);
    $result = $ip2region->simple('8.8.8.8');
    echo $result;
} catch (Exception $e) {
    error_log("IP查询失败: " . $e->getMessage());
    // 处理错误或使用默认数据库
}
```

### 3. 性能监控

```php
$ip2region = new \Ip2Region('file', $v4Path, $v6Path);

// 获取性能统计
$stats = $ip2region->getStats();
echo "查询次数: " . $stats['query_count'] . "\n";
echo "平均查询时间: " . $stats['avg_query_time'] . "ms\n";

// 获取内存使用情况
$memory = $ip2region->getMemoryUsage();
echo "内存使用: " . $memory['current'] . "MB\n";
```

### 4. 数据库文件验证

```php
<?php
require 'vendor/autoload.php';

function validateDatabaseFiles($v4Path, $v6Path) {
    $errors = [];
    
    // 检查IPv4数据库
    if ($v4Path && !file_exists($v4Path)) {
        $errors[] = "IPv4数据库文件不存在: $v4Path";
    } elseif ($v4Path && filesize($v4Path) < 10 * 1024 * 1024) {
        $errors[] = "IPv4数据库文件可能损坏，大小异常: " . filesize($v4Path) . " bytes";
    }
    
    // 检查IPv6数据库
    if ($v6Path && !file_exists($v6Path)) {
        $errors[] = "IPv6数据库文件不存在: $v6Path";
    } elseif ($v6Path && filesize($v6Path) < 100 * 1024 * 1024) {
        $errors[] = "IPv6数据库文件可能损坏，大小异常: " . filesize($v6Path) . " bytes";
    }
    
    return $errors;
}

// 使用示例
$errors = validateDatabaseFiles('/path/to/v4.xdb', '/path/to/v6.xdb');
if (!empty($errors)) {
    foreach ($errors as $error) {
        echo "错误: $error\n";
    }
} else {
    echo "数据库文件验证通过\n";
}
?>
```

### 5. 自动数据库更新

```php
<?php
require 'vendor/autoload.php';

class DatabaseUpdater {
    private $dbDir;
    private $updateUrl = 'https://cdn.jsdelivr.net/gh/lionsoul2014/ip2region@master/data/';
    
    public function __construct($dbDir) {
        $this->dbDir = $dbDir;
        if (!is_dir($this->dbDir)) {
            mkdir($this->dbDir, 0755, true);
        }
    }
    
    public function checkAndUpdate($version = 'v4') {
        $fileName = "ip2region_{$version}.xdb";
        $localPath = $this->dbDir . '/' . $fileName;
        $remoteUrl = $this->updateUrl . $fileName;
        
        // 检查本地文件是否存在
        if (!file_exists($localPath)) {
            return $this->downloadDatabase($remoteUrl, $localPath);
        }
        
        // 检查文件大小是否合理
        $localSize = filesize($localPath);
        $minSize = $version === 'v4' ? 10 * 1024 * 1024 : 100 * 1024 * 1024;
        
        if ($localSize < $minSize) {
            return $this->downloadDatabase($remoteUrl, $localPath);
        }
        
        return true;
    }
    
    private function downloadDatabase($url, $localPath) {
        $context = stream_context_create([
            'http' => ['timeout' => 300]
        ]);
        
        $data = file_get_contents($url, false, $context);
        if ($data === false) {
            return false;
        }
        
        return file_put_contents($localPath, $data) !== false;
    }
}

// 使用示例
$updater = new DatabaseUpdater('/var/lib/ip2region');
if ($updater->checkAndUpdate('v4')) {
    echo "IPv4数据库更新成功\n";
} else {
    echo "IPv4数据库更新失败\n";
}
?>
```

### 6. 多实例管理

```php
<?php
require 'vendor/autoload.php';

class Ip2RegionManager {
    private static $instances = [];
    
    public static function getInstance($cachePolicy = 'file', $v4Path = null, $v6Path = null) {
        $key = md5($cachePolicy . $v4Path . $v6Path);
        
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new \Ip2Region($cachePolicy, $v4Path, $v6Path);
        }
        
        return self::$instances[$key];
    }
    
    public static function clearAllInstances() {
        self::$instances = [];
    }
}

// 使用示例
$searcher1 = Ip2RegionManager::getInstance('file', '/path/to/v4.xdb', '/path/to/v6.xdb');
$searcher2 = Ip2RegionManager::getInstance('file', '/path/to/v4.xdb', '/path/to/v6.xdb');
// $searcher1 和 $searcher2 是同一个实例

$searcher3 = Ip2RegionManager::getInstance('content'); // 不同的配置，会创建新实例
?>
```

## 高级用法

### 1. 批量查询优化

```php
<?php
require 'vendor/autoload.php';

try {
    $ip2region = new \Ip2Region('file', '/path/to/v4.xdb', '/path/to/v6.xdb');
    
    // 批量查询，提高性能
    $ips = ['8.8.8.8', '114.114.114.114', '2001:4860:4860::8888'];
    $results = $ip2region->batchSearch($ips);
    
    foreach ($results as $ip => $region) {
        echo "$ip -> $region\n";
    }
} catch (Exception $e) {
    echo "批量查询失败: " . $e->getMessage() . "\n";
}
?>
```

### 2. 性能监控和统计

```php
<?php
require 'vendor/autoload.php';

try {
    $ip2region = new \Ip2Region('file', '/path/to/v4.xdb', '/path/to/v6.xdb');
    
    // 执行一些查询
    $ip2region->simple('8.8.8.8');
    $ip2region->simple('114.114.114.114');
    
    // 获取性能统计
    $stats = $ip2region->getStats();
    echo "查询次数: " . $stats['query_count'] . "\n";
    echo "平均查询时间: " . $stats['avg_query_time'] . "ms\n";
    echo "总查询时间: " . $stats['total_query_time'] . "ms\n";
    
    // 获取内存使用情况
    $memory = $ip2region->getMemoryUsage();
    echo "当前内存使用: " . $memory['current'] . "MB\n";
    echo "峰值内存使用: " . $memory['peak'] . "MB\n";
} catch (Exception $e) {
    echo "监控失败: " . $e->getMessage() . "\n";
}
?>
```

### 3. 缓存管理

```php
<?php
require 'vendor/autoload.php';

try {
    $ip2region = new \Ip2Region('file', '/path/to/v4.xdb', '/path/to/v6.xdb');
    
    // 清理过期缓存
    $cleared = \Ip2Region::clearExpiredCache();
    echo "清理了 $cleared 个过期缓存文件\n";
    
    // 清理所有缓存
    \Ip2Region::clearCache();
    echo "所有缓存已清理\n";
    
    // 清理持久化缓存
    \Ip2Region::clearPersistentCache();
    echo "持久化缓存已清理\n";
} catch (Exception $e) {
    echo "缓存管理失败: " . $e->getMessage() . "\n";
}
?>
```

### 4. 详细IP信息查询

```php
<?php
require 'vendor/autoload.php';

try {
    $ip2region = new \Ip2Region('file', '/path/to/v4.xdb', '/path/to/v6.xdb');
    
    // 获取详细IP信息
    $ipInfo = $ip2region->getIpInfo('8.8.8.8');
    echo "IP地址: " . $ipInfo['ip'] . "\n";
    echo "国家: " . $ipInfo['country'] . "\n";
    echo "省份: " . $ipInfo['province'] . "\n";
    echo "城市: " . $ipInfo['city'] . "\n";
    echo "ISP: " . $ipInfo['isp'] . "\n";
    echo "原始数据: " . $ipInfo['raw'] . "\n";
} catch (Exception $e) {
    echo "IP信息查询失败: " . $e->getMessage() . "\n";
}
?>
```

## 部署建议

### 1. 生产环境配置

```php
<?php
// 生产环境推荐配置
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 30);

$ip2region = new \Ip2Region('file', 
    '/var/lib/ip2region/ip2region_v4.xdb',
    '/var/lib/ip2region/ip2region_v6.xdb'
);
?>
```

### 2. 容器化部署

```dockerfile
# Dockerfile 示例
FROM php:8.1-cli

# 安装必要扩展
RUN docker-php-ext-install sockets

# 复制项目文件
COPY . /app
WORKDIR /app

# 下载数据库文件
RUN ./vendor/bin/ip2down download all

# 设置权限
RUN chmod +x ./vendor/bin/ip2down

# 启动命令
CMD ["php", "your-script.php"]
```

### 3. 负载均衡配置

```nginx
# nginx.conf 示例
upstream ip2region_backend {
    server 127.0.0.1:9001;
    server 127.0.0.1:9002;
    server 127.0.0.1:9003;
}

server {
    listen 80;
    server_name ip2region.example.com;
    
    location / {
        proxy_pass http://ip2region_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

---

**注意**：本文档基于 ip2region v3.0+ 版本编写，如使用其他版本可能存在差异。
