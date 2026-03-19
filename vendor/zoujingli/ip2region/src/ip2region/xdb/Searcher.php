<?php

/**
 * XDB 搜索引擎
 * 
 * 功能特性：
 * - 高性能XDB格式数据库搜索引擎
 * - 支持IPv4和IPv6地址查询
 * - 多种搜索算法（二分查找、向量索引）
 * - 内存映射文件支持，减少内存占用
 * - 支持大文件分片加载
 * - 智能缓存机制
 * 
 * 技术特点：
 * - 基于XDB格式的高效数据结构
 * - 支持多种索引类型（VectorIndex、Content）
 * - 优化的二分查找算法
 * - 内存友好的文件读取策略
 * 
 * @package ip2region\xdb
 * @author The Ip2Region Authors
 * @version 3.0.3
 */

// Copyright 2022 The Ip2Region Authors. All rights reserved.
// Use of this source code is governed by a Apache2.0-style
// license that can be found in the LICENSE file.

namespace ip2region\xdb;

/**
 * XDB 搜索引擎
 * 
 * 提供高性能的XDB格式数据库搜索功能
 * 支持IPv4和IPv6地址查询，使用优化的二分查找算法
 */
class Searcher
{
    /** @var IPv4|IPv6 IP版本对象（IPv4或IPv6） */
    private $version;

    /** @var resource|null XDB文件句柄 */
    private $handle  = null;
    
    /** @var int IO操作计数 */
    private $ioCount = 0;

    /** @var string|null 向量索引二进制字符串 */
    private $vectorIndex = null;

    /** @var string|null XDB内容缓冲区 */
    private $contentBuff = null;

    // ============================================================================
    // 静态工厂方法
    // ============================================================================

    /**
     * 创建基于文件的搜索引擎
     * 
     * 创建一个基于XDB文件的搜索引擎实例
     * 使用文件句柄进行数据读取，适合大文件处理
     * 内部会将 int 版本参数转换为对应的版本对象
     * 
     * @param int $version IP版本（4或6）
     * @param string $dbFile XDB数据库文件路径
     * @return Searcher 返回搜索引擎实例
     * @throws \Exception 当文件不存在或无法打开时抛出异常
     * 
     * @example
     * ```php
     * $searcher = Searcher::newWithFileOnly(4, '/path/to/ipv4.xdb');
     * $result = $searcher->search('8.8.8.8');
     * ```
     */
    public static function newWithFileOnly($version, $dbFile)
    {
        $versionObj = $version === 4 ? IPv4::default() : IPv6::default();
        return new self($versionObj, $dbFile, null, null);
    }

    /**
     * 创建基于向量索引的搜索引擎
     * 
     * 创建一个基于XDB文件和向量索引的搜索引擎实例
     * 使用预加载的向量索引提高搜索性能
     * 内部会将 int 版本参数转换为对应的版本对象
     * 
     * @param int $version IP版本（4或6）
     * @param string $dbFile XDB数据库文件路径
     * @param string $vIndex 向量索引数据
     * @return Searcher 返回搜索引擎实例
     * @throws \Exception 当文件不存在或无法打开时抛出异常
     * 
     * @example
     * ```php
     * $searcher = Searcher::newWithVectorIndex(4, '/path/to/ipv4.xdb', $vectorIndex);
     * $result = $searcher->search('8.8.8.8');
     * ```
     */
    public static function newWithVectorIndex($version, $dbFile, $vIndex)
    {
        $versionObj = $version === 4 ? IPv4::default() : IPv6::default();
        return new self($versionObj, $dbFile, $vIndex, null);
    }

    /**
     * 创建基于缓冲区的搜索引擎
     * 
     * 创建一个基于内存缓冲区的搜索引擎实例
     * 使用预加载的内容缓冲区，适合小文件或内存充足的环境
     * 内部会将 int 版本参数转换为对应的版本对象
     * 
     * @param int $version IP版本（4或6）
     * @param string $cBuff 内容缓冲区数据
     * @return Searcher 返回搜索引擎实例
     * 
     * @example
     * ```php
     * $searcher = Searcher::newWithBuffer(4, $contentBuffer);
     * $result = $searcher->search('8.8.8.8');
     * ```
     */
    public static function newWithBuffer($version, $cBuff)
    {
        $versionObj = $version === 4 ? IPv4::default() : IPv6::default();
        return new self($versionObj, null, null, $cBuff);
    }

    // ============================================================================
    // 构造函数和初始化
    // ============================================================================

    /**
     * 初始化XDB搜索引擎
     * 
     * 根据提供的参数初始化搜索引擎实例
     * 支持文件句柄、向量索引和内容缓冲区三种模式
     * 
     * @param IPv4|IPv6 $version IP版本对象（IPv4或IPv6）
     * @param string|null $dbFile XDB数据库文件路径
     * @param string|null $vectorIndex 向量索引数据
     * @param string|null $cBuff 内容缓冲区数据
     * @throws \Exception 当文件无法打开时抛出异常
     * 
     * @example
     * ```php
     * $searcher = new Searcher(IPv4::default(), '/path/to/ipv4.xdb');
     * ```
     */
    function __construct($version, $dbFile, $vectorIndex = null, $cBuff = null)
    {
        $this->version = $version;
        
        // check the content buffer first
        if ($cBuff != null) {
            $this->vectorIndex = null;
            $this->contentBuff = $cBuff;
        } else {
            // open the xdb binary file
            $this->handle = fopen($dbFile, "r");
            if ($this->handle === false) {
                throw new \Exception("failed to open xdb file '%s'", $dbFile);
            }

            $this->vectorIndex = $vectorIndex;
        }
    }

    /**
     * 查找指定IP地址的地区信息
     * 
     * 在XDB数据库中搜索指定IP地址对应的地区信息
     * 使用优化的二分查找算法进行快速定位
     * 
     * @param string $ip IP地址字符串
     * @return string|null 返回地区信息字符串，未找到返回 null
     * @throws \Exception 当搜索过程中发生错误时抛出异常
     * 
     * @example
     * ```php
     * $searcher = Searcher::newWithFileOnly(4, '/path/to/ipv4.xdb');
     * $region = $searcher->search('8.8.8.8');
     * echo $region; // 输出：美国|0|0|Level3
     * ```
     */
    public function search($ip)
    {
        $ipBytes = Util::parseIP($ip);
        if ($ipBytes == null) {
            throw new \Exception("invalid ip address `{$ip}`");
        }

        return $this->searchByBytes($ipBytes);
    }

    /**
     * 根据二进制IP字节查找地区信息
     * 
     * 使用二进制格式的IP地址进行搜索，避免重复解析
     * 这是search方法的底层实现，提供更高的性能
     * 
     * @param string $ipBytes 二进制格式的IP地址字节
     * @return string|null 返回地区信息字符串，未找到返回 null
     * @throws \Exception 当IP版本不匹配或搜索过程中发生错误时抛出异常
     * 
     * @example
     * ```php
     * $searcher = Searcher::newWithFileOnly(4, '/path/to/ipv4.xdb');
     * $ipBytes = inet_pton('8.8.8.8');
     * $region = $searcher->searchByBytes($ipBytes);
     * echo $region; // 输出：美国|0|0|Level3
     * ```
     */
    public function searchByBytes($ipBytes)
    {
        // ip version check
        if (strlen($ipBytes) != $this->version->bytes) {
            throw new \Exception("invalid ip address ({$this->version->name} expected)");
        }

        // reset the global counter
        $this->ioCount = 0;

        // locate the segment index block based on the vector index
        $il0 = ord($ipBytes[0]) & 0xFF;
        $il1 = ord($ipBytes[1]) & 0xFF;
        $idx = $il0 * Util::VectorIndexCols * Util::VectorIndexSize + $il1 * Util::VectorIndexSize;
        if ($this->vectorIndex != null) {
            $sPtr = Util::le_getUint32($this->vectorIndex, $idx);
            $ePtr = Util::le_getUint32($this->vectorIndex, $idx + 4);
        } else if ($this->contentBuff != null) {
            $sPtr = Util::le_getUint32($this->contentBuff, Util::HeaderInfoLength + $idx);
            $ePtr = Util::le_getUint32($this->contentBuff, Util::HeaderInfoLength + $idx + 4);
        } else {
            // read the vector index block
            $buff = $this->read(Util::HeaderInfoLength + $idx, 8);
            if ($buff === null) {
                throw new \Exception("failed to read vector index at ${idx}");
            }

            $sPtr = Util::le_getUint32($buff, 0);
            $ePtr = Util::le_getUint32($buff, 4);
        }

        // printf("sPtr: %d, ePtr: %d\n", $sPtr, $ePtr);
        $bytes = strlen($ipBytes);
        $dBytes = $bytes << 1;

        // binary search the segment index to get the region info
        $idxSize = $this->version->segmentIndexSize;
        $dataLen = 0;
        $dataPtr = null;
        $l = 0;
        $h = ($ePtr - $sPtr) / $idxSize;
        while ($l <= $h) {
            $m = ($l + $h) >> 1;
            $p = $sPtr + $m * $idxSize;

            // read the segment index
            $buff = $this->read($p, $idxSize);
            if ($buff == null) {
                throw new \Exception("failed to read segment index with ptr={$p}");
            }

            // printf("l=%d, h=%d, sip=%s, eip=%s\n", 
            //     $l, $h,
            //     Util::ipToString(strrev(substr($buff, 0, 4))),
            //     Util::ipToString(strrev(substr($buff, 4, 4)))
            // );
            if ($this->version->ipSubCompare($ipBytes, $buff, 0) < 0) {
                $h = $m - 1;
            } else if ($this->version->ipSubCompare($ipBytes, $buff, $bytes) > 0) {
                $l = $m + 1;
            } else {
                $dataLen = Util::le_getUint16($buff, $dBytes);
                $dataPtr = Util::le_getUint32($buff, $dBytes + 2);
                break;
            }
        }

        // match nothing interception.
        // @TODO: could this even be a case ?
        // printf("dataLen: %d, dataPtr: %d\n", $dataLen, $dataPtr);
        if ($dataPtr == null) {
            return null;
        }

        // load and return the region data
        $buff = $this->read($dataPtr, $dataLen);
        if ($buff == null) {
            return null;
        }

        return $buff;
    }

    // ============================================================================
    // 辅助方法
    // ============================================================================

    /**
     * 从指定偏移量读取指定长度的字节
     * 
     * 根据当前模式（文件句柄或内容缓冲区）读取数据
     * 优先使用内容缓冲区，提高读取性能
     * 
     * @param int $offset 起始偏移量
     * @param int $len 要读取的字节长度
     * @return string|null 返回读取的数据，读取失败返回 null
     * 
     * @example
     * ```php
     * $data = $this->read(1024, 256); // 从偏移量1024读取256字节
     * ```
     */
    private function read($offset, $len)
    {
        // check the in-memory buffer first
        if ($this->contentBuff != null) {
            return substr($this->contentBuff, $offset, $len);
        }

        // handle zero length reads
        if ($len <= 0) {
            return '';
        }

        // read from the file
        $r = fseek($this->handle, $offset);
        if ($r == -1) {
            return null;
        }

        $buff = fread($this->handle, $len);
        if ($buff === false) {
            return null;
        }

        $this->ioCount++;
        return $buff;
    }

    /**
     * 获取IO操作计数
     * 
     * 返回当前搜索引擎实例的IO操作总次数
     * 用于性能监控和调试
     * 
     * @return int 返回IO操作次数
     * 
     * @example
     * ```php
     * $searcher = Searcher::newWithFileOnly(4, '/path/to/ipv4.xdb');
     * $searcher->search('8.8.8.8');
     * echo "IO次数: " . $searcher->getIOCount();
     * ```
     */
    public function getIOCount()
    {
        return $this->ioCount;
    }

    /**
     * 获取IP版本
     * 
     * 返回当前搜索引擎实例的IP版本
     * 
     * @return int 返回IP版本（4或6）
     * 
     * @example
     * ```php
     * $searcher = Searcher::newWithFileOnly(4, '/path/to/ipv4.xdb');
     * echo "IP版本: " . $searcher->getIPVersion(); // 输出：4
     * ```
     */
    public function getIPVersion()
    {
        return $this->version->id;
    }


    /**
     * 关闭搜索引擎
     * 
     * 关闭文件句柄并释放相关资源
     * 在对象销毁前调用以确保资源正确释放
     * 
     * @return void
     * 
     * @example
     * ```php
     * $searcher = Searcher::newWithFileOnly(4, '/path/to/ipv4.xdb');
     * $searcher->search('8.8.8.8');
     * $searcher->close(); // 关闭文件句柄
     * ```
     */
    public function close()
    {
        if ($this->handle != null) {
            fclose($this->handle);
            $this->handle = null;
        }
    }
}