/*
 Navicat Premium Dump SQL

 Source Server         : 1-本地
 Source Server Type    : MySQL
 Source Server Version : 50744 (5.7.44)
 Source Host           : 127.0.0.1:3306
 Source Schema         : erp_licheng

 Target Server Type    : MySQL
 Target Server Version : 50744 (5.7.44)
 File Encoding         : 65001

 Date: 19/05/2026 10:10:31
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for fa_admin
-- ----------------------------
DROP TABLE IF EXISTS `fa_admin`;
CREATE TABLE `fa_admin` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `username` varchar(20) DEFAULT '' COMMENT '用户名',
  `nickname` varchar(50) DEFAULT '' COMMENT '昵称',
  `password` varchar(32) DEFAULT '' COMMENT '密码',
  `salt` varchar(30) DEFAULT '' COMMENT '密码盐',
  `avatar` varchar(255) DEFAULT '' COMMENT '头像',
  `email` varchar(100) DEFAULT '' COMMENT '电子邮箱',
  `mobile` varchar(11) DEFAULT '' COMMENT '手机号码',
  `loginfailure` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '失败次数',
  `logintime` bigint(16) DEFAULT NULL COMMENT '登录时间',
  `loginip` varchar(50) DEFAULT NULL COMMENT '登录IP',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  `token` varchar(59) DEFAULT '' COMMENT 'Session标识',
  `status` varchar(30) NOT NULL DEFAULT 'normal' COMMENT '状态',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='管理员表';

-- ----------------------------
-- Table structure for fa_admin_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_admin_log`;
CREATE TABLE `fa_admin_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `admin_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '管理员ID',
  `username` varchar(30) DEFAULT '' COMMENT '管理员名字',
  `url` varchar(1500) DEFAULT '' COMMENT '操作页面',
  `title` varchar(100) DEFAULT '' COMMENT '日志标题',
  `content` longtext NOT NULL COMMENT '内容',
  `ip` varchar(50) DEFAULT '' COMMENT 'IP',
  `useragent` varchar(255) DEFAULT '' COMMENT 'User-Agent',
  `createtime` bigint(16) DEFAULT NULL COMMENT '操作时间',
  PRIMARY KEY (`id`),
  KEY `name` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=151 DEFAULT CHARSET=utf8mb4 COMMENT='管理员日志表';

-- ----------------------------
-- Table structure for fa_area
-- ----------------------------
DROP TABLE IF EXISTS `fa_area`;
CREATE TABLE `fa_area` (
  `id` int(10) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `pid` int(10) DEFAULT NULL COMMENT '父id',
  `shortname` varchar(100) DEFAULT NULL COMMENT '简称',
  `name` varchar(100) DEFAULT NULL COMMENT '名称',
  `mergename` varchar(255) DEFAULT NULL COMMENT '全称',
  `level` tinyint(4) DEFAULT NULL COMMENT '层级:1=省,2=市,3=区/县',
  `pinyin` varchar(100) DEFAULT NULL COMMENT '拼音',
  `code` varchar(100) DEFAULT NULL COMMENT '长途区号',
  `zip` varchar(100) DEFAULT NULL COMMENT '邮编',
  `first` varchar(50) DEFAULT NULL COMMENT '首字母',
  `lng` varchar(100) DEFAULT NULL COMMENT '经度',
  `lat` varchar(100) DEFAULT NULL COMMENT '纬度',
  PRIMARY KEY (`id`),
  KEY `pid` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='地区表';

-- ----------------------------
-- Table structure for fa_attachment
-- ----------------------------
DROP TABLE IF EXISTS `fa_attachment`;
CREATE TABLE `fa_attachment` (
  `id` int(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `category` varchar(50) DEFAULT '' COMMENT '类别',
  `admin_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '管理员ID',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '会员ID',
  `url` varchar(255) DEFAULT '' COMMENT '物理路径',
  `imagewidth` int(10) unsigned DEFAULT '0' COMMENT '宽度',
  `imageheight` int(10) unsigned DEFAULT '0' COMMENT '高度',
  `imagetype` varchar(30) DEFAULT '' COMMENT '图片类型',
  `imageframes` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '图片帧数',
  `filename` varchar(100) DEFAULT '' COMMENT '文件名称',
  `filesize` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '文件大小',
  `mimetype` varchar(100) DEFAULT '' COMMENT 'mime类型',
  `extparam` varchar(255) DEFAULT '' COMMENT '透传数据',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建日期',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  `uploadtime` bigint(16) DEFAULT NULL COMMENT '上传时间',
  `storage` varchar(100) NOT NULL DEFAULT 'local' COMMENT '存储位置',
  `sha1` varchar(40) DEFAULT '' COMMENT '文件 sha1编码',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COMMENT='附件表';

-- ----------------------------
-- Table structure for fa_auth_group
-- ----------------------------
DROP TABLE IF EXISTS `fa_auth_group`;
CREATE TABLE `fa_auth_group` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '父组别',
  `name` varchar(100) DEFAULT '' COMMENT '组名',
  `rules` text NOT NULL COMMENT '规则ID',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  `status` varchar(30) DEFAULT '' COMMENT '状态',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COMMENT='分组表';

-- ----------------------------
-- Table structure for fa_auth_group_access
-- ----------------------------
DROP TABLE IF EXISTS `fa_auth_group_access`;
CREATE TABLE `fa_auth_group_access` (
  `uid` int(10) unsigned NOT NULL COMMENT '会员ID',
  `group_id` int(10) unsigned NOT NULL COMMENT '级别ID',
  UNIQUE KEY `uid_group_id` (`uid`,`group_id`),
  KEY `uid` (`uid`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='权限分组表';

-- ----------------------------
-- Table structure for fa_auth_rule
-- ----------------------------
DROP TABLE IF EXISTS `fa_auth_rule`;
CREATE TABLE `fa_auth_rule` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('menu','file') NOT NULL DEFAULT 'file' COMMENT 'menu为菜单,file为权限节点',
  `pid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '父ID',
  `name` varchar(100) DEFAULT '' COMMENT '规则名称',
  `title` varchar(50) DEFAULT '' COMMENT '规则名称',
  `icon` varchar(50) DEFAULT '' COMMENT '图标',
  `url` varchar(255) DEFAULT '' COMMENT '规则URL',
  `condition` varchar(255) DEFAULT '' COMMENT '条件',
  `remark` varchar(255) DEFAULT '' COMMENT '备注',
  `ismenu` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否为菜单',
  `menutype` enum('addtabs','blank','dialog','ajax') DEFAULT NULL COMMENT '菜单类型',
  `extend` varchar(255) DEFAULT '' COMMENT '扩展属性',
  `py` varchar(30) DEFAULT '' COMMENT '拼音首字母',
  `pinyin` varchar(100) DEFAULT '' COMMENT '拼音',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  `weigh` int(10) NOT NULL DEFAULT '0' COMMENT '权重',
  `status` varchar(30) DEFAULT '' COMMENT '状态',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`) USING BTREE,
  KEY `pid` (`pid`),
  KEY `weigh` (`weigh`)
) ENGINE=InnoDB AUTO_INCREMENT=522 DEFAULT CHARSET=utf8mb4 COMMENT='节点表';

-- ----------------------------
-- Table structure for fa_category
-- ----------------------------
DROP TABLE IF EXISTS `fa_category`;
CREATE TABLE `fa_category` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '父ID',
  `type` varchar(30) DEFAULT '' COMMENT '栏目类型',
  `name` varchar(30) DEFAULT '',
  `nickname` varchar(50) DEFAULT '',
  `flag` set('hot','index','recommend') DEFAULT '',
  `image` varchar(100) DEFAULT '' COMMENT '图片',
  `keywords` varchar(255) DEFAULT '' COMMENT '关键字',
  `description` varchar(255) DEFAULT '' COMMENT '描述',
  `diyname` varchar(30) DEFAULT '' COMMENT '自定义名称',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  `weigh` int(10) NOT NULL DEFAULT '0' COMMENT '权重',
  `status` varchar(30) DEFAULT '' COMMENT '状态',
  PRIMARY KEY (`id`),
  KEY `weigh` (`weigh`,`id`),
  KEY `pid` (`pid`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COMMENT='分类表';

-- ----------------------------
-- Table structure for fa_config
-- ----------------------------
DROP TABLE IF EXISTS `fa_config`;
CREATE TABLE `fa_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) DEFAULT '' COMMENT '变量名',
  `group` varchar(30) DEFAULT '' COMMENT '分组',
  `title` varchar(100) DEFAULT '' COMMENT '变量标题',
  `tip` varchar(100) DEFAULT '' COMMENT '变量描述',
  `type` varchar(30) DEFAULT '' COMMENT '类型:string,text,int,bool,array,datetime,date,file',
  `visible` varchar(255) DEFAULT '' COMMENT '可见条件',
  `value` text COMMENT '变量值',
  `content` text COMMENT '变量字典数据',
  `rule` varchar(100) DEFAULT '' COMMENT '验证规则',
  `extend` varchar(255) DEFAULT '' COMMENT '扩展属性',
  `setting` varchar(255) DEFAULT '' COMMENT '配置',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COMMENT='系统配置';

-- ----------------------------
-- Table structure for fa_ems
-- ----------------------------
DROP TABLE IF EXISTS `fa_ems`;
CREATE TABLE `fa_ems` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `event` varchar(30) DEFAULT '' COMMENT '事件',
  `email` varchar(100) DEFAULT '' COMMENT '邮箱',
  `code` varchar(10) DEFAULT '' COMMENT '验证码',
  `times` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '验证次数',
  `ip` varchar(30) DEFAULT '' COMMENT 'IP',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邮箱验证码表';

-- ----------------------------
-- Table structure for fa_kuerp_accounts
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_accounts`;
CREATE TABLE `fa_kuerp_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `account_coding` varchar(100) NOT NULL COMMENT '账户编号',
  `account_name` varchar(100) NOT NULL COMMENT '账户名称',
  `balance` double(15,4) DEFAULT NULL COMMENT '当前余额',
  `start_account_balance` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '期初余额',
  `start_account_time` varchar(20) NOT NULL DEFAULT '' COMMENT '建账日期',
  `account_type_id` enum('1','2') NOT NULL DEFAULT '1' COMMENT '账户类型:1=现金,2=银行存款',
  `is_default` enum('0','1') DEFAULT '0' COMMENT '默认:0=否,1=是',
  `status` enum('0','1') DEFAULT '1' COMMENT '状态:0=关闭,1=正常',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  `deletetime` bigint(16) DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='账户表';

-- ----------------------------
-- Table structure for fa_kuerp_accounts_copewith
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_accounts_copewith`;
CREATE TABLE `fa_kuerp_accounts_copewith` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `suppliers_id` int(11) NOT NULL DEFAULT '0' COMMENT '供应商',
  `purchase_time` bigint(16) DEFAULT NULL,
  `purchase_code` varchar(100) NOT NULL DEFAULT '' COMMENT '单据编号',
  `business_type` enum('0','1','2','3','4','5','6','7','8','9','10') DEFAULT NULL COMMENT '业务类型:0=期初余额,1=预收冲应收,2=预付冲应付,3=应收冲应付,4=应收转应收,5=应付转应付,6=普通采购,7=采购退回,8=其他支出,9=付款,10=核销',
  `receivable` double(15,4) DEFAULT '0.0000' COMMENT '增加应付款',
  `advance` double(15,4) DEFAULT '0.0000' COMMENT '增加预付款',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `sale_money` double(15,4) DEFAULT NULL COMMENT '购货金额',
  `receivable_money` double(15,4) DEFAULT NULL COMMENT '应付金额',
  `amount` double(15,4) DEFAULT NULL COMMENT '优惠金额',
  `cost` double(15,4) DEFAULT NULL,
  `real_money` double(15,4) DEFAULT NULL COMMENT '实际付款金额',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='应付账款明细表';

-- ----------------------------
-- Table structure for fa_kuerp_accounts_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_accounts_log`;
CREATE TABLE `fa_kuerp_accounts_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `account_coding` varchar(100) NOT NULL COMMENT '账户编号',
  `account_name` varchar(100) NOT NULL COMMENT '账户名称',
  `balance` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '当前余额',
  `income` double(15,4) DEFAULT NULL COMMENT '收入',
  `expenditure` double(15,4) DEFAULT NULL COMMENT '支出',
  `pay_time` bigint(16) DEFAULT NULL,
  `bill_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `business_type` tinyint(3) DEFAULT NULL COMMENT '业务类型:1=期初余额,2=普通采购,3=购货付款,4=购货退货,5=销货,6=销货退货,7=收款单,8=付款单,9=其他收入单,10=其他支出单,11=转账单转入,12=转账单转出',
  `supplyer_code` varchar(50) DEFAULT NULL COMMENT '往来单位',
  `customers_id` int(11) DEFAULT NULL COMMENT '往来单位',
  `payment` int(11) DEFAULT NULL COMMENT '收/付款人',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  `deletetime` bigint(16) DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COMMENT='账户记录表';

-- ----------------------------
-- Table structure for fa_kuerp_accounts_receivable
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_accounts_receivable`;
CREATE TABLE `fa_kuerp_accounts_receivable` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `customers_id` int(11) NOT NULL DEFAULT '0' COMMENT '客户',
  `purchase_time` bigint(16) DEFAULT NULL,
  `purchase_code` varchar(100) NOT NULL DEFAULT '' COMMENT '单据编号',
  `business_type` enum('0','1','2','3','4','5','6','7','8','9','10') DEFAULT NULL COMMENT '业务类型:0=期初余额,1=预收冲应收,2=预付冲应付,3=应收冲应付,4=应收转应收,5=应付转应付,6=普通销售,7=销售退回,8=其他收入,9=收款,10=核销',
  `receivable` double(15,4) DEFAULT NULL COMMENT '增加应收款',
  `advance` double(15,4) DEFAULT NULL COMMENT '增加预收款',
  `staff_member_id` int(11) DEFAULT NULL COMMENT '销售人员',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `sale_money` double(15,4) DEFAULT NULL COMMENT '销售金额',
  `receivable_money` double(15,4) DEFAULT NULL COMMENT '应收金额',
  `amount` double(15,4) DEFAULT NULL COMMENT '优惠金额',
  `cost` double(15,4) DEFAULT NULL COMMENT '客户承担费用',
  `real_money` double(15,4) DEFAULT NULL COMMENT '实际收款金额',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='应收账款明细表';

-- ----------------------------
-- Table structure for fa_kuerp_accounts_transfer
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_accounts_transfer`;
CREATE TABLE `fa_kuerp_accounts_transfer` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `money` double(15,4) DEFAULT NULL COMMENT '合计金额',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `print_num` int(11) DEFAULT NULL COMMENT '打印次数',
  `pay_status` enum('0','1','2') DEFAULT '0' COMMENT '付款状态:0=未付款,1=全部付款,2=部分付款',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='资金转账单';

-- ----------------------------
-- Table structure for fa_kuerp_accounts_transfer_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_accounts_transfer_info`;
CREATE TABLE `fa_kuerp_accounts_transfer_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_id` int(11) DEFAULT NULL COMMENT '转账单ID',
  `purchase_time` bigint(16) DEFAULT NULL,
  `accounts_out_id` int(11) DEFAULT NULL COMMENT '转出账户',
  `accounts_enter_id` int(11) DEFAULT NULL COMMENT '转入账户',
  `settlementtype_id` int(11) DEFAULT NULL COMMENT '结算方式',
  `settlement_number` varchar(50) DEFAULT NULL COMMENT '结算号',
  `money` double(15,4) DEFAULT NULL COMMENT '金额',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='转账单详情';

-- ----------------------------
-- Table structure for fa_kuerp_address
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_address`;
CREATE TABLE `fa_kuerp_address` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `nickname` varchar(100) DEFAULT NULL COMMENT '地址简称',
  `connect_name` varchar(100) NOT NULL COMMENT '联系人',
  `connect_phone` varchar(20) NOT NULL COMMENT '联系电话',
  `connect_mobile` varchar(11) NOT NULL COMMENT '手机号码',
  `province` int(11) NOT NULL DEFAULT '0' COMMENT '省',
  `city` int(11) NOT NULL DEFAULT '0' COMMENT '市',
  `area` int(11) NOT NULL DEFAULT '0' COMMENT '区',
  `detail_address` varchar(255) NOT NULL COMMENT '详细地址',
  `post_code` varchar(50) NOT NULL COMMENT '邮政编码',
  `is_default` enum('0','1') NOT NULL DEFAULT '0' COMMENT '默认地址:0=否,1=是',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='发货地址表';

-- ----------------------------
-- Table structure for fa_kuerp_adjustment
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_adjustment`;
CREATE TABLE `fa_kuerp_adjustment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1') DEFAULT NULL COMMENT '业务类型:1=成本调整',
  `money` double(15,4) DEFAULT NULL COMMENT '金额',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `print_num` int(11) DEFAULT NULL COMMENT '打印次数',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='成本调整单';

-- ----------------------------
-- Table structure for fa_kuerp_adjustment_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_adjustment_info`;
CREATE TABLE `fa_kuerp_adjustment_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `goods_id` int(11) DEFAULT NULL COMMENT '商品ID',
  `goods_unit` int(11) DEFAULT '0' COMMENT '单位',
  `money` double(15,4) DEFAULT NULL COMMENT '金额',
  `stores_id` int(11) DEFAULT NULL COMMENT '仓库',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='成本调整单详情';

-- ----------------------------
-- Table structure for fa_kuerp_allocation
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_allocation`;
CREATE TABLE `fa_kuerp_allocation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) NOT NULL DEFAULT '' COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL COMMENT '备注',
  `print_num` int(11) DEFAULT '0' COMMENT '打印次数',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='调拨单';

-- ----------------------------
-- Table structure for fa_kuerp_allocation_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_allocation_info`;
CREATE TABLE `fa_kuerp_allocation_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `allocation_id` varchar(50) NOT NULL DEFAULT '' COMMENT '调拨单ID',
  `goods_id` int(11) DEFAULT NULL COMMENT '商品ID',
  `goods_attr` int(11) DEFAULT NULL COMMENT '商品属性',
  `goods_unit` int(11) DEFAULT NULL COMMENT '商品单位',
  `stores_out` int(11) DEFAULT NULL COMMENT '调出仓库',
  `stores_enter` int(11) DEFAULT NULL COMMENT '调入仓库',
  `number` int(11) DEFAULT NULL COMMENT '数量',
  `goods_batch_id` int(11) DEFAULT NULL COMMENT '批次ID',
  `goods_batch_name` varchar(255) DEFAULT NULL COMMENT '批次',
  `goods_serial_id` text COMMENT '序列号',
  `remarks` varchar(255) DEFAULT NULL COMMENT '分录备注',
  `allow_stock` double(15,4) DEFAULT '0.0000' COMMENT '可用库存',
  `goods_note` varchar(255) DEFAULT NULL COMMENT '商品备注',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='调拨单详情';

-- ----------------------------
-- Table structure for fa_kuerp_area
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_area`;
CREATE TABLE `fa_kuerp_area` (
  `id` int(10) NOT NULL COMMENT 'ID',
  `pid` int(10) DEFAULT NULL COMMENT '父id',
  `shortname` varchar(100) DEFAULT NULL COMMENT '简称',
  `name` varchar(100) DEFAULT NULL COMMENT '名称',
  `level` tinyint(4) DEFAULT NULL COMMENT '层级 0 1 2 省市区县',
  `adcode` varchar(100) DEFAULT NULL COMMENT '地区编号',
  `lng` varchar(100) DEFAULT NULL COMMENT '经度',
  `lat` varchar(100) DEFAULT NULL COMMENT '纬度'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='地区表';

-- ----------------------------
-- Table structure for fa_kuerp_assemble
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_assemble`;
CREATE TABLE `fa_kuerp_assemble` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2') DEFAULT NULL COMMENT '业务类型:1=组装单',
  `money` double(15,4) DEFAULT NULL COMMENT '费用',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `print_num` int(11) DEFAULT NULL COMMENT '打印次数',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='组装单';

-- ----------------------------
-- Table structure for fa_kuerp_assemble_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_assemble_info`;
CREATE TABLE `fa_kuerp_assemble_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) NOT NULL DEFAULT '' COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2') NOT NULL DEFAULT '1' COMMENT '类型:1=组装单',
  `type` enum('1','2') NOT NULL DEFAULT '2' COMMENT '类型:1=组合件,2=子件',
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `goods_code` varchar(50) NOT NULL DEFAULT '' COMMENT '商品编号',
  `goods_attr` int(11) DEFAULT NULL COMMENT '商品属性',
  `goods_unit` int(11) NOT NULL DEFAULT '0' COMMENT '商品单位',
  `stores_id` int(11) NOT NULL DEFAULT '0' COMMENT '仓库',
  `allow_stock` double(15,4) DEFAULT '0.0000' COMMENT '可用库存',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `period` text COMMENT '序列号',
  `basic_number` double(15,4) DEFAULT '0.0000' COMMENT '基本数量',
  `unit_cost` double DEFAULT '0' COMMENT '单位成本',
  `cost_money` double DEFAULT '0' COMMENT '成本',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `batch_id` int(11) DEFAULT NULL COMMENT '批次ID',
  `batch` varchar(255) DEFAULT NULL COMMENT '批次',
  `manufacture` int(11) DEFAULT NULL COMMENT '生产日期',
  `guarantee` int(11) DEFAULT NULL COMMENT '保质期',
  `validity` int(11) DEFAULT NULL COMMENT '过期时间',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `print_num` int(11) DEFAULT '0' COMMENT '打印次数',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='组装单详情';

-- ----------------------------
-- Table structure for fa_kuerp_assemble_info_temp
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_assemble_info_temp`;
CREATE TABLE `fa_kuerp_assemble_info_temp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) NOT NULL DEFAULT '' COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2') NOT NULL DEFAULT '1' COMMENT '类型:1=组装单',
  `type` enum('1','2') NOT NULL DEFAULT '2' COMMENT '类型:1=组合件,2=子件',
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `goods_code` varchar(50) NOT NULL DEFAULT '' COMMENT '商品编号',
  `goods_attr` int(11) DEFAULT NULL COMMENT '商品属性',
  `goods_unit` int(11) NOT NULL DEFAULT '0' COMMENT '商品单位',
  `stores_id` int(11) NOT NULL DEFAULT '0' COMMENT '仓库',
  `allow_stock` double(15,4) DEFAULT '0.0000' COMMENT '可用库存',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `period` text COMMENT '序列号',
  `basic_number` double(15,4) DEFAULT '0.0000' COMMENT '基本数量',
  `unit_cost` double DEFAULT '0' COMMENT '单位成本',
  `cost_money` double DEFAULT '0' COMMENT '成本',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `batch_id` int(11) DEFAULT NULL COMMENT '批次ID',
  `batch` varchar(255) DEFAULT NULL COMMENT '批次',
  `manufacture` int(11) DEFAULT NULL COMMENT '生产日期',
  `guarantee` int(11) DEFAULT NULL COMMENT '保质期',
  `validity` int(11) DEFAULT NULL COMMENT '过期时间',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `print_num` int(11) DEFAULT '0' COMMENT '打印次数',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='组装单详情模板';

-- ----------------------------
-- Table structure for fa_kuerp_assemble_temp
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_assemble_temp`;
CREATE TABLE `fa_kuerp_assemble_temp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `temp_name` varchar(255) NOT NULL DEFAULT '' COMMENT '模板名称',
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2') DEFAULT NULL COMMENT '业务类型:1=组装单',
  `money` double(15,4) DEFAULT NULL COMMENT '费用',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `print_num` int(11) DEFAULT NULL COMMENT '打印次数',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='组装单模板';

-- ----------------------------
-- Table structure for fa_kuerp_attr_combination
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_attr_combination`;
CREATE TABLE `fa_kuerp_attr_combination` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attr_name` varchar(255) DEFAULT NULL,
  `attr_ids` varchar(255) NOT NULL DEFAULT '' COMMENT '属性组合ID(从小到大)',
  `attr_code` varchar(30) NOT NULL DEFAULT '' COMMENT '属性编号',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='属性组合';

-- ----------------------------
-- Table structure for fa_kuerp_attribute
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_attribute`;
CREATE TABLE `fa_kuerp_attribute` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL COMMENT '属性名称',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='属性';

-- ----------------------------
-- Table structure for fa_kuerp_aux_combination
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_aux_combination`;
CREATE TABLE `fa_kuerp_aux_combination` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) unsigned DEFAULT NULL,
  `attr_ids` varchar(255) NOT NULL DEFAULT '' COMMENT '属性组合ID(从小到大)',
  `attr_code` varchar(30) NOT NULL DEFAULT '' COMMENT '属性编号',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='辅助属性组合';

-- ----------------------------
-- Table structure for fa_kuerp_auxiliary
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_auxiliary`;
CREATE TABLE `fa_kuerp_auxiliary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `name` varchar(120) NOT NULL DEFAULT '' COMMENT '属性名称',
  `image` varchar(255) NOT NULL DEFAULT '' COMMENT '图片',
  `pid` int(11) NOT NULL DEFAULT '0' COMMENT '上级',
  `path` varchar(255) DEFAULT NULL COMMENT 'path',
  `weigh` int(11) DEFAULT NULL,
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态:0=禁用,1=正常',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='商品辅助属性';

-- ----------------------------
-- Table structure for fa_kuerp_checkout
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_checkout`;
CREATE TABLE `fa_kuerp_checkout` (
  `id` int(10) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `checkdate` varchar(50) DEFAULT NULL COMMENT '结账日期',
  `checktime` bigint(16) DEFAULT NULL,
  `preparation_id` int(11) DEFAULT NULL COMMENT '操作人',
  `preparation_time` bigint(16) DEFAULT NULL,
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='结账表';

-- ----------------------------
-- Table structure for fa_kuerp_checkout_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_checkout_info`;
CREATE TABLE `fa_kuerp_checkout_info` (
  `id` int(10) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `checkout_id` int(11) DEFAULT NULL COMMENT '结账ID',
  `goods_id` int(11) DEFAULT NULL COMMENT '商品名称',
  `goods_code` varchar(50) DEFAULT NULL COMMENT '商品编号',
  `basic_nmber` decimal(15,4) DEFAULT '0.0000' COMMENT '数量',
  `all_money` decimal(15,4) DEFAULT '0.0000' COMMENT '成本',
  `purchase_id` int(11) DEFAULT NULL COMMENT '结账截止购货ID',
  `purchase_time` bigint(16) DEFAULT NULL,
  `av_basic_nmber` decimal(15,4) DEFAULT '0.0000' COMMENT '移动平均数量',
  `av_all_money` decimal(15,4) DEFAULT '0.0000' COMMENT '移动平均成本',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='结账详情表';

-- ----------------------------
-- Table structure for fa_kuerp_collection
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_collection`;
CREATE TABLE `fa_kuerp_collection` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2') DEFAULT '1' COMMENT '类型:1=预收单,2=收款单',
  `customers_id` int(11) DEFAULT NULL COMMENT '客户',
  `money` double(15,4) DEFAULT NULL COMMENT '收款合计',
  `write_off` double(15,4) DEFAULT NULL COMMENT '核销金额',
  `discount` double(15,4) DEFAULT NULL COMMENT '整单折扣',
  `advance` double(15,4) DEFAULT NULL COMMENT '预收款',
  `staff_member_id` int(11) unsigned DEFAULT NULL COMMENT '收款人',
  `remarks` varchar(255) DEFAULT NULL COMMENT '备注',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `examine_time` bigint(16) DEFAULT NULL,
  `examine_status` enum('0','1') NOT NULL DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `print_num` int(11) DEFAULT NULL COMMENT '打印次数',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='收款单';

-- ----------------------------
-- Table structure for fa_kuerp_collection_account
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_collection_account`;
CREATE TABLE `fa_kuerp_collection_account` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `collection_id` int(11) NOT NULL DEFAULT '0' COMMENT '收款单ID',
  `purchase_code` varchar(50) NOT NULL DEFAULT '' COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `accounts_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '结算账户',
  `money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '收款金额',
  `settlementtype` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '结算方式',
  `number` varchar(50) DEFAULT '' COMMENT '结算号',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='收款账户信息';

-- ----------------------------
-- Table structure for fa_kuerp_collection_sale
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_collection_sale`;
CREATE TABLE `fa_kuerp_collection_sale` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `collection_id` int(11) NOT NULL DEFAULT '0' COMMENT '收款单ID',
  `purchase_code` varchar(50) NOT NULL DEFAULT '' COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `purchase_number` varchar(50) NOT NULL DEFAULT '' COMMENT '原单编号',
  `business_type` enum('1','2','3','4','5','6') NOT NULL DEFAULT '1' COMMENT '业务类别:1=预收单,2=预付单,3=应收,4=应付,5=期初应收/付款,6=期初预收/付款',
  `times` int(11) NOT NULL DEFAULT '0' COMMENT '单据日期',
  `money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '单据金额',
  `write_off` double(15,4) DEFAULT NULL COMMENT '已核销金额',
  `write_off_money` double(15,4) DEFAULT NULL COMMENT '未核销金额',
  `write_off_price` double(15,4) DEFAULT '0.0000' COMMENT '本次核销金额',
  `remark` varchar(255) DEFAULT NULL COMMENT '单据备注',
  `old_money` double(15,4) DEFAULT NULL,
  `old_status` tinyint(3) DEFAULT NULL,
  `old_status_i` tinyint(3) DEFAULT NULL,
  `business_name` varchar(255) DEFAULT NULL COMMENT '单据名称',
  `source_id` int(11) NOT NULL DEFAULT '0' COMMENT '源单ID',
  `type` enum('1','2','3','4') DEFAULT NULL COMMENT '类型:1=购货/销货,2=退货,3=期初,4=核销',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='收款单单据信息';

-- ----------------------------
-- Table structure for fa_kuerp_cost
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_cost`;
CREATE TABLE `fa_kuerp_cost` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2','3','4') DEFAULT NULL COMMENT '业务类型:1=采购,2=销售,3=采购退回,4=销售退回',
  `suppliers_id` int(11) DEFAULT NULL COMMENT '供应商',
  `suppliers_ids` int(11) DEFAULT NULL COMMENT '源单往来单位',
  `expend_type_id` int(11) DEFAULT NULL COMMENT '支出类别',
  `customers_id` int(11) DEFAULT NULL COMMENT '客户',
  `income_type_id` int(11) DEFAULT NULL COMMENT '收入类别',
  `purchase_number` varchar(50) DEFAULT NULL COMMENT '源单号',
  `purchase_times` int(11) DEFAULT NULL COMMENT '源单日期',
  `money` double(15,4) DEFAULT NULL COMMENT '金额',
  `pay_money` double(15,4) DEFAULT NULL COMMENT '未付金额',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `print_num` int(11) DEFAULT NULL COMMENT '打印次数',
  `pay_status` enum('0','1','2') DEFAULT '0' COMMENT '付款状态:0=未付款,1=全部付款,2=部分付款',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='采购销售费用清单';

-- ----------------------------
-- Table structure for fa_kuerp_customer_goodscoding
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_customer_goodscoding`;
CREATE TABLE `fa_kuerp_customer_goodscoding` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `customers_id` int(11) NOT NULL DEFAULT '0' COMMENT '客户',
  `goods_code` varchar(30) NOT NULL DEFAULT '' COMMENT '关联商品',
  `unit_id` int(11) DEFAULT NULL COMMENT '单位',
  `customer_code` varchar(50) NOT NULL DEFAULT '' COMMENT '客户物料编码',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客户物料编码';

-- ----------------------------
-- Table structure for fa_kuerp_customerlevel
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_customerlevel`;
CREATE TABLE `fa_kuerp_customerlevel` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '等级名称',
  `status` enum('0','1') NOT NULL DEFAULT '0' COMMENT '状态:0=禁用,1=正常',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COMMENT='客户等级';

-- ----------------------------
-- Table structure for fa_kuerp_customers
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_customers`;
CREATE TABLE `fa_kuerp_customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `customer_code` varchar(50) DEFAULT NULL COMMENT '客户编号',
  `customertype_id` int(2) NOT NULL DEFAULT '0' COMMENT '客户类别',
  `customerlevel_id` int(2) NOT NULL DEFAULT '1' COMMENT '客户等级',
  `customer_name` varchar(150) NOT NULL DEFAULT '' COMMENT '客户名称',
  `saler` varchar(100) NOT NULL DEFAULT '' COMMENT '销售人员',
  `connet_name` varchar(50) NOT NULL DEFAULT '' COMMENT '联系人',
  `customer_mobile` varchar(11) NOT NULL DEFAULT '' COMMENT '客户手机',
  `customer_phone` varchar(20) NOT NULL DEFAULT '' COMMENT '客户座机',
  `qq_wx_email` varchar(100) NOT NULL DEFAULT '' COMMENT 'QQ/微信/Email',
  `need_receive_account` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '应收款余额',
  `receive_account` double(15,4) DEFAULT NULL COMMENT '期初应收款',
  `opening_prepayment` double(15,4) DEFAULT NULL COMMENT '期初预收款',
  `identification` varchar(100) DEFAULT NULL COMMENT '纳税人识别号',
  `getgoods_address` varchar(255) NOT NULL DEFAULT '' COMMENT '收货地址',
  `note` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `deposit_bank` varchar(255) DEFAULT NULL COMMENT '开户银行',
  `bank_number` varchar(255) DEFAULT NULL COMMENT '银行卡号',
  `staff_member_id` int(11) DEFAULT NULL COMMENT '销售人员',
  `account_time` bigint(16) DEFAULT NULL,
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COMMENT='客户表';

-- ----------------------------
-- Table structure for fa_kuerp_customers_user
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_customers_user`;
CREATE TABLE `fa_kuerp_customers_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `customers_code` varchar(30) NOT NULL DEFAULT '' COMMENT '客户编号',
  `sid` int(11) unsigned DEFAULT NULL COMMENT '客户ID',
  `first_connect_name` varchar(100) NOT NULL DEFAULT '' COMMENT '联系人名称',
  `first_connect_mobile` varchar(11) NOT NULL COMMENT '手机',
  `first_connect_phone` varchar(20) NOT NULL COMMENT '座机',
  `qq_wx_email` varchar(120) NOT NULL COMMENT 'QQ/微信/Email',
  `connect_address` varchar(255) NOT NULL COMMENT '联系地址',
  `is_first` tinyint(3) DEFAULT '0' COMMENT '首要联系人:0=否,1=是',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态:0=禁用,1=正常',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  `deletetime` bigint(16) DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='客户联系人';

-- ----------------------------
-- Table structure for fa_kuerp_customertype
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_customertype`;
CREATE TABLE `fa_kuerp_customertype` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `type_name` varchar(100) NOT NULL COMMENT '分类名称',
  `pid` int(11) NOT NULL DEFAULT '0' COMMENT '上级分类id',
  `path` varchar(255) DEFAULT NULL,
  `type_image` varchar(255) DEFAULT NULL COMMENT '分类图',
  `status` enum('0','1') NOT NULL DEFAULT '0' COMMENT '状态:0=禁用,1=正常',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='客户分类表';

-- ----------------------------
-- Table structure for fa_kuerp_enclosure
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_enclosure`;
CREATE TABLE `fa_kuerp_enclosure` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `image` varchar(255) NOT NULL DEFAULT '' COMMENT '图片',
  `type` enum('1','2') DEFAULT '1' COMMENT '类型:1=购货,2=销货',
  `status` enum('0','1') DEFAULT '1' COMMENT '状态:0=禁用,1=启用',
  `upload_date` varchar(30) DEFAULT NULL COMMENT '上传日期',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='原始单据表';

-- ----------------------------
-- Table structure for fa_kuerp_enclosure_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_enclosure_log`;
CREATE TABLE `fa_kuerp_enclosure_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `enclosure_id` int(11) NOT NULL DEFAULT '0' COMMENT '附件ID',
  `order_id` int(11) DEFAULT '0' COMMENT '单据ID',
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `type` enum('1','2','3','4','5','6') DEFAULT NULL COMMENT '单据类型:1=购货订单,2:购货单,3:购货退货单,4:销货订单,5:销货单,6:销货退货单',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='原始单据关联表';

-- ----------------------------
-- Table structure for fa_kuerp_expend_type
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_expend_type`;
CREATE TABLE `fa_kuerp_expend_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `name` varchar(120) NOT NULL DEFAULT '' COMMENT '名称',
  `image` varchar(255) NOT NULL DEFAULT '' COMMENT '图片',
  `pid` int(11) NOT NULL DEFAULT '0' COMMENT '上级分类id',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态:0=禁用,1=正常',
  `path` varchar(255) DEFAULT NULL,
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='支出类别表';

-- ----------------------------
-- Table structure for fa_kuerp_expenditure
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_expenditure`;
CREATE TABLE `fa_kuerp_expenditure` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2') DEFAULT NULL COMMENT '业务类型:1=其他支出,2=其他',
  `suppliers_id` int(11) DEFAULT NULL COMMENT '供应商',
  `accounts_id` int(11) DEFAULT NULL COMMENT '账户',
  `cost_id` int(11) DEFAULT NULL COMMENT '采购销售ID',
  `money` double(15,4) DEFAULT NULL COMMENT '金额',
  `pay_money` double(15,4) DEFAULT NULL COMMENT '已付金额',
  `arrears` double(15,4) DEFAULT NULL COMMENT '本次欠款',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `print_num` int(11) DEFAULT NULL COMMENT '打印次数',
  `pay_status` enum('0','1','2') DEFAULT '0' COMMENT '付款状态:0=未付款,1=全部付款,2=部分付款',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='其他支出单';

-- ----------------------------
-- Table structure for fa_kuerp_expenditure_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_expenditure_info`;
CREATE TABLE `fa_kuerp_expenditure_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_id` int(11) DEFAULT NULL COMMENT '收入单ID',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2') DEFAULT NULL COMMENT '业务类型:1=其他收入',
  `suppliers_id` int(11) DEFAULT NULL COMMENT '供应商',
  `expend_type_id` int(11) DEFAULT NULL COMMENT '支出类别',
  `money` double(15,4) DEFAULT NULL COMMENT '金额',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `cost_id` int(11) DEFAULT NULL,
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='其他支出单详情';

-- ----------------------------
-- Table structure for fa_kuerp_goods
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods`;
CREATE TABLE `fa_kuerp_goods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) unsigned DEFAULT NULL,
  `goods_code` varchar(30) NOT NULL DEFAULT '' COMMENT '商品编号',
  `goods_name` varchar(255) NOT NULL DEFAULT '' COMMENT '商品名称',
  `goods_barcode` varchar(50) DEFAULT '' COMMENT '商品条码',
  `goods_specification_id` varchar(50) NOT NULL DEFAULT '' COMMENT '规格型号',
  `goods_type_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品类别',
  `manager_warehouse_id` int(11) NOT NULL DEFAULT '0' COMMENT '首选仓库',
  `unit_id` int(11) NOT NULL DEFAULT '0' COMMENT '计量单位',
  `more_unit` enum('1','2') NOT NULL DEFAULT '2' COMMENT '多计量单位:1=是,2=否',
  `more_unit_id` int(11) DEFAULT NULL COMMENT '多单位ID',
  `market_sell_price` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '零售价',
  `wholesale_price` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '批发价',
  `vip_price` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT 'vip会员价',
  `discount` double(15,4) unsigned DEFAULT '0.0000' COMMENT '折扣率1',
  `discount2` double(15,4) unsigned DEFAULT '0.0000' COMMENT '折扣率2',
  `purchase` double(15,4) unsigned DEFAULT '0.0000' COMMENT '预计采购价',
  `goods_image` varchar(255) NOT NULL DEFAULT '' COMMENT '商品主图',
  `goods_images` text COMMENT '商品图集',
  `content` text COMMENT '商品详情',
  `inventory` double(15,4) NOT NULL DEFAULT '0.0000',
  `more_attribute` enum('0','1') DEFAULT '0' COMMENT '多属性:0=否,1=是',
  `attribute_id` varchar(30) DEFAULT '' COMMENT '商品属性条码',
  `note` varchar(255) NOT NULL COMMENT '备注',
  `is_offten` enum('0','1') NOT NULL DEFAULT '0' COMMENT '是否常用:0=否,1=是',
  `stock_warning` enum('0','1') DEFAULT '0' COMMENT '库存预警:0=否,1=是',
  `min_stock` decimal(15,4) DEFAULT '0.0000',
  `max_stock` decimal(15,4) DEFAULT '0.0000',
  `branch` enum('0','1') DEFAULT '0' COMMENT '分仓预警:0=否,1=是',
  `warehousing` int(11) DEFAULT NULL COMMENT '首选入库单位',
  `warehouse` int(11) DEFAULT NULL COMMENT '首选出库单位',
  `period` double(15,4) DEFAULT '0.0000' COMMENT '期初数量',
  `unit_cost` double(15,4) DEFAULT '0.0000' COMMENT '单位成本',
  `price` double(15,4) DEFAULT '0.0000' COMMENT '期初总价',
  `period_status` enum('0','1') DEFAULT '0' COMMENT '期初设置',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态:0=下架,1=上架',
  `is_edit` enum('0','1') DEFAULT '1' COMMENT '允许修改:0=否,1=是',
  `auxiliary` tinyint(3) DEFAULT NULL COMMENT '设置商品辅助属性',
  `auxiliary_type` varchar(255) DEFAULT NULL COMMENT '辅助属性',
  `auxiliary_type_c` varchar(255) DEFAULT NULL COMMENT '辅助属性2',
  `batch` tinyint(3) DEFAULT NULL COMMENT '启用批次保质期管理',
  `shelf_life` int(11) DEFAULT NULL COMMENT '保质期天数',
  `early_warning` int(11) DEFAULT NULL COMMENT '预警天数',
  `serial` tinyint(3) DEFAULT NULL COMMENT '启用序列号',
  `tag` set('hot','sale') DEFAULT 'sale' COMMENT '标签',
  `sale_number` double(15,4) DEFAULT '0.0000' COMMENT '销量',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  `deletetime` bigint(16) DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COMMENT='商品表';

-- ----------------------------
-- Table structure for fa_kuerp_goods_attr
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_attr`;
CREATE TABLE `fa_kuerp_goods_attr` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `goods_code` varchar(30) NOT NULL DEFAULT '' COMMENT '商品编号',
  `goods_name` varchar(255) NOT NULL DEFAULT '' COMMENT '商品名称',
  `goods_barcode` varchar(50) DEFAULT '' COMMENT '商品条码',
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `attribute_id` int(11) DEFAULT NULL COMMENT '属性组合ID',
  `attr_code` varchar(30) DEFAULT NULL COMMENT '属性编号',
  `barcode` varchar(30) DEFAULT NULL COMMENT '属性条码',
  `unit` int(11) NOT NULL DEFAULT '0' COMMENT '计量单位',
  `market_sell_price` double(15,4) unsigned DEFAULT NULL COMMENT '零售价',
  `wholesale_price` double(15,4) unsigned DEFAULT NULL COMMENT '批发价',
  `vip_price` double(15,4) unsigned DEFAULT NULL COMMENT 'vip会员价',
  `discount` double(15,4) unsigned DEFAULT NULL COMMENT '折扣率1',
  `discount2` double(15,4) unsigned DEFAULT NULL COMMENT '折扣率2',
  `purchase` double(15,4) unsigned DEFAULT NULL COMMENT '预计采购价',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品属性价格表';

-- ----------------------------
-- Table structure for fa_kuerp_goods_batch
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_batch`;
CREATE TABLE `fa_kuerp_goods_batch` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `goods_code` varchar(30) DEFAULT NULL COMMENT '商品编号',
  `attribute_id` int(11) DEFAULT '0',
  `stores_id` int(11) NOT NULL DEFAULT '0' COMMENT '仓库ID',
  `batch_name` varchar(255) DEFAULT NULL COMMENT '批次',
  `manufacture` int(11) unsigned DEFAULT NULL COMMENT '生产日期',
  `guarantee` int(11) unsigned DEFAULT NULL COMMENT '保质期',
  `validity` int(11) DEFAULT NULL COMMENT '有效期至',
  `address` varchar(255) DEFAULT NULL COMMENT '产地',
  `registration` varchar(255) DEFAULT NULL COMMENT '注册证号',
  `production` varchar(255) DEFAULT NULL COMMENT '生产许可证',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品批次表';

-- ----------------------------
-- Table structure for fa_kuerp_goods_batch_exchange_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_batch_exchange_info`;
CREATE TABLE `fa_kuerp_goods_batch_exchange_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `goods_code` varchar(30) DEFAULT NULL COMMENT '商品编号',
  `attribute_id` varchar(30) DEFAULT NULL COMMENT '属性组合ID',
  `goods_unit` int(11) DEFAULT NULL COMMENT '商品单位',
  `stores_id` int(11) NOT NULL DEFAULT '0' COMMENT '仓库ID',
  `stock` double(15,4) unsigned NOT NULL DEFAULT '0.0000' COMMENT '库存',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态:0=关闭,1=正常',
  `number` float(15,4) unsigned DEFAULT '0.0000' COMMENT '期初数量',
  `batch_name` varchar(255) DEFAULT NULL COMMENT '批次',
  `manufacture` int(11) unsigned DEFAULT NULL COMMENT '生产日期',
  `guarantee` int(11) unsigned DEFAULT NULL COMMENT '保质期',
  `validity` int(11) DEFAULT NULL COMMENT '有效期至',
  `purchase_id` int(11) DEFAULT NULL COMMENT '购货单号',
  `purchase_info_id` int(11) DEFAULT NULL COMMENT '购货单详情ID',
  `type` tinyint(3) DEFAULT '0' COMMENT '类型:1=期初,2=购货',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品退货批次详情表';

-- ----------------------------
-- Table structure for fa_kuerp_goods_batch_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_batch_info`;
CREATE TABLE `fa_kuerp_goods_batch_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `goods_code` varchar(30) DEFAULT NULL COMMENT '商品编号',
  `attribute_id` int(11) DEFAULT '0',
  `goods_unit` int(11) DEFAULT NULL COMMENT '商品单位',
  `stores_id` int(11) NOT NULL DEFAULT '0' COMMENT '仓库ID',
  `stock` double(15,4) unsigned NOT NULL DEFAULT '0.0000' COMMENT '库存',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态:0=关闭,1=正常',
  `number` float(15,4) unsigned DEFAULT '0.0000' COMMENT '期初数量',
  `batch_name` varchar(255) DEFAULT NULL COMMENT '批次',
  `manufacture` int(11) unsigned DEFAULT NULL COMMENT '生产日期',
  `guarantee` int(11) unsigned DEFAULT NULL COMMENT '保质期',
  `validity` int(11) DEFAULT NULL COMMENT '有效期至',
  `purchase_id` int(11) DEFAULT NULL COMMENT '购货单号',
  `purchase_info_id` int(11) DEFAULT NULL COMMENT '购货单详情ID',
  `assemble_id` int(11) DEFAULT NULL COMMENT '组合单ID',
  `split_id` int(11) DEFAULT NULL COMMENT '拆卸单ID',
  `type` tinyint(3) DEFAULT '0' COMMENT '类型:1=期初,2=购货,3=盘盈,4=其他入库,5=组合单,6=拆卸单',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品批次详情表';

-- ----------------------------
-- Table structure for fa_kuerp_goods_batch_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_batch_log`;
CREATE TABLE `fa_kuerp_goods_batch_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `unit_price` double(15,4) DEFAULT '0.0000' COMMENT '商品单价',
  `tax_included` double(15,4) DEFAULT '0.0000' COMMENT '含税单价',
  `all_money` double(15,4) DEFAULT '0.0000' COMMENT '金额',
  `tax_money` double(15,4) DEFAULT '0.0000' COMMENT '税额',
  `money_tax_price` double(15,4) DEFAULT '0.0000' COMMENT '价税合计',
  `goods_unit` int(11) DEFAULT NULL COMMENT '商品单位',
  `stores_id` int(11) DEFAULT NULL COMMENT '仓库ID',
  `batch_id` int(11) DEFAULT NULL COMMENT '批次ID',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2','3','4','5','6','7','8','9','10','11','12','13','14') DEFAULT NULL COMMENT '业务类型:1=期初余额,2=普通采购,3=采购退回,4=普通销售,5=销售退回,6=调拨单,7=盘盈,8=其他入库,9=盘亏,10=其他出库,11=组合单,12=组合单,13=拆卸单,14=拆卸单',
  `customer_id` int(11) DEFAULT NULL COMMENT '客户/供应商',
  `sale_id` int(11) DEFAULT NULL COMMENT '销售人员',
  `remarks` varchar(255) DEFAULT NULL COMMENT '备注',
  `order_id` int(11) DEFAULT NULL COMMENT '来源关联ID',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='批次跟踪表';

-- ----------------------------
-- Table structure for fa_kuerp_goods_batch_sale_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_batch_sale_info`;
CREATE TABLE `fa_kuerp_goods_batch_sale_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `goods_code` varchar(30) DEFAULT NULL COMMENT '商品编号',
  `attribute_id` varchar(30) DEFAULT NULL COMMENT '属性组合ID',
  `goods_unit` int(11) DEFAULT NULL COMMENT '商品单位',
  `stores_id` int(11) NOT NULL DEFAULT '0' COMMENT '仓库ID',
  `stock` double(15,4) unsigned NOT NULL DEFAULT '0.0000' COMMENT '库存',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态:0=关闭,1=正常',
  `number` float(15,4) unsigned DEFAULT '0.0000' COMMENT '期初数量',
  `batch_name` varchar(255) DEFAULT NULL COMMENT '批次',
  `manufacture` int(11) unsigned DEFAULT NULL COMMENT '生产日期',
  `guarantee` int(11) unsigned DEFAULT NULL COMMENT '保质期',
  `validity` int(11) DEFAULT NULL COMMENT '有效期至',
  `purchase_id` int(11) DEFAULT NULL COMMENT '购货单号',
  `purchase_info_id` int(11) DEFAULT NULL COMMENT '购货单详情ID',
  `type` tinyint(3) DEFAULT '0' COMMENT '类型:1=期初,2=购货',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品销售批次详情表';

-- ----------------------------
-- Table structure for fa_kuerp_goods_batch_salee_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_batch_salee_info`;
CREATE TABLE `fa_kuerp_goods_batch_salee_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `goods_code` varchar(30) DEFAULT NULL COMMENT '商品编号',
  `attribute_id` varchar(30) DEFAULT NULL COMMENT '属性组合ID',
  `goods_unit` int(11) DEFAULT NULL COMMENT '商品单位',
  `stores_id` int(11) NOT NULL DEFAULT '0' COMMENT '仓库ID',
  `stock` double(15,4) unsigned NOT NULL DEFAULT '0.0000' COMMENT '库存',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态:0=关闭,1=正常',
  `number` float(15,4) unsigned DEFAULT '0.0000' COMMENT '期初数量',
  `batch_name` varchar(255) DEFAULT NULL COMMENT '批次',
  `manufacture` int(11) unsigned DEFAULT NULL COMMENT '生产日期',
  `guarantee` int(11) unsigned DEFAULT NULL COMMENT '保质期',
  `validity` int(11) DEFAULT NULL COMMENT '有效期至',
  `purchase_id` int(11) DEFAULT NULL COMMENT '购货单号',
  `purchase_info_id` int(11) DEFAULT NULL COMMENT '购货单详情ID',
  `type` tinyint(3) DEFAULT '0' COMMENT '类型:1=期初,2=购货',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品退货批次详情表';

-- ----------------------------
-- Table structure for fa_kuerp_goods_batch_stock
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_batch_stock`;
CREATE TABLE `fa_kuerp_goods_batch_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `goods_code` varchar(30) DEFAULT NULL COMMENT '商品编号',
  `attribute_id` int(11) DEFAULT '0',
  `stores_id` int(11) NOT NULL DEFAULT '0' COMMENT '仓库ID',
  `stock` double(15,4) unsigned NOT NULL DEFAULT '0.0000' COMMENT '库存',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态:0=关闭,1=正常',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品批次库存表';

-- ----------------------------
-- Table structure for fa_kuerp_goods_exchange
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_exchange`;
CREATE TABLE `fa_kuerp_goods_exchange` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_type` enum('0','1','2') DEFAULT '0' COMMENT '类型:0=退货单,1=购货订单退货,2=购货单退货',
  `purchase_time` bigint(16) DEFAULT NULL,
  `delivery_time` bigint(16) DEFAULT NULL,
  `supplyer_code` varchar(30) DEFAULT NULL COMMENT '供应商编号',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `all_money` double(15,4) DEFAULT '0.0000' COMMENT '购货金额',
  `purchase_order_id` varchar(50) DEFAULT NULL COMMENT '购货订单号',
  `purchase_id` varchar(50) DEFAULT NULL COMMENT '购货单号',
  `sales_order_id` varchar(50) DEFAULT NULL COMMENT '销货订单号',
  `preferential_rate` double(15,4) DEFAULT '0.0000' COMMENT '优惠率',
  `preferential_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠额',
  `real_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠后金额',
  `tax_money` double(15,4) DEFAULT NULL,
  `purchase_money` double(15,4) DEFAULT '0.0000' COMMENT '采购费用',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `pay_status` enum('0','1','2') DEFAULT '0' COMMENT '付款状态:0=未付款,1=全部付款,2=部分付款',
  `check_status` enum('0','1') DEFAULT '0' COMMENT '对单状态:0=未对单,1=已对单',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `pay_money` double(15,4) DEFAULT '0.0000' COMMENT '已付款金额',
  `account` int(11) DEFAULT NULL COMMENT '付款账户',
  `retreat_status` enum('0','1','2') DEFAULT NULL COMMENT '退款状态:0=未退款,1=全部退款,2=部分退款',
  `retreat_number` double(15,4) DEFAULT '0.0000' COMMENT '退货数量',
  `retreat_money` double(15,4) DEFAULT '0.0000' COMMENT '退款金额',
  `note` varchar(255) DEFAULT NULL COMMENT '备注',
  `arrears` double(15,4) DEFAULT '0.0000' COMMENT '本次欠款',
  `all_arrears` double(15,4) DEFAULT '0.0000' COMMENT '总欠款',
  `print_num` int(11) DEFAULT '0' COMMENT '打印次数',
  `cost_data` text COMMENT '采购销售费用',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='购货退货单';

-- ----------------------------
-- Table structure for fa_kuerp_goods_exchange_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_exchange_info`;
CREATE TABLE `fa_kuerp_goods_exchange_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_type` enum('0','1','2') DEFAULT NULL COMMENT '类型:1=购货,2=退货',
  `purchase_time` bigint(16) DEFAULT NULL,
  `delivery_time` bigint(16) DEFAULT NULL,
  `supplyer_code` varchar(30) DEFAULT NULL COMMENT '供应商编号',
  `goods_code` varchar(30) NOT NULL DEFAULT '' COMMENT '商品编号',
  `goods_note` varchar(255) DEFAULT NULL COMMENT '商品备注',
  `goods_attr` int(11) DEFAULT NULL COMMENT '商品属性',
  `goods_unit` int(11) DEFAULT NULL COMMENT '商品单位',
  `stores_id` int(11) DEFAULT NULL COMMENT '仓库',
  `allow_stock` double(15,4) DEFAULT '0.0000' COMMENT '可用库存',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `default_unit` int(11) DEFAULT NULL COMMENT '基本单位',
  `basic_nmber` double(15,4) DEFAULT '0.0000',
  `unit_price` double(15,4) DEFAULT '0.0000' COMMENT '购货单价',
  `tax_included` double(15,4) DEFAULT NULL COMMENT '含税单价',
  `discount` float(5,2) DEFAULT '0.00' COMMENT '折扣率',
  `dis_money` double(15,4) DEFAULT '0.0000' COMMENT '折扣额',
  `all_money` double(15,4) DEFAULT '0.0000' COMMENT '购货金额',
  `tax_rate` double(15,4) DEFAULT NULL COMMENT '税率',
  `tax_money` double(15,4) DEFAULT NULL COMMENT '税额',
  `money_tax_price` double(15,4) DEFAULT NULL COMMENT '价税合计',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `purchase_order_id` varchar(50) DEFAULT NULL COMMENT '购货订单号',
  `purchase_id` varchar(50) DEFAULT NULL COMMENT '购货单号',
  `sales_order_id` varchar(50) DEFAULT NULL COMMENT '销货订单号',
  `returns_order_id` varchar(50) DEFAULT NULL COMMENT '退货订单号',
  `purchase_info_id` int(11) DEFAULT NULL COMMENT '购货单详情ID',
  `preferential_rate` float(5,2) DEFAULT '0.00' COMMENT '优惠率',
  `preferential_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠额',
  `real_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠后金额',
  `purchase_money` double(15,4) DEFAULT '0.0000' COMMENT '采购费用',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `pay_status` enum('0','1','2') DEFAULT '0' COMMENT '付款状态:0=未付款,1=部分付款,2=全部付款',
  `check_status` enum('0','1') DEFAULT '0' COMMENT '对单状态:0=未对单,1=已对单',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `pay_money` double(15,4) DEFAULT '0.0000' COMMENT '已付款金额',
  `retreat_status` tinyint(3) DEFAULT '0' COMMENT '退款状态:0=未退款,1=全部退款,2=部分退款',
  `retreat_number` double(15,4) DEFAULT '0.0000' COMMENT '退货数量',
  `retreat_money` double(15,4) DEFAULT '0.0000' COMMENT '退款金额',
  `account` int(11) DEFAULT NULL COMMENT '付款账户',
  `print_num` int(11) DEFAULT '0' COMMENT '打印次数',
  `batch_id` int(11) DEFAULT NULL,
  `batch_name` varchar(100) DEFAULT NULL,
  `manufacture` int(11) DEFAULT NULL,
  `guarantee` int(11) DEFAULT NULL,
  `validity` int(11) DEFAULT NULL,
  `period` text COMMENT '序列号',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='退货明细';

-- ----------------------------
-- Table structure for fa_kuerp_goods_excpur
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_excpur`;
CREATE TABLE `fa_kuerp_goods_excpur` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exchange_id` int(11) DEFAULT '0',
  `purchase_id` int(11) DEFAULT NULL,
  `type` enum('0','1','2') DEFAULT '0' COMMENT '单据类型',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='退单关联';

-- ----------------------------
-- Table structure for fa_kuerp_goods_excpur_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_excpur_info`;
CREATE TABLE `fa_kuerp_goods_excpur_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exchange_id` int(11) DEFAULT '0',
  `purchase_id` int(11) DEFAULT NULL,
  `type` enum('0','1','2') DEFAULT NULL COMMENT '类型:1=购货订单,2=购货单',
  `number` float(11,2) DEFAULT '0.00',
  `money` float(11,2) DEFAULT '0.00' COMMENT '退款金额',
  `all_money` double(15,4) DEFAULT '0.0000' COMMENT '购货金额',
  `tax_rate` double(15,4) DEFAULT '0.0000' COMMENT '税率',
  `tax_money` double(15,4) DEFAULT '0.0000' COMMENT '税额',
  `money_tax_price` double(15,4) DEFAULT '0.0000' COMMENT '价税合计',
  `status` enum('0','1') DEFAULT '0' COMMENT '是否审核',
  `times` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='退单关联';

-- ----------------------------
-- Table structure for fa_kuerp_goods_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_log`;
CREATE TABLE `fa_kuerp_goods_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_id` int(11) DEFAULT NULL COMMENT '单据ID',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15') NOT NULL DEFAULT '1' COMMENT '业务类型:1=期初余额,2=普通采购,3=采购退回,4=普通销售,5=销售退回,6=调拨单,7=成本调整,8=盘盈,9=其他入库,10=盘亏,11=其他出库,12=组装单,13组装单,14=拆卸单,15=拆卸单',
  `customer_id` int(11) DEFAULT NULL COMMENT '往来单位',
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `goods_code` varchar(30) DEFAULT NULL COMMENT '商品编号',
  `attribute_id` int(11) DEFAULT NULL COMMENT '属性组合ID',
  `goods_unit` int(11) DEFAULT NULL COMMENT '商品单位',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `stores_id` int(11) DEFAULT '0' COMMENT '仓库ID',
  `enter_number` double(15,4) DEFAULT NULL COMMENT '入库数量',
  `enter_basics` double(15,4) DEFAULT NULL COMMENT '基本单位数量',
  `enter_unit` double(15,4) DEFAULT NULL COMMENT '单位成本',
  `enter_cost` double(15,4) DEFAULT NULL COMMENT '成本',
  `out_number` double(15,4) DEFAULT NULL COMMENT '出库数量',
  `out_basics` double(15,4) DEFAULT NULL COMMENT '基本单位数量',
  `out_unit` double(15,4) DEFAULT NULL COMMENT '单位成本',
  `out_cost` double(15,4) DEFAULT NULL COMMENT '成本',
  `balance_basics` double(15,4) DEFAULT NULL COMMENT '基本单位数量',
  `balance_unit` double(15,4) DEFAULT NULL COMMENT '单位成本',
  `balance_cost` double(15,4) DEFAULT NULL COMMENT '成本',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COMMENT='商品收发明细表';

-- ----------------------------
-- Table structure for fa_kuerp_goods_period
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_period`;
CREATE TABLE `fa_kuerp_goods_period` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `goods_code` varchar(30) DEFAULT NULL COMMENT '商品编号',
  `attribute_id` int(11) DEFAULT NULL COMMENT '属性组合ID',
  `stores_id` int(11) NOT NULL DEFAULT '0' COMMENT '仓库ID',
  `stock` double(15,4) unsigned NOT NULL DEFAULT '0.0000' COMMENT '库存',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态:0=关仓,1=正常',
  `period` double(15,4) unsigned DEFAULT '0.0000' COMMENT '期初数量',
  `unit_cost` double(15,4) unsigned DEFAULT '0.0000' COMMENT '单位成本',
  `price` double(15,4) unsigned DEFAULT '0.0000' COMMENT '期初总价',
  `stock_warning` enum('0','1') DEFAULT '0' COMMENT '库存预警:0=否,1=是',
  `min_stock` double(15,4) DEFAULT '0.0000' COMMENT '最低库存',
  `max_stock` double(15,4) DEFAULT '0.0000' COMMENT '最高库存',
  `address` varchar(255) DEFAULT NULL COMMENT '产地',
  `registration` varchar(100) DEFAULT NULL COMMENT '注册证号',
  `production` varchar(100) DEFAULT NULL COMMENT '生产许可证',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  `deletetime` bigint(16) DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品期初表';

-- ----------------------------
-- Table structure for fa_kuerp_goods_price
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_price`;
CREATE TABLE `fa_kuerp_goods_price` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `goods_code` varchar(30) NOT NULL DEFAULT '' COMMENT '商品编号',
  `goods_name` varchar(255) NOT NULL DEFAULT '' COMMENT '商品名称',
  `goods_barcode` varchar(50) DEFAULT '' COMMENT '商品条码',
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `unit` int(11) NOT NULL DEFAULT '0' COMMENT '计量单位',
  `market_sell_price` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '零售价',
  `wholesale_price` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '批发价',
  `vip_price` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT 'vip会员价',
  `discount` double(15,4) unsigned DEFAULT '0.0000' COMMENT '折扣率1',
  `discount2` double(15,4) unsigned DEFAULT '0.0000' COMMENT '折扣率2',
  `purchase` double(15,4) unsigned DEFAULT '0.0000' COMMENT '预计采购价',
  `is_default` tinyint(3) NOT NULL DEFAULT '0' COMMENT '是否主单位:0=否,1=是',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  `deletetime` bigint(16) DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品价格表';

-- ----------------------------
-- Table structure for fa_kuerp_goods_purchase
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_purchase`;
CREATE TABLE `fa_kuerp_goods_purchase` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_type` enum('1','2') DEFAULT '1' COMMENT '类型:1=购货,2=退货',
  `purchase_time` bigint(16) DEFAULT NULL,
  `delivery_time` bigint(16) DEFAULT NULL,
  `supplyer_code` varchar(30) DEFAULT NULL COMMENT '供应商编号',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `all_money` float(11,2) DEFAULT '0.00' COMMENT '购货金额',
  `purchase_order_id` varchar(50) DEFAULT NULL COMMENT '购货订单号',
  `sales_order_id` varchar(50) DEFAULT NULL COMMENT '销货订单号',
  `returns_order_id` varchar(50) DEFAULT NULL COMMENT '退货订单号',
  `preferential_rate` float(5,2) DEFAULT '0.00' COMMENT '优惠率',
  `preferential_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠额',
  `real_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠后金额',
  `tax_money` double(15,4) DEFAULT NULL COMMENT '税额',
  `purchase_money` double(15,4) DEFAULT '0.0000' COMMENT '采购费用',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `pay_status` enum('0','1','2') DEFAULT '0' COMMENT '付款状态:0=未付款,1=全部付款,2=部分付款',
  `check_status` enum('0','1') DEFAULT '0' COMMENT '对单状态:0=未对单,1=已对单',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `pay_money` double(15,4) DEFAULT '0.0000' COMMENT '已付款金额',
  `account` int(11) DEFAULT NULL COMMENT '付款账户',
  `note` varchar(255) DEFAULT NULL COMMENT '备注',
  `arrears` double(15,4) DEFAULT '0.0000' COMMENT '本次欠款',
  `all_arrears` double(15,4) DEFAULT '0.0000' COMMENT '总欠款',
  `print_num` int(11) DEFAULT '0' COMMENT '打印次数',
  `cost_data` text COMMENT '采购费用',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_code` (`purchase_code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='购货单';

-- ----------------------------
-- Table structure for fa_kuerp_goods_purchase_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_purchase_info`;
CREATE TABLE `fa_kuerp_goods_purchase_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_type` enum('1','2') DEFAULT '1' COMMENT '类型:1=购货,2=退货',
  `purchase_time` bigint(16) DEFAULT NULL,
  `delivery_time` bigint(16) DEFAULT NULL,
  `supplyer_code` varchar(30) DEFAULT NULL COMMENT '供应商编号',
  `goods_code` varchar(30) NOT NULL DEFAULT '' COMMENT '商品编号',
  `goods_note` varchar(255) DEFAULT NULL COMMENT '商品备注',
  `goods_attr` int(11) DEFAULT NULL COMMENT '商品属性',
  `goods_unit` int(11) DEFAULT NULL COMMENT '商品单位',
  `stores_id` int(11) DEFAULT NULL COMMENT '仓库',
  `allow_stock` double(15,4) DEFAULT '0.0000' COMMENT '可用库存',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `default_unit` int(11) DEFAULT NULL COMMENT '基本单位',
  `basic_nmber` double(15,4) DEFAULT '0.0000',
  `unit_price` double(15,4) DEFAULT '0.0000' COMMENT '购货单价',
  `tax_included` double(15,4) DEFAULT NULL COMMENT '含税单价',
  `discount` float(5,2) DEFAULT '0.00' COMMENT '折扣率',
  `dis_money` double(15,4) DEFAULT '0.0000' COMMENT '折扣额',
  `all_money` double(15,4) DEFAULT '0.0000' COMMENT '购货金额',
  `tax_rate` double(15,4) DEFAULT NULL COMMENT '税率',
  `tax_money` double(15,4) DEFAULT NULL COMMENT '税额',
  `money_tax_price` double(15,4) DEFAULT NULL COMMENT '价税合计',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `purchase_order_id` varchar(50) DEFAULT NULL COMMENT '购货订单号',
  `sales_order_id` varchar(50) DEFAULT NULL COMMENT '销货订单号',
  `returns_order_id` varchar(50) DEFAULT NULL COMMENT '退货订单号',
  `preferential_rate` float(5,2) DEFAULT '0.00' COMMENT '优惠率',
  `preferential_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠额',
  `real_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠后金额',
  `purchase_money` double(15,4) DEFAULT '0.0000' COMMENT '采购费用',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `pay_status` enum('0','1','2') DEFAULT '0' COMMENT '付款状态:0=未付款,1=部分付款,2=全部付款',
  `check_status` enum('0','1') DEFAULT '0' COMMENT '对单状态:0=未对单,1=已对单',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `pay_money` double(15,4) DEFAULT '0.0000' COMMENT '已付款金额',
  `account` int(11) DEFAULT NULL COMMENT '付款账户',
  `print_num` int(11) DEFAULT '0' COMMENT '打印次数',
  `order_info_id` varchar(255) DEFAULT NULL COMMENT '购货订单详情ID',
  `batch_id` int(11) DEFAULT NULL,
  `batch_name` varchar(100) DEFAULT NULL,
  `manufacture` int(11) DEFAULT NULL,
  `guarantee` int(11) DEFAULT NULL,
  `validity` int(11) DEFAULT NULL,
  `period` text COMMENT '序列号',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_code` (`purchase_code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='商品采购明细';

-- ----------------------------
-- Table structure for fa_kuerp_goods_purchase_order
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_purchase_order`;
CREATE TABLE `fa_kuerp_goods_purchase_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_type` enum('1','2') DEFAULT '1' COMMENT '类型:1=购货,2=退货',
  `purchase_time` bigint(16) DEFAULT NULL,
  `delivery_time` bigint(16) DEFAULT NULL,
  `supplyer_code` varchar(30) DEFAULT NULL COMMENT '供应商编号',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `all_money` double(15,4) DEFAULT '0.0000' COMMENT '购货金额',
  `purchase_id` varchar(50) DEFAULT NULL COMMENT '购货单号',
  `sales_order_id` varchar(50) DEFAULT NULL COMMENT '销货订单号',
  `returns_order_id` varchar(50) DEFAULT NULL COMMENT '退货订单号',
  `preferential_rate` double(15,4) DEFAULT '0.0000' COMMENT '优惠率',
  `preferential_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠额',
  `real_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠后金额',
  `tax_money` double(15,4) unsigned DEFAULT NULL COMMENT '税额',
  `purchase_money` double(15,4) DEFAULT '0.0000' COMMENT '采购费用',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `status` enum('0','1','2','3') DEFAULT '0' COMMENT '状态:0=未入库,1=全部入库,2=部分入库,3=已取消',
  `check_status` enum('0','1') DEFAULT '0' COMMENT '对单状态:0=未对单,1=已对单',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `pay_money` double(15,4) DEFAULT '0.0000' COMMENT '已付款金额',
  `store_number` double(15,4) DEFAULT '0.0000' COMMENT '入库数量',
  `account` int(11) DEFAULT NULL COMMENT '付款账户',
  `note` varchar(255) DEFAULT NULL COMMENT '备注',
  `arrears` double(15,4) DEFAULT '0.0000' COMMENT '本次欠款',
  `all_arrears` double(15,4) DEFAULT '0.0000' COMMENT '总欠款',
  `print_num` int(11) DEFAULT '0' COMMENT '打印次数',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_code` (`purchase_code`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='购货订单';

-- ----------------------------
-- Table structure for fa_kuerp_goods_purchase_order_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_purchase_order_info`;
CREATE TABLE `fa_kuerp_goods_purchase_order_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_type` enum('1','2') DEFAULT '1' COMMENT '类型:1=购货,2=退货',
  `purchase_time` bigint(16) DEFAULT NULL,
  `delivery_time` bigint(16) DEFAULT NULL,
  `supplyer_code` varchar(30) DEFAULT NULL COMMENT '供应商编号',
  `goods_code` varchar(30) NOT NULL DEFAULT '' COMMENT '商品编号',
  `goods_note` varchar(255) DEFAULT NULL COMMENT '商品备注',
  `goods_attr` int(11) DEFAULT NULL COMMENT '商品属性',
  `goods_unit` int(11) DEFAULT NULL COMMENT '商品单位',
  `stores_id` int(11) DEFAULT NULL COMMENT '仓库',
  `allow_stock` double(15,4) DEFAULT '0.0000' COMMENT '可用库存',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `purchase_number` double(15,4) DEFAULT '0.0000' COMMENT '可生成购货单数量',
  `default_unit` int(11) DEFAULT NULL COMMENT '基本单位',
  `basic_nmber` double(15,4) DEFAULT '0.0000',
  `unit_price` double(15,4) DEFAULT '0.0000' COMMENT '购货单价',
  `tax_included` double(15,4) DEFAULT NULL COMMENT '含税单价',
  `discount` double(15,4) DEFAULT '0.0000' COMMENT '折扣率',
  `dis_money` double(15,4) DEFAULT '0.0000' COMMENT '折扣额',
  `all_money` double(15,4) DEFAULT '0.0000' COMMENT '购货金额',
  `tax_rate` double(15,4) unsigned DEFAULT '0.0000' COMMENT '税率',
  `tax_money` double(15,4) unsigned DEFAULT '0.0000' COMMENT '税额',
  `money_tax_price` double(15,4) DEFAULT '0.0000' COMMENT '价税合计',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `purchase_id` varchar(50) DEFAULT NULL COMMENT '购货单号',
  `sales_order_id` varchar(50) DEFAULT NULL COMMENT '销货订单号',
  `returns_order_id` varchar(50) DEFAULT NULL COMMENT '退货订单号',
  `preferential_rate` double(15,4) DEFAULT '0.0000' COMMENT '优惠率',
  `preferential_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠额',
  `real_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠后金额',
  `purchase_money` double(15,4) DEFAULT '0.0000' COMMENT '采购费用',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `status` enum('0','1','2','3') DEFAULT '0' COMMENT '状态:0=未入库,1=全部入库,2=部分入库,3=已取消',
  `check_status` enum('0','1') DEFAULT '0' COMMENT '对单状态:0=未对单,1=已对单',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `pay_money` double(15,4) DEFAULT '0.0000' COMMENT '已付款金额',
  `account` int(11) DEFAULT NULL COMMENT '付款账户',
  `print_num` int(11) DEFAULT '0' COMMENT '打印次数',
  `library_number` double(15,4) DEFAULT '0.0000' COMMENT '未入库数量',
  `library_money` double(15,4) DEFAULT '0.0000' COMMENT '未入库金额',
  `library_time` bigint(16) DEFAULT NULL,
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_code` (`purchase_code`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='购货订单详情';

-- ----------------------------
-- Table structure for fa_kuerp_goods_purchase_summary
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_purchase_summary`;
CREATE TABLE `fa_kuerp_goods_purchase_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_type` enum('1','2') DEFAULT '1' COMMENT '类型:1=购货,2=退货',
  `purchase_time` bigint(16) DEFAULT NULL,
  `delivery_time` bigint(16) DEFAULT NULL,
  `supplyer_code` varchar(30) DEFAULT NULL COMMENT '供应商编号',
  `goods_code` varchar(30) NOT NULL DEFAULT '' COMMENT '商品编号',
  `goods_note` varchar(255) DEFAULT NULL COMMENT '商品备注',
  `goods_attr` int(11) DEFAULT NULL COMMENT '商品属性',
  `goods_unit` int(11) DEFAULT NULL COMMENT '商品单位',
  `stores_id` int(11) DEFAULT NULL COMMENT '仓库',
  `allow_stock` double(15,4) DEFAULT '0.0000' COMMENT '可用库存',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `default_unit` int(11) DEFAULT NULL COMMENT '基本单位',
  `basic_nmber` double(15,4) DEFAULT '0.0000',
  `unit_price` float(11,2) DEFAULT '0.00' COMMENT '购货单价',
  `tax_included` double(15,4) DEFAULT NULL COMMENT '含税单价',
  `discount` float(5,2) DEFAULT '0.00' COMMENT '折扣率',
  `dis_money` double(15,4) DEFAULT '0.0000' COMMENT '折扣额',
  `all_money` double(15,4) DEFAULT '0.0000' COMMENT '购货金额',
  `tax_rate` double(15,4) DEFAULT NULL COMMENT '税率',
  `tax_money` double(15,4) DEFAULT NULL COMMENT '税额',
  `money_tax_price` double(15,4) DEFAULT NULL COMMENT '价税合计',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `purchase_order_id` varchar(50) DEFAULT NULL COMMENT '购货订单号',
  `sales_order_id` varchar(50) DEFAULT NULL COMMENT '购货单号',
  `returns_order_id` varchar(50) DEFAULT NULL COMMENT '退货订单号',
  `preferential_rate` float(5,2) DEFAULT '0.00' COMMENT '优惠率',
  `preferential_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠额',
  `real_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠后金额',
  `purchase_money` double(15,4) DEFAULT '0.0000' COMMENT '采购费用',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `pay_status` enum('0','1','2') DEFAULT '0' COMMENT '付款状态:0=未付款,1=部分付款,2=全部付款',
  `check_status` enum('0','1') DEFAULT '0' COMMENT '对单状态:0=未对单,1=已对单',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `pay_money` double(15,4) DEFAULT '0.0000' COMMENT '已付款金额',
  `account` int(11) DEFAULT NULL COMMENT '付款账户',
  `print_num` int(11) DEFAULT '0' COMMENT '打印次数',
  `order_info_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `batch_name` varchar(100) DEFAULT NULL,
  `manufacture` int(11) DEFAULT NULL,
  `guarantee` int(11) DEFAULT NULL,
  `validity` int(11) DEFAULT NULL,
  `period` text COMMENT '序列号',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='采购汇总表';

-- ----------------------------
-- Table structure for fa_kuerp_goods_purord
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_purord`;
CREATE TABLE `fa_kuerp_goods_purord` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT '0',
  `purchase_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='订单关联';

-- ----------------------------
-- Table structure for fa_kuerp_goods_purord_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_purord_info`;
CREATE TABLE `fa_kuerp_goods_purord_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT '0',
  `purchase_id` int(11) DEFAULT NULL COMMENT '购货明细ID',
  `type` enum('0','1','2') DEFAULT NULL COMMENT '类型:1=购货订单,2=购货单',
  `number` float(11,2) DEFAULT '0.00' COMMENT '数量',
  `money` float(11,2) DEFAULT '0.00' COMMENT '已付款金额',
  `all_money` float(11,2) DEFAULT '0.00' COMMENT '购货金额',
  `tax_rate` double(15,4) DEFAULT '0.0000' COMMENT '税率',
  `tax_money` double(15,4) DEFAULT '0.0000' COMMENT '税额',
  `money_tax_price` double(15,4) DEFAULT '0.0000' COMMENT '价税合计',
  `status` enum('0','1') DEFAULT '0',
  `times` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='订单关联';

-- ----------------------------
-- Table structure for fa_kuerp_goods_saleexc
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_saleexc`;
CREATE TABLE `fa_kuerp_goods_saleexc` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exchange_id` int(11) DEFAULT '0',
  `purchase_id` int(11) DEFAULT NULL,
  `type` enum('0','1','2') DEFAULT '0' COMMENT '单据类型',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='退单关联';

-- ----------------------------
-- Table structure for fa_kuerp_goods_saleexc_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_saleexc_info`;
CREATE TABLE `fa_kuerp_goods_saleexc_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exchange_id` int(11) DEFAULT '0',
  `purchase_id` int(11) DEFAULT NULL,
  `type` enum('0','1','2') DEFAULT NULL COMMENT '类型:1=销货订单,2=销货单',
  `number` float(11,2) DEFAULT '0.00',
  `money` float(11,2) DEFAULT '0.00' COMMENT '退款金额',
  `all_money` double(15,4) DEFAULT '0.0000' COMMENT '购货金额',
  `tax_rate` double(15,4) DEFAULT '0.0000' COMMENT '税率',
  `tax_money` double(15,4) DEFAULT '0.0000' COMMENT '税额',
  `money_tax_price` double(15,4) DEFAULT '0.0000' COMMENT '价税合计',
  `status` enum('0','1') DEFAULT '0' COMMENT '是否审核',
  `times` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='退单关联';

-- ----------------------------
-- Table structure for fa_kuerp_goods_saleord
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_saleord`;
CREATE TABLE `fa_kuerp_goods_saleord` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT '0',
  `purchase_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='销售订单关联';

-- ----------------------------
-- Table structure for fa_kuerp_goods_saleord_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_saleord_info`;
CREATE TABLE `fa_kuerp_goods_saleord_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT '0',
  `purchase_id` int(11) DEFAULT NULL COMMENT '销货明细ID',
  `number` float(11,2) DEFAULT '0.00' COMMENT '数量',
  `money` float(11,2) DEFAULT '0.00' COMMENT '已收款金额',
  `all_money` float(11,2) DEFAULT '0.00' COMMENT '销货金额',
  `tax_rate` double(15,4) DEFAULT '0.0000' COMMENT '税率',
  `tax_money` double(15,4) DEFAULT '0.0000' COMMENT '税额',
  `money_tax_price` double(15,4) DEFAULT '0.0000' COMMENT '价税合计',
  `status` enum('0','1') DEFAULT '0',
  `times` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='销售订单关联';

-- ----------------------------
-- Table structure for fa_kuerp_goods_sales
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_sales`;
CREATE TABLE `fa_kuerp_goods_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_type` enum('1','2') DEFAULT '1' COMMENT '类型:1=销货,2=退货',
  `purchase_time` bigint(16) DEFAULT NULL,
  `delivery_time` bigint(16) DEFAULT NULL,
  `customers_id` int(11) DEFAULT NULL COMMENT '客户',
  `receiving_address` varchar(255) DEFAULT NULL COMMENT '收货地址',
  `sale_user` int(11) DEFAULT NULL COMMENT '销售员',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `all_money` float(15,4) DEFAULT '0.0000' COMMENT '销货金额',
  `purchase_order_id` varchar(50) DEFAULT NULL COMMENT '购货订单号',
  `sales_order_id` varchar(50) DEFAULT NULL COMMENT '销货订单号',
  `returns_order_id` varchar(50) DEFAULT NULL COMMENT '退货订单号',
  `preferential_rate` float(5,2) DEFAULT '0.00' COMMENT '优惠率',
  `preferential_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠额',
  `real_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠后金额',
  `tax_money` double(15,4) DEFAULT NULL COMMENT '税额',
  `money_tax_price` double(15,4) DEFAULT '0.0000' COMMENT '价税合计',
  `purchase_money` double(15,4) DEFAULT '0.0000' COMMENT '采购费用',
  `customers_money` double(15,4) DEFAULT '0.0000' COMMENT '客户承担费用',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `pay_status` enum('0','1','2') DEFAULT '0' COMMENT '收款状态:0=未收款,1=全部收款,2=部分收款',
  `check_status` enum('0','1') DEFAULT '0' COMMENT '对单状态:0=未对单,1=已对单',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `pay_money` double(15,4) DEFAULT '0.0000' COMMENT '已付款金额',
  `account` int(11) DEFAULT NULL COMMENT '付款账户',
  `note` varchar(255) DEFAULT NULL COMMENT '备注',
  `arrears` double(15,4) DEFAULT '0.0000' COMMENT '本次欠款',
  `all_arrears` double(15,4) DEFAULT '0.0000' COMMENT '总欠款',
  `print_num` int(11) DEFAULT '0' COMMENT '打印次数',
  `cost_data` text COMMENT '采购销售费用',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_code` (`purchase_code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='销货单';

-- ----------------------------
-- Table structure for fa_kuerp_goods_sales_exchange
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_sales_exchange`;
CREATE TABLE `fa_kuerp_goods_sales_exchange` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_type` enum('0','1','2') DEFAULT '0' COMMENT '类型:0=退货,1=销货订单退货,2=销货单退货',
  `purchase_time` bigint(16) DEFAULT NULL,
  `delivery_time` bigint(16) DEFAULT NULL,
  `customers_id` int(11) DEFAULT NULL COMMENT '客户',
  `receiving_address` varchar(255) DEFAULT NULL COMMENT '收货地址',
  `sale_user` int(11) DEFAULT NULL COMMENT '销售员',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `all_money` double(15,4) DEFAULT '0.0000' COMMENT '购货金额',
  `purchase_order_id` varchar(50) DEFAULT NULL COMMENT '购货订单号',
  `purchase_id` varchar(50) DEFAULT NULL COMMENT '购货单号',
  `sales_order_id` varchar(50) DEFAULT NULL COMMENT '销货订单号',
  `preferential_rate` float(5,2) DEFAULT '0.00' COMMENT '优惠率',
  `preferential_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠额',
  `real_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠后金额',
  `tax_money` double(15,4) DEFAULT NULL COMMENT '税额',
  `money_tax_price` double(15,4) DEFAULT '0.0000' COMMENT '价税合计',
  `purchase_money` double(15,4) DEFAULT '0.0000' COMMENT '采购费用',
  `customers_money` double(15,4) DEFAULT '0.0000' COMMENT '客户承担费用',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `pay_status` enum('0','1','2') DEFAULT '0' COMMENT '收款状态:0=未收款,1=全部收款,2=部分收款',
  `check_status` enum('0','1') DEFAULT '0' COMMENT '对单状态:0=未对单,1=已对单',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `pay_money` double(15,4) DEFAULT '0.0000' COMMENT '已付款金额',
  `account` int(11) DEFAULT NULL COMMENT '付款账户',
  `retreat_status` enum('0','1','2') DEFAULT NULL COMMENT '退款状态:0=未退款,1=全部退款,2=部分退款',
  `retreat_number` double(15,4) DEFAULT '0.0000' COMMENT '退货数量',
  `retreat_money` double(15,4) DEFAULT '0.0000' COMMENT '退款金额',
  `note` varchar(255) DEFAULT NULL COMMENT '备注',
  `arrears` double(15,4) DEFAULT '0.0000' COMMENT '本次欠款',
  `all_arrears` double(15,4) DEFAULT '0.0000' COMMENT '总欠款',
  `print_num` int(11) DEFAULT '0' COMMENT '打印次数',
  `cost_data` text COMMENT '采购销售费用',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='销货退货单';

-- ----------------------------
-- Table structure for fa_kuerp_goods_sales_exchange_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_sales_exchange_info`;
CREATE TABLE `fa_kuerp_goods_sales_exchange_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_type` enum('0','1','2') DEFAULT NULL COMMENT '类型:1=销货,2=退货',
  `purchase_time` bigint(16) DEFAULT NULL,
  `delivery_time` bigint(16) DEFAULT NULL,
  `customers_id` int(11) DEFAULT NULL COMMENT '客户',
  `receiving_address` varchar(255) DEFAULT NULL COMMENT '收货地址',
  `sale_user` int(11) DEFAULT NULL COMMENT '销售员',
  `goods_code` varchar(30) NOT NULL DEFAULT '' COMMENT '商品编号',
  `goods_note` varchar(255) DEFAULT NULL COMMENT '商品备注',
  `goods_attr` int(11) DEFAULT NULL COMMENT '属性',
  `goods_unit` int(11) DEFAULT NULL COMMENT '商品单位',
  `stores_id` int(11) DEFAULT NULL COMMENT '仓库',
  `allow_stock` double(15,4) DEFAULT '0.0000' COMMENT '可用库存',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `default_unit` int(11) DEFAULT NULL COMMENT '基本单位',
  `basic_nmber` double(15,4) DEFAULT '0.0000',
  `unit_price` double(15,4) DEFAULT '0.0000' COMMENT '购货单价',
  `cost_price` double(15,4) DEFAULT NULL COMMENT '成本价',
  `tax_included` double(15,4) DEFAULT '0.0000' COMMENT '含税单价',
  `discount` float(5,2) DEFAULT '0.00' COMMENT '折扣率',
  `dis_money` double(15,4) DEFAULT '0.0000' COMMENT '折扣额',
  `all_money` double(15,4) DEFAULT '0.0000' COMMENT '购货金额',
  `tax_rate` double(15,4) DEFAULT '0.0000' COMMENT '税率',
  `tax_money` double(15,4) DEFAULT '0.0000' COMMENT '税额',
  `money_tax_price` double(15,4) DEFAULT '0.0000' COMMENT '价税合计',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `purchase_order_id` varchar(50) DEFAULT NULL COMMENT '购货订单号',
  `purchase_id` varchar(50) DEFAULT NULL COMMENT '购货单号',
  `sales_order_id` varchar(50) DEFAULT NULL COMMENT '销货订单号',
  `returns_order_id` varchar(50) DEFAULT NULL COMMENT '退货订单号',
  `purchase_info_id` int(11) DEFAULT NULL COMMENT '购货单详情ID',
  `preferential_rate` float(5,2) DEFAULT '0.00' COMMENT '优惠率',
  `preferential_money` float(11,2) DEFAULT '0.00' COMMENT '优惠额',
  `real_money` float(11,2) DEFAULT '0.00' COMMENT '优惠后金额',
  `purchase_money` float(11,2) DEFAULT '0.00' COMMENT '采购费用',
  `customers_money` float(11,2) DEFAULT '0.00' COMMENT '客户承担费用',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `pay_status` enum('0','1','2') DEFAULT '0' COMMENT '收款状态:0=未收款,1=全部收款,2=部分收款',
  `check_status` enum('0','1') DEFAULT '0' COMMENT '对单状态:0=未对单,1=已对单',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `pay_money` float(11,2) DEFAULT '0.00' COMMENT '已付款金额',
  `retreat_status` float(11,2) DEFAULT '0.00' COMMENT '退款状态:0=未退款,1=全部退款,2=部分退款',
  `retreat_number` float(11,2) DEFAULT '0.00' COMMENT '退货数量',
  `retreat_money` float(11,2) DEFAULT '0.00' COMMENT '退款金额',
  `account` int(11) DEFAULT NULL COMMENT '付款账户',
  `print_num` int(11) DEFAULT '0' COMMENT '打印次数',
  `batch_id` int(11) DEFAULT NULL,
  `batch_name` varchar(100) DEFAULT NULL COMMENT '批次',
  `manufacture` int(11) DEFAULT NULL COMMENT '生产日期',
  `guarantee` int(11) DEFAULT NULL COMMENT '保质期',
  `validity` int(11) DEFAULT NULL COMMENT '过期时间',
  `period` text COMMENT '序列号',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='退货明细';

-- ----------------------------
-- Table structure for fa_kuerp_goods_sales_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_sales_info`;
CREATE TABLE `fa_kuerp_goods_sales_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_type` enum('1','2') DEFAULT '1' COMMENT '类型:1=销货,2=退货',
  `purchase_time` bigint(16) DEFAULT NULL,
  `delivery_time` bigint(16) DEFAULT NULL,
  `customers_id` int(11) DEFAULT NULL COMMENT '客户',
  `receiving_address` varchar(255) DEFAULT NULL COMMENT '收货地址',
  `sale_user` int(11) DEFAULT NULL COMMENT '销售员',
  `goods_code` varchar(30) NOT NULL DEFAULT '' COMMENT '商品编号',
  `goods_note` varchar(255) DEFAULT NULL COMMENT '商品备注',
  `goods_attr` int(11) DEFAULT NULL COMMENT '商品属性',
  `goods_unit` int(11) DEFAULT NULL COMMENT '商品单位',
  `stores_id` int(11) DEFAULT NULL COMMENT '仓库',
  `allow_stock` double(15,4) DEFAULT '0.0000' COMMENT '可用库存',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `default_unit` int(11) DEFAULT NULL COMMENT '基本单位',
  `basic_nmber` double(15,4) DEFAULT '0.0000',
  `unit_price` double(15,4) DEFAULT '0.0000' COMMENT '销货单价',
  `cost_price` double(15,4) DEFAULT NULL COMMENT '成本价',
  `tax_included` double(15,4) DEFAULT '0.0000' COMMENT '含税单价',
  `discount` float(5,2) DEFAULT '0.00' COMMENT '折扣率',
  `dis_money` double(15,4) DEFAULT '0.0000' COMMENT '折扣额',
  `all_money` double(15,4) DEFAULT '0.0000' COMMENT '购货金额',
  `tax_rate` double(15,4) DEFAULT '0.0000' COMMENT '税率',
  `tax_money` double(15,4) DEFAULT '0.0000' COMMENT '税额',
  `money_tax_price` double(15,4) DEFAULT '0.0000' COMMENT '价税合计',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `purchase_order_id` varchar(50) DEFAULT NULL COMMENT '销货订单号',
  `sales_order_id` varchar(50) DEFAULT NULL COMMENT '销货订单号',
  `returns_order_id` varchar(50) DEFAULT NULL COMMENT '退货订单号',
  `preferential_rate` float(5,2) DEFAULT '0.00' COMMENT '优惠率',
  `preferential_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠额',
  `real_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠后金额',
  `purchase_money` double(15,4) DEFAULT '0.0000' COMMENT '采购费用',
  `customers_money` double(15,4) DEFAULT '0.0000' COMMENT '客户承担费用',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `pay_status` enum('0','1','2') DEFAULT '0' COMMENT '收款状态:0=未收款,1=部分收款,2=全部收款',
  `check_status` enum('0','1') DEFAULT '0' COMMENT '对单状态:0=未对单,1=已对单',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `pay_money` double(15,4) DEFAULT '0.0000' COMMENT '已付款金额',
  `account` int(11) DEFAULT NULL COMMENT '付款账户',
  `print_num` int(11) DEFAULT '0' COMMENT '打印次数',
  `order_info_id` varchar(255) DEFAULT NULL COMMENT '购货订单详情ID',
  `batch_id` int(11) DEFAULT NULL,
  `batch_name` varchar(100) DEFAULT NULL,
  `manufacture` int(11) DEFAULT NULL,
  `guarantee` int(11) DEFAULT NULL,
  `validity` int(11) DEFAULT NULL,
  `period` text COMMENT '序列号',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='商品销售明细';

-- ----------------------------
-- Table structure for fa_kuerp_goods_sales_order
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_sales_order`;
CREATE TABLE `fa_kuerp_goods_sales_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_type` enum('1','2') DEFAULT '1' COMMENT '类型:1=销货,2=退货',
  `purchase_time` bigint(16) DEFAULT NULL,
  `delivery_time` bigint(16) DEFAULT NULL,
  `customers_id` int(11) DEFAULT NULL COMMENT '客户',
  `receiving_address` varchar(255) DEFAULT NULL COMMENT '收货地址',
  `sale_user` int(11) DEFAULT NULL COMMENT '销售员',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `all_money` double(15,4) DEFAULT '0.0000' COMMENT '购货金额',
  `purchase_id` varchar(50) DEFAULT NULL COMMENT '销货单号',
  `sales_order_id` varchar(50) DEFAULT NULL COMMENT '销货订单号',
  `returns_order_id` varchar(50) DEFAULT NULL COMMENT '退货订单号',
  `discount` float(5,2) DEFAULT '10.00' COMMENT '折扣',
  `preferential_rate` float(5,2) DEFAULT '0.00' COMMENT '优惠率',
  `preferential_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠额',
  `real_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠后金额',
  `tax_money` double(15,4) DEFAULT NULL COMMENT '税额',
  `money_tax_price` double(15,4) DEFAULT '0.0000' COMMENT '价税合计',
  `purchase_money` double(15,4) DEFAULT '0.0000' COMMENT '采购费用',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `status` enum('0','1','2','3') DEFAULT '0' COMMENT '状态:0=未出库,1=全部出库,2=部分出库,3=已取消',
  `check_status` enum('0','1') DEFAULT '0' COMMENT '对单状态:0=未对单,1=已对单',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `pay_money` double(15,4) DEFAULT '0.0000' COMMENT '已付款金额',
  `store_number` double(15,4) DEFAULT '0.0000' COMMENT '入库数量',
  `account` int(11) DEFAULT NULL COMMENT '付款账户',
  `note` varchar(255) DEFAULT NULL COMMENT '备注',
  `arrears` double(15,4) DEFAULT '0.0000' COMMENT '本次欠款',
  `all_arrears` double(15,4) DEFAULT '0.0000' COMMENT '总欠款',
  `print_num` int(11) DEFAULT '0' COMMENT '打印次数',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_code` (`purchase_code`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='销货订单';

-- ----------------------------
-- Table structure for fa_kuerp_goods_sales_order_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_sales_order_info`;
CREATE TABLE `fa_kuerp_goods_sales_order_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_type` enum('1','2') DEFAULT '1' COMMENT '类型:1=销货,2=退货',
  `purchase_time` bigint(16) DEFAULT NULL,
  `delivery_time` bigint(16) DEFAULT NULL,
  `customers_id` int(11) DEFAULT NULL COMMENT '客户',
  `receiving_address` varchar(255) DEFAULT NULL COMMENT '收货地址',
  `sale_user` int(11) DEFAULT NULL COMMENT '销售员',
  `goods_code` varchar(30) NOT NULL DEFAULT '' COMMENT '商品编号',
  `goods_note` varchar(255) DEFAULT NULL COMMENT '商品备注',
  `goods_attr` int(11) DEFAULT NULL COMMENT '商品属性',
  `goods_unit` int(11) DEFAULT NULL COMMENT '商品单位',
  `stores_id` int(11) DEFAULT NULL COMMENT '仓库',
  `allow_stock` double(15,4) DEFAULT '0.0000' COMMENT '可用库存',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `purchase_number` double(15,4) DEFAULT '0.0000' COMMENT '可生成购货单数量',
  `default_unit` int(11) DEFAULT NULL COMMENT '基本单位',
  `basic_nmber` double(15,4) DEFAULT '0.0000',
  `unit_price` double(15,4) DEFAULT '0.0000' COMMENT '购货单价',
  `cost_price` double(15,4) DEFAULT NULL COMMENT '成本价',
  `tax_included` double(15,4) DEFAULT '0.0000' COMMENT '含税单价',
  `discount` double(15,4) DEFAULT '0.0000' COMMENT '折扣率',
  `dis_money` double(15,4) DEFAULT '0.0000' COMMENT '折扣额',
  `all_money` double(15,4) DEFAULT '0.0000' COMMENT '购货金额',
  `tax_rate` double(15,4) DEFAULT '0.0000' COMMENT '税率',
  `tax_money` double(15,4) DEFAULT '0.0000' COMMENT '税额',
  `money_tax_price` double(15,4) DEFAULT '0.0000' COMMENT '价税合计',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `purchase_id` varchar(50) DEFAULT NULL COMMENT '购货单号',
  `sales_order_id` varchar(50) DEFAULT NULL COMMENT '销货订单号',
  `returns_order_id` varchar(50) DEFAULT NULL COMMENT '退货订单号',
  `preferential_rate` float(5,2) DEFAULT '0.00' COMMENT '优惠率',
  `preferential_money` float(11,2) DEFAULT '0.00' COMMENT '优惠额',
  `real_money` float(11,2) DEFAULT '0.00' COMMENT '优惠后金额',
  `purchase_money` float(11,2) DEFAULT '0.00' COMMENT '采购费用',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `status` enum('0','1','2','3') DEFAULT '0' COMMENT '状态:0=未出库,1=全部出库,2=部分出库,3=已取消',
  `check_status` enum('0','1') DEFAULT '0' COMMENT '对单状态:0=未对单,1=已对单',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `pay_money` float(11,2) DEFAULT '0.00' COMMENT '已付款金额',
  `account` int(11) DEFAULT NULL COMMENT '付款账户',
  `print_num` int(11) DEFAULT '0' COMMENT '打印次数',
  `library_number` int(11) DEFAULT '0' COMMENT '未入库数量',
  `library_money` float(11,2) DEFAULT '0.00' COMMENT '未入库金额',
  `library_time` bigint(16) DEFAULT NULL,
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_code` (`purchase_code`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='销货订单详情';

-- ----------------------------
-- Table structure for fa_kuerp_goods_sales_profit
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_sales_profit`;
CREATE TABLE `fa_kuerp_goods_sales_profit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_time` bigint(16) DEFAULT NULL,
  `customers_id` int(11) DEFAULT NULL COMMENT '客户',
  `sale_user` int(11) DEFAULT NULL COMMENT '销售员',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_type` enum('1','2') DEFAULT NULL COMMENT '业务类别:1=普通销售,2=销售退货',
  `number` double(15,4) DEFAULT NULL COMMENT '数量',
  `amount` double(15,4) DEFAULT NULL COMMENT '折扣额',
  `all_money` varchar(255) DEFAULT NULL COMMENT '销售收入',
  `tax_money` double(15,4) DEFAULT NULL COMMENT '税额',
  `money_tax_price` double(15,4) DEFAULT NULL COMMENT '价税合计',
  `sales_cost` double(15,4) DEFAULT NULL COMMENT '销售成本',
  `preferential_money` double(15,4) DEFAULT NULL COMMENT '优惠额',
  `purchase_money` double(15,4) DEFAULT NULL COMMENT '销售费用',
  `real_money` double(15,4) DEFAULT NULL COMMENT '优惠后金额',
  `customers_money` double(15,4) DEFAULT NULL COMMENT '客户承担费用',
  `need_money` double(15,4) DEFAULT NULL COMMENT '应收金额',
  `pay_money` double(15,4) DEFAULT NULL COMMENT '已收款金额',
  `remark` varchar(255) DEFAULT NULL COMMENT '整单备注',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='销售利润表';

-- ----------------------------
-- Table structure for fa_kuerp_goods_sales_summary
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_sales_summary`;
CREATE TABLE `fa_kuerp_goods_sales_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_type` enum('1','2') DEFAULT '1' COMMENT '类型:1=购货,2=退货',
  `purchase_time` bigint(16) DEFAULT NULL,
  `delivery_time` bigint(16) DEFAULT NULL,
  `customers_id` int(11) DEFAULT NULL COMMENT '客户',
  `receiving_address` varchar(255) DEFAULT NULL COMMENT '收货地址',
  `sale_user` int(11) DEFAULT NULL COMMENT '销售员',
  `goods_code` varchar(30) NOT NULL DEFAULT '' COMMENT '商品编号',
  `goods_note` varchar(255) DEFAULT NULL COMMENT '商品备注',
  `goods_attr` int(11) DEFAULT NULL COMMENT '商品属性',
  `goods_unit` int(11) DEFAULT NULL COMMENT '商品单位',
  `stores_id` int(11) DEFAULT NULL COMMENT '仓库',
  `allow_stock` double(15,4) DEFAULT '0.0000' COMMENT '可用库存',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `default_unit` int(11) DEFAULT NULL COMMENT '基本单位',
  `basic_nmber` double(15,4) DEFAULT '0.0000',
  `unit_price` double(15,4) DEFAULT '0.0000' COMMENT '购货单价',
  `cost_price` double(15,4) DEFAULT NULL,
  `tax_included` double(15,4) DEFAULT NULL COMMENT '含税单价',
  `discount` float(5,2) DEFAULT '0.00' COMMENT '折扣率',
  `dis_money` double(15,4) DEFAULT '0.0000' COMMENT '折扣额',
  `all_money` float(15,4) DEFAULT '0.0000' COMMENT '购货金额',
  `tax_rate` double(15,4) DEFAULT '0.0000' COMMENT '税率',
  `tax_money` double(15,4) DEFAULT '0.0000' COMMENT '税额',
  `money_tax_price` double(15,4) DEFAULT NULL COMMENT '价税合计',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `purchase_order_id` varchar(50) DEFAULT NULL COMMENT '销货订单号',
  `sales_order_id` varchar(50) DEFAULT NULL COMMENT '销货单号',
  `returns_order_id` varchar(50) DEFAULT NULL COMMENT '退货单号',
  `preferential_rate` float(5,2) DEFAULT '0.00' COMMENT '优惠率',
  `preferential_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠额',
  `real_money` double(15,4) DEFAULT '0.0000' COMMENT '优惠后金额',
  `purchase_money` double(15,4) DEFAULT '0.0000' COMMENT '采购费用',
  `customers_money` double(15,4) DEFAULT '0.0000' COMMENT '客户承担费用',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `pay_status` enum('0','1','2') DEFAULT '0' COMMENT '付款状态:0=未付款,1=部分付款,2=全部付款',
  `check_status` enum('0','1') DEFAULT '0' COMMENT '对单状态:0=未对单,1=已对单',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `pay_money` double(15,4) DEFAULT '0.0000' COMMENT '已付款金额',
  `account` int(11) DEFAULT NULL COMMENT '付款账户',
  `print_num` int(11) DEFAULT '0' COMMENT '打印次数',
  `order_info_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL COMMENT '批次ID',
  `batch_name` varchar(100) DEFAULT NULL COMMENT '批次',
  `manufacture` int(11) DEFAULT NULL COMMENT '生产日期',
  `guarantee` int(11) DEFAULT NULL COMMENT '保质期',
  `validity` int(11) DEFAULT NULL COMMENT '过期时间',
  `period` text COMMENT '序列号',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='销售汇总表';

-- ----------------------------
-- Table structure for fa_kuerp_goods_serial
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_serial`;
CREATE TABLE `fa_kuerp_goods_serial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `goods_id` int(11) DEFAULT NULL COMMENT '商品ID',
  `goods_code` varchar(30) DEFAULT NULL COMMENT '商品编号',
  `stores_id` int(11) unsigned DEFAULT NULL COMMENT '仓库ID',
  `attribute_id` varchar(255) DEFAULT NULL COMMENT '属性组合',
  `serial` varchar(30) DEFAULT NULL COMMENT '序列号',
  `status` enum('1','2','3','4','5','6','7','8','9','10','11','12','13','14') DEFAULT '1' COMMENT '状态:1=购货,2=购货退货,3=销货,4=销货退货,5=盘点,6=其他,7=盘盈,8=其他入库,9=盘亏,10=其他出库,11=组合单,12=组合单,13=拆卸单,14=拆卸单',
  `store_status` enum('0','1','2') DEFAULT '0' COMMENT '在库状态:0=未入库,1=在库,2=出库',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `purchase_id` int(11) DEFAULT NULL COMMENT '购货单ID',
  `purchase_code` varchar(30) DEFAULT NULL COMMENT '购货单单号',
  `purchase_exchange_id` int(11) DEFAULT NULL COMMENT '购货退货单ID',
  `purchase_exchange_code` varchar(30) DEFAULT NULL COMMENT '购货退货单单号',
  `sale_id` int(11) DEFAULT NULL COMMENT '销货单ID',
  `sale_code` varchar(30) DEFAULT NULL COMMENT '销货单号',
  `sale_exchange_id` int(11) DEFAULT NULL COMMENT '销货退货单ID',
  `sale_exchange_code` varchar(30) DEFAULT NULL COMMENT '销货退货单单号',
  `order_id` int(11) DEFAULT NULL COMMENT '来源ID',
  `warehousing_id` int(11) DEFAULT NULL COMMENT '入库单ID',
  `warehousing_status` enum('1','2') DEFAULT '1' COMMENT '是否其他入库:1=否,2=是',
  `warehoue_id` int(11) DEFAULT NULL COMMENT '出库单ID',
  `assemble_id` int(11) DEFAULT NULL COMMENT '组合单ID',
  `assemble_status` enum('1','2') DEFAULT '2' COMMENT '组装单入库',
  `split_id` int(11) DEFAULT NULL COMMENT '拆卸单ID',
  `split_status` enum('1','2') DEFAULT '2' COMMENT '拆卸单入库',
  `old_status` int(11) DEFAULT NULL,
  `old_store` int(11) DEFAULT NULL,
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品序列号';

-- ----------------------------
-- Table structure for fa_kuerp_goods_serial_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_serial_log`;
CREATE TABLE `fa_kuerp_goods_serial_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `goods_id` int(11) DEFAULT NULL COMMENT '商品ID',
  `stores_id` int(11) unsigned DEFAULT NULL COMMENT '仓库ID',
  `attribute_id` int(11) DEFAULT NULL COMMENT '属性组合',
  `serial` varchar(30) DEFAULT NULL COMMENT '序列号',
  `business_type` enum('1','2','3','4','5','6','7','8','9','10','11','12','13','14') DEFAULT NULL COMMENT '业务类型:1=期初,2=普通采购,3=采购退回,4=普通销售,5=销售退回,6=调拨单,,7=盘盈,8=其他入库,9=盘亏,10=其他出库,11=组合单,12=组合单,13=拆卸单,14=拆卸单',
  `store_status` enum('0','1','2') DEFAULT '0' COMMENT '在库状态:0=未入库,1=在库,2=出库',
  `remarks` varchar(255) DEFAULT NULL COMMENT '备注',
  `purchase_id` int(11) DEFAULT NULL COMMENT '单据ID',
  `purchase_code` varchar(30) DEFAULT NULL COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL COMMENT '往来单位',
  `order_id` int(11) DEFAULT NULL COMMENT '来源ID',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品序列号跟踪';

-- ----------------------------
-- Table structure for fa_kuerp_goods_stock
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_stock`;
CREATE TABLE `fa_kuerp_goods_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `goods_code` varchar(30) DEFAULT NULL COMMENT '商品编号',
  `attribute_id` int(11) DEFAULT NULL COMMENT '属性组合ID',
  `stores_id` int(11) NOT NULL DEFAULT '0' COMMENT '仓库ID',
  `stock` double(15,4) unsigned NOT NULL DEFAULT '0.0000' COMMENT '库存',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态:0=关仓,1=正常',
  `period` double(15,4) unsigned DEFAULT '0.0000' COMMENT '期初数量',
  `unit_cost` double(15,4) unsigned DEFAULT '0.0000' COMMENT '单位成本',
  `price` double(15,4) unsigned DEFAULT '0.0000' COMMENT '期初总价',
  `stock_warning` enum('0','1') DEFAULT '0' COMMENT '库存预警:0=否,1=是',
  `min_stock` double(15,4) DEFAULT '0.0000' COMMENT '最低库存',
  `max_stock` double(15,4) DEFAULT '0.0000' COMMENT '最高库存',
  `address` varchar(255) DEFAULT NULL COMMENT '产地',
  `registration` varchar(100) DEFAULT NULL COMMENT '注册证号',
  `production` varchar(100) DEFAULT NULL COMMENT '生产许可证',
  `cost_money` double(15,4) DEFAULT '0.0000' COMMENT '总成本',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  `deletetime` bigint(16) DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='商品库存表';

-- ----------------------------
-- Table structure for fa_kuerp_goods_stock_warning
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_stock_warning`;
CREATE TABLE `fa_kuerp_goods_stock_warning` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `goods_code` varchar(30) DEFAULT NULL COMMENT '商品编号',
  `stores_id` int(11) NOT NULL DEFAULT '0' COMMENT '仓库ID',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态:0=关仓,1=正常',
  `stock_warning` enum('0','1') DEFAULT '0' COMMENT '库存预警:0=否,1=是',
  `min_stock` double(15,4) unsigned DEFAULT NULL COMMENT '最低库存',
  `max_stock` double(15,4) unsigned DEFAULT NULL COMMENT '最高库存',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品库存预警表';

-- ----------------------------
-- Table structure for fa_kuerp_goods_store_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_store_info`;
CREATE TABLE `fa_kuerp_goods_store_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT '0',
  `purchase_id` int(11) DEFAULT NULL,
  `number` double(15,4) DEFAULT '0.0000',
  `money` double(15,4) DEFAULT '0.0000',
  `all_money` double(15,4) DEFAULT '0.0000',
  `createtime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='入库';

-- ----------------------------
-- Table structure for fa_kuerp_goods_type
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_goods_type`;
CREATE TABLE `fa_kuerp_goods_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `supplier_type_name` varchar(120) NOT NULL DEFAULT '' COMMENT '类别名称',
  `supplier_type_image` varchar(255) DEFAULT '' COMMENT '图片',
  `pid` int(11) NOT NULL DEFAULT '0' COMMENT '上级',
  `path` varchar(255) DEFAULT NULL COMMENT 'path',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态:0=禁用,1=正常',
  `weigh` int(11) DEFAULT '0',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='商品分类';

-- ----------------------------
-- Table structure for fa_kuerp_income
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_income`;
CREATE TABLE `fa_kuerp_income` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2') DEFAULT NULL COMMENT '业务类型:1=其他收入,2=其他',
  `customers_id` int(11) DEFAULT NULL COMMENT '客户',
  `accounts_id` int(11) DEFAULT NULL COMMENT '账户',
  `cost_id` int(11) DEFAULT NULL COMMENT '采购销售ID',
  `money` double(15,4) DEFAULT NULL COMMENT '金额',
  `pay_money` double(15,4) DEFAULT NULL COMMENT '已收金额',
  `arrears` double(15,4) DEFAULT NULL COMMENT '本次欠款',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `print_num` int(11) DEFAULT NULL COMMENT '打印次数',
  `pay_status` enum('0','1','2') DEFAULT '0' COMMENT '付款状态:0=未付款,1=全部付款,2=部分付款',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='其他收入单';

-- ----------------------------
-- Table structure for fa_kuerp_income_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_income_info`;
CREATE TABLE `fa_kuerp_income_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_id` int(11) DEFAULT NULL COMMENT '收入单ID',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2') DEFAULT NULL COMMENT '业务类型:1=其他收入',
  `customers_id` int(11) DEFAULT NULL COMMENT '客户',
  `income_type_id` int(11) DEFAULT NULL COMMENT '收入类别',
  `money` double(15,4) DEFAULT NULL COMMENT '金额',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='其他收入单详情';

-- ----------------------------
-- Table structure for fa_kuerp_income_type
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_income_type`;
CREATE TABLE `fa_kuerp_income_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `name` varchar(120) NOT NULL DEFAULT '' COMMENT '名称',
  `image` varchar(255) NOT NULL DEFAULT '' COMMENT '图片',
  `pid` int(11) NOT NULL DEFAULT '0' COMMENT '上级分类id',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态:0=禁用,1=正常',
  `path` varchar(255) DEFAULT NULL,
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='收入类别表';

-- ----------------------------
-- Table structure for fa_kuerp_inventory
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_inventory`;
CREATE TABLE `fa_kuerp_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '盘点编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `store_type` enum('1','2') DEFAULT '1' COMMENT '盘点仓库:1=全部仓库,2=单仓',
  `result` varchar(255) DEFAULT NULL COMMENT '盘点结果',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='盘点';

-- ----------------------------
-- Table structure for fa_kuerp_inventory_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_inventory_info`;
CREATE TABLE `fa_kuerp_inventory_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '盘点编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `goods_type` int(11) DEFAULT NULL COMMENT '商品类别',
  `goods_id` int(11) DEFAULT NULL COMMENT '商品ID',
  `stores_id` int(11) DEFAULT NULL COMMENT '仓库ID',
  `goods_attr` int(11) DEFAULT NULL COMMENT '商品属性',
  `stock` double(15,4) DEFAULT NULL COMMENT '系统库存',
  `invertory_stock` double(15,4) DEFAULT NULL COMMENT '盘点库存',
  `profit` double(15,4) DEFAULT NULL COMMENT '盘盈盘亏',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='盘点详情';

-- ----------------------------
-- Table structure for fa_kuerp_inventory_serial
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_inventory_serial`;
CREATE TABLE `fa_kuerp_inventory_serial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '盘点编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `store_type` enum('1','2') DEFAULT '1' COMMENT '盘点仓库:1=全部仓库,2=单仓',
  `result` varchar(255) DEFAULT NULL COMMENT '盘点结果',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='序列号盘点';

-- ----------------------------
-- Table structure for fa_kuerp_inventory_serial_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_inventory_serial_info`;
CREATE TABLE `fa_kuerp_inventory_serial_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '盘点编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `goods_type` int(11) DEFAULT NULL COMMENT '商品类别',
  `goods_id` int(11) DEFAULT NULL COMMENT '商品ID',
  `stores_id` int(11) DEFAULT NULL COMMENT '仓库ID',
  `goods_attr` int(11) DEFAULT NULL COMMENT '商品属性',
  `stock` double(15,4) DEFAULT NULL COMMENT '系统库存',
  `invertory_stock` double(15,4) DEFAULT NULL COMMENT '盘点库存',
  `profit` double(15,4) DEFAULT NULL COMMENT '盘盈盘亏',
  `period_period` text COMMENT '盘点序列号',
  `matching` int(11) DEFAULT NULL COMMENT '匹配',
  `inventory` int(11) DEFAULT NULL COMMENT '盘盈',
  `loss` int(11) DEFAULT NULL COMMENT '盘亏',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='序列号盘点详情';

-- ----------------------------
-- Table structure for fa_kuerp_invoicing
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_invoicing`;
CREATE TABLE `fa_kuerp_invoicing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `suppliers_code` varchar(50) DEFAULT NULL COMMENT '供应商编号',
  `status` enum('0','1','2','3','4','5') DEFAULT NULL COMMENT '状态:0=未付款,1=全部付款,2=部分付款,3=未退款,4=全部退款,5=部分退款',
  `bill_time` bigint(16) DEFAULT NULL,
  `bill_code` varchar(50) DEFAULT NULL COMMENT '源单据编号',
  `bill_codes` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `money` double(15,4) DEFAULT NULL COMMENT '金额',
  `tax` double(15,4) DEFAULT NULL COMMENT '税额',
  `real_money` double(15,4) DEFAULT NULL COMMENT '价税合计',
  `pay_money` double(15,4) DEFAULT '0.0000' COMMENT '已开票金额',
  `give_money` double(15,4) DEFAULT NULL COMMENT '未开票金额',
  `invoice_status` enum('0','1') DEFAULT '0' COMMENT '开票状态:0=未开,1=已开',
  `createtime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='采购开票表';

-- ----------------------------
-- Table structure for fa_kuerp_invoicing_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_invoicing_log`;
CREATE TABLE `fa_kuerp_invoicing_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `suppliers_code` varchar(50) DEFAULT NULL COMMENT '供应商编号',
  `status` enum('0','1','2','3','4','5') DEFAULT NULL COMMENT '状态:0=未付款,1=全部付款,2=部分付款,3=未退款,4=全部退款,5=部分退款',
  `bill_time` bigint(16) DEFAULT NULL,
  `bill_code` varchar(50) DEFAULT NULL COMMENT '源单据编号',
  `bill_codes` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `money` double(15,4) DEFAULT NULL COMMENT '金额',
  `tax` double(15,4) DEFAULT NULL COMMENT '税额',
  `real_money` double(15,4) DEFAULT NULL COMMENT '价税合计',
  `pay_money` double(15,4) DEFAULT '0.0000' COMMENT '已开票金额',
  `give_money` double(15,4) DEFAULT NULL COMMENT '未开票金额',
  `createtime` bigint(16) DEFAULT NULL,
  `times` int(11) DEFAULT NULL COMMENT '开票时间',
  `invoice` varchar(255) DEFAULT NULL COMMENT '发票号',
  `invoice_header` varchar(255) DEFAULT NULL COMMENT '发票抬头',
  `invoicing_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='采购开票表';

-- ----------------------------
-- Table structure for fa_kuerp_mini_banner
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_mini_banner`;
CREATE TABLE `fa_kuerp_mini_banner` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '标题(管理用)',
  `image` varchar(500) NOT NULL DEFAULT '' COMMENT '轮播图URL',
  `link_type` varchar(30) NOT NULL DEFAULT 'page' COMMENT '跳转类型: page=小程序页面, none=无跳转',
  `link_url` varchar(500) NOT NULL DEFAULT '' COMMENT '跳转链接(小程序页面路径)',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序(数字越小越靠前)',
  `status` varchar(20) NOT NULL DEFAULT 'normal' COMMENT '状态: normal=启用, hidden=禁用',
  `createtime` bigint(16) NOT NULL DEFAULT '0',
  `updatetime` bigint(16) NOT NULL DEFAULT '0',
  `deletetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='小程序轮播图(订货通)';

-- ----------------------------
-- Table structure for fa_kuerp_mini_cart
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_mini_cart`;
CREATE TABLE `fa_kuerp_mini_cart` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL COMMENT '用户ID',
  `goods_id` bigint(20) NOT NULL COMMENT '商品ID',
  `attr_ids` varchar(100) NOT NULL DEFAULT '' COMMENT '所选属性组合ID(多属性商品,格式:1&2)',
  `attr_name` varchar(255) NOT NULL DEFAULT '' COMMENT '属性描述(快照,如"红色/XL")',
  `goods_unit` bigint(20) NOT NULL DEFAULT '0' COMMENT '所选单位ID',
  `unit_name` varchar(50) NOT NULL DEFAULT '' COMMENT '单位名称(快照)',
  `quantity` decimal(12,2) NOT NULL DEFAULT '1.00' COMMENT '数量',
  `createdtime` bigint(16) NOT NULL DEFAULT '0',
  `updatetime` bigint(16) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_goods_attr` (`user_id`,`goods_id`,`attr_ids`,`goods_unit`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='购物车表';

-- ----------------------------
-- Table structure for fa_kuerp_mini_config
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_mini_config`;
CREATE TABLE `fa_kuerp_mini_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '配置名称(唯一标识)',
  `value` text NOT NULL COMMENT '配置值',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT '配置说明',
  `createtime` bigint(16) NOT NULL DEFAULT '0',
  `updatetime` bigint(16) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COMMENT='小程序系统配置(订货通)';

-- ----------------------------
-- Table structure for fa_kuerp_mini_delivery
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_mini_delivery`;
CREATE TABLE `fa_kuerp_mini_delivery` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) NOT NULL COMMENT '订单ID(fa_kuerp_mini_order.id)',
  `delivery_no` varchar(50) NOT NULL COMMENT '发货单号',
  `delivery_type` varchar(30) NOT NULL COMMENT '配送方式: logistics=物流配送, professional=专业配送',
  `express_company` varchar(100) NOT NULL DEFAULT '' COMMENT '快递公司名称',
  `express_code` varchar(50) NOT NULL DEFAULT '' COMMENT '快递公司编码(如yunda,shentong)',
  `express_no` varchar(100) NOT NULL DEFAULT '' COMMENT '快递单号',
  `carrier_name` varchar(100) NOT NULL DEFAULT '' COMMENT '承运方/配送员',
  `carrier_mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '配送员电话',
  `status` varchar(30) NOT NULL DEFAULT 'shipped' COMMENT '发货状态: shipped=已发货, received=已签收',
  `shipped_time` bigint(16) NOT NULL DEFAULT '0' COMMENT '发货时间',
  `received_time` bigint(16) NOT NULL DEFAULT '0' COMMENT '签收时间',
  `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '发货管理员ID',
  `remark` varchar(500) NOT NULL DEFAULT '' COMMENT '发货备注',
  `createdtime` bigint(16) NOT NULL DEFAULT '0',
  `updatetime` bigint(16) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_delivery_no` (`delivery_no`),
  KEY `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='发货记录表(物流关联到订单级别)';

-- ----------------------------
-- Table structure for fa_kuerp_mini_order
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_mini_order`;
CREATE TABLE `fa_kuerp_mini_order` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `order_no` varchar(50) NOT NULL COMMENT '订单号(kuerp_prefix编号规则)',
  `user_id` bigint(20) NOT NULL COMMENT '用户ID(fa_kuerp_mini_user.id)',
  `customer_id` int(11) NOT NULL DEFAULT '0' COMMENT '关联fa_kuerp_customers.id',
  `status` varchar(30) NOT NULL DEFAULT 'pending_confirm' COMMENT '订单状态',
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '订单总金额',
  `preferential` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '优惠金额',
  `payable_amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '应付金额(=total-preferential)',
  `remark` varchar(500) NOT NULL DEFAULT '' COMMENT '用户备注',
  `admin_remark` varchar(500) NOT NULL DEFAULT '' COMMENT '管理员备注',
  `receiver_name` varchar(50) NOT NULL COMMENT '收货人姓名',
  `receiver_mobile` varchar(20) NOT NULL COMMENT '收货人电话',
  `receiver_province` varchar(50) NOT NULL DEFAULT '' COMMENT '省',
  `receiver_city` varchar(50) NOT NULL DEFAULT '' COMMENT '市',
  `receiver_district` varchar(50) NOT NULL DEFAULT '' COMMENT '区/县',
  `receiver_address` varchar(300) NOT NULL DEFAULT '' COMMENT '详细地址(街道/门牌号)',
  `receiver_zip` varchar(10) NOT NULL DEFAULT '' COMMENT '邮编',
  `delivery_type` varchar(30) NOT NULL DEFAULT 'logistics' COMMENT '配送方式: logistics=物流配送, professional=专业配送',
  `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '处理管理员ID',
  `confirm_time` bigint(16) NOT NULL DEFAULT '0' COMMENT '确认时间',
  `payment_time` bigint(16) NOT NULL DEFAULT '0' COMMENT '对单/付款时间(即销货单对单时间)',
  `sales_code` varchar(50) NOT NULL DEFAULT '' COMMENT '关联销货单号(确认时生成)',
  `complete_time` bigint(16) NOT NULL DEFAULT '0' COMMENT '完成时间',
  `createdtime` bigint(16) NOT NULL DEFAULT '0',
  `updatetime` bigint(16) NOT NULL DEFAULT '0',
  `deletedtime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_user` (`user_id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_sales_code` (`sales_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='小程序订单主表(订货通)';

-- ----------------------------
-- Table structure for fa_kuerp_mini_order_goods
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_mini_order_goods`;
CREATE TABLE `fa_kuerp_mini_order_goods` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) NOT NULL COMMENT '订单ID(fa_kuerp_mini_order.id)',
  `goods_id` bigint(20) NOT NULL COMMENT '商品ID(fa_kuerp_goods)',
  `goods_code` varchar(100) NOT NULL DEFAULT '' COMMENT '商品编码(快照)',
  `goods_name` varchar(255) NOT NULL DEFAULT '' COMMENT '商品名称(快照)',
  `goods_image` varchar(500) NOT NULL DEFAULT '' COMMENT '商品图片(快照)',
  `goods_unit` bigint(20) NOT NULL DEFAULT '0' COMMENT '单位ID',
  `unit_name` varchar(50) NOT NULL DEFAULT '' COMMENT '单位名称(快照)',
  `attr_ids` varchar(100) NOT NULL DEFAULT '' COMMENT '属性组合ID',
  `attr_name` varchar(255) NOT NULL DEFAULT '' COMMENT '属性描述(快照,如"红色/XL")',
  `quantity` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '数量',
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '单价(快照)',
  `all_money` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '金额小计(=quantity*unit_price)',
  `createdtime` bigint(16) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_goods` (`goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单商品明细表(订货通)';

-- ----------------------------
-- Table structure for fa_kuerp_mini_user
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_mini_user`;
CREATE TABLE `fa_kuerp_mini_user` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '关联fa_user.id',
  `customer_id` int(11) NOT NULL DEFAULT '0' COMMENT '关联fa_kuerp_customers.id(登录时自动创建)',
  `openid` varchar(100) NOT NULL COMMENT '微信openid',
  `unionid` varchar(100) NOT NULL DEFAULT '' COMMENT '微信unionid(跨平台统一标识)',
  `apptype` varchar(20) NOT NULL DEFAULT 'miniapp' COMMENT '来源: miniapp=小程序, mp=公众号',
  `nickname` varchar(100) NOT NULL DEFAULT '' COMMENT '微信昵称',
  `avatar` varchar(500) NOT NULL DEFAULT '' COMMENT '微信头像',
  `mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '手机号',
  `session_key` varchar(100) NOT NULL DEFAULT '' COMMENT '小程序session_key(仅miniapp)',
  `status` varchar(20) NOT NULL DEFAULT 'normal',
  `createdtime` bigint(16) NOT NULL DEFAULT '0',
  `updatetime` bigint(16) NOT NULL DEFAULT '0',
  `deletedtime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_openid_apptype` (`openid`,`apptype`),
  KEY `idx_user` (`user_id`),
  KEY `idx_unionid` (`unionid`),
  KEY `idx_customer` (`customer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COMMENT='微信用户表(小程序/公众号)';

-- ----------------------------
-- Table structure for fa_kuerp_payment
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_payment`;
CREATE TABLE `fa_kuerp_payment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2') DEFAULT '1' COMMENT '类型:1=预付单,2=收款单',
  `customers_id` int(11) DEFAULT NULL COMMENT '客户',
  `money` double(15,4) DEFAULT NULL COMMENT '付款合计',
  `write_off` double(15,4) DEFAULT NULL COMMENT '核销金额',
  `discount` double(15,4) DEFAULT NULL COMMENT '整单折扣',
  `advance` double(15,4) DEFAULT NULL COMMENT '预付款',
  `staff_member_id` int(11) unsigned DEFAULT NULL COMMENT '付款人',
  `remarks` varchar(255) DEFAULT NULL COMMENT '备注',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `examine_time` bigint(16) DEFAULT NULL,
  `examine_status` enum('0','1') NOT NULL DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `print_num` int(11) DEFAULT NULL COMMENT '打印次数',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='付款单';

-- ----------------------------
-- Table structure for fa_kuerp_payment_account
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_payment_account`;
CREATE TABLE `fa_kuerp_payment_account` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `collection_id` int(11) NOT NULL DEFAULT '0' COMMENT '收款单ID',
  `purchase_code` varchar(50) NOT NULL DEFAULT '' COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `accounts_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '结算账户',
  `money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '付款金额',
  `settlementtype` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '结算方式',
  `number` varchar(50) DEFAULT '' COMMENT '结算号',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='付款账户信息';

-- ----------------------------
-- Table structure for fa_kuerp_payment_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_payment_log`;
CREATE TABLE `fa_kuerp_payment_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `suppliers_code` varchar(50) DEFAULT NULL COMMENT '供应商编号',
  `business_type` enum('1','2','3','4','5','6','7') DEFAULT NULL COMMENT '业务类型:1=普通采购,2=采购退回,3=付款,4=退款,5=期初应付款,6=期初预付款,7=核销',
  `bill_time` bigint(16) DEFAULT NULL,
  `bill_code` varchar(50) DEFAULT NULL COMMENT '源单据编号',
  `bill_codes` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_money` double(15,4) DEFAULT NULL COMMENT '采购金额',
  `money_tax_price` double(15,4) DEFAULT NULL,
  `discount_money` double(15,4) DEFAULT NULL COMMENT '优惠金额',
  `real_money` double(15,4) DEFAULT NULL COMMENT '优惠后金额',
  `pay_money` double(15,4) DEFAULT NULL COMMENT '本次付款',
  `give_money` double(15,4) DEFAULT NULL COMMENT '应付款余额',
  `reamrk` varchar(255) DEFAULT NULL COMMENT '备注',
  `createtime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COMMENT='采购付款一览表';

-- ----------------------------
-- Table structure for fa_kuerp_payment_sale
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_payment_sale`;
CREATE TABLE `fa_kuerp_payment_sale` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `collection_id` int(11) NOT NULL DEFAULT '0' COMMENT '收款单ID',
  `purchase_code` varchar(50) NOT NULL DEFAULT '' COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `purchase_number` varchar(50) NOT NULL DEFAULT '' COMMENT '原单编号',
  `business_type` enum('1','2','3','4','5','6') NOT NULL DEFAULT '1' COMMENT '单据类型:1=预收单,2=预付单,3=应收,4=应付,5=期初应收/付款,6=期初预收/付款',
  `times` int(11) NOT NULL DEFAULT '0' COMMENT '单据日期',
  `money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '单据金额',
  `write_off` double(15,4) DEFAULT NULL COMMENT '已核销金额',
  `write_off_money` double(15,4) DEFAULT NULL COMMENT '未核销金额',
  `write_off_price` double(15,4) DEFAULT '0.0000' COMMENT '本次核销金额',
  `remark` varchar(255) DEFAULT NULL COMMENT '单据备注',
  `old_money` double(15,4) DEFAULT NULL,
  `old_status` tinyint(3) DEFAULT NULL,
  `old_money_i` double(15,4) DEFAULT NULL,
  `old_status_i` tinyint(3) DEFAULT NULL,
  `business_name` varchar(255) DEFAULT NULL COMMENT '单据名称',
  `source_id` int(11) NOT NULL DEFAULT '0' COMMENT '源单ID',
  `type` enum('1','2','3','4') DEFAULT NULL COMMENT '类型:1=购货/销货,2=退货,3=期初,4=核销',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='付款单单据信息';

-- ----------------------------
-- Table structure for fa_kuerp_prefix
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_prefix`;
CREATE TABLE `fa_kuerp_prefix` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `type` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '单据类型',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '单据',
  `prefix` varchar(10) NOT NULL DEFAULT '' COMMENT '前缀',
  `year` enum('1','2') NOT NULL DEFAULT '1' COMMENT '年',
  `month` enum('1','2') NOT NULL DEFAULT '1' COMMENT '月',
  `day` enum('1','2') NOT NULL DEFAULT '1' COMMENT '日',
  `hour` enum('1','2') NOT NULL DEFAULT '1' COMMENT '时',
  `minute` enum('1','2') NOT NULL DEFAULT '1' COMMENT '分',
  `second` enum('1','2') NOT NULL DEFAULT '1' COMMENT '秒',
  `start` int(11) unsigned DEFAULT NULL COMMENT '起始数',
  `strlen` int(11) unsigned DEFAULT '0' COMMENT '自定义数字长度',
  `example` varchar(50) DEFAULT NULL COMMENT '示例',
  `dbname` varchar(50) DEFAULT NULL COMMENT '表',
  `field` varchar(50) DEFAULT NULL COMMENT '字段名',
  PRIMARY KEY (`id`),
  UNIQUE KEY `prefix` (`prefix`),
  UNIQUE KEY `type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COMMENT='单号设置';

-- ----------------------------
-- Table structure for fa_kuerp_sales_invoicing
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_sales_invoicing`;
CREATE TABLE `fa_kuerp_sales_invoicing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `customers_id` int(11) DEFAULT NULL COMMENT '客户',
  `status` enum('0','1','2','3','4','5') DEFAULT NULL COMMENT '状态:0=未收款,1=全部收款,2=部分收款,3=未退款,4=全部退款,5=部分退款',
  `bill_time` bigint(16) DEFAULT NULL,
  `bill_code` varchar(50) DEFAULT NULL COMMENT '源单据编号',
  `bill_codes` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `money` double(15,4) DEFAULT NULL COMMENT '金额',
  `tax` double(15,4) DEFAULT NULL COMMENT '税额',
  `real_money` double(15,4) DEFAULT NULL COMMENT '价税合计',
  `customers_money` double(15,4) DEFAULT '0.0000' COMMENT '客户承担费用',
  `pay_money` double(15,4) DEFAULT '0.0000' COMMENT '已开票金额',
  `give_money` double(15,4) DEFAULT NULL COMMENT '未开票金额',
  `invoice_status` enum('0','1') DEFAULT '0' COMMENT '开票状态:0=未开,1=已开',
  `createtime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='销货开票表';

-- ----------------------------
-- Table structure for fa_kuerp_sales_invoicing_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_sales_invoicing_log`;
CREATE TABLE `fa_kuerp_sales_invoicing_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `customers_id` int(11) DEFAULT NULL COMMENT '客户',
  `status` enum('0','1','2','3','4','5') DEFAULT NULL COMMENT '状态:0=未收款,1=全部收款,2=部分收款,3=未退款,4=全部退款,5=部分退款',
  `bill_time` bigint(16) DEFAULT NULL,
  `bill_code` varchar(50) DEFAULT NULL COMMENT '源单据编号',
  `bill_codes` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `money` double(15,4) DEFAULT NULL COMMENT '金额',
  `tax` double(15,4) DEFAULT NULL COMMENT '税额',
  `real_money` double(15,4) DEFAULT NULL COMMENT '价税合计',
  `customers_money` double(15,4) DEFAULT '0.0000' COMMENT '客户承担费用',
  `pay_money` double(15,4) DEFAULT '0.0000' COMMENT '已开票金额',
  `give_money` double(15,4) DEFAULT NULL COMMENT '未开票金额',
  `createtime` bigint(16) DEFAULT NULL,
  `times` int(11) DEFAULT NULL COMMENT '开票时间',
  `invoice` varchar(255) DEFAULT NULL COMMENT '发票号',
  `invoice_header` varchar(255) DEFAULT NULL COMMENT '发票抬头',
  `invoicing_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='销货开票表';

-- ----------------------------
-- Table structure for fa_kuerp_sales_payment_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_sales_payment_log`;
CREATE TABLE `fa_kuerp_sales_payment_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `customers_id` int(11) DEFAULT NULL COMMENT '客户',
  `sale_user` int(11) DEFAULT NULL COMMENT '销售员',
  `business_type` enum('1','2','3','4','5','6','7') DEFAULT NULL COMMENT '业务类型:1=销货,2=销货退回,3=付款,4=退款,5=期初应收款,6=期初预收款,7=核销',
  `bill_time` bigint(16) DEFAULT NULL,
  `bill_code` varchar(50) DEFAULT NULL COMMENT '源单据编号',
  `bill_codes` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_money` double(15,4) DEFAULT NULL COMMENT '销售金额',
  `money_tax_price` double(15,4) DEFAULT NULL COMMENT '价税合计',
  `discount_money` double(15,4) DEFAULT NULL COMMENT '优惠金额',
  `real_money` double(15,4) DEFAULT NULL COMMENT '优惠后金额',
  `customers_money` double(15,4) DEFAULT '0.0000' COMMENT '客户承担费用',
  `pay_money` double(15,4) DEFAULT NULL COMMENT '本次付款',
  `give_money` double(15,4) DEFAULT NULL COMMENT '应收款余额',
  `reamrk` varchar(255) DEFAULT NULL COMMENT '备注',
  `createtime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COMMENT='销货收款一览表';

-- ----------------------------
-- Table structure for fa_kuerp_sets
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_sets`;
CREATE TABLE `fa_kuerp_sets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `type` tinyint(3) DEFAULT '1' COMMENT '类型:1=基础参数,2=功能参数',
  `name` varchar(100) DEFAULT NULL COMMENT '变量名称',
  `nickname` varchar(100) DEFAULT NULL COMMENT '名称',
  `value` varchar(255) DEFAULT NULL COMMENT '设置值',
  `is_edit` enum('0','1') DEFAULT '0' COMMENT '修改:0=允许,1=不允许',
  `notice` varchar(255) DEFAULT NULL COMMENT '提示',
  `explain` varchar(255) DEFAULT NULL COMMENT '说明',
  `is_checked` tinyint(3) DEFAULT '0' COMMENT '是否选中',
  `set` text COMMENT '场景',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COMMENT='设置';

-- ----------------------------
-- Table structure for fa_kuerp_settlementtype
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_settlementtype`;
CREATE TABLE `fa_kuerp_settlementtype` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '结算方式名称',
  `image` varchar(255) DEFAULT '' COMMENT '图片',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '结算方式状态:0=禁用,1=启用',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  `deletetime` bigint(16) DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='结算方式表';

-- ----------------------------
-- Table structure for fa_kuerp_spendingtype
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_spendingtype`;
CREATE TABLE `fa_kuerp_spendingtype` (
  `id` int(11) NOT NULL,
  `spending_type_name` varchar(120) NOT NULL DEFAULT '' COMMENT '类型名称',
  `spending_type_image` varchar(255) DEFAULT '' COMMENT '图片',
  `status` enum('0','1') DEFAULT '1' COMMENT '状态:0=禁用,1=启用',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='支出类别表';

-- ----------------------------
-- Table structure for fa_kuerp_split
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_split`;
CREATE TABLE `fa_kuerp_split` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2') DEFAULT NULL COMMENT '业务类型:1=拆卸单',
  `money` double(15,4) DEFAULT NULL COMMENT '费用',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `print_num` int(11) DEFAULT NULL COMMENT '打印次数',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='拆卸单';

-- ----------------------------
-- Table structure for fa_kuerp_split_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_split_info`;
CREATE TABLE `fa_kuerp_split_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) NOT NULL DEFAULT '' COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2') NOT NULL DEFAULT '1' COMMENT '类型:1=拆卸单',
  `type` enum('1','2') NOT NULL DEFAULT '2' COMMENT '类型:1=组合件,2=子件',
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `goods_code` varchar(50) NOT NULL DEFAULT '' COMMENT '商品编号',
  `goods_attr` int(11) DEFAULT NULL COMMENT '商品属性',
  `goods_unit` int(11) NOT NULL DEFAULT '0' COMMENT '商品单位',
  `stores_id` int(11) NOT NULL DEFAULT '0' COMMENT '仓库',
  `allow_stock` double(15,4) DEFAULT '0.0000' COMMENT '可用库存',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `period` text COMMENT '序列号',
  `basic_number` double(15,4) DEFAULT '0.0000' COMMENT '基本数量',
  `unit_cost` double DEFAULT '0' COMMENT '单位成本',
  `cost_money` double DEFAULT '0' COMMENT '成本',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `batch_id` int(11) DEFAULT NULL COMMENT '批次ID',
  `batch` varchar(255) DEFAULT NULL COMMENT '批次',
  `manufacture` int(11) DEFAULT NULL COMMENT '生产日期',
  `guarantee` int(11) DEFAULT NULL COMMENT '保质期',
  `validity` int(11) DEFAULT NULL COMMENT '过期时间',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `print_num` int(11) DEFAULT '0' COMMENT '打印次数',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='拆卸单详情';

-- ----------------------------
-- Table structure for fa_kuerp_split_info_temp
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_split_info_temp`;
CREATE TABLE `fa_kuerp_split_info_temp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) NOT NULL DEFAULT '' COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2') NOT NULL DEFAULT '1' COMMENT '类型:1=拆卸单',
  `type` enum('1','2') NOT NULL DEFAULT '2' COMMENT '类型:1=组合件,2=子件',
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `goods_code` varchar(50) NOT NULL DEFAULT '' COMMENT '商品编号',
  `goods_attr` int(11) DEFAULT NULL COMMENT '商品属性',
  `goods_unit` int(11) NOT NULL DEFAULT '0' COMMENT '商品单位',
  `stores_id` int(11) NOT NULL DEFAULT '0' COMMENT '仓库',
  `allow_stock` double(15,4) DEFAULT '0.0000' COMMENT '可用库存',
  `number` double(15,4) DEFAULT '0.0000' COMMENT '数量',
  `period` text COMMENT '序列号',
  `basic_number` double(15,4) DEFAULT '0.0000' COMMENT '基本数量',
  `unit_cost` double DEFAULT '0' COMMENT '单位成本',
  `cost_money` double DEFAULT '0' COMMENT '成本',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `batch_id` int(11) DEFAULT NULL COMMENT '批次ID',
  `batch` varchar(255) DEFAULT NULL COMMENT '批次',
  `manufacture` int(11) DEFAULT NULL COMMENT '生产日期',
  `guarantee` int(11) DEFAULT NULL COMMENT '保质期',
  `validity` int(11) DEFAULT NULL COMMENT '过期时间',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `print_num` int(11) DEFAULT '0' COMMENT '打印次数',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='拆卸单详情模板';

-- ----------------------------
-- Table structure for fa_kuerp_split_temp
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_split_temp`;
CREATE TABLE `fa_kuerp_split_temp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `temp_name` varchar(255) NOT NULL DEFAULT '' COMMENT '模板名称',
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2') DEFAULT NULL COMMENT '业务类型:1=拆卸单',
  `money` double(15,4) DEFAULT NULL COMMENT '费用',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `examine_status` enum('0','1') DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `examine_time` bigint(16) DEFAULT NULL,
  `print_num` int(11) DEFAULT NULL COMMENT '打印次数',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='拆卸单模板';

-- ----------------------------
-- Table structure for fa_kuerp_staff_member
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_staff_member`;
CREATE TABLE `fa_kuerp_staff_member` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `men_code` varchar(100) DEFAULT NULL COMMENT '职员编号',
  `name` varchar(100) DEFAULT NULL COMMENT '职员名称',
  `phone` varchar(20) DEFAULT NULL COMMENT '电话',
  `status` enum('0','1') DEFAULT '1' COMMENT '状态:0=禁用,1=正常',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='职员表';

-- ----------------------------
-- Table structure for fa_kuerp_stores
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_stores`;
CREATE TABLE `fa_kuerp_stores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `store_code` varchar(30) NOT NULL DEFAULT '' COMMENT '仓库编号',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '仓库名称',
  `status` enum('0','1') DEFAULT '1' COMMENT '状态:0=关闭,1=正常',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='仓库表';

-- ----------------------------
-- Table structure for fa_kuerp_suppliers
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_suppliers`;
CREATE TABLE `fa_kuerp_suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `supplyer_code` varchar(30) NOT NULL DEFAULT '' COMMENT '供应商编号',
  `supplyertype_id` int(11) NOT NULL DEFAULT '0' COMMENT '供应商类别',
  `supplyer_name` varchar(120) NOT NULL COMMENT '供应商名称',
  `first_connect_name` varchar(100) NOT NULL DEFAULT '' COMMENT '联系人',
  `first_connect_mobile` varchar(11) NOT NULL COMMENT '手机',
  `first_connect_phone` varchar(20) DEFAULT '' COMMENT '座机',
  `qq_wx_email` varchar(120) DEFAULT '' COMMENT 'QQ/微信/Email',
  `connect_address` varchar(255) DEFAULT '' COMMENT '联系地址',
  `note` varchar(255) DEFAULT '' COMMENT '备注',
  `need_give_account` float(11,2) NOT NULL DEFAULT '0.00' COMMENT '应付款余额',
  `receive_account` double(15,4) DEFAULT NULL COMMENT '期初应付款',
  `account_time` bigint(16) DEFAULT NULL,
  `opening_payable` double(15,4) unsigned DEFAULT '0.0000' COMMENT '期初应付款',
  `opening_prepayment` double(15,4) unsigned DEFAULT '0.0000' COMMENT '期初预付款',
  `vat_rate` double(15,4) unsigned DEFAULT NULL COMMENT '增值税税率',
  `identification` varchar(50) DEFAULT NULL COMMENT '纳税人识别号',
  `deposit_bank` varchar(150) DEFAULT NULL COMMENT '开户银行',
  `bank_number` varchar(30) DEFAULT NULL COMMENT '银行卡号',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态:0=禁用,1=正常',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='供应商表';

-- ----------------------------
-- Table structure for fa_kuerp_suppliers_user
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_suppliers_user`;
CREATE TABLE `fa_kuerp_suppliers_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `supplyer_code` varchar(30) NOT NULL DEFAULT '' COMMENT '供应商编号',
  `sid` int(11) unsigned DEFAULT NULL COMMENT '供应商ID',
  `first_connect_name` varchar(100) NOT NULL DEFAULT '' COMMENT '联系人名称',
  `first_connect_mobile` varchar(11) NOT NULL COMMENT '手机',
  `first_connect_phone` varchar(20) NOT NULL COMMENT '座机',
  `qq_wx_email` varchar(120) NOT NULL COMMENT 'QQ/微信/Email',
  `connect_address` varchar(255) NOT NULL COMMENT '联系地址',
  `is_first` tinyint(3) DEFAULT '0' COMMENT '首要联系人:0=否,1=是',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态:0=禁用,1=正常',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  `deletetime` bigint(16) DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='供应商联系人';

-- ----------------------------
-- Table structure for fa_kuerp_supplyertype
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_supplyertype`;
CREATE TABLE `fa_kuerp_supplyertype` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `supplier_type_name` varchar(120) NOT NULL DEFAULT '' COMMENT '类别名称',
  `supplier_type_img` varchar(255) NOT NULL DEFAULT '' COMMENT '图片',
  `pid` int(11) NOT NULL DEFAULT '0' COMMENT '上级',
  `path` varchar(255) DEFAULT NULL COMMENT 'path',
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '状态:0=禁用,1=正常',
  `weigh` int(11) DEFAULT NULL,
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='供应商分类';

-- ----------------------------
-- Table structure for fa_kuerp_transfers
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_transfers`;
CREATE TABLE `fa_kuerp_transfers` (
  `id` int(11) NOT NULL,
  `documents_code` varchar(50) NOT NULL COMMENT '单据编号',
  `documents_date` varchar(50) NOT NULL COMMENT '单据日期',
  `goods_code` varchar(100) NOT NULL COMMENT '商品编号',
  `goods_type_id` int(11) NOT NULL COMMENT '商品类别',
  `goodsnote` text NOT NULL COMMENT '商品备注',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  `deletetime` bigint(16) DEFAULT NULL COMMENT '删除时间',
  `num` int(11) NOT NULL COMMENT '数量',
  `unit_id` int(11) NOT NULL COMMENT '单位',
  `out_store_id` int(11) NOT NULL COMMENT '调出仓库',
  `to_store_id` int(11) NOT NULL COMMENT '调入仓库',
  `maker_id` int(11) NOT NULL COMMENT '制单人',
  `fl_note` text NOT NULL COMMENT '分录备注',
  `audit` varchar(100) NOT NULL COMMENT '审核人',
  `main_note` text NOT NULL COMMENT '备注',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for fa_kuerp_unit
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_unit`;
CREATE TABLE `fa_kuerp_unit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL COMMENT '单位名称',
  `type` enum('1','2') DEFAULT '1' COMMENT '类型:1=单一单位,2=多单位',
  `is_main` enum('0','1') DEFAULT '0' COMMENT '是否主单位:0=否,1=是',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='计量单位';

-- ----------------------------
-- Table structure for fa_kuerp_unit_conversion
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_unit_conversion`;
CREATE TABLE `fa_kuerp_unit_conversion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `unit_name` varchar(255) DEFAULT NULL,
  `main_unit` int(11) NOT NULL DEFAULT '0' COMMENT '主计量单位',
  `vice_unit` int(11) NOT NULL DEFAULT '0' COMMENT '副计量单位',
  `advance_rate` float(11,2) NOT NULL DEFAULT '0.00' COMMENT '换算进率',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='计量单位换算';

-- ----------------------------
-- Table structure for fa_kuerp_warehouse
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_warehouse`;
CREATE TABLE `fa_kuerp_warehouse` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2') DEFAULT NULL COMMENT '业务类别:1=盘亏,2=其他出库',
  `number` double(15,4) DEFAULT NULL COMMENT '数量',
  `price` double(15,4) DEFAULT NULL COMMENT '金额',
  `customers_id` int(11) DEFAULT NULL COMMENT '客户',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `examine_time` bigint(16) DEFAULT NULL,
  `examine_status` enum('0','1') NOT NULL DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `remarks` varchar(255) DEFAULT NULL COMMENT '备注',
  `print_num` int(11) DEFAULT NULL COMMENT '打印次数',
  `order_id` int(11) DEFAULT NULL COMMENT '盘点单ID',
  `order_type` enum('1','2') DEFAULT NULL COMMENT '单据类型:1=盘点,2=序列号盘点',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='其他出库单';

-- ----------------------------
-- Table structure for fa_kuerp_warehouse_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_warehouse_info`;
CREATE TABLE `fa_kuerp_warehouse_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '盘点编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `goods_id` int(11) DEFAULT NULL COMMENT '商品',
  `goods_attr` int(11) DEFAULT NULL COMMENT '商品属性',
  `goods_unit` int(11) DEFAULT NULL COMMENT '商品单位',
  `stores_id` int(11) DEFAULT NULL COMMENT '仓库',
  `stock` double(15,4) DEFAULT NULL COMMENT '可用库存',
  `number` double(15,4) DEFAULT NULL COMMENT '数量',
  `basic_nmber` double(15,4) DEFAULT NULL COMMENT '基础数量',
  `period` text COMMENT '序列号',
  `unit_price` double(15,4) DEFAULT NULL COMMENT '出库单价',
  `price` double(15,4) DEFAULT NULL COMMENT '出库金额',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `batch` varchar(255) DEFAULT NULL COMMENT '批次',
  `batch_id` int(11) DEFAULT NULL COMMENT '批次ID',
  `manufacture` int(11) DEFAULT NULL COMMENT '生产日期',
  `guarantee` int(11) DEFAULT NULL COMMENT '保质期',
  `validity` int(11) DEFAULT NULL COMMENT '有效期至',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='其他出库单详情';

-- ----------------------------
-- Table structure for fa_kuerp_warehousing
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_warehousing`;
CREATE TABLE `fa_kuerp_warehousing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2') DEFAULT NULL COMMENT '业务类别:1=盘盈,2=其他入库',
  `number` double(15,4) DEFAULT NULL COMMENT '数量',
  `price` double(15,4) DEFAULT NULL COMMENT '金额',
  `suppliers_id` int(11) DEFAULT NULL COMMENT '供应商',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `examine_time` bigint(16) DEFAULT NULL,
  `examine_status` enum('0','1') NOT NULL DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `remarks` varchar(255) DEFAULT NULL COMMENT '备注',
  `print_num` int(11) DEFAULT NULL COMMENT '打印次数',
  `order_id` int(11) DEFAULT NULL COMMENT '盘点单ID',
  `order_type` enum('1','2') DEFAULT NULL COMMENT '单据类型:1=盘点,2=序列号盘点',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='其他入库单';

-- ----------------------------
-- Table structure for fa_kuerp_warehousing_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_warehousing_info`;
CREATE TABLE `fa_kuerp_warehousing_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '盘点编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `goods_id` int(11) DEFAULT NULL COMMENT '商品',
  `goods_attr` int(11) DEFAULT NULL COMMENT '商品属性',
  `goods_unit` int(11) DEFAULT NULL COMMENT '商品单位',
  `stores_id` int(11) DEFAULT NULL COMMENT '仓库',
  `stock` double(15,4) DEFAULT NULL COMMENT '可用库存',
  `number` double(15,4) DEFAULT NULL COMMENT '数量',
  `basic_nmber` double(15,4) DEFAULT NULL COMMENT '基础数量',
  `period` text COMMENT '序列号',
  `unit_price` double(15,4) DEFAULT NULL COMMENT '入库单价',
  `price` double(15,4) DEFAULT NULL COMMENT '入库金额',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `batch` varchar(255) DEFAULT NULL COMMENT '批次',
  `batch_id` int(11) DEFAULT NULL COMMENT '批次ID',
  `manufacture` int(11) DEFAULT NULL COMMENT '生产日期',
  `guarantee` int(11) DEFAULT NULL COMMENT '保质期',
  `validity` int(11) DEFAULT NULL COMMENT '有效期至',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='其他入库单详情';

-- ----------------------------
-- Table structure for fa_kuerp_writeoff
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_writeoff`;
CREATE TABLE `fa_kuerp_writeoff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_code` varchar(50) DEFAULT NULL COMMENT '单据编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `business_type` enum('1','2','3','4','5') NOT NULL DEFAULT '1' COMMENT '类型:1=预收冲应收,2=预付冲应付,3=应收冲应付,4=应收转应收,5=应付转应付',
  `customers_id` int(11) DEFAULT NULL COMMENT '客户',
  `customers_out_id` int(11) DEFAULT NULL COMMENT '转出客户',
  `customers_enter_id` int(11) DEFAULT NULL COMMENT '转入客户',
  `suppliers_id` int(11) DEFAULT NULL COMMENT '供应商',
  `suppliers_out_id` int(11) DEFAULT NULL COMMENT '转出供应商',
  `suppliers_enter_id` int(11) DEFAULT NULL COMMENT '转入供应商',
  `write_off` double(15,4) DEFAULT NULL COMMENT '核销金额',
  `remarks` varchar(255) DEFAULT NULL COMMENT '备注',
  `preparation_id` int(11) DEFAULT NULL COMMENT '制单人',
  `examine_id` int(11) DEFAULT NULL COMMENT '审核人',
  `operation_id` int(11) DEFAULT NULL COMMENT '最后操作人',
  `examine_time` bigint(16) DEFAULT NULL,
  `examine_status` enum('0','1') NOT NULL DEFAULT '0' COMMENT '审核状态:0=未审核,1=已审核',
  `print_num` int(11) DEFAULT NULL COMMENT '打印次数',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='核销单';

-- ----------------------------
-- Table structure for fa_kuerp_writeoff_info
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_writeoff_info`;
CREATE TABLE `fa_kuerp_writeoff_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `collection_id` int(11) NOT NULL DEFAULT '0' COMMENT '核销单ID',
  `purchase_code` varchar(50) NOT NULL DEFAULT '' COMMENT '单据编号',
  `purchase_type` enum('1','2','3','4','5','6') DEFAULT NULL COMMENT '单据类型:1=预收单,2=预付单,3=应收,4=应付,5=期初应收/付款,6=期初预收/付款',
  `purchase_time` bigint(16) DEFAULT NULL,
  `type` enum('1','2','3','4','5','6') DEFAULT '1' COMMENT '类型:1=购货/销货,2=退货,3=期初,4=核销',
  `purchase_number` varchar(50) NOT NULL DEFAULT '' COMMENT '原单编号',
  `business_type` tinyint(3) NOT NULL DEFAULT '0' COMMENT '类型:1=预收冲应收,2=预付冲应付,3=应收冲应付,4=应收转应收,5=应付转应付',
  `times` int(11) NOT NULL DEFAULT '0' COMMENT '单据日期',
  `money` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '单据金额',
  `write_off` double(15,4) DEFAULT NULL COMMENT '已核销金额',
  `write_off_money` double(15,4) DEFAULT NULL COMMENT '未核销金额',
  `write_off_price` double(15,4) DEFAULT '0.0000' COMMENT '本次核销金额',
  `old_money` double(15,4) DEFAULT NULL,
  `old_status` int(11) DEFAULT NULL,
  `top` enum('1','2') DEFAULT '1' COMMENT '位置:1=上,2=下',
  `source_id` int(11) DEFAULT NULL COMMENT '源单ID',
  `business_name` varchar(255) DEFAULT NULL COMMENT '单据名称',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='核销单据信息';

-- ----------------------------
-- Table structure for fa_kuerp_writeoff_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_writeoff_log`;
CREATE TABLE `fa_kuerp_writeoff_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL COMMENT '源单ID',
  `write_off` double(15,4) DEFAULT NULL COMMENT '未核销金额',
  `money` double(15,4) DEFAULT NULL COMMENT '已核销金额',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='核销单据信息';

-- ----------------------------
-- Table structure for fa_kuerp_writeoff_source
-- ----------------------------
DROP TABLE IF EXISTS `fa_kuerp_writeoff_source`;
CREATE TABLE `fa_kuerp_writeoff_source` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `purchase_type` enum('1','2','3','4','5','6') DEFAULT NULL COMMENT '单据类型:1=预收单,2=预付单,3=应收,4=应付,5=期初应收/付款,6=期初预收/付款',
  `type` enum('1','2','3','4') DEFAULT NULL COMMENT '类型:1=购货/销货,2=退货,3=期初,4=核销',
  `suppliers_code` varchar(50) DEFAULT NULL COMMENT '供应商编号',
  `customers_id` int(11) DEFAULT NULL COMMENT '客户Id',
  `purchase_code` varchar(50) NOT NULL DEFAULT '' COMMENT '原单编号',
  `purchase_time` bigint(16) DEFAULT NULL,
  `price` double(15,4) DEFAULT '0.0000' COMMENT '单据金额',
  `money` double(15,4) DEFAULT '0.0000' COMMENT '已核销金额',
  `write_off` double(15,4) DEFAULT '0.0000' COMMENT '未核销金额',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COMMENT='核销单源单';

-- ----------------------------
-- Table structure for fa_sms
-- ----------------------------
DROP TABLE IF EXISTS `fa_sms`;
CREATE TABLE `fa_sms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `event` varchar(30) DEFAULT '' COMMENT '事件',
  `mobile` varchar(20) DEFAULT '' COMMENT '手机号',
  `code` varchar(10) DEFAULT '' COMMENT '验证码',
  `times` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '验证次数',
  `ip` varchar(30) DEFAULT '' COMMENT 'IP',
  `createtime` bigint(16) unsigned DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='短信验证码表';

-- ----------------------------
-- Table structure for fa_test
-- ----------------------------
DROP TABLE IF EXISTS `fa_test`;
CREATE TABLE `fa_test` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(10) DEFAULT '0' COMMENT '会员ID',
  `admin_id` int(10) DEFAULT '0' COMMENT '管理员ID',
  `category_id` int(10) unsigned DEFAULT '0' COMMENT '分类ID(单选)',
  `category_ids` varchar(100) DEFAULT NULL COMMENT '分类ID(多选)',
  `tags` varchar(255) DEFAULT '' COMMENT '标签',
  `week` enum('monday','tuesday','wednesday') DEFAULT NULL COMMENT '星期(单选):monday=星期一,tuesday=星期二,wednesday=星期三',
  `flag` set('hot','index','recommend') DEFAULT '' COMMENT '标志(多选):hot=热门,index=首页,recommend=推荐',
  `genderdata` enum('male','female') DEFAULT 'male' COMMENT '性别(单选):male=男,female=女',
  `hobbydata` set('music','reading','swimming') DEFAULT NULL COMMENT '爱好(多选):music=音乐,reading=读书,swimming=游泳',
  `title` varchar(100) DEFAULT '' COMMENT '标题',
  `content` text COMMENT '内容',
  `image` varchar(100) DEFAULT '' COMMENT '图片',
  `images` varchar(1500) DEFAULT '' COMMENT '图片组',
  `attachfile` varchar(100) DEFAULT '' COMMENT '附件',
  `keywords` varchar(255) DEFAULT '' COMMENT '关键字',
  `description` varchar(255) DEFAULT '' COMMENT '描述',
  `city` varchar(100) DEFAULT '' COMMENT '省市',
  `array` varchar(255) DEFAULT '' COMMENT '数组:value=值',
  `json` varchar(255) DEFAULT '' COMMENT '配置:key=名称,value=值',
  `multiplejson` varchar(1500) DEFAULT '' COMMENT '二维数组:title=标题,intro=介绍,author=作者,age=年龄',
  `price` decimal(10,2) unsigned DEFAULT '0.00' COMMENT '价格',
  `views` int(10) unsigned DEFAULT '0' COMMENT '点击',
  `workrange` varchar(100) DEFAULT '' COMMENT '时间区间',
  `startdate` date DEFAULT NULL COMMENT '开始日期',
  `activitytime` datetime DEFAULT NULL COMMENT '活动时间(datetime)',
  `year` year(4) DEFAULT NULL COMMENT '年',
  `times` time DEFAULT NULL COMMENT '时间',
  `refreshtime` bigint(16) DEFAULT NULL COMMENT '刷新时间',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  `deletetime` bigint(16) DEFAULT NULL COMMENT '删除时间',
  `weigh` int(10) DEFAULT '0' COMMENT '权重',
  `switch` tinyint(1) DEFAULT '0' COMMENT '开关',
  `status` enum('normal','hidden') DEFAULT 'normal' COMMENT '状态',
  `state` enum('0','1','2') DEFAULT '1' COMMENT '状态值:0=禁用,1=正常,2=推荐',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='测试表';

-- ----------------------------
-- Table structure for fa_third
-- ----------------------------
DROP TABLE IF EXISTS `fa_third`;
CREATE TABLE `fa_third` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int(10) unsigned DEFAULT '0' COMMENT '会员ID',
  `platform` varchar(30) DEFAULT '' COMMENT '第三方应用',
  `apptype` varchar(50) DEFAULT '' COMMENT '应用类型',
  `unionid` varchar(100) DEFAULT '' COMMENT '第三方UNIONID',
  `openname` varchar(100) NOT NULL DEFAULT '' COMMENT '第三方会员昵称',
  `openid` varchar(100) DEFAULT '' COMMENT '第三方OPENID',
  `access_token` varchar(255) DEFAULT '' COMMENT 'AccessToken',
  `refresh_token` varchar(255) DEFAULT 'RefreshToken',
  `expires_in` int(10) unsigned DEFAULT '0' COMMENT '有效期',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  `logintime` bigint(16) DEFAULT NULL COMMENT '登录时间',
  `expiretime` bigint(16) DEFAULT NULL COMMENT '过期时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `platform` (`platform`,`openid`),
  KEY `user_id` (`user_id`,`platform`),
  KEY `unionid` (`platform`,`unionid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='第三方登录表';

-- ----------------------------
-- Table structure for fa_user
-- ----------------------------
DROP TABLE IF EXISTS `fa_user`;
CREATE TABLE `fa_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `group_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '组别ID',
  `username` varchar(32) DEFAULT '' COMMENT '用户名',
  `nickname` varchar(50) DEFAULT '' COMMENT '昵称',
  `password` varchar(32) DEFAULT '' COMMENT '密码',
  `salt` varchar(30) DEFAULT '' COMMENT '密码盐',
  `email` varchar(100) DEFAULT '' COMMENT '电子邮箱',
  `mobile` varchar(11) DEFAULT '' COMMENT '手机号',
  `avatar` varchar(255) DEFAULT '' COMMENT '头像',
  `level` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '等级',
  `gender` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '性别',
  `birthday` date DEFAULT NULL COMMENT '生日',
  `bio` varchar(100) DEFAULT '' COMMENT '格言',
  `money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '余额',
  `score` int(10) NOT NULL DEFAULT '0' COMMENT '积分',
  `successions` int(10) unsigned NOT NULL DEFAULT '1' COMMENT '连续登录天数',
  `maxsuccessions` int(10) unsigned NOT NULL DEFAULT '1' COMMENT '最大连续登录天数',
  `prevtime` bigint(16) DEFAULT NULL COMMENT '上次登录时间',
  `logintime` bigint(16) DEFAULT NULL COMMENT '登录时间',
  `loginip` varchar(50) DEFAULT '' COMMENT '登录IP',
  `loginfailure` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '失败次数',
  `loginfailuretime` bigint(16) DEFAULT NULL COMMENT '最后登录失败时间',
  `joinip` varchar(50) DEFAULT '' COMMENT '加入IP',
  `jointime` bigint(16) DEFAULT NULL COMMENT '加入时间',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  `token` varchar(50) DEFAULT '' COMMENT 'Token',
  `status` varchar(30) DEFAULT '' COMMENT '状态',
  `verification` varchar(255) DEFAULT '' COMMENT '验证',
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `email` (`email`),
  KEY `mobile` (`mobile`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COMMENT='会员表';

-- ----------------------------
-- Table structure for fa_user_group
-- ----------------------------
DROP TABLE IF EXISTS `fa_user_group`;
CREATE TABLE `fa_user_group` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT '' COMMENT '组名',
  `rules` text COMMENT '权限节点',
  `createtime` bigint(16) DEFAULT NULL COMMENT '添加时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  `status` enum('normal','hidden') DEFAULT NULL COMMENT '状态',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='会员组表';

-- ----------------------------
-- Table structure for fa_user_money_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_user_money_log`;
CREATE TABLE `fa_user_money_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '会员ID',
  `money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '变更余额',
  `before` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '变更前余额',
  `after` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '变更后余额',
  `memo` varchar(255) DEFAULT '' COMMENT '备注',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员余额变动表';

-- ----------------------------
-- Table structure for fa_user_rule
-- ----------------------------
DROP TABLE IF EXISTS `fa_user_rule`;
CREATE TABLE `fa_user_rule` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(10) DEFAULT NULL COMMENT '父ID',
  `name` varchar(50) DEFAULT NULL COMMENT '名称',
  `title` varchar(50) DEFAULT '' COMMENT '标题',
  `remark` varchar(100) DEFAULT NULL COMMENT '备注',
  `ismenu` tinyint(1) DEFAULT NULL COMMENT '是否菜单',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  `weigh` int(10) DEFAULT '0' COMMENT '权重',
  `status` enum('normal','hidden') DEFAULT NULL COMMENT '状态',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COMMENT='会员规则表';

-- ----------------------------
-- Table structure for fa_user_score_log
-- ----------------------------
DROP TABLE IF EXISTS `fa_user_score_log`;
CREATE TABLE `fa_user_score_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '会员ID',
  `score` int(10) NOT NULL DEFAULT '0' COMMENT '变更积分',
  `before` int(10) NOT NULL DEFAULT '0' COMMENT '变更前积分',
  `after` int(10) NOT NULL DEFAULT '0' COMMENT '变更后积分',
  `memo` varchar(255) DEFAULT '' COMMENT '备注',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员积分变动表';

-- ----------------------------
-- Table structure for fa_user_token
-- ----------------------------
DROP TABLE IF EXISTS `fa_user_token`;
CREATE TABLE `fa_user_token` (
  `token` varchar(50) NOT NULL COMMENT 'Token',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '会员ID',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `expiretime` bigint(16) DEFAULT NULL COMMENT '过期时间',
  PRIMARY KEY (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员Token表';

-- ----------------------------
-- Table structure for fa_version
-- ----------------------------
DROP TABLE IF EXISTS `fa_version`;
CREATE TABLE `fa_version` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `oldversion` varchar(30) DEFAULT '' COMMENT '旧版本号',
  `newversion` varchar(30) DEFAULT '' COMMENT '新版本号',
  `packagesize` varchar(30) DEFAULT '' COMMENT '包大小',
  `content` varchar(500) DEFAULT '' COMMENT '升级内容',
  `downloadurl` varchar(255) DEFAULT '' COMMENT '下载地址',
  `enforce` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '强制更新',
  `createtime` bigint(16) DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint(16) DEFAULT NULL COMMENT '更新时间',
  `weigh` int(10) NOT NULL DEFAULT '0' COMMENT '权重',
  `status` varchar(30) DEFAULT '' COMMENT '状态',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='版本表';

SET FOREIGN_KEY_CHECKS = 1;
