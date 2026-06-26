-- 仓库表: 新增仓库类型字段，区分零件仓和普通仓
-- BOM计算时只统计零件仓的库存数量
ALTER TABLE fa_kuerp_stores ADD COLUMN store_type ENUM('part','general') DEFAULT 'general' COMMENT '仓库类型:part=零件仓,general=普通仓' AFTER status;
