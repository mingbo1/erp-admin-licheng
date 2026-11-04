# 版本与部署记录

> 线上站点：`118.31.20.155`（后台入口 `/eXvpGbzZYD.php`，库 `dyerp_shengbokj_`）
> 部署方式：无 SSH，代码走 FTP（`118.31.20.155:39683` / `dyerp_dayan` / FTP 根 = 项目根）
> 数据库：`mysql -h118.31.20.155 -udyerp_shengbokj_ -D dyerp_shengbokj_`

**当前版本：`1.6.26`（2026-11-04）**

版本号的唯一来源是 `addons/kuerp/info.ini` 的 `version`；每次上线递增末位，
并在下表的「版本清单」里追加一行。线上根目录的 `DEPLOY_VERSION.txt` 记录线上实际部署的版本与文件清单。

---

## 一、版本清单

| 版本 | 部署日期 | 内容摘要 | 新增/执行的迁移 | 备注 |
|------|---------|---------|----------------|------|
| 1.6.26 | 2026-11-04 | 成品入库成本计算（组装单/外协加工单）、出库管理审核出库校验库存、计划排期审核补建核销源单、付款申请详情[查看明细]、采购单列表按采购订单分组排序、商品/组装单/入库单界面细节 | `migration_add_payment_apply_recon_detail_auth.sql`（执行，幂等） | 33 个文件；数据库**无结构变更**（schema 与线上逐列一致） |
| 1.6.25 | 2026-11-03 | 销货预订单仓库兜底 + 负库存出库、销货对账开票扣减差异、未付款按月合计、采购预订单调用BOM、组装单默认仓库 | `migration_allow_negative_stock.sql`（执行） | 45 个文件；`fa_kuerp_goods_stock.stock` 改为有符号 |
| 1.6.24 | 2026-10-31 | 申请出库填写快递信息 + 送货单打印、销货订单发货按钮隐藏 | `migration_add_outbound_express.sql` | |

---

## 二、部署流程（每次上线照做）

1. **比对**：FTP 递归列出远端 `size/mtime`，与本地同名文件逐一对齐；size 不同再 `RETR` 算 md5 确认。
   只传真正不同的文件，并在 `DEPLOY_VERSION.txt` 里登记。
2. **备份**：远端被覆盖的文件先 `RETR` 下到 `~/erp-deploy-backup/<日期>/remote/`；
   数据库结构 `mysqldump --no-data --single-transaction` 存 `~/erp-db-backup/<日期>/`。
3. **上传**：
   - 普通文件：`STOR <目标>.tmpnew` → 校验 md5 → `RNFR <目标>` → `RNTO <目标>.deploybak` → `RNFR tmpnew` → `RNTO <目标>` → 删备份。
   - ⚠️ **目标是新文件（远端不存在）时直接 `STOR` 目标路径**，绝不能走「改名备份目录」的兜底流程
     （2026-11-04 踩过：把 `/addons/kuerp` 整个目录改名后重建，导致 controller/library/model/uniapp 全部消失，需手工回滚）。
   - ⚠️ 属主 `uid 501` 的目录不能覆盖（`553 Permission denied`）：`RNFR 目录 → 目录__deploybak_时间戳` →
     `MKD 原目录` → 把该目录**全部文件（含子目录）**重新 `STOR`。
4. **数据库**：先比对 `mysqldump --no-data`（忽略 `AUTO_INCREMENT`）确认结构差异；有差异才跑迁移。
   迁移脚本里的 `__PREFIX__` 需 `sed 's/__PREFIX__/fa_/g'` 后执行，且要求**可重复执行**。
   角色权限只能按 `name` **增量追加**（`FIND_IN_SET` 判重），不能按本地覆盖（线上有角色独有权限）。
5. **清缓存**：递归删除 `runtime/cache/*` 与 `runtime/temp/*`（菜单、编译模板），否则新菜单/模板不生效。
6. **验证**：登录页 200、几个 kuerp 列表页 200；用户端需强刷（Ctrl/Cmd+Shift+R），
   nginx 对 `/assets/` 有 12 小时缓存，JS 改动最好带版本号。
7. **记录**：递增 `info.ini` 版本号、更新本文件版本清单、更新 `DEPLOY_VERSION.txt`、提交 git 并推送。

---

## 三、固定不部署的文件（线上与本地的差异是有意的）

| 文件 | 原因 |
|------|------|
| `application/extra/addons.php` | 线上注册了 `cms` 插件（钩子/路由），本地没有；覆盖会禁用 CMS |
| `application/extra/site.php` | 线上站点名/备案号/版本与本地占位值不同 |
| `public/assets/js/backend/kuerp/goods_log.js` | 线上版本更新（含「基本单位数量」两列），覆盖会退回旧功能 |
| `application/config.php` | 本地 `login_captcha=false` 是开发便利，线上保持验证码开启 |
| `.env` / `.env.sample` | 含线上数据库与 FTP 凭据，**不进版本库** |

---

## 四、新功能上线后需要人工处理的事项

- 新菜单/新权限规则：超管天然可见，其他角色需在「角色管理」手工勾选（项目惯例）。
- 新增定时任务（如 `kuerp:ar_analysis`、`kuerp:stock_summary`）：需在服务器 crontab 配置；
  CLI 必须用 PHP 8.0（默认 `php` 8.4 会让 TP5 的 console 触发 Deprecated → Fatal）。
- 新单号前缀 / 新设置项写在 `fa_kuerp_prefix`、`fa_kuerp_sets`（迁移里已带，执行后到设置页确认）。
