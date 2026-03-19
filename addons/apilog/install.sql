CREATE TABLE IF NOT EXISTS `__PREFIX__apilog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(255) DEFAULT NULL COMMENT 'IP',
  `url` varchar(255) DEFAULT NULL COMMENT '请求地址',
  `method` enum('GET','POST','PUT','DELETE') DEFAULT NULL COMMENT '请求方法',
  `param` text COMMENT '参数',
  `ua` varchar(255) DEFAULT '' COMMENT 'UA',
  `controller` varchar(255) DEFAULT NULL COMMENT '控制器',
  `action` varchar(255) DEFAULT NULL COMMENT '操作',
  `time` float(11,6) DEFAULT '0.000000' COMMENT '耗时',
  `code` int(11) DEFAULT '200' COMMENT '状态码',
  `createtime` int(11) DEFAULT NULL COMMENT '请求时间',
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `response` text COMMENT '响应内容',
  PRIMARY KEY (`id`),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='api日志';

--
--1.0.1版本 增加响应内容记录
--
ALTER TABLE `__PREFIX__apilog` ADD COLUMN `response` text COMMENT '响应内容';
