SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================
-- 微信小程序端 (订货通) 数据库脚本
-- 适用: 酷柚易汛进销存插件 v2.x 扩展
-- 说明: 在已有的 install.sql 基础上追加小程序相关表
-- ============================================================

-- ============================================================
-- 1. 扩展已有表: fa_kuerp_mini_user — 增加 customer_id 字段
-- ============================================================
ALTER TABLE `__PREFIX__kuerp_mini_user`
    ADD COLUMN `customer_id` INT(11) NOT NULL DEFAULT 0 COMMENT '关联fa_kuerp_customers.id(登录时自动创建)' AFTER `user_id`,
    ADD KEY `idx_customer` (`customer_id`);

-- ============================================================
-- 2. 扩展已有表: fa_kuerp_mini_cart — 增加属性和单位支持
-- ============================================================
ALTER TABLE `__PREFIX__kuerp_mini_cart`
    ADD COLUMN `attr_ids`   VARCHAR(100) NOT NULL DEFAULT '' COMMENT '所选属性组合ID(多属性商品,格式:1&2)' AFTER `goods_id`,
    ADD COLUMN `attr_name`  VARCHAR(255) NOT NULL DEFAULT '' COMMENT '属性描述(快照,如"红色/XL")' AFTER `attr_ids`,
    ADD COLUMN `goods_unit` BIGINT(20) NOT NULL DEFAULT 0 COMMENT '所选单位ID' AFTER `attr_name`,
    ADD COLUMN `unit_name`  VARCHAR(50) NOT NULL DEFAULT '' COMMENT '单位名称(快照)' AFTER `goods_unit`,
    DROP INDEX `uk_user_goods`,
    ADD UNIQUE KEY `uk_user_goods_attr` (`user_id`, `goods_id`, `attr_ids`, `goods_unit`);

-- ============================================================
-- 3. 新表: fa_kuerp_mini_order — 订单主表
-- ============================================================
CREATE TABLE IF NOT EXISTS `__PREFIX__kuerp_mini_order` (
    `id`                 BIGINT PRIMARY KEY AUTO_INCREMENT,
    `order_no`           VARCHAR(50)     NOT NULL COMMENT '订单号(kuerp_prefix编号规则)',

    `user_id`            BIGINT          NOT NULL COMMENT '用户ID(fa_kuerp_mini_user.id)',
    `customer_id`        INT(11)         NOT NULL DEFAULT 0 COMMENT '关联fa_kuerp_customers.id',

    `status`             VARCHAR(30)     NOT NULL DEFAULT 'pending_confirm' COMMENT '订单状态',
    `total_amount`       DECIMAL(12, 2)  NOT NULL DEFAULT 0.00 COMMENT '订单总金额',
    `preferential`       DECIMAL(12, 2)  NOT NULL DEFAULT 0.00 COMMENT '优惠金额',
    `payable_amount`     DECIMAL(12, 2)  NOT NULL DEFAULT 0.00 COMMENT '应付金额(=total-preferential)',
    `remark`             VARCHAR(500)    NOT NULL DEFAULT '' COMMENT '用户备注',
    `admin_remark`       VARCHAR(500)    NOT NULL DEFAULT '' COMMENT '管理员备注',

    -- 收货信息（快照，地址变更不影响已有订单）
    `receiver_name`      VARCHAR(50)     NOT NULL COMMENT '收货人姓名',
    `receiver_mobile`    VARCHAR(20)     NOT NULL COMMENT '收货人电话',
    `receiver_province`  VARCHAR(50)     NOT NULL DEFAULT '' COMMENT '省',
    `receiver_city`      VARCHAR(50)     NOT NULL DEFAULT '' COMMENT '市',
    `receiver_district`  VARCHAR(50)     NOT NULL DEFAULT '' COMMENT '区/县',
    `receiver_address`   VARCHAR(300)    NOT NULL DEFAULT '' COMMENT '详细地址(街道/门牌号)',
    `receiver_zip`       VARCHAR(10)     NOT NULL DEFAULT '' COMMENT '邮编',

    -- 配送方式
    `delivery_type`      VARCHAR(30)     NOT NULL DEFAULT 'logistics' COMMENT '配送方式: logistics=物流配送, professional=专业配送',

    -- 后台操作
    `admin_id`           INT(11)         NOT NULL DEFAULT 0 COMMENT '处理管理员ID',
    `confirm_time`       BIGINT(16)      NOT NULL DEFAULT 0 COMMENT '确认时间',
    `payment_time`       BIGINT(16)      NOT NULL DEFAULT 0 COMMENT '对单/付款时间(即销货单对单时间)',
    `sales_code`         VARCHAR(50)     NOT NULL DEFAULT '' COMMENT '关联销货单号(确认时生成)',
    `complete_time`      BIGINT(16)      NOT NULL DEFAULT 0 COMMENT '完成时间',

    `createdtime`        BIGINT(16)      NOT NULL DEFAULT 0,
    `updatetime`         BIGINT(16)      NOT NULL DEFAULT 0,
    `deletedtime`        BIGINT(16)      DEFAULT NULL,

    UNIQUE KEY `uk_order_no` (`order_no`),
    KEY `idx_user` (`user_id`),
    KEY `idx_customer` (`customer_id`),
    KEY `idx_status` (`status`),
    KEY `idx_sales_code` (`sales_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='小程序订单主表(订货通)';

-- ============================================================
-- 4. 新表: fa_kuerp_mini_order_goods — 订单商品明细表
-- ============================================================
CREATE TABLE IF NOT EXISTS `__PREFIX__kuerp_mini_order_goods` (
    `id`              BIGINT PRIMARY KEY AUTO_INCREMENT,
    `order_id`        BIGINT          NOT NULL COMMENT '订单ID(fa_kuerp_mini_order.id)',
    `goods_id`        BIGINT          NOT NULL COMMENT '商品ID(fa_kuerp_goods)',
    `goods_code`      VARCHAR(100)    NOT NULL DEFAULT '' COMMENT '商品编码(快照)',
    `goods_name`      VARCHAR(255)    NOT NULL DEFAULT '' COMMENT '商品名称(快照)',
    `goods_image`     VARCHAR(500)    NOT NULL DEFAULT '' COMMENT '商品图片(快照)',
    `goods_unit`      BIGINT          NOT NULL DEFAULT 0 COMMENT '单位ID',
    `unit_name`       VARCHAR(50)     NOT NULL DEFAULT '' COMMENT '单位名称(快照)',
    `attr_ids`        VARCHAR(100)    NOT NULL DEFAULT '' COMMENT '属性组合ID',
    `attr_name`       VARCHAR(255)    NOT NULL DEFAULT '' COMMENT '属性描述(快照,如"红色/XL")',
    `quantity`        DECIMAL(12, 2)  NOT NULL DEFAULT 0.00 COMMENT '数量',
    `unit_price`      DECIMAL(12, 2)  NOT NULL DEFAULT 0.00 COMMENT '单价(快照)',
    `all_money`       DECIMAL(12, 2)  NOT NULL DEFAULT 0.00 COMMENT '金额小计(=quantity*unit_price)',
    `createdtime`     BIGINT(16)      NOT NULL DEFAULT 0,

    KEY `idx_order` (`order_id`),
    KEY `idx_goods` (`goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单商品明细表(订货通)';

-- ============================================================
-- 5. 新表: fa_kuerp_mini_delivery — 发货记录表
-- ============================================================
CREATE TABLE IF NOT EXISTS `__PREFIX__kuerp_mini_delivery` (
    `id`               BIGINT PRIMARY KEY AUTO_INCREMENT,
    `order_id`         BIGINT          NOT NULL COMMENT '订单ID(fa_kuerp_mini_order.id)',
    `delivery_no`      VARCHAR(50)     NOT NULL COMMENT '发货单号',
    `delivery_type`    VARCHAR(30)     NOT NULL COMMENT '配送方式: logistics=物流配送, professional=专业配送',

    -- 物流配送
    `express_company`  VARCHAR(100)    NOT NULL DEFAULT '' COMMENT '快递公司名称',
    `express_code`     VARCHAR(50)     NOT NULL DEFAULT '' COMMENT '快递公司编码(如yunda,shentong)',
    `express_no`       VARCHAR(100)    NOT NULL DEFAULT '' COMMENT '快递单号',

    -- 专业配送
    `carrier_name`     VARCHAR(100)    NOT NULL DEFAULT '' COMMENT '承运方/配送员',
    `carrier_mobile`   VARCHAR(20)     NOT NULL DEFAULT '' COMMENT '配送员电话',

    `status`           VARCHAR(30)     NOT NULL DEFAULT 'shipped' COMMENT '发货状态: shipped=已发货, received=已签收',
    `shipped_time`     BIGINT(16)      NOT NULL DEFAULT 0 COMMENT '发货时间',
    `received_time`    BIGINT(16)      NOT NULL DEFAULT 0 COMMENT '签收时间',
    `admin_id`         INT(11)         NOT NULL DEFAULT 0 COMMENT '发货管理员ID',
    `remark`           VARCHAR(500)    NOT NULL DEFAULT '' COMMENT '发货备注',
    `createdtime`      BIGINT(16)      NOT NULL DEFAULT 0,
    `updatetime`       BIGINT(16)      NOT NULL DEFAULT 0,

    UNIQUE KEY `uk_delivery_no` (`delivery_no`),
    KEY `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='发货记录表(物流关联到订单级别)';

-- ============================================================
-- 6. 新表: fa_kuerp_mini_banner — 轮播图表
-- ============================================================
CREATE TABLE IF NOT EXISTS `__PREFIX__kuerp_mini_banner` (
    `id`            INT(11)         NOT NULL AUTO_INCREMENT,
    `title`         VARCHAR(100)    NOT NULL DEFAULT '' COMMENT '标题(管理用)',
    `image`         VARCHAR(500)    NOT NULL DEFAULT '' COMMENT '轮播图URL',
    `link_type`     VARCHAR(30)     NOT NULL DEFAULT 'page' COMMENT '跳转类型: page=小程序页面, none=无跳转',
    `link_url`      VARCHAR(500)    NOT NULL DEFAULT '' COMMENT '跳转链接(小程序页面路径)',
    `sort`          INT(11)         NOT NULL DEFAULT 0 COMMENT '排序(数字越小越靠前)',
    `status`        VARCHAR(20)     NOT NULL DEFAULT 'normal' COMMENT '状态: normal=启用, hidden=禁用',
    `createtime`    BIGINT(16)      NOT NULL DEFAULT 0,
    `updatetime`    BIGINT(16)      NOT NULL DEFAULT 0,
    `deletetime`    BIGINT(16)      DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='小程序轮播图(订货通)';

-- ============================================================
-- 7. 新表: fa_kuerp_mini_config — 系统配置表
-- ============================================================
CREATE TABLE IF NOT EXISTS `__PREFIX__kuerp_mini_config` (
    `id`            INT(11)         NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(100)    NOT NULL COMMENT '配置名称(唯一标识)',
    `value`         TEXT            NOT NULL COMMENT '配置值',
    `description`   VARCHAR(255)    NOT NULL DEFAULT '' COMMENT '配置说明',
    `createtime`    BIGINT(16)      NOT NULL DEFAULT 0,
    `updatetime`    BIGINT(16)      NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='小程序系统配置(订货通)';

-- ============================================================
-- 8. 初始配置数据
-- ============================================================
INSERT INTO `__PREFIX__kuerp_mini_config` (`name`, `value`, `description`) VALUES
('price_level',         'market_sell_price', '商品展示价格: market_sell_price=零售价, wholesale_price=批发价, vip_price=VIP价'),
('delivery_fee',        '0',                 '配送费(默认0=免运费)'),
('service_phone',       '',                  '客服电话'),
('order_number_prefix', 'DD',                '订单编号前缀'),
('home_search_enabled', '1',                 '首页是否启用搜索: 1=启用, 0=禁用');

COMMIT;
