# 版本与部署记录

> 线上站点：`118.31.20.155`（后台入口 `/eXvpGbzZYD.php`，库 `dyerp_shengbokj_`）
> 部署方式：无 SSH，代码走 FTP（`118.31.20.155:39683` / `dyerp_dayan` / FTP 根 = 项目根）
> 数据库：`mysql -h118.31.20.155 -udyerp_shengbokj_ -D dyerp_shengbokj_`

**当前版本：`1.6.44`（2026-11-09）**

版本号的唯一来源是 `addons/kuerp/info.ini` 的 `version`；每次上线递增末位，
并在下表的「版本清单」里追加一行。线上根目录的 `DEPLOY_VERSION.txt` 记录线上实际部署的版本与文件清单。

---

## 一、版本清单

| 版本 | 部署日期 | 内容摘要 | 新增/执行的迁移 | 备注 |
|------|---------|---------|----------------|------|
| 1.6.44 | 2026-11-09 | ①**销货退货单：仓库「退货入库」** —— 退货单审核只做账务（不再加库存/成本/收发明细/序列号/批次），改由销货单出库列表行 [退货入库] 弹窗按本次数量分批入库（全部入完按钮消失）；已有入库记录时禁止反审核/删除退货单；出库编辑页新增「出库记录 / 退货入库记录」面板。②同批上线 11-08 未上线项：**销货单打印改为送货单版式**（价格列默认隐藏）、生成退货单明细补 型号/规格2/规格3、**纯退货/净额为负的对账单收款状态不再误判「已收款」**、分类列表缩略图、组装单排期生产备注带入 | `migration_add_goods_return_inbound.sql`（`goods_sales_exchange_info.out_number` 新增并回填已审核退货单=retreat_number、新表 `kuerp_goods_return_inbound_log`、规则 `goods_sales_outbound/returninbound` 增量授予已有 index 的角色） | 36 文件（25 改 + 11 新增）；**`site.version` 1.0.13 → 1.0.14**；线上实测：out_number 回填 2 行（2/10）、新表已建、规则 808 落库；登录页+8 个 kuerp 页面 200；清 `runtime/cache` 3 + `runtime/temp` 40 |
| 1.6.43 | 2026-11-09 | **排产管理**（`kuerp/production_manage`）：计划排期的**只读副本**，只显示已排产（`production_status=1`）的销货单；工具栏只有 刷新/导出，行操作只有 查看详情（add/edit/del/排产/取消排产/锁定/改期 后端一律拦截）；列表与计划排期同源，详情页服务端渲染（含型号/规格/属性/单位/仓库/品号与功率/生产备注与合计） | `migration_add_production_manage.sql`（菜单 + 查看/查看详情/导出 三条规则，增量授予已有计划排期权限的角色） | 新增 1 控制器 + 2 视图 + 1 JS + 1 lang + 迁移；改 `Kuerp.php`($menu) 与 `GoodsSalesSchedule.php`（`applyIndexExtraWhere` / `exportDocName` 钩子、`appendDeliveryModeText` 改 protected）；线上授予 8 个角色 |
| 1.6.42 | 2026-11-09 | **计划排期「审核/反审核」下线**：工具栏两个按钮、后端 `GoodsSalesSchedule::examine()` 方法、权限规则一并移除（审核改由销货单列表完成）；原按钮位置新增 **[预计生产完成时间]**，勾选 1~n 条后弹窗填日期，统一写入所选行（复用 `setExpectedDelivery`，行内编辑保留） | `migration_remove_sales_schedule_examine.sql`（删除规则 `kuerp/goods_sales_schedule/examine` 并从各角色 rules 串摘掉该 id） | 3 改 + 1 新增；线上规则 697 已删 |
| 1.6.41 | 2026-11-08 | 采购对账两处修复：①**购货退货单冲减数量取错字段** —— `kuerp_goods_exchange.number` 是原采购数量（如 10×4=40），实际退货数在 `retreat_number`（如 4），对账明细/单据列表/对账单 `total_number` 全部按 `retreat_number` 计算，且商品行只列「实际退货数量>0」的明细（原先 4 个 BOM 行各显示 −10）；②**对账单「+ 上传对账单」一直失败** —— `detail.html` 的 `{:url('kuerp/purchase_recon/uploadfile/ids/' . $id)}` 被 TP5 当 module/controller/action 三段解析，丢掉 `kuerp/purchase_recon` 前缀，请求打到 `/uploadfile/ids/5` → 404 → JS 走 error 分支提示「上传失败，请重试」；改为三段 url + JS 拼 `/ids/<id>` | `migration_fix_purchase_recon_return_number.sql`（数据修正，可重复执行；已对线上执行，DZD20261108001 的退货行 −40 → −4、`total_number` 0 → 36） | 3 改 + 1 新增 + info.ini；无 DDL、无新权限；**`site.version` 1.0.12 → 1.0.13**（线上 `.env` 的 `app.debug=false`，require 的 `urlArgs` 取 `site.version`，不递增浏览器会用 12h 缓存的旧 JS）；线上实测：详情页退货行 −4/−6.80、上传按钮 200 并落到「对账单原始单据」 |
| 1.6.40 | 2026-11-07 | 采购单打印页（`goods_purchase/printpage`）**移除底部「订货说明」1~5 条**（交货方式/收货地址/付款方式/质量保证/合同声明），明细后直接接签字栏；其中第 2、3 条原由设置项提供的两个配置项 `purchase_print_receipt` / `purchase_print_payment` 一并下线，设置 → 基础设置 不再出现 | `migration_remove_purchase_print_terms.sql`（DELETE 两个设置项，可重复执行；远端旧 `migration_add_purchase_print_terms.sql` 已删除） | 5 上传 + 1 删除；无 DDL；`site.version` 不改（无 JS/CSS）；远端 `fa_kuerp_sets` 两行已删（COUNT=0）；清 `runtime/cache` 3 项 + `runtime/temp` 47 项 |
| 1.6.39 | 2026-11-06 | 采购单 / 采购预订单 / 采购预订单(计划) / 采购计划 四个模块**添加+编辑页**明细表：「型号 / 规格2-厂商 / 规格3-封装」三列加宽（min-width 160/140/120）并改为可折行显示（原为 100% 宽只读 input，超长被截断），数量/单价/税额等数字列收窄（64px，税率 52px）；JS 新增 `renderSpecText()` 在选商品/换属性/换单位/换仓库后同步文本，并新增 `initSpecText()` 在打开编辑页时按商品档案回填三列（此前这三个编辑页三列恒为空） | 无（未做任何 DDL） | 12 文件（8 视图 + 4 JS）；`site.version` 不用改——线上 `app_debug` 开启、`urlArgs=time()`，`/assets/` 12h 缓存天然绕过；`goods_purchase*.js` 三份与 `goods_purchase/{add,edit}.html` 是 CRLF，按字节改写保留行尾 |
| 1.6.38 | 2026-11-06 | 外协加工单 / 外协订单**详情页**（`process/detail.html`、`process_order/detail.html`）的「出库原材料」「入库成品」两表在商品名称后新增 **型号 / 规格2-厂商 / 规格3-封装** 三列（取 `kuerp_goods` 的 `goods_specification_id`/`vendor_name`/`packaging`）；顺带修复这两页两列常年显示 `-`：`goods_unit_name` 从未查询赋值、`goods_attr_name` 取 `kuerp_goods_attr.attr_code`；原表头写作「型号」实为辅助属性名，已更名为「属性」 | 无（未做任何 DDL） | 4 文件（2 控制器 + 2 视图）；纯服务端渲染、无 JS/CSS，`site.version` 不用改；线上 4 个文件逐个 md5 校验通过、无 `.tmpnew`/`.deploybak` 残留；`info.ini` 线上原为 1.6.36（1.6.37 分类缩略图那批只传了 12 个前端文件、漏了 info.ini），本次一并升到 1.6.38 |
| 1.6.37 | 2026-11-06 | 6 个分类列表（商品分类/供应商分类/客户分类/支出类别/收入类别/结算方式）图片列统一 40×40 缩略图（`Controller.api.imageCell()` + 各视图 `.cat-thumb` 样式），空图输出同尺寸占位；`table.render` 加 `done` 回调逐行同步固定列行高；点缩略图 `Layer.photos` 看原图。修复「分类图片过大 → 行高不一致 → 左侧类别名称与右侧字段错位」 | 无 | 12 个文件（6 JS + 6 视图）；`site.version` **不用改**——线上 `app_debug` 开启，`urlArgs` 是 `time()`（每请求唯一，实测 `backend.css?v=` 每次不同），`/assets/` 的 12h 缓存天然被绕过；线上清空 `runtime/cache`+`runtime/temp` 34 个文件；线上逐个 md5 校验通过、无 `.tmpnew`/`.deploybak` 残留 |
| 1.6.36 | 2026-11-06 | 销货排期（`goods_sales_schedule`）编辑页「型号」列空白：数据行的 `.goods_specification_id` 只读框模板里没有 `value`，只有选商品/改单位/改仓库/改属性时由 JS 回填 → 打开编辑页时历史行型号恒为空（后端 `edit()` 本就 assign 了 `unit.goods.goods_specification_id`）。`edit.html` 数据行补 `value="{$info.unit.goods.goods_specification_id\|default=''\|htmlentities}"` | 无（未做任何 DDL） | 2 文件（视图 + info.ini）；`site.version` 不变（无 JS/CSS）；线上清空 `runtime/temp` 52 个编译模板 + `runtime/cache` 3 个配置缓存；线上核查 `XHD20261106001` 三个商品（X04-0001/X01-0046/A01-0020）型号均已维护，修复后可见。同类「只读型号框无 value」写法仍在 split/adjustment/warehousing/warehouse/process_order 的 edit 页，本次未改 |
| 1.6.35 | 2026-11-06 | 采购单入库「入库状态」筛选 + [重置]；收发明细/收发汇总/商品库存余额 三页「型号规格」搜索；采购入库弹窗「本次入库数量」默认待入库数量；采购入库/退货出库 操作记录改为每条记录下方展开明细表格；组装单列表默认按单据日期倒序 | 无（mysqldump --no-data 全库 183 表比对零差异） | 19 文件 + site.php；`goods_log.js` 改为「线上版本 + 型号规格搜索」合并版（保留线上 基本单位数量/单位成本/成本 列），自此本地与线上一致；`site.version` 1.0.11→1.0.12 |
| 1.6.34 | 2026-11-05 | 销货对账明细：①收款状态口径修正（销货单 `pay_status` 1=全部/2=部分，对账工作台 1=部分/2=已收款，`buildItemRows()` 直接透传导致全额收款显示「部分收款」）→ 新增 `SalesRecon::docPayStatus()` 1↔2 互换；②单号可复制（`.recon-doc-code` 放开 user-select + 点击复制） | 无 | 4 个文件；`site.version` 1.0.10→1.0.11；线上 `XHD20261104001`（327 全额已收）修正后显示「已收款」 |
| 1.6.33 | 2026-11-05 | 采购退货「退货出库」：退货单**审核只做账务**，库存/成本/收发明细/序列号/批次改由仓库在「采购单入库」列表点【退货出库】时按本次数量扣减（可分批，全部出完按钮消失）；已有出库记录时禁止反审核/删除；采购单详情与采购入库详情展示「退货出库记录」；计划部门「采购预订单」编辑页去掉「生成采购单」按钮 | `migration_add_goods_return_outbound.sql`（加 `exchange_info.out_number`、新表 `goods_return_outbound_log`、权限规则 803 + 按 index 增量授予 11 个角色；含历史已审核退货单 `out_number=retreat_number` 回填） | 14 个文件（4 新增）；`site.version` 1.0.9→1.0.10；线上原有退货单 GHTH20261105001 已回填为「已全部出库」 |
| 1.6.32 | 2026-11-05 | 采购预订单明细 ≥50 行时保存 500（`Undefined array key "preferential_rate"`）的定位与兜底：PHP `max_input_vars=1000` 会静默截断表单尾部字段与多余明细行，新增 `GoodsPurchaseOrder::checkFormTruncated()`，add/edit 在解析前校验尾部字段与各明细列长度，被截断时返回中文提示而非 500（`GoodsPurchaseOrder`、`GoodsPurchaseOrderPlan` 共 4 处调用） | 无 | 2 改；**需服务器侧把 `public/.user.ini` 的 `max_input_vars` 调到 10000**（该文件属主 root，FTP 无法改，须在宝塔面板改），否则 ≥50 行仍存不进去，只是报错变友好 |
| 1.6.31 | 2026-11-05 | 第二批「后加按钮」纳入权限表：采购单打印/汇总/导出、组装单打印/实际出入库/保存、外协订单打印、BOM打印、出库管理打印申请单/送货单、销货订单确认收货、计划排期排产/锁定零件/导出/改完成日期、采购预订单导出/导入明细、采购单(仓库)打印、小程序用户增删改；`mini_banner`/`mini_nav` 权限名驼峰改下划线（此前非超管看不到按钮） | `migration_add_buttons_auth_rules_batch2.sql`（15 条规则 788-802 + 按 index 增量授权） | 29 代码文件 + install.sql + 迁移 + site.php（含销货单详情底部「出库记录」面板）；结构无变更；`site.version` 1.0.8→1.0.9 |
| 1.6.30 | 2026-11-05 | 销货出库/出库管理「出库状态」筛选 + 未排产禁止申请出库（排产状态列）、送货单按钮从行上移到申请记录弹窗、采购入库列表已全部入库不再显示「入库」、采购入库详情加「入库记录」面板、采购预订单(计划)/仓库隐藏供应商列、BOM 打印页 A4 竖版列宽、采购入库 详情/打印/入库 纳入权限表 | `migration_add_missing_button_auth_rules.sql`（5 条规则）、`migration_add_goods_purchase_storage_auth_rules.sql`（3 条规则 + 授予已有查看权限的 11 个角色）、`migration_add_purchase_print_terms.sql`（2 个设置项，幂等）、`migration_widen_goods_specification_id.sql`（空操作） | 19 个文件；结构无变更；`site.version` 1.0.7→1.0.8 |
| 1.6.29 | 2026-11-04 | BOM 列表/选择弹窗的**子件行**补显示 型号 / 规格2-厂商 / 规格3-封装（此前只显示在父商品行） | 无 | 2 改（`GoodsBom.php`、`goods_bom.js`）；`site.version` 1.0.5→1.0.6 |
| 1.6.28 | 2026-11-04 | BOM 选择器统一为树形、商品计算器改用统一选择器、采购预订单(仓库)补「选择BOM模板」、采购单打印按客户模板改版、商品库存余额/收发存汇总去掉成本列、商品「规格型号」加长 | `migration_add_purchase_print_terms.sql`（执行，插入 2 个设置项）、`migration_widen_goods_specification_id.sql`（执行，线上已是 255 空操作） | 20 改 + 2 新；`site.version` 1.0.4→1.0.5（刷新浏览器缓存） |
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
| `application/config.php` | 本地 `login_captcha=false` 是开发便利，线上保持验证码开启 |
| `.env` / `.env.sample` | 含线上数据库与 FTP 凭据，**不进版本库** |

> `public/assets/js/backend/kuerp/goods_log.js` 曾在 1.6.30~1.6.34 期间列为「不部署」（线上版比本地多
> 基本单位数量/单位成本/成本 列）。1.6.35 已按「线上版本 + 型号规格搜索」合并后上线，本地与线上一致，
> 该文件恢复正常部署。若将来再出现线上手工改过而本地没有的 JS，处理方式同样是**以线上为底合并**，不要整文件覆盖。

---

## 四、新功能上线后需要人工处理的事项

- 新菜单/新权限规则：超管天然可见，其他角色需在「角色管理」手工勾选（项目惯例）。
- 新增定时任务（如 `kuerp:ar_analysis`、`kuerp:stock_summary`）：需在服务器 crontab 配置；
  CLI 必须用 PHP 8.0（默认 `php` 8.4 会让 TP5 的 console 触发 Deprecated → Fatal）。
- 新单号前缀 / 新设置项写在 `fa_kuerp_prefix`、`fa_kuerp_sets`（迁移里已带，执行后到设置页确认）。
