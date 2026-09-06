-- ============================================
-- 供应商联系人手机号字段加宽：varchar(11) -> varchar(30)
-- 背景: 供应商导入模板中"手机"列可填写区号固话(如0574-88087205)
--       甚至多个号码(如0574-62592167、62592468)，11位无法容纳
-- 执行方式: 在MySQL中执行以下SQL
-- ============================================

ALTER TABLE `fa_kuerp_suppliers`
MODIFY COLUMN `first_connect_mobile` varchar(30) NOT NULL COMMENT '手机';

ALTER TABLE `fa_kuerp_suppliers_user`
MODIFY COLUMN `first_connect_mobile` varchar(30) NOT NULL COMMENT '手机';
