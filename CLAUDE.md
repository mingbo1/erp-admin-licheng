# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**FastAdmin** — 基于 ThinkPHP 5.0 + Bootstrap 的极速后台开发框架。

**kuerp (酷柚易汛进销存)** — 作为 FastAdmin 插件运行，解决采购、销售、仓库、资金四大经营问题。

## Architecture

### Framework Stack
- **后端**: ThinkPHP 5.0 (dev-master), PHP >= 7.4
- **前端**: Bootstrap 3, AdminLTE, RequireJS, jQuery
- **构建**: Grunt (JS/CSS 压缩打包)
- **数据库**: MySQL (通过 `application/database.php` 配置，`.env` 覆盖)
- **ORM**: ThinkPHP Model + `think\Db` 门面

### Project Layout (Plugin-Specific)

```
addons/kuerp/                          # 进销存插件入口
├── Kuerp.php                          # 插件类（菜单定义/生命周期钩子）
├── library/                           # 核心业务逻辑
│   ├── Goods.php                      #   成本计算(移动平均/先进先出)、编码生成、工具方法
│   ├── GoodsPurchase.php              #   采购价格计算
│   ├── GoodsSales.php                 #   销售价格计算(含税金/折扣联动)
│   └── Checkout.php                   #   结账/反结账、利润核算
├── model/                             # 数据模型（ThinkPHP Model）
├── controller/Index.php               # 前台控制器（无前台页面）
├── install.sql                        # 数据库建表语句
└── info.ini                           # 插件元信息

application/admin/controller/kuerp/    # 后台控制器（90个）
application/admin/model/kuerp/         # 后台模型（78个）
application/admin/view/kuerp/          # 后台视图（91个）
application/admin/lang/zh-cn/kuerp/    # 语言包（80个）
public/assets/js/backend/kuerp/        # 后台JS文件（90个）
public/assets/addons/kuerp/            # 插件前端资源
```

### Key Design Patterns

1. **插件生命周期** — `Kuerp.php` 定义 install/uninstall/enable/disable/upgrade，全部通过 `Menu::` 类管理菜单
2. **菜单即路由** — `$menu` 属性定义 6 级嵌套结构，自动生成后台路由和权限规则
3. **Controller 层** — 全部继承 `app\common\controller\Backend`，`$model` 属性绑定 Model
4. **Library 层** — 静态方法调用，通过 `think\Db` 门面直接操作数据库（不经过 Model）
5. **Model 层** — 标准 ThinkPHP Model，定义表名、时间戳、追加属性
6. **审核机制** — 每个单据的 `examine` 方法统一处理审核/反审核，审核后触发库存变动和成本计算

### Core Business Modules

| 模块 | 路径前缀 | 功能 |
|------|---------|------|
| 数据面板 | `kuerp/datainfo` | Dashboard、库存预警 |
| 购货 | `kuerp/goods_purchase*` | 购货订单/单/退货/报表/开票 |
| 销货 | `kuerp/goods_sales*` | 销货订单/单/退货/报表/开票/利润 |
| 仓库 | `kuerp/allocation|adjustment|inventory|assemble|split|warehouse*` | 调拨/成本调整/盘点/组装拆卸/出入库 |
| 资金 | `kuerp/payment|collection|writeoff|accounts_*|income|expenditure|cost` | 收付款/核销/转账/报表 |
| 资料 | `kuerp/customers|suppliers|goods|stores|accounts|staff_member|address` | 基础资料管理 |
| 辅助资料 | `kuerp/customerlevel|customertype|goods_type|expend_type|income_type|settlementtype|unit|auxiliary` | 分类/单位/属性 |
| 设置 | `kuerp/sets|initialization|checkout` | 参数/初始化/结账 |

### Document Business Flow

```
购货订单 → 审核 → 采购单(部分/全部) → 审核 → 库存+ → 采购明细
                                                          ↓
购货退货单 ← 审核 ← 库存-                                    成本核算
                                                          ↓
销货订单 → 审核 → 销货单(部分/全部) → 审核 → 库存- → 销售利润
                                                          ↓
销货退货单 ← 审核 ← 库存+
```

## Common Commands

```bash
# 安装依赖
composer install
npm install

# 前端构建（压缩JS/CSS）
grunt

# 前端构建（指定模块）
grunt build --module=kuerp

# 数据库配置
# 编辑 .env 文件中的 [database] 段

# 开发服务器（PHP内置）
php -S localhost:8000 -t public/
```

## Environment

- `.env` 覆盖 `application/database.php` 和 `application/config.php` 中的配置（注意不要提交 `.env` 到版本控制）
- 数据库表前缀: `fa_`（所有 kuerp 表使用 `fa_kuerp_*` 命名）
- 插件授权文件 `.addonrc` 包含授权域名和许可信息
- 入口文件: `public/index.php`

---

# 销货排期模块功能详解

> 最后更新: 2026-06-26
> 涉及模块: `goods_sales_schedule`(销货排期)、`assemble`(组装单)、`process_order`(外协加工)、`goods_sales_order`(销货订单)

---

## 一、模块关系概览

```
销货订单(goods_sales_order)
    └── 销货排期(goods_sales_schedule)  ← 本模块核心
        ├── BOM零件需求计算
        ├── 零件库存锁定/解锁 ────────── 影响 → 组装单(assemble) / 外协加工(process_order)
        ├── 生成采购订单 → 购货订单(goods_purchase_order)
        ├── 生成组装单 → 组装单(assemble)
        └── 生成销货单 → 销货单(goods_sales)
```

### 关键关联字段

| 表 | 字段 | 指向 |
|---|------|------|
| `fa_kuerp_goods_sales_schedule` | `purchase_order_id` | 销货订单号 (与 `fa_kuerp_goods_sales_order.purchase_code` 对应) |
| `fa_kuerp_assemble` | `sales_order_id` | 销货订单号 |
| `fa_kuerp_assemble_info` | `sales_order_id` | 销货订单号 |
| `fa_kuerp_goods_lock` | `sales_order_id` | 销货订单号 |
| `fa_kuerp_goods_bom_info` | `type=1`(父件) / `type=2`(子件) | BOM 模板结构 |

---

## 二、BOM 零件需求计算

### 2.1 入口

在销货排期编辑页 (`goods_sales_schedule/edit.html`) 底部，有一个 **BOM零件需求** 面板，自动根据排期中的商品行计算 BOM 子件需求。

### 2.2 计算逻辑

```
对排期中每个商品:
  1. 查找该商品的所有 BOM 模板 (fa_kuerp_goods_bom WHERE goods_id=父商品ID)
  2. 对每个BOM模板，查询子件 (fa_kuerp_goods_bom_info WHERE bom_id=BOM.ID AND type='2')
  3. 过滤: 只取 product_type='part'(零件) 的子件商品
  4. 计算需求量: required_qty = bom_number × schedule_basic_number
     - bom_number: BOM中1个父商品需要多少个子商品
     - schedule_basic_number: 排期中该父商品的基本单位数量
  5. 按 goods_id + stores_id 汇总所有父商品对同一个零件的需求
```

### 2.3 数据查询链路

```
前端 JS: loadBomRequirement()
  ↓ POST schedule_id + goods_data (实时页面商品数据)
后端: GoodsSalesSchedule::getBomRequirement()
  ↓ 查 kuerp_goods_bom → kuerp_goods_bom_info (JOIN kuerp_goods.product_type='part')
  ↓ 查 kuerp_goods_stock (各仓库库存)
  ↓ 查 kuerp_goods_lock (锁定状态, 按 sales_order_id)
  ↓ 查 kuerp_stores (仓库名称)
返回: [{goods_id, goods_code, goods_name, required_total, total_stock, total_diff,
         stock_details: [{stores_id, stores_name, stock, required, diff}],
         lock_info: {stores_id: {lock_quantity, used_quantity, remaining}},
         has_lock, sales_order_id}]
```

### 2.4 前端展示

BOM 表格列: 商品编号 | 商品名称 | 需求数量 | 当前库存 | 库存差值 | 仓库明细 | 操作

- **仓库明细列**: 每仓库一行，格式: `仓库名: 库存=X / 需求=Y / 差值=Z`
- **操作列**: 根据锁定状态显示 [锁定] 或 [解锁] 按钮 + 锁定信息

---

## 三、库存锁定/解锁机制

### 3.1 设计目的

销货订单承诺给客户交付成品。成品拆解为零件后，需要**预留**这些零件的库存，防止被其他订单的组装单/外协加工单消耗掉。

### 3.2 核心理念

> 锁定 = 订单级库存预留。同一个订单的组装单可以使用锁定库存，其他订单不行。

### 3.3 数据库结构

**`fa_kuerp_goods_lock`** — 库存锁定表

| 字段 | 类型 | 说明 |
|------|------|------|
| id | int(11) PK | |
| sales_order_id | varchar(50) | 销货订单号（与排期关联） |
| goods_id | int(11) | 商品ID |
| goods_code | varchar(50) | 商品编号 |
| attribute_id | int(11) DEFAULT 0 | 属性ID（暂固定为0） |
| stores_id | int(11) | 仓库ID |
| lock_quantity | double(15,4) | 锁定数量（基本单位） |
| used_quantity | double(15,4) DEFAULT 0 | 已被组装单出库使用的数量 |
| status | enum('0','1') DEFAULT '1' | 0=已解锁, 1=锁定中 |

唯一约束: `(sales_order_id, goods_id, stores_id, attribute_id)` — 同一订单+商品+仓库只有一条记录。

### 3.4 可用库存计算公式

```
available_stock(物理库存, 所有锁定, 当前订单) =
    physical_stock
  - Σ(lock_quantity - used_quantity) WHERE status='1' AND sales_order_id ≠ current
  ─────────────────────────────────────────────────────────────────────────────
  = physical_stock - total_effective_locked + own_effective_locked
```

**关键规则**:
- ✅ 同一销售订单的组装单: `available = physical_stock`（能看到并使用自己的锁定库存）
- ❌ 其他订单的组装单: `available = physical_stock - locked_by_others`（看不到别人的锁定）
- ❌ 无销售订单关联的组装单/外协加工: `available = physical_stock - all_locks`
- ⚠️ 采购单/销货单: **不受锁定影响**（后端不传 `sales_order_id` 参数时不触发调整）

### 3.5 锁定操作

#### 3.5.1 锁定

```
排期编辑页 → BOM表格 → 某零件行 → [锁定]
  ↓ 弹窗内容:
    - 商品名称（只读）
    - 需求数量（只读）
    - 锁定仓库（下拉选择，选项来自 stock_details）
    - 锁定数量（输入框，默认=需求数量，可修改）
  ↓ 校验:
    - lock_quantity > 0
    - lock_quantity ≤ 该仓库可用库存（已扣除其他订单锁定）
  ↓ POST kuerp/goods_sales_schedule/lockGoods
  ↓ GoodsSalesSchedule::lockGoods()
    - 查排期 → 获得 sales_order_id
    - 调 Goods::getAvailableStock() 校验库存
    - 查是否已有该 goods+stores 的锁定 → 覆盖更新
    - 否则 INSERT 新记录
```

**锁定校验公式**:
```
lock_quantity ≤ physical_stock - locked_by_other_orders
```
即: 不能锁定已被别人锁定的库存。物理库存1500，订单B已锁800，订单A最多锁700。

#### 3.5.2 解锁

```
BOM表格 → 已锁定零件 → [解锁] → 确认弹窗
  ↓ POST kuerp/goods_sales_schedule/unlockGoods
  ↓ GoodsSalesSchedule::unlockGoods()
    - 设置 status='0'（软删除，保留记录）
```

#### 3.5.3 覆盖更新

同一订单+同一商品+同一仓库重复点"锁定" = **覆盖更新**（lock_quantity 重置，used_quantity 归零）。

### 3.6 锁定消费（组装单出库时）

```
组装单 → 审核/手动出库 → 子件出库成功
  ↓ Goods::consumeLock(sales_order_id, goods_id, stores_id, outbound_qty)
    - 查找同 sales_order_id + goods_id + stores_id 的生效锁定
    - used_quantity += outbound_qty (基本单位)
    - 若 used_quantity ≥ lock_quantity → status='0'（自动解锁）
```

**消费时机**:
- 自动审核模式: `Assemble::examine()` → `Db::commit()` 后
- 手动出库模式: `Assemble::saveOutbound()` → `Db::commit()` 后

### 3.7 库存可见性矩阵

| 调用方 | sales_order_id 传值 | 看到的可用库存 | 说明 |
|--------|-------------------|--------------|------|
| 采购单/销货单/销货订单 | (不传参 → 内部用空串) | physical_stock - all_locks | 无关联订单，扣除所有锁定 |
| 组装单(来源订单A) | "XHDD001" | physical_stock | 能看到自己的锁定 |
| 组装单(来源订单B) | "XHDD002" | physical_stock - A的锁定 | 看不到别人的锁定 |
| 组装单(无来源) | "" | physical_stock - all_locks | |
| 外协加工单 | (不传参，内部用空串) | physical_stock - all_locks | ProcessOrder::getUnit() 内部调用 |

### 3.8 实现位置

| 层次 | 文件 | 方法 |
|------|------|------|
| 库存计算核心 | `addons/kuerp/library/Goods.php` | `getAvailableStock()`, `consumeLock()`, `getLockList()` |
| 锁定/解锁API | `application/admin/controller/kuerp/GoodsSalesSchedule.php` | `lockGoods()`, `unlockGoods()`, `getLockStatus()` |
| BOM锁定展示 | `goods_sales_schedule/edit.html` + `goods_sales_schedule.js` | `renderBomTable()`, lock/unlock事件 |
| 购货/销货库存调整 | `application/admin/controller/kuerp/GoodsPurchase.php` | `getUnit()` 始终调用 `getAvailableStock()`（不传 `sales_order_id` 时用空串） |
| 销货库存调整 | `application/admin/controller/kuerp/GoodsSales.php` | `getUnit()` / `getUnits()` 始终调用 `getAvailableStock()`（用空串） |
| 组装单消费锁定 | `application/admin/controller/kuerp/Assemble.php` | `examine()`, `saveOutbound()` |
| 外协加工库存调整 | `application/admin/controller/kuerp/ProcessOrder.php` | `getUnit()` 始终扣除全部锁定 |
| JS传参 | `public/assets/js/backend/kuerp/assemble.js` | `changeGoods()` 传入 `sales_order_id` |

---

## 四、"生成组装单"功能

### 4.1 入口

排期编辑页 → 每行商品 → **[生成组装单]** 按钮

### 4.2 流程

```
1. 点击按钮 → 打开 kuerp/assemble/add?schedule_id=X&goods_code=YY
2. Assemble::add() GET 检测参数:
   - 查排期 → 获取 sales_order_id (purchase_order_id)
   - 查排期商品详情 (kuerp_goods_sales_info)
   - 查BOM模板 → 子件(product_type='part')
   - 计算子件数量: child_number = bom_number × schedule_basic_number
   - 按 goods_id 汇总
   - 将所有数据通过 assignconfig 传给前端
3. assemble.js initScheduleBom IIFE:
   - 设置隐藏域 .sales_order_id
   - 填充组合件行(type=1): goods_code, goods_name, 数量, 仓库
   - 填充子件行(type=2): 遍历 bomChildren, 每行设置 goods_code + 计算数量
   - 调用 changeGoods() 获取单位/库存信息
4. 保存 → fa_kuerp_assemble.sales_order_id + fa_kuerp_assemble_info.sales_order_id
```

### 4.3 销货订单操作日志

组装单审核/反审核时，写入 `fa_kuerp_goods_sales_order_log`:

| 操作 | action | note |
|------|--------|------|
| 子件出库审核 | "生成组装单" | "组装单[ZZD...]子件出库审核通过" |
| 子件出库反审核 | "取消组装单" | "组装单[ZZD...]子件出库反审核" |

---

## 五、"生成采购订单"功能

### 5.1 入口

排期编辑页 → **[生成采购订单]** 按钮（页面顶部）

### 5.2 流程

```
点击按钮 → 打开 kuerp/goods_purchase_order/add?schedule_id=X
  ↓ GoodsPurchaseOrder::add() GET:
    - 查排期信息 → 预填供应商、日期等
    - 查排期商品列表 → BOM子件计算（类似组装单）
    - assignconfig 传递给前端
  ↓ 前端预填商品行
  ↓ 保存 → 购货订单关联排期
```

---

## 六、数据库迁移记录

| 迁移文件 | 内容 | 执行状态 |
|---------|------|---------|
| `addons/kuerp/migration_add_assemble_sales_order_id.sql` | assemble/assemble_info 表增加 `sales_order_id` 字段 | 需手动执行 |
| `addons/kuerp/migration_add_goods_lock.sql` | 新建 `fa_kuerp_goods_lock` 表 | 需手动执行 |
| `addons/kuerp/migration_add_goods_frequent_customers.sql` | `fa_kuerp_goods` 增加 `frequent_customers` 字段（商品常用客户） | 需手动执行 |

### 6.1 执行命令

```bash
mysql -uroot -proot -D erp_licheng < addons/kuerp/migration_add_assemble_sales_order_id.sql
mysql -uroot -proot -D erp_licheng < addons/kuerp/migration_add_goods_lock.sql
mysql -h127.0.0.1 -uroot -proot -D erp_licheng < addons/kuerp/migration_add_goods_frequent_customers.sql
```

---

## 七、开发注意事项

### 7.1 JS 作用域

`goods_sales_schedule.js` 和 `assemble.js` 的核心函数（`changeGoods`, `changeGoodsInfo`, `manufacture`, `validity` 等）都定义在 `bindevent` 闭包内。如果要在页面加载时调用这些函数，必须在 `bindevent` 内部使用 IIFE（如 `initScheduleBom`），**不能** 放在 Controller 层级。

### 7.2 $noNeedRight

公开 API 方法必须在控制器的 `$noNeedRight` 数组中声明，否则未登录用户无法访问。

### 7.3 库存查询统一调整

所有 Controller 层的 `getUnit()` 方法在查询物理库存后，统一调用 `Goods::getAvailableStock()` 调整：

- **`GoodsPurchase::getUnit()`**: 始终调整。`sales_order_id` 由调用方 POST 传入（无传入时用空串 → 扣除所有锁定）
- **`GoodsSales::getUnit()` / `getUnits()`**: 始终调整。传入空串 → 扣除所有锁定
- **`ProcessOrder::getUnit()`**: 始终调整。传入空串 → 扣除所有锁定
- **Library 层** (`GoodsPurchase::getUnit()`): 查原始物理库存，不变（供 `GoodsSalesOrder` 编辑页预填等只读场景使用）

### 7.4 组装单审核分为两种模式

- **自动模式** (`$examine_set != 1`): `examine()` 方法直接做库存变动 + 消费锁定，全部在 `Db::commit()` 前完成
- **手动模式** (`$examine_set == 1`): `examine()` 只改状态，实际出入库通过 `saveOutbound()` 方法，锁定消费在 `saveOutbound()` 的 commit 后进行

### 7.5 BOM子件过滤

BOM零件需求列表（`GoodsSalesSchedule::getBomRequirement()`）和「生成组装单」预填（`Assemble.php`）取 `product_type IN ('part','semi_finished')`（零件+半成品）的子件，仅 `finished`（成品）不参与计算。「生成采购订单」预填（`GoodsPurchaseOrder.php` 与 `GoodsPurchaseOrderPlan.php` 的 `add()`，两处为复制代码需同步改）取 `product_type IN ('part','semi_finished','finished')`；且**无BOM模板的排期商品**（无法组装、只能整件采购的成品/半成品）也会做自身库存缺口检测（需求=basic_nmber，库存=全部仓库合计，仓库取排期行的 stores_id）。过滤都在 `kuerp_goods_bom_info` JOIN `kuerp_goods` 时完成。

### 7.6 商品属性(goods_attr)与库存 attribute_id

- 单据行的 `goods_attr` = `fa_kuerp_goods_attr` 行ID；库存表 `attribute_id` 用同一套ID过滤（采购入库写入时一致，见 `GoodsSales.php` examine）。
- **初始化导入的库存 `attribute_id=NULL`**，所以无辅助属性商品的单据行属性必须留空：`GoodsPurchase::getUnit()` 对无属性商品也返回一条 `attr_name=''` 的纯单位行，若前端自动选中它（旧代码 `attrarr[0].id`），单据行会带上单位行ID，审核出库按 `attribute_id=单位行ID` 查库存必然查不到 → 误报"库存不足"（编辑页可用库存也显示0）。
- `assemble.js` / `goods_purchase_order.js` / `goods_purchase_order_plan.js` 已修复：只自动选中 `attr_name` 非空的辅助属性行。服务端"请选择商品属性"校验仅对 `auxiliary==1` 的商品生效，属性留空是合法的。

### 7.7 组装单"一键生成采购预订单(计划)"

组装单 add/edit 页"子件"标题旁的 [一键生成采购预订单(计划)] 按钮（已审核单据上仍可点：bindevent 的"审核的单据不可编辑"块统一 `$('.btn').attr('disabled')` 后对 `.shortage_purchase` 单独 removeAttr 恢复）：遍历 `#assemble_table_c` 子件行，缺口 = `number`(需求数量) − `allow_stock`(可用库存)，缺口>0 的行（同商品+属性+仓库合并）经 `Assemble::setdata()` 存入 `Session('GoodsPurchaseOrder')`，然后 `parent.Fast.api.open("kuerp/goods_purchase_order_plan/add?type=1")` 预填（2026-11-04 由原 `goods_purchase_order/add` 改为计划版页面，计划部门不接触价格；`GoodsPurchaseOrderPlan::add($type)` 同样读该 Session，视图无单价/金额列，价格字段为 hidden 0）。这与补货模块(`Replenishment::setdata`)共用同一 Session key 和 `type=1` 预填链路（`GoodsPurchaseOrder::add()` 读 Session → `assignconfig("list")` → `initlist()` 填行），行上的 `purchase_code` 字段写入购货行 `sales_order_id`（组装单关联的销货订单号，edit 页无 `.sales_order_id` 隐藏域，从 `Config.assembleSalesOrderId` 取）。全部子件库存充足时提示且不打开采购页。

权限：`setdata` **不在** `$noNeedRight`，受 `fa_auth_rule` 规则 `kuerp/assemble/setdata`（pid=组装单菜单）控制（见 `migration_add_assemble_setdata_auth_rule.sql`）；`Assemble::_initialize` 里 `$this->auth->check()` 结果 assign 为 `canShortagePurchase`，add/edit 视图用 `{if $canShortagePurchase}` 包住按钮，后端同步校验。超管（rules=`*`）始终可见，其他角色需在 角色管理→组装单 勾选"一键生成采购预订单(计划)"（规则标题已同步）。

### 7.8 组装单实际出库/入库弹窗与领料单打印（2026-10-14）

`outbound_edit.html`（实际出库/入库弹窗）：子件出库表和组合件入库表在"商品名称"后各加 **型号 / 规格2/厂商 / 规格3/封装** 三列（`outboundEdit()` 的 info 查询 JOIN `kuerp_goods` 带出 `goods_specification_id`/`vendor_name`/`packaging`）；panel-heading 右侧 [打印领料单] 按钮 → 内联 `printPicking()`（`Fast` 为全局，fast.js 挂 `window.Fast`）→ `Fast.api.open("kuerp/assemble/pickingpage?ids=X")`。

`Assemble::pickingpage()` + `pickingpage.html`（在 `$noNeedRight`，不记 `print_num`）：仅取子件(type=2)，列含上述三字段 + 计划数量(number) + 剩余数量(max(0, number-out_number))，页脚有 领料人/发料人/审核 签字栏；辅助属性列同样受 `goods_attr_sd` 开关控制。

**2026-10-21 出库/入库弹窗改版**（与 7.9 外协加工单同构）：
- `out_number_ipt` / `in_number_ipt` 默认值由「剩余可出/可入数量」改为 `value="0"`；`assemble.js` 的 `Form.api.bindevent` 第 4 参（submit 前置回调）校验「所有 `.out_number_ipt/.in_number_ipt` 均 ≤0 → `Toastr.error('请至少填写一项实际出库/入库数量')` 并 return false」，不发请求（后端本就 `< =0 跳过`，全 0 时 `请至少填写一项实际出库/入库数量` 兜底）。
- [确认出库/入库]/[取消] 从 form 末尾移入主面板 `panel-body` 内（组合件入库表之后的 `<hr>` + 按钮行），与表单同属一个盒子。
- 新增 **「出库/入库操作记录」独立面板**（主面板之后、页面最下方）：`Assemble::outboundEdit()` 查 `kuerp_goods_log`（**`purchase_code=单据编号 AND business_type IN (12,13)`**，12=组合件入库 / 13=子件出库，与 process 的 16/17 不同），`LEFT JOIN kuerp_goods` 带三规格字段，服务端补 `stores_name`/`admin_name`/`is_out`，`createtime DESC, id DESC`（最新在前），空态"暂无出库/入库记录"。列：时间/类型/商品编号/商品名称/型号/规格2/规格3/仓库/数量/操作人 —— **不含单价/金额**：`Assemble::saveOutbound()` 写 `kuerp_goods_log` 时 `out_unit`/`out_cost`/`enter_unit`/`enter_cost` 一律留空（与 process 的写法不同，那边有写）。反审核会删除 12/13 两类的 log 行，故反审核后记录清空。
- ⚠️ 组装单出/入库全部完成后 `outboundEdit()` 直接报"该单据已完成全部出库和入库"，弹窗不可再打开 → 记录面板只在**分批未完成**时可见（这是既有守卫，未改）。

### 7.9 外协加工单(kuerp/process)出库/入库审核流程改造（2026-10-15，对齐组装单）

> 涉及：`Process.php`、`process.js`、`process/outbound_edit.html`（新增）、`process/detail.html`、
> `migration_add_process_outbound_flow.sql`、`model/kuerp/ProcessLog.php`（新增）

**新流程**（替代原"审核即扣库存 + 一次性出库/取消出库"）：

```
添加加工单 → [原料出库审核 examine()] → [入库成品审核 examineInbound()] → [实际出库/入库 outboundEdit()/saveOutbound()]（可分批多次，弹窗内可选结算加工费）

> 原"外协入库 processIn()"已移除（2026-10-15）：成品入库并入"实际出库/入库"弹窗，加工费结算同弹窗底部"加工费结算(可选)"面板（金额>0 时生成 kuerp_process_fee 记录 + 账户流水扣款 + kuerp_cost 费用表，字段校验同原 processIn）。控制器 processIn()/getInfo()、视图 process_in.html、JS bindeventProcessIn、菜单/权限规则同步删除（迁移文件第7段清理 fa_auth_rule）。入库顺序强制：examineInbound 前置校验 examine_status==1（'未进行原料出库审核'），JS 列表同守卫。
```

**双模式**：
- **手动** (`examine_set==1`)：`examine()` 仅改状态（不再扣库存）；实际原材料出库在 outboundEdit 页分批填写。反审核限制：`outbound_status!=0`（有出库记录）或 `inbound_examine_status==1` 时禁止。
- **自动** (`examine_set!=1`)：`examine()` 审核即全部出库（设 `outbound_status=2`+回写 `out_number=number`）；`add()` 保存后自动串联 `examineInbound($result,1)`。反审核恢复全部库存并重置 `outbound_status=0`/`out_number=0`。edit/del 对已审核单先 `examineInbound($ids,2)` 再 `examine($ids,2)`（顺序不可反，examine 反审核有 inbound_examine_status 前置校验）。

**字段/表**（`migration_add_process_outbound_flow.sql`，需手动执行）：`fa_kuerp_process` +`inbound_examine_status/id/time`；`fa_kuerp_process_info` +`out_number`；新表 `fa_kuerp_process_log`（process_id/action/note/admin_id/createtime）。历史修复：已审核单据统一标记 `outbound_status=2`+`out_number=number`（其库存在旧逻辑已扣，防二次出库）。

**实际出库/入库 `saveOutbound()`**：POST `out_data[info_id]`(本次出库数) + `in_data[info_id][in_number/unit_cost/stores_id]` + `batch_data[info_id][batch/manufacture/guarantee/validity]` + `fee_data[total_process_fee/account_id/expend_type_id/fee_time/remark]`(可选加工费)；出库 gated `examine_status==1`，入库 gated `inbound_examine_status==1`；按比例折算 `basic_number = basic_number/number×本次`，出库成本 `unit_cost×basic_part`（部分出库按比例计成本）；累计 `out_number`/`in_number`，主表 `outbound_status`/`in_status` 0→1→2；收发明细 business_type '16'外协出库/'17'外协入库；批次沿用单据行 batch_id（页面新填批次优先）；序列号按未出库（store_status==1）顺序取。成品入库 `unit_cost` 取最近一次，`cost_money` 累加（同旧 processIn 约定）。

**出入库记录面板**（outbound_edit.html）：`outboundEdit()` 从 `kuerp_goods_log` 取 `purchase_code=单号 AND business_type IN (16,17)` 的每次分批记录（JOIN goods 带三规格字段，服务端补仓库名/操作人昵称、is_out 标记），渲染 时间/类型/商品/仓库/数量/单价/金额/操作人 表格，空态"暂无出入库记录"。**2026-10-21 改版**：原先放在 panel-heading 右侧的 [出入库记录] 折叠按钮（`#history_panel`，默认隐藏）已删除；记录改为**页面最下方独立的 `.panel` 盒子**（标题「出入库记录」+ 说明文字），排序 `createtime DESC, id DESC`（最新在前）。同时 [确认出库/入库]/[取消] 按钮**从 form 末尾移到主面板 panel-body 内**（「加工费结算」之后，`<hr>` + 按钮行），与表单同属一个盒子；「加工费结算」的 备注 字段由 `col-sm-9`+`label col-sm-2` 改为 `col-xs-12`+`label col-xs-4 col-sm-1`，使备注输入框左边缘与「加工费金额」输入框**精确对齐**（实测两者 left 均为 144px；`col-sm-1`=8.33% 正好等于 `col-sm-3` 内 `col-xs-4` 的偏移）。
**本次出库/入库数量默认 0**（2026-10-21）：两个数量输入框原默认值为"剩余可出/可入数量"（`number - out_number`），用户不填直接点确认就会整批出/入库；现改为 `value="0"`，并在 `process.js` 的 `Form.api.bindevent` 第 4 参（submit 前置回调）校验"所有 `.out_number_ipt/.in_number_ipt` 均 ≤0 时 `Toastr.error('请至少填写一项本次出库/入库数量')` 并 return false"，不发请求。后端本就有 `< =0 跳过、全 0 报'请至少填写一项实际数量'` 的兜底，两层防护。

**操作记录**：`ProcessLog::writeLog($processId, $action, $note)`（读 Session admin），埋点：添加单据/编辑单据/原料出库审核(反审核)/入库成品审核(反审核)/原料出库/成品入库/加工费结算；详情页 `detail.html` 操作记录面板（服务端渲染 log_list+admin nickname 映射）。del() 同步删 log。

**权限规则**：新增 `kuerp/process/examineinbound`、`kuerp/process/outboundEdit`、`kuerp/process/saveOutbound`（pid=601）；删除旧 `kuerp/process/outbound`、`kuerp/process/cancelOutbound`、`kuerp/process/processIn`（控制器方法已移除）。index 工具栏：原料出库审核/反审核、入库成品审核/反审核、实际出库/入库；四道审核按钮与组装单一致仅手动模式显示（`{if $set['examine']['is_checked']==1}`）；视图 `$auth->check($nowmenu.'/...')` 用小写方法名（大小写不敏感，与 assemble 一致）。JS 列表 `examine_f` 客户端守卫仅在手动模式检查 `outbound_status`/`inbound_examine_status`（自动模式已审核单恒为 outbound_status=2，不能拦）。

**顺带修复的隐患**（序列号/批次功能开启即崩，此前从未暴露）：
- `fa_kuerp_goods_serial` 表**没有** `process_id` 列 → 统一改写 `order_id`（=kuerp_process_info.id，与 goods_serial_log 的 order_id 一致）；原 examine()反审核 的序列号恢复 where 同样改为 order_id。成品入库序列号 status=15、出库 status=14 不变（现由 saveOutbound 写入）。
- `fa_kuerp_goods_batch_info` 表没有 `process_info_id` 列 → 迁移文件补 `ALTER TABLE ... ADD COLUMN process_info_id`（对齐 assemble_id/split_id 模式），Process add/edit/del 对该表的写入/删除恢复正常。
- edit()/del() 调 `examineInbound($ids,2)` 前必须判断 `inbound_examine_status==1`，否则空列表返回 `['status'=>0,'msg'=>'系统错误']` 误拦正常流程。

**已验证**（本地 curl 登录实测，手动+自动两模式）：outboundEdit 未审核拦截、手动审核不动库存、分批出库（库存/成本/收发明细/日志/状态 0→1→2）、入库成品审核门禁（未原料审核拦截 / 未审核时入库输入禁用）、分批入库（新库存行创建、in_number/cost_money 累计）、反审核顺序守卫、超量拦截、del/edit 手动模式拦截、自动模式审核即全部出库 + 反审核恢复库存/重置 out_number + del 级联清理。

---

## 八、完整业务流程总览

```
销货订单 A (XHDD...001) → 审核
    ↓
销货排期 (关联 XHDD...001)
    ├── 商品: 成品A × 5, 成品B × 3
    ├── BOM零件需求:
    │   零件a: 需求1000, 库存1500, 差值+500  → [锁定1000]
    │   零件b: 需求600,  库存400,  差值-200  → [锁定400]
    │   零件c: 需求300,  库存800,  差值+500  → [锁定300]
    │
    ├── [生成采购订单] → 购货订单(采购缺货零件)
    │
    ├── 成品A行 → [生成组装单] → 组装单(ZZD...001, sales_order_id=XHDD...001)
    │    组合件: 成品A × 5
    │    子件:   零件a × 1000, 零件b × 400    ← BOM计算
    │    → 审核出库 → consumeLock(XHDD001, a, 1000) → used_quantity=1000 → 自动解锁
    │
    └── 成品B行 → [生成组装单] → 组装单(ZZD...002, sales_order_id=XHDD...001)
         组合件: 成品B × 3
         子件:   零件a × 200, 零件c × 300    ← BOM计算
         → 审核出库 → consumeLock(XHDD001, a, 200) → ...

外协加工单 (无关联订单):
    看到零件a可用库存: 1500 - 1000(被XHDD001锁) = 500
    看到零件b可用库存: 400  - 400(被XHDD001锁)  = 0

其他销货订单 B (XHDD...002) 的组装单:
    看到零件a可用库存: 1500 - 1000(被XHDD001锁) = 500
```

---

# 下拉框关键字搜索增强（select-enhancer）

> 涉及文件：`public/assets/js/backend/kuerp/select-enhancer.js`（新增）、
> `application/admin/view/kuerp/common/menu.html`、`public/assets/addons/kuerp/css/kuerp.css`（该目录 gitignore，随文件树部署）

## 目标

kuerp 后台全部原生 `<select>`（约 648 个）转为 bootstrap-select 并默认开启关键字搜索。
仅 kuerp 模块生效，不动 FastAdmin 核心页面。

## 加载入口

`kuerp/common/menu.html`（每个 kuerp 页面都 include）里的内联脚本：轮询等
`Config.jsname` 出现后 `require(['backend/kuerp/select-enhancer'])`，仅当
`Config.jsname` 以 `backend/kuerp` 开头时加载。

不放进 `require-backend.js` 的模块钩子里——生产环境 `app_debug=false` 加载的是
`require-backend.min.js`（压缩产物），改源码不生效。

## 关键机制

| 机制 | 说明 |
|------|------|
| 全局默认 | `$.fn.selectpicker.defaults` 设 `liveSearch/liveSearchStyle:'contains'/size:10/noneSelectedText:''`；单元素加 `data-live-search="false"` 可关闭 |
| 动态元素 | `MutationObserver`(childList/subtree/attributes:disabled) 转换动态插入的 select、刷新被重填选项的下拉框 |
| 值变化 | 劫持 `$.fn.val` 写入路径，对已转换的 select 延后 `refresh`（`.html(新选项)` 紧跟 `.val(x)` 也能一次刷新到位） |
| class 复制 | 插件 `setStyle()` 会把 select 的 class 复制到包裹层，导致 `.find('.goods_unit').val()` 读到包裹层空值；monkey-patch `setStyle` 移除业务 class（保留 form-control / col-*） |
| `.layui-form` | 列表页搜索栏的 select 由 layui 的 form 模块自行渲染，**不转换**，改为打 `lay-search` 属性开启 layui 自带搜索（模块加载即标记；若 layui 已渲染且标题框 `readonly`，用 `layui.form.render('select')` 重渲染兜底） |
| 菜单容器 | `.list{overflow:auto}` 与 `.layui-layer-content{overflow:auto}` 会裁切菜单：弹层内的 select → `container` 取最近的 `.layui-layer`（既逃出裁切、又留在弹层之上；挂 body 会被弹层 z-index 19891014 盖住）；弹层外的 `.list` → `container:'body'`；其余保持默认 |
| 已有 selectpicker | 视图里 `class="selectpicker"` 的下拉由 form.js 在 bindevent 时初始化，若早于本模块则没有搜索框，`upgradeExistingPickers()` 重建补上 |

## 注意

- 序列号互斥（`allocation.js` 的 `check_serial_option()`）只能禁用「被**其它行**选走」的 option：
  禁用本行已选中的 option 会让 jQuery `.val()` / `serialize()` / 原生表单提交都丢掉该值。
- `noneSelectedText:''`：`<option value=""></option>` 这类空值选项保持与原生一致的空白显示，
  否则 bootstrap-select 会填上"没有选中任何项"。
- 表格内下拉按钮必须 `margin:0`：`.list .btn{margin:auto 8px}`（kuerp.css 顶部，为表格里其他按钮设的）会命中
  bootstrap-select 的 `btn` 切换按钮——按钮 `width:100%` 再加左 8px 外边距，左侧空 8px、右侧溢出单元格 8px，
  盖住相邻「可用库存」列的数字。kuerp.css 已在 `.list .bootstrap-select>.btn` 归零该 margin，勿删。
- 换新库版本时注意 `$.fn.selectpicker.Constructor.BootstrapVersion = '3'`（与 `require-form.js:105` 一致），
  未设置时插件按 Bootstrap 4 分支渲染会出错。

---

# 销货订单反审核：自动二审订单级联处理

> 涉及文件：`application/admin/controller/kuerp/GoodsSalesOrder.php`（examine() 反审核分支 + cascadeRemoveDocs 等辅助方法）、
> `public/assets/js/backend/kuerp/goods_sales_order.js`（examine_f 确认弹窗）

## 规则

- **自动二审**（审核时金额未超阈值 `sales_order_examine_money`，系统自动完成二审）的订单：判定条件
  `second_examine_status==1 && examine_id>0 && examine_id==second_examine_id`，允许审核人**直接反审核**。
- 若订单已生成销货单/退货单：系统级联对其生成的单据依次执行 **反审核（恢复库存/冲销应收）→ 删除（清除 saleord/saleexc 关联）**，
  然后订单反审核。级联复用 `GoodsSales::examine($id,2)` / `GoodsSales::del($id)`、`GoodsSalesExchange` 同名方法，
  其自身安全校验仍然生效：已对单(check_status=1)/已生成收款单/核销单/下游退货单 时中断，整单回滚并提示
  `销货单[XHDxxx]处理失败：<原因>`。
- **手动二审**（董事长 `secondExamine`，examine_id≠second_examine_id）的订单维持原限制：已生成销货单/退货单不允许反审核。
- 全程包在 `Db::startTrans()` 中（依赖 TP5 事务嵌套 savepoint），任何一步失败整体回滚。
- 操作日志：订单日志记 `反审核（级联反审核并删除：XHDxxx、THDxxx）`，销货单反审核自身会写订单日志 `取消出库`。

## 实现要点

- `runCascadeMethod()`：FastAdmin `success()/error()` 均抛 `HttpResponseException`，解析响应 JSON `code`：
  1=成功静默，0=转抛普通 `Exception(msg)`；`examine_set` 关闭时控制器以数组返回（`status=0` 同样视为失败）。
- 跨控制器实例化 `new GoodsSales()` / `new GoodsSalesExchange()` 会触发其 `_initialize()`（依赖当前已登录管理员会话，可用）；
  `del()` 要求 `isPost()`（`Fast.api.ajax` 为 POST，满足）。
- 顺带修复：反审核日志此前取不到 `purchase_code`（field 列表缺该字段），已补。
- 反审核对孤儿数据容错（2026-10-08）：`GoodsSales::examine()` 反审核分支中，单据明细引用的商品档案或
  `kuerp_goods_stock` 库存行不存在时（商品被删/库存初始化重建），跳过该行库存恢复而非报"系统数据错误"阻断；
  级联反审核（上一节）因此对历史孤儿单据同样可用。销货单反审核不删 `kuerp_goods_saleord` 关联（仅 `del()` 删），
  所以"反审核销货单"后销货订单仍显示已生成销货单，须删除销货单（或走销货订单的直接反审核级联）才解除。

---

# 外协订单(process_order)使用BOM模版后 入库成品数量 ↔ 出库原材料数量 联动（2026-10-15）

> 涉及文件：仅 `public/assets/js/backend/kuerp/process_order.js`（纯前端表单联动，后端零改动）

- **填入**：[使用BOM模版] 选中模板后，父件行记 `data-bom-base=父件number`（`kuerp_goods_bom_info.number`，通常为1），每个子件行记 `data-bom-ratio=子件number`（BOM子件数量为**1个父件**所需量，见 `Assemble.php:571` 的 `bom_number×父件数量` 约定），子件初始出库数 = ratio × base（此前固定填 `v.number`，base≠1 的模板会少填，此处顺带修正）。
- **联动**：修改父件行（`data-bom-base` 存在的行）`.number` 时，所有带 `data-bom-ratio` 的出库行同步为 `ratio × 新父件数量`，并重算该行 `all_money`（单价×新数量）。例：BOM 1A=2b+4c+5d，父件改 5 → 出库变 10/20/25。
- **解除**：某出库行数量被**手动**修改时移除其 `data-bom-ratio`（程序化赋值不触发 change，不会误解除）——手动微调过的材料行不再被后续父件数量变化覆盖。手动新增的出库行无该属性，天然不参与联动。
- 仅 add 页有 [使用BOM模版] 按钮（edit.html 无）；处理函数在共用 `bindevent` 中，纯 DOM 属性驱动，无后端/存储依赖。

---

# 商品常用客户标签 + 销货订单选货按客户优先排序（2026-10-15）

> 涉及文件：`addons/kuerp/migration_add_goods_frequent_customers.sql`（新迁移）、
> `application/admin/controller/kuerp/Goods.php`（index 列表文本 + chosegoods 排序/标记）、
> `application/admin/view/kuerp/goods/add.html|edit.html`（表单字段）、
> `public/assets/js/backend/kuerp/goods.js`（列表列 + 选货弹窗）、
> `public/assets/js/backend/kuerp/goods_sales_order.js`（弹窗/联想传客户）

## 数据与表单

- `fa_kuerp_goods.frequent_customers` varchar(255) 存**逗号分隔客户ID**（刻意不复用 `fa_kuerp_customer_goodscoding`——那是"客户物料编码"，按 goods_code 关联、语义不同）。
- 商品 add/edit 页「常用客户」字段：selectpage `data-multiple="true" data-source="kuerp/customers/index" data-field="customer_name"`（多选 hidden input 提交/回显逗号串）；位于 `c-manager_warehouse_id` 同一行的右半列。`Goods::add()/edit()` 是无字段过滤的原生 insert/update，新列落库零控制器改动——**列不存在时保存会直接报 Unknown column，迁移必须先执行**。
- 商品列表（layui）新增「常用客户」列：`Goods::index()` 逐行后处理里把 ID 串转名称（`frequent_customers_text`，已删客户ID降级为空段）。

## 选货优先排序

- `Goods::chosegoods()`（选货弹窗表格 + 销货订单行内联想共用的唯一接口）AJAX 查询：`input('customers_id')` **(int) 强转后 >0** 才加 `orderRaw('FIND_IN_SET(<cid>, frequent_customers) DESC, id ASC')`；未传/空 = 字节级原行为（该接口被 15+ 单据共用，勿无条件改排序）。该查询无 SQL 分页，PHP 内按属性变体展开后 array_slice 分页，故排序全局生效于所有页。
- 响应行带 `is_frequent` 标记（仅 customers_id>0 时返回，属性变体随父件）；弹窗商品名列据此前置红色 `[常用]`。
- 仅**销货订单**（goods_sales_order.js）传客户：弹窗 URL 拼 `?customers_id=`（`openCustomersId`，避开回调内已有 `customers_id` 同名 var），行内联想 POST data 加 `customers_id`。**其他单据（购货、销货单、组装等）不传参、排序不变**——若日后要放开，在对应 opener 处照抄传参即可。
- 弹窗侧 `goods.js chosegoods()` 用正则从 iframe `location.search` 取 `customers_id`，拼进 `table.render` 的 **url 查询串**（仿同文件 chosesale 先例）。勿改成 `where` 注入——layui `table.reload` 会整体替换 where 对象，翻页/搜索后参数就丢了；URL 查询串则由 layui 自动保留。
- 扫码枪路径（`getGoods` 按条码精确匹配）不涉及排序，未改。

---

# 工序小时看板（process_board，2026-10-16）

> 涉及文件：`addons/kuerp/migration_add_process_board.sql`、`addons/kuerp/migration_add_process_board_chart_rule.sql`、
> `application/admin/controller/kuerp/ProcessBoard.php`、`application/admin/model/kuerp/ProcessBoard.php`、
> `application/admin/view/kuerp/process_board/{index,add,edit,chart}.html`、
> `public/assets/js/backend/kuerp/process_board.js`、`application/admin/lang/zh-cn/kuerp/process_board.php`、
> `addons/kuerp/Kuerp.php` $menu（数据面板组下新增 kuerp/process_board）

## 结构

- **一天一条看板**：`fa_kuerp_process_board.board_date`（Unix，当天零点）唯一键；add/edit 均做重复日期拦截。
- 明细 `fa_kuerp_process_board_info` 每小时一行（board_id 关联，edit 删旧插新，del 级联）。
- 行字段：时段文本 / 目标 / 累计目标 / 组装单号(可空) / 型号(手填) / 一次合格 / 累计合格 / 差异 / 状态 / 不良 / 返工合格 / 备注。
- 菜单：数据面板 → 工序小时看板（fa_auth_rule pid=86，迁移内用派生表子查询解析 kuerp/process_board 自身 id 作子规则 pid）。

## 计算口径（服务端 buildBoardData 权威计算，前端 JS 同步预览）

- 累计目标逐行累加；累计合格取提交值（留空则=前行累计+本行一次合格）；**差异=累计合格−累计目标（不足为负数）**；**累计合格≥累计目标 → OK，否则 NG**。
- 日汇总落主表（列表直读）：一次合格=Σ、返工合格=Σ、总合格=两者和、一次不良=Σ、**返工后不良=max(一次不良−返工合格,0)**、
  一次良率=一次合格/(一次合格+一次不良)、最终良率=总合格/(一次合格+一次不良)（分母0→0）。

## 前端要点

- **时段生成器**：开始/结束整点下拉（默认 8 点–20 点）+ [生成时段]按钮 → 每小时一行（支持跨天如 20→8），有数据时 Layer.confirm 清空重建；行可自由增删/改时段文字（午休折半直接改文本）。**批量设置目标**：旁边 `#batch_target` 输入框 + [批量填充]按钮 → 一键填充所有时段行的目标（有非零目标时 Layer.confirm 覆盖确认），填后 `recalc('keep')` 重算累计目标/差异/状态/汇总。
- **动态行的组装单号 selectpage**：模板行 input 带 `assemble_code_src` 类（**不带** selectpage 类，避免被 `Form.api.bindevent` 初始化）；克隆成新行后 JS 移除 src 类、加 selectpage 类并手动 `$input.selectPage({eAjaxSuccess...})`。服务端渲染的已有行直接 `class="form-control selectpage" value="{$info.assemble_code}"`（插件自动取 input value 作初值）。数据源 `kuerp/assemble/index`（其 index 已有 keyField→selectpage 转发），`data-primary-key="purchase_code" data-field="purchase_code"`（直接存单号串）。
- 累计合格数量默认自动级联（改任意行"一次合格"→ full 模式重算该行及之后所有累计），但**可手改**（keep 模式只重算差异/状态/汇总，下次改动"一次合格"时会被级联覆盖，同 Excel 行为）。
- 模板行各 input 带 `name="row[xxx][]"`（提交时多出一组空值，服务端按"全空行跳过"处理）；只读列（累计目标/差异/状态）**无 name**，服务端重算。
- **每小时目标/实际对比图只存在于独立大屏页**（2026-10-16 用户明确：add/edit 编辑页不放内嵌图表，`#board_chart` 面板与 process_board.js 的 echarts 依赖/renderChart/图表数据收集已全部移除，JS define 也不再引 'echarts'）。
- **独立图表大屏页**（2026-10-16）：`ProcessBoard::chart()` + `chart.html`（完全独立 HTML，同 pickingpage 模式，不 include menu），列表行 [图表] 按钮 `Fast.api.open` 95%×92% 打开——**看板背景图/对比图都在这个弹窗页，编辑页没有**。背景=银河实拍图 `public/assets/img/start.jpg`（**fixed 定位 div 层实现**：`.bg-photo`（`position:fixed` + `background-image:url("__CDN__/assets/img/start.jpg") cover`）+ `.bg-veil`（半透明压暗层 `rgba(6,12,35,.45→.66)`）+ 内容 `.board-wrap z-index:1`。**不用** body 多层 background + `background-attachment:fixed`——body 带 `overflow-x:hidden` 时该属性在 iframe 里可能整层不渲染（用户实测"看不到背景图"后重写为 div 层）。另加 JS `new Image()` 预加载 onerror 降级：图 404 时把 .bg-photo 换成 CSS 星点，避免纯色背景）；顶部 8 张毛玻璃参数卡（一次合格/返工合格/总合格/一次良率/最终良率/一次不良/返工后不良/目标合计，日期在标题副行）；下方图表：**目标=金色折线(#ffd04b)、实际合格=柱状**（OK 青绿渐变/NG 红渐变，`echarts.graphic.LinearGradient`，柱色按"该时段一次合格≥目标"——与行上累计口径 OK/NG 不同），全部柱/点带数值标签，echarts 用裸 `<script src="__CDN__/assets/js/echarts.min.js">` 引入（UMD 全局 echarts，**不要写 `{__CDN__}`**——花括号会被 TP 模板引擎吃掉变成空 `src="{}/..."`；裸 `__CDN__` 占位符由 View 输出过滤器 str_replace 替换，`<style>` 块内同样生效）。明细数据经 `{$info_list|json_encode}` 内联为 JS 变量。权限规则 `kuerp/process_board/chart`（`migration_add_process_board_chart_rule.sql`，已同步 Kuerp.php $menu 与 index.html 行按钮 `$auth->check`）。站点 nginx vhost（PhpWebStudy）docroot=public、`view_replace_str.__CDN__`=''，`/assets/img/start.jpg` 已实测 200；浏览器看到旧页面时强刷（Cmd+Shift+R）即可。
- ⚠️ **模板里不要在 `<?php ?>` 原生块内写含 `{$var}` 的字符串**（如 `echo "<option value='{$h}'>"`）：TP 模板引擎会连 PHP 块内的 `{$h}` 一起编译成 `<?php echo $h; ?>`，输出的 HTML 选项值变成乱码（本次时段下拉框 24 项全废的根因）。整点下拉用控制器 `assign("hourList", range(0,23))` + `{volist}` 渲染。
- ⚠️ **`{:(...)}` 形式的输出标签不编译**（如 `{:(round($row['x']*100,2))}` 会原样输出到 HTML）：外层多包一层圆括号的 `{:(...)}` TP5 不识别。正确写法去掉外层括号并用点语法取键：`{:round($row.first_pass_yield*100,2)}`（参照 `goods_purchase/summary.html`）。edit.html 汇总面板曾因此带着原样字符串渲染，被 JS recalc 覆盖掩盖；chart.html 无 recalc 才暴露，两处已同改。

---

# 计划排期审核填写"预计交付日期"（2026-10-18）

> 涉及文件：`addons/kuerp/migration_add_goods_sales_expected_delivery.sql`（需手动执行，本次已对 erp_licheng 执行）、
> `application/admin/controller/kuerp/GoodsSalesSchedule.php`、
> `public/assets/js/backend/kuerp/goods_sales_schedule.js`、`goods_sales.js`、
> `application/admin/lang/zh-cn/kuerp/goods_sales_schedule.php|goods_sales.php`

- **字段**：`fa_kuerp_goods_sales.expected_delivery_date` **varchar(10)**（存 `YYYY-MM-DD` 字符串，非时间戳——layui 行内编辑需要直接可读的值）。计划排期与销货单共用同一张表（`GoodsSalesSchedule` model `$name='kuerp_goods_sales'`），字段两边天然同步。
- **审核弹窗**：排期列表工具栏 [审核]（layui toolbar `examine` 事件）先弹 layer 弹窗填预计交付日期（laydate，默认当天，必填校验），确定后 `examine?examine=1&ids=..&expected_delivery_date=..`；**批量勾选多条时统一写入同一日期**。后端 `examine()` 分支1 读该参数、正则校验 `^\d{4}-\d{2}-\d{2}$`，非空才并入 update（不传参 = 原行为，其他调用方零影响；已 grep 确认无其他 JS 调用方）。反审核**不**清除该日期。
- **二次修改**：列表"预计交付日期"列（备注列前）带 `edit: 'text'` 行内编辑（layui 2.9.16），`table.on('edit(table)')` 校验格式后 POST `setExpectedDelivery`（`$noNeedRight` 已加 `setexpecteddelivery`，POST ids+date，支持批量；非法格式/更新失败时 `table.reload` 还原单元格）。清空单元格 = 置 NULL。已审核/未审核单都可改。
- **销货单界面**：`goods_sales.js` 列表在备注列前加同名列（**仅展示**，无 edit），供销售查看。
- **不打印**：所有打印模板（printpage 等）未引入该字段。
- 测试快照（curl 登录实测，测后已清理测试行并还原密码/验证码配置）：带日期审核落库、批量同日期、行内改期、非法格式拒绝、清空置 NULL、反审核保留日期、index 列表返回字段。

---

# 出库流程调整：计划申请出库 → 仓库「出库管理」审核执行（2026-10-18）

> 涉及文件：`addons/kuerp/migration_add_goods_outbound_apply.sql`（已对 erp_licheng 执行）、
> `application/admin/controller/kuerp/GoodsOutboundApply.php`（新）、`application/admin/model/kuerp/GoodsOutboundApply.php`（新）、
> `application/admin/view/kuerp/goods_outbound_apply/{index,detail}.html`（新）、
> `application/admin/view/kuerp/goods_sales_outbound/apply.html`（新）、
> `public/assets/js/backend/kuerp/goods_outbound_apply.js`（新）、
> `GoodsSalesOutbound.php`、`GoodsSales.php`（反审核拦截）、`goods_sales_outbound/{index.html,js}`、
> 两处 lang、`addons/kuerp/Kuerp.php` $menu、`addons/kuerp/install.sql`

## 新流程（彻底移除直接出库，无旁路）

```
计划部门（销售单出库页 kuerp/goods_sales_outbound）          仓库部门（新菜单「出库管理」kuerp/goods_outbound_apply，挂仓库组 pid=185）
├── [申请出库] 弹窗填本次各产品出库数量（≤剩余未出）   →      ├── 列表 CKSQ 单号（申请单）
├── [撤销申请]（仅待审核 status=0 可撤，status→3）            ├── [审核出库] = 详情弹窗内逐行可改数量 → [同意出库并执行]（整批扣库存）
└── 列表看出库状态(未/部分/全部出库)+申请状态(4态)             └── [拒绝出库] 必填原因（status→2）
```

## 关键设计

- **权限收口**：`GoodsSalesOutbound::$noNeedRight` 移除 `outbound/saveoutbound`，且**不建**对应 auth_rule → 非超管直连一律"你没有权限访问"（已实测）；超管 bypass 但 UI 入口已删。`saveOutbound($ids=null,$outData=null)` 签名扩展：内部调用传数组（`GoodsOutboundApply::runSaveOutbound`），HTTP 调用走 `post('out_data/a')`。
- **表**：`fa_kuerp_goods_outbound_apply`（apply_code CKSQ=前缀type 29 / sales_id / purchase_code / status enum('0','1','2','3') 待审/已同意/已拒绝/已撤销 / out_data text 存同意时实际出库数量JSON审计 / refuse_reason / 撤销复用 examine_admin_id+examine_time 字段）+ `_info`（apply_id / **sales_info_id** / goods_id / stores_id / number）。
- **多申请并存**（2026-10-18 按用户需求放开）：同一销货单可**同时存在多条申请**（含多条待审）。申请数量口径 = `number - out_number - 待审占用`（待审占用 = 该行在所有 status='0' 申请中的数量合计，`GoodsSalesOutbound::pendingApplyQtyMap()`）；apply.html 明细行加「待审申请中/还可申请」两列 + 底部「历次申请记录」面板（单号/申请人/时间/申请内容摘要 goods_summary/状态/拒绝原因，status=0 行带撤销按钮 → applycancel 传**申请ID**）。仓库同意一条即出库一次，其余待审申请的可出数量随 out_number 增长自动减少（同意时仍按实时 `number-out_number` 封顶，超量报错回滚，先到先得）。
- **applyCancel 参数（2026-10-30 改为显式字段）**：`apply_id` 撤该条申请（apply.html 历次记录行）；`sales_id` 撤该销货单最新一条待审（列表行按钮）；旧参数 `ids` 仍在（先当申请ID且须 status=0，否则回落当销货单ID）。⚠️ 申请ID 与销货单ID 是两个独立自增序列，旧写法把 `ids` 直接当申请ID查 → **撞号时撤错单/报「该出库申请不存在或不是待审核状态」**（实测：销货单 id=5 有两条待审申请，点列表[撤销申请]恒失败，因为申请表恰好有 id=5 的已拒绝单）。列表按钮显隐：申请出库 = `outbound_status!=2`；撤销申请 = `apply_pending_count>0`；申请记录 = `outbound_status==2 && apply_count>0`。
- **「申请记录」只读入口（2026-10-30）**：已全部出库时原「申请出库」按钮消失 → 历次申请/出库记录打不开。现列表行在同一位置改显灰色 **[申请记录]**（`lay-event="applyrecord"`，与申请出库共用 `apply` 权限），打开 `kuerp/goods_sales_outbound/apply?ids=X&view=1`：同一弹窗但标题改为「出库申请 / 出库记录」、隐藏提交按钮与本次申请数量输入（服务端 `$isView` + `$allOut` 两个开关，`allOut` 由 SUM(number)/SUM(out_number) 判定，即使不带 view 参数、单据已全部出库时也不会误提交）。历次记录表新增 **「实际出库数量」** 列（解析 `out_data` JSON 按申请时的 goods_code 还原）。列表 `index()` 额外下发 `apply_count`（各状态申请合计）。行操作列宽 180→250（三个按钮同行不再被 layui 截断成 `…`）。
- **编辑/审核侧口径**：`GoodsOutboundApply::edit()` cap = `number - out_number - 其他待审占用(排除本申请, otherPendingQtyMap)`；detail.html/edit.html 加「其他待审占用」列供仓库/计划参考。其他待审占用计算与申请侧同口径（错报文案带「其他待审申请已占用 N」）。
- **examine() 事务结构**：校验全在事务外 → `Db::startTrans` → 条件 UPDATE `WHERE id=? AND status='0'`（并发抢占，后到者"该出库申请已被处理"）→ `runSaveOutbound(sales_id, $finalOutData)` → commit；catch 整体回滚，**申请回到待审**（库存不足时报"销货出库执行失败：…库存不足"，可改小数量重审，已实测零库存变动）。`runSaveOutbound` 逐字仿 `GoodsSalesOrder::runCascadeMethod`：**先解析 JSON code，仅 code!=1 才 throw**（success 也抛 HttpResponseException，成功分支误回滚是最易错点）；`examine_set` 关闭时数组返回 status=0 同样视为失败。
- **反审核拦截**（堵部分出库后反审核超回库存）：`GoodsSalesOutbound::examine` 与 `GoodsSales::examine` 反审核分支 guard-loop 内，`SUM(sales_info.out_number)>0 → '该销货单已有出库记录（出库申请已执行），不能反审核'`（两处都含 `$examine_set!=1` 数组返回双写）。
- **撤销为软撤**：`applyCancel` 只把 status 置 '3' 并回填 examine_admin_id/examine_time（历次记录里显示「已撤销」+ 撤销人/时间），不删数据。
- **撤销入口在历次记录里（2026-10-30）**：销货单出库**列表行不再有[撤销申请]按钮**（`#barDemoOutbound` 只剩 申请出库/申请记录 + 编辑，列宽回到 180），撤销按钮移到 `apply.html`「历次申请记录」表的 **操作列**（每条待审记录各自的 [撤销申请]，`$auth->check($nowmenu.'/applycancel')` 门禁，非待审显示 `--`）。JS `Controller.apply` 的 `.btn-cancel-apply` 传 `apply_id` → 撤销后刷新父列表并 `location.reload()`。列表 JS 里原来的 `applycancel` 行事件分支已删除。
- **编辑/删除申请**（2026-10-18 补充，规则 id 720/719）：`edit($ids)` 仅 status='0' 可改各明细申请数量（POST `number[apply_info行ID]`，GET/POST 共用 `assignApplyDetail()` 装配——detail()/edit() 同构；校验超剩余未出/全0/负数/明细已变更，条件 UPDATE 守卫并发）；`del($ids)` status='1'（已同意出库，库存已扣）**禁止删除**，其余级联删 apply_info（delete 再加 `where status<>'1'` 双保险，事务包裹）。列表行按钮：编辑仅 status=0 显示、删除仅 status!=1 显示，均带 `$auth->check` 门禁 + `hiddens`。
- ⚠️ **行操作模板 ID 不能叫 `barDemo`**：`kuerp/common/menu.html` 全局自带默认 `#barDemo`（仅 编辑/删除），且每个 kuerp 页面第一行就 include 它——页面里自定义的 `barDemo` 排在 DOM 第二位，layui `toolbar: '#barDemo'` 取第一个匹配 → 行按钮被全局默认版顶掉（症状：列表只剩编辑/删除、自定义按钮全消失/点了没反应）。goods_sales_outbound 用 `barDemoOutbound`、goods_outbound_apply 用 `barDemoApply` 避开；新增列表页自定义行按钮时同理必须换名。
- **index 计算列**（goods_sales_outbound）：批量 `SUM(number)/SUM(out_number) GROUP BY purchase_code` → outbound_status 0/1/2；apply 表按 sales_id 最新一条 → apply_status（无申请/已撤销=''）。JS 端 `{{# if}}` 与 TP `$auth->check` 共存控制按钮显隐（apply_status=='0' 只显示撤销，outbound_status!=2 且无待审才显示申请）。
- ⚠️ **行按钮的 `lay-event` 必须在 JS 的 `table.on('tool(table)')` 里有分支，且每个需要 JS 的页面动作都要在模块里建同名 `Controller.xxx`**（2026-10-27 修复）：`require-backend.js` 只调 `Controller[Config.actionname]()`，方法不存在时只查 `Controller._empty`，两者都没有就**静默什么都不执行**。此前 `barDemoOutbound` 模板里的 `apply`/`applycancel` 两个 `lay-event` 在 `goods_sales_outbound.js` 里没有分支、`apply` 页也没有 `Controller.apply`（apply.html 无任何 `<script>`）→ 表现为「点申请出库/撤销申请完全没反应」，apply 弹窗内表单也不会走 ajax 提交、历次记录的 `.btn-cancel-apply` 无处理。现已补：列表 `apply` 打开弹窗、`applycancel` 确认后传**销货单ID** 撤销；`Controller.apply` 里 `Form.api.bindevent($("form[role=form]"))`（成功后默认关弹窗+刷父列表）+ `.btn-cancel-apply` 事件委托（传**申请ID**，成功后刷父列表并 `location.reload()`）。
- **本次申请出库数量默认 0**（2026-10-27）：`apply.html` 的 `.out_number_ipt` 由 `value="{$item.can_apply}"`（默认可申请数量，不填就会整批报上去）改为 `value="0"`，`max` 仍为可申请数量；`Controller.apply` 的 `Form.api.bindevent` 第 4 参（submit 前置回调）校验「所有 `.out_number_ipt` 均 ≤0 → `Toastr.error('请至少填写一项申请出库数量')` 并 return false」，不发请求（后端本就「全 0 → `请填写出库申请数量`」兜底）。与组装单/外协加工的实际出库弹窗同一套约定。
- **detail.html**：`apply_number`=申请时数量（apply_info.number），`number`=实时计划数量（JOIN sales_info），两者不一致显示"明细已变更"红字且禁止同意（sales_missing）；本次出库数量 input `name="out_data[{$item.sales_info_id}]"` 仅 status='0' 可编辑，POST 缺省回退 apply_info.number。
- **已实测**（curl 登录，测后已清理测试数据、还原 config.php login_captcha=true 与 admin/wangjian 密码）：申请落库/重复拦截/全0拦截/超量拦截/未审核单拦截、撤销 0→3、同意（调整数量 5/8）→ 库存 77→72/100→92 + out_number + goods_log business_type='4' + out_data JSON、再审拦截、超量cap拦截、库存不足整体回滚、拒绝空原因拦截/带原因 status=2、两处反审核拦截、wangjian(非超管)直连 saveOutbound/outbound 均无权限。多申请并存（2026-10-18）：同单三条待审(A/B/D)并存、第4条超待审占用拦截（文案带剩余量）、apply.html 历史面板+撤销按钮渲染、编辑按"其他待审占用"封顶（拦截文案带占用值）、同意A出库6/10后撤销B、同意D再出库1（库存/日志/状态全部正确）、detail「其他待审占用」列、列表 apply_pending_count。⚠️ TP 模板输出标签里三元不能套 `|date=` 过滤器语法（编译出 `$var|date=...` 原样残留导致 parse error），用 `date('Y-m-d',$var)` 函数调用。
- **权限分配**（角色管理 UI 手工，超管天然全通）：计划部/计划专员需 639 菜单 + `goods_sales_outbound/apply|applycancel`；仓储部需 `goods_outbound_apply` 菜单+index/detail/examine/refuse。侧栏出现「出库管理」需清 runtime/cache。

---

# 采购汇总改版 + 导出Excel（2026-10-20）

> 涉及文件：`application/admin/controller/kuerp/GoodsPurchase.php`（`summary()` 重构 + 新增 `export()`/`buildSummaryData()`）、
> `application/admin/view/kuerp/goods_purchase/summary.html`

## 展示格式（列变化）

- **新增**：序号 | 日期 | 单号 | 供应商名称 | 型号 | 规格2/厂商 | 规格3/封装
  （后三列取 `kuerp_goods` 的 `goods_specification_id` / `vendor_name` / `packaging`，与组装单 outbound_edit 三列口径一致）
- **去掉**：仓库、单位换算、副单位数、采购费用
- **保留**：供应商类别、商品编号、商品名称、计量单位、基本单位数量、单价、购货金额、税额/价税合计（`taxes` 开关控制）
- 辅助属性仍单独占一列（`class="goods_attr_sd"`，属性功能全局关闭时隐藏，恢复见 [[商品属性]] 开关）

## 汇总口径变更

原：`GROUP BY i.goods_code,i.goods_attr,i.stores_id`（跨单据、跨仓库合并，无法挂日期/单号）。
现：**`GROUP BY i.purchase_code,i.goods_code,i.goods_attr`** —— 同一采购单内同商品(+属性)合并为一行（仓库不再区分），
不同单据的同一商品各占一行；`ORDER BY p.purchase_time ASC, i.purchase_code ASC, MIN(i.id) ASC`，序号按此顺序从 1 递增。
供应商名称/日期 JOIN `kuerp_goods_purchase p`（**注意 `kuerp_goods_purchase_info.supplyer_code` 实际为空串**，不能从明细表取），
`purchase_time` 为 bigint 时间戳，页面与导出均格式化为 `Y-m-d`。

## 导出

- `export()`（POST `ids=1,2,3`）与页面展示**共用 `buildSummaryData($idArr)`**（返回 list/totals/taxes/price_decimal/number_decimal），
  列顺序、格式化口径（`price_decimal`/`number_decimal`）完全一致；末行「合计」合并 A:L + 数量/单价/金额（有税时追加税额/价税合计）。
- 用 `\PhpOffice\PhpSpreadsheet`（全限定名，本文件未 use），`save('php://output')` + `exit`，文件名 `采购汇总YmdHis.xlsx`。
- 前端：summary.html 顶部 [导出Excel] 按钮 → 隐藏 form **POST** 提交（ajax 拿不到二进制流）；
  ⚠️ summary.html 是**裸页面（只 include bootstrap.min.css，无 jQuery、无 menu）**，所以用 `document.getElementById().submit()` 原生写法，
  action 用 `{:url('kuerp/goods_purchase/export')}`（渲染为 `/eXvpGbzZYD.php/kuerp/goods_purchase/export`）。
- `export` 已加入 `$noNeedRight`（与 `summary` 同，未建 auth_rule）。
- ⚠️ **合计行 colspan 要跟着隐藏列走**：`属性` 列用 `goods_attr_sd{display:none}` 隐藏后该列**不占表格列位**（浏览器实测 rect 0-0），
  但 colspan 仍按 DOM 里的列数算就会多占一列 —— 表现为合计的数字整体右移一格（首版即踩此坑）。
  故 colspan 写成 `{if 辅助属性开启}12{else/}11{/if}`；**以后在本表加/减前置列必须同步改这个数**。
  本页 `{* *}` 不是 TP5 注释语法（会被原样输出），TP5 用 `{/* */}` 或 `{// }`。
- 已实测（curl 登录 erp.licheng.test，测后已清理测试单 GHDTEST0001）：两单据 4 行渲染与分组正确（同单同商品两仓库 3+5 → 合并 8 行、金额 30+50=80）、
  导出 xlsx 15 列含表头/合计（数量 12、单价 8.33、金额 100）、空 ids 与 summary 无 ids 均返回提示。

---

# 购货订单明细 Excel 导出 / 导入（导出与导入共用同一份模板，2026-10-20）

> 涉及文件：`application/admin/controller/kuerp/GoodsPurchaseOrder.php`（新增 `export()` / `parseimport()` + `$noNeedRight`）、
> `application/admin/view/kuerp/goods_purchase_order/{add,edit}.html`（明细表上方 `.po-io-bar` 工具栏）、
> `public/assets/js/backend/kuerp/goods_purchase_order.js`（`changeGoods` 新增第 8 参 `opts`、`formatInfo` 新增第 12 参 `nowField`）

## 入口与流程

- add/edit 页明细表格上方新增 [导出] [导入] 按钮 + 隐藏 `<input type="file" id="po-import-file">`（**无 name**，不参与表单提交）。
- **导出**：JS 收集当前表格明细为 JSON → 隐藏 form（`target` 指向隐藏 iframe）POST `kuerp/goods_purchase_order/export` 下载 xlsx。
  ajax 拿不到二进制流必须走 form 提交；`action` 用 `Fast.api.fixurl(...)`（`$.ajaxSetup` 的 URL 修正只作用于 `$.ajax`）。
  **明细为空时导出的即为空白模板**，所以导出与导入天然共用一份模板。
- **导入**：选文件 → FormData POST `parseimport`（一次请求，不走 `ajax/upload`）→ 返回解析结果 → **替换**表格全部明细行。
  导入只解析校验、**不写库**；真正落库仍走 add/edit 原有校验（不能编辑已审核/已生成采购单的单据、请选择仓库、商品不存在…）。

## 模板结构（3 个 sheet）

1. `购货订单明细`（sheet 0，活动表）：表头第 1 行、数据从第 2 行起，预留 500 行。
   列：商品编号* / 商品名称 / 商品单位* / 仓库* / 供应商 / 数量* / 采购单价 / 折扣率% / 税率% / 备注。
2. `Lists`（隐藏）：下拉数据源 —— A 商品编号、B 商品名称、C 单位名、D 仓库名、E 供应商「编号 名称」、F 供应商编号。
3. `填写说明`：填写规则。

- 四个下拉（商品编号/单位/仓库/供应商）用 `DataValidation::TYPE_LIST` + `formula1 = Lists!$A$2:$A$n` **跨表区间引用**
  （内联列表上限 255 字符，1132 个商品必须用区间）。
  ⚠️ **必须 `setShowDropDown(true)`**：PhpSpreadsheet 的 Xlsx writer 把该值**取反**写出（`showDropDown="0"` 才是显示下拉），
  不设置（默认 false）会写出 `showDropDown="1"` 把下拉**藏起来**。
  列表校验还要显式 `setOperator('')`，否则写出多余的 `operator="between"`。
- 商品名称列写公式 `=IFERROR(VLOOKUP($A2,Lists!$A$2:$B$n,2,0),"")`（导出时同样写公式而非写死名称），选编号即自动带出名称。
- 商品编号/单位/仓库/供应商/备注 一律 `setCellValueExplicit(..., DataType::TYPE_STRING)`，避免纯数字编号被 Excel 转数字丢前导零。

## 导入解析要点

- 表头**按名称容错匹配**（`excelColumns()` 的 alias + `normalizeExcelHeader()` 去掉空白 / `*` / 结尾 `%`），允许表头前有标题行
  （扫描前 10 行找「商品编号」）；sheet 先按名 `购货订单明细` 取，取不到回退 sheet 0。硬性必需列只有 `商品编号`、`数量`。
- **有错整单拒绝**（`code=0` + 前 8 条带行号的中文错误），不做部分导入（前端语义是"替换全部明细"）：
  商品编号不存在 / 数量非大于 0 的数字 / 仓库名不存在 / 单价·折扣率·税率非数字或为负。
- 软性问题降级为 `warnings`（仍导入，前端 `Toastr.warning` 提示）：
  仓库留空→按 `goods.manager_warehouse_id`（该商品无默认仓库则报错）/ 单位名不适用于该商品→改默认单位 /
  供应商名不存在→忽略（改按商品默认供应商）。
- 单位解析：候选 = `goods.unit_id` +（`more_unit=1` 时）`kuerp_unit_conversion` 中 `main_unit=goods.unit_id` 的 vice_unit，按名称在这批 id 里找；
  **匹配不到或留空返回 `goods_unit=0`**，前端传 `''` 交给 `changeGoods()` 走它原有的默认单位逻辑（含 `warehousing` 特例）。
- 供应商解析：整串 label 精确匹配 → 单独编号匹配 → `explode(' ')` 取首段当编号 → 都不行则告警忽略。
  下拉选项文本与页面 `<select>` 一致（`编号 名称`），所以往返能原样对上。
- 下拉取数与页面同口径：商品/仓库/供应商均 `status='1'`。

## 前端落行（`applyImportedRows`）

- 保留 `#goods_table` 第 0 行（表头）与第 1 行（模板行），其余行整体移除后重建 ——
  edit 页的 `supplier-group-header` 分组标题行与「生成采购单」按钮一并清除，保存后由服务端按新明细重新分组。
  （两页模板行都在 index 1：add 页是 `.goods_id0`，**edit 页是无类名的 `<tr style="display: none">`**，所以只能用 `rows[1]` 取。）
- 每行先填 goods_code / stores_id / supplier / remark，再调 `changeGoods(...)` 复用原有的单位·库存·价格·税金计算链。
- ⚠️ **edit 页所有行都没有 `sales_order_id` 类名**（只有 add 页模板行有），要按行读写该字段必须用 `[name="row[sales_order_id][]"]` 选择器。
- 替换前按 商品编号 记录 `sales_order_id` 与 `goods_note`（页面上不可手工填写的只读列），替换后回填；
  否则「导出→导入」往返会把排期关联和商品备注清空。
- `changeGoods` 新增可选第 8 参 `opts = {unit_price, discount, tax_rate}`（**不传时行为与改动前逐字一致**）：
  内部 `doFormatInfo(v)` 在 `opts.unit_price` 有值时以**不含税单价**为准，调 `formatInfo(..., obj, 'unit_price')`
  （`formatInfo` 新增第 12 参 `nowField` 透传给 `changeGoodsInfo`），让后端按单价回算含税单价/金额/税额；
  `opts.tax_rate` / `opts.discount` 覆盖 `v.tax_rate` / `0`。未传 `opts.unit_price` 时保留原有 `buy_price`(含税) 与默认价格两条分支。
- 按钮状态：已审核单据（`examine_set=1 && examine_status=1`）时 `$('.btn').attr('disabled')` 会把两个按钮一起禁掉，
  之后 `$('#btn-po-export').removeAttr('disabled')` 恢复导出（导出只读不改单据），导入保持禁用。
- **能否导入由服务端算好下发**：`edit()` 里 `assignconfig('importBlockReason', ...)`（空串=可导入），
  前端只做 `if (Config.importBlockReason) { 禁用 + title 提示 }`，不在前端重复实现业务规则。
  四条拦截与 `edit()` POST 的保存校验**逐条对齐**（顺序即优先级）：
  ① 结账 `Checkout::checkOutTime($row['purchase_time'])`（`purchase_time <= 最近结账日` 即拒）；
  ② 已审核 `examine_set=1 && examine_status=1`；③ `purchase_type=1` 且 `kuerp_goods_purord.order_id`=**订单id** 有行；
  ④ `purchase_type!=1` 且 `kuerp_goods_excpur.purchase_id`=**订单id** 有行。
  为何必须拦：导入是"替换全部明细"，edit() 保存时明细行是删了重插 → `info.id` 全变，
  而 `kuerp_goods_purord_info.order_id` 存的是**明细行id**，已生成采购单的关联会被打断（不是策略问题，是数据完整性）。
  ⚠️ 不要退回用 `Config.supplierGroups[].generated` 判断：那是**行级**标志（`kuerp_goods_purord_info.order_id`=**明细行id**），
  只在 `purchase_type=1` 下有效（退货单走 `kuerp_goods_excpur`，该标志恒为 false），且 `supplierGroups` 是以供应商编号为键的**对象**
  （PHP 关联数组 json_encode 后不是数组，用 `.length` 遍历会静默失效）。

## 已验证（curl 登录 erp.licheng.test 实测；未产生测试单据——导入不写库）

- 导出：xlsx 正常（3 sheet：明细 / Lists 隐藏 / 填写说明）；A·C·D·E 下拉 `showDropDown="0"`（=显示）且区间引用正确
  （1132 / 15 / 6 / 112 项）、F/G/H/I 数字校验、B 列 VLOOKUP 公式带缓存值、商品编号为文本类型；
  文件名 `filename*=UTF-8''…` 中文名 + ASCII 回退。
- 导入：往返 2 行文件单位/仓库/供应商/数量/单价/折扣/税率/备注全部还原；表头去掉 `*` 仍匹配；sheet 名不同回退 sheet 0；
  错误路径（商品不存在 / 数量空或 0 / 仓库名错 / 单价非数字 / 折扣为负 / 空模板 / 无表头 / 非 Excel 内容 / 扩展名 .csv / 不带文件）
  均返回可读中文提示；告警路径（仓库留空→默认仓库、单位不适用、供应商名不存在）与「商品无默认仓库且仓库留空→报错」均正确；
  供应商三种写法（编号 / 编号+错名→前缀回退 / 仅名称→告警）正确；税率写成 `13%` 也能识别。
- 定价链实测：`getUnit` → `formatInfo`（`opts.unit_price` 分支实际参数）→ `formatInfo2(now_field=unit_price)` 结果自洽
  （number=3、unit_price=3.33、all_money=9.99、basic_nmber=3）。本站 `taxes.is_checked=0`，故 tax_rate 被后端强制为 0，与手工选货一致。
- 导入拦截四条分支**全部实测**：订单1（已审核+已生成采购单）→「已审核的单据不能修改明细，请先反审核」；
  临时测试单 ZZTEST-PO（未审核+`kuerp_goods_purord` 有行）→「该购货订单已生成采购单，不能修改明细」；
  ZZTEST-EX（`purchase_type=2`+`kuerp_goods_excpur` 有行）→「该购货订单已生成退货单，不能修改明细」；
  订单2（干净）→ 空串可导入；add 页不下发该 config。测试数据已删净（orders 2 / info 4 / purord 5 / excpur 0，与快照一致），
  仅 `fa_kuerp_goods_purchase_order` 自增号前进到 4。
  结账分支未构造实景（库中 `fa_kuerp_checkout` 为空，造数会改动用户期间结账状态），但走的是与保存校验同一个 `checkOutTime()` 调用。
- ⚠️ **未做浏览器点击实测**（本机无浏览器自动化）：前端落行为静态核对 —— 用脚本校验了两页视图的模板行字段齐备性、
  `#goods_table` 第 1 行为隐藏模板行、`Config.supplierGroups` 的实际 JSON 形状。

---

# 采购交货日期：采购订单选定 → 计划/仓库展示 → 采购单可改并回写（2026-10-20）

> 涉及文件：`application/admin/controller/kuerp/GoodsPurchase.php`（add/edit 存 `delivery_time` + edit 回写来源订单）、
> `GoodsPurchaseStorage.php`（index 不再隐藏 `delivery_time`）、
> `application/admin/view/kuerp/goods_purchase/{add,edit}.html`、`goods_purchase_storage/detail.html`、
> `application/admin/lang/zh-cn/kuerp/goods_purchase_storage.php`、
> `public/assets/js/backend/kuerp/{goods_purchase,goods_purchase_order,goods_purchase_order_plan,goods_purchase_storage}.js`
>
> **无数据库迁移**：四张表（`kuerp_goods_purchase[_order][_info]`）的 `delivery_time bigint(16)` 列早已存在。

## 字段与语义

- `delivery_time` 存 **Unix 时间戳**（`strtotime('Y-m-d')`），非字符串——与 `purchase_time` 同口径。
- 单据头与明细行**同时**写同一个值（`kuerp_goods_purchase[_info].delivery_time`、`kuerp_goods_purchase_order[_info].delivery_time`）。
  购货订单的 add/edit 本来就这样写（`GoodsPurchaseOrder.php` 主表 + 明细各一处），采购单本次补齐同口径。
- 界面标签统一用全局 lang key `Delivery_time` = **`交货日期`**（2026-10-20 由原「交货时间」全量改词，共 21 个 lang 文件 + `GoodsSalesSchedule.php:988` 导出表头硬编码一处；
  另有 `goods_purchase_storage.php` 原本缺该 key，已补齐）。
  ⚠️ 前端列表列头走的是 **JS `__()` → `Fast.lang()`**（`fast.js:296`）：它会把 key **转小写**再查 `Lang['delivery_time']`，
  所以改 lang 文件即生效；可用 `/eXvpGbzZYD.php/ajax/lang?controllername=kuerp.<控制器>&lang=zh-cn` 直接验证下发值。
  注意 `controllername` 必须带 `kuerp.` 前缀，否则 `Ajax::lang()` 的 `Loader::parseClass()` 找不到类，只会返回公共语言包。

## 三条链路

```
采购订单(goods_purchase_order) add/edit 已有「交货日期」输入框（laydate，默认今天）→ 存 delivery_time
        ↓ [生成采购单] 打开 kuerp/goods_purchase/add?ids=<订单id>
采购单 add 页：value = 来源订单的 delivery_time（无则今天）→ POST 落库（头+明细）→ 天然「带过去」
        ↓ 采购单 edit 页可改「交货日期」
保存时：写自己的 delivery_time，并**回写来源购货订单**（按 purchase_order_id = 订单编号 反查）
        ↓ 计划购货订单(goods_purchase_order_plan) 与采购订单共用 fa_kuerp_goods_purchase_order 表 → 自动同步
```

## 展示位置（4 个列表 + 1 个详情）

| 页面 | 改动 |
|------|------|
| 采购订单 `goods_purchase_order` | 列表 `purchase_time` 后加「交货日期」列 |
| 计划购货订单 `goods_purchase_order_plan` | 同上（与采购订单同一份表，天然同步） |
| 采购单 `goods_purchase` | 列表加列 + add/edit 表单新增可编辑字段 |
| 采购入库 `goods_purchase_storage` | 列表加列 + `detail.html` 信息区加一行；**`index()` 的 `hidden()` 里原本显式隐藏了 `delivery_time`，本次移除** |

列表列模板：`d.delivery_time` 为空时显示灰色 `—`（不要直接 `toDateString(0)`，会显示 1970-01-01）。

## 不给默认日期（2026-10-20，用户要求）

> 「交货日期不要给默认日期，避免后续错误填写」—— 空着就必须是空，系统不许替用户编一个日期。

- **add 表单一律留空**：`goods_purchase_order/add.html`、`goods_purchase_order_plan/add.html` 原为 `value="{:date('Y-m-d')}"`（默认今天），已改 `value=""`；
  `goods_purchase/add.html` 原为 `有值→订单日期 / 无值→今天`，已去掉今天的兜底 → **只从来源购货订单带值，订单没有就留空**。
- **edit 表单防 1970**：`goods_purchase_order/edit.html`、`goods_purchase_order_plan/edit.html` 原为裸 `{$row.delivery_time|date='Y-m-d',###}`，
  `delivery_time` 为 NULL 时 `date('Y-m-d', null)` 会渲染成 **`1970-01-01`**（比"默认日期"更糟的脏值）。已统一改成
  `{if condition='!empty($row.delivery_time)'}{$row.delivery_time|date='Y-m-d',###}{/if}`（与 `goods_purchase/edit.html` 同写法）。
- ⚠️ **空值必须落 NULL，不能落 0**：`strtotime('')` 返回 `false`（不是 null），直接写库会变成 `0`。
  故 `GoodsPurchaseOrder.php` 与 `GoodsPurchaseOrderPlan.php` 各 4 处（add/edit × 明细行/主表）统一改为
  `!empty($params['delivery_time']) ? strtotime($params['delivery_time']) : null`。
  批量生成路径（`batchGeneratePurchase`/`generateBySupplier`）的 4 处 `$order['delivery_time']` 保持原样——那是原样复制订单值，本来就可能是 null。
- 渲染侧各处**都已**有空值守卫，无需再改：4 个列表 JS（`if (d.delivery_time)`）、`goods_purchase_order_info.js` 跟踪表、
  `goods_purchase_storage/detail.html`、`goods_sales_schedule/printpage.html`。
  （例外：`goods_sales/edit.html:68` 的销货单交货日期仍是裸 `|date=`，属销货模块、本次未动。）
- `data-use-current="true"` 在 kuerp 模板里是**无效果的遗留属性**（全项目 JS 无任何引用），留空与否与它无关。

## 回写实现（`GoodsPurchase::edit()`）

在 `Db::commit()` 之前、明细重插之后，包在同一事务内：

```php
if(!empty($row['purchase_order_id'])){
    Db::name('kuerp_goods_purchase_order')->where(['purchase_code'=>$row['purchase_order_id']])
        ->update(['delivery_time'=>$delivery_time,'updatetime'=>time()]);
    Db::name('kuerp_goods_purchase_order_info')->where(['purchase_code'=>$row['purchase_order_id']])
        ->update(['delivery_time'=>$delivery_time,'updatetime'=>time()]);
}
```

- `$row['purchase_order_id']` 存的是**购货订单编号字符串**（不是 id），采购单 add 时由 `$params['purchase_order_id'][$k]` 行级值汇总而来。
- 主表 + 明细**都要更新**：购货订单 add/edit 保证同一订单所有明细行的 `delivery_time` 恒等于表头值，
  只改主表会让「采购订单跟踪表」(`goods_purchase_order_info`) 显示旧日期。
- 明细表 `kuerp_goods_purchase_order_info` 有 `updatetime` 列，可一起更新。

## 其它生成入口无需改动

`GoodsPurchaseOrder::batchGeneratePurchase()`（多供应商自动生成）与 `generateBySupplier()` 本来就复制
`$order['delivery_time']` → 采购单主表、`$item['delivery_time']` → 采购单明细；`GoodsPurchaseOrderPlan` 里同名方法为复制代码，同样已带。
只有「打开 add 表单手填」这一条路径原先漏了，本次补齐。

## 权限/边界

- 采购入库 `GoodsPurchaseStorage` 只有 详情/打印/入库 三个行按钮，**无编辑入口** → 仓库侧只读，改不了交货日期。
- 采购单 edit 页受既有校验约束：已审核（`examine=1 && examine_status=1`）/已对单/已生成退货单 均不能编辑，回写自然也走不到。
- `采购计划 goods_purchase_plan` 是**独立控制器 + 独立视图/JS**（虽共用 `fa_kuerp_goods_purchase` 表），本次未改，不受影响。

## 已验证（curl 登录 erp.licheng.test 实测，测试数据已删净）

- 4 个 index 接口均返回 `delivery_time`；`goods_purchase_storage` 的 `all_money` 仍被隐藏。
- 表单渲染（去默认日期后重测）：`goods_purchase_order/add`、`goods_purchase_order_plan/add`、`goods_purchase/add`（无来源单）均 `value=""`；
  `goods_purchase/add?ids=1`→2026-10-11（从来源订单带值）、`.../edit?ids=1`→2026-10-11（自身值）；
  用 `delivery_time=NULL` 的临时订单 `ZZNULL-DT` 验证 edit 页渲染**空**（修复前会显示 1970-01-01）。
- 空值落库实测：`ZZNULL2` 订单 **add** 传空 → 主表 + 明细 `delivery_time IS NULL`（不是 0）；
  **edit** 传 2026-11-11 → 两处 1794326400；再传空 → 两处回到 NULL。
- 用克隆的 `ZZTEST-ORDER`/`ZZTEST-PUR` 实测 **edit 保存 + 回写**：采购单改 2026-12-25 → 采购单主表/明细、来源订单主表/明细 4 处全部变为 1798128000，
  且 `goods_purchase_order_plan/index` 该单返回同一时间戳（计划部门同步可见）。
- 实测 **add 保存**：`ZZTEST-PUR2` 传 2027-01-15 → 主表 + 明细均落库。
- `goods_purchase_storage/detail?ids=999906` 渲染出「交货日期：2027-01-15」。
- 改词后实测：7 个页面服务端渲染均只出现「交货日期」、无「交货时间」；4 个列表页 `ajax/lang` 下发 `"delivery_time":"交货日期"`；
  21 个 lang 文件 + 控制器 `php -l` 全通过。
- 12 个相关页面 HTTP 200 无 Fatal/Parse error；4 个 JS 文件 `node --check` 通过。
- 清理：`ZZTEST*` / `ZZNULL*` 在 purchase/purchase_info/order/order_info/purord/purord_info 六表全部删净（计数 0）；
  用户原有数据未动（采购单 1、购货订单 1/2/5/7 的 `delivery_time` 保持原值）。测试期间用户并发新建的 999902-999904 未受影响。

---

# 采购订单列表：右侧固定列(操作列)与数据行行高对齐（2026-10-20）

> 涉及文件：`public/assets/js/backend/kuerp/goods_purchase_order.js`、`goods_purchase_order_plan.js`（复制代码，同步改）

## 现象与根因

layui table 的 `fixed:'right'` 列是**与主表分离的另一张表格**（`.layui-table-fixed-r`），行高各自独立、
靠滚动同步；layui 默认单元格 `height:28px + nowrap`，所以两边永远等高。但 kuerp.css 把
`.layui-table-cell{height:auto}` 覆盖掉了，凡是单元格里有**强制换行内容**（`<br>`、块级 `<div>`）的列表页，
主表行会被撑高而固定列仍按单行渲染 → 操作列按钮逐行向上漂移（越往下错得越多）。
采购订单列表的「供应商」（多供应商 `<br>` 拼接）与「关联采购单」（多个 `<div>`）两列正是这种情况；
计划购货订单页同代码，窗口较窄出现横向滚动（固定列浮层显示）时同样错位。

## 修复

`table.render` 加 `done` 回调，按主表行高逐行同步固定列行高（**与 assemble.js / goods.js / split.js 已有写法一致**）：

```js
,done: function () {
    $(".layui-table-main tr").each(function (index, val) {
        $(".layui-table-fixed").each(function () {
            $($(this).find(".layui-table-body tbody tr")[index]).height($(val).height());
        });
    });
    $(".layui-table-header tr").each(function (index, val) { /* 表头同样处理 */ });
}
```

- `done` 在每次 `table.render` / `table.reload`（搜索、翻页、刷新）后都会执行，行高不会失效；
  固定列浮层隐藏（表格未横向溢出）时行高也已写入 tr，之后窗口缩小触发浮层显示时依然对齐。
- ⚠️ 只在单元格含 `<br>`/`<div>` 的列表页需要；纯单行列表页（如 goods_sales_schedule、process、process_order 实测均 41px 等高）无需加。

## 已验证（headless Chrome + CDP 实测 erp.licheng.test，登录态注入，未改任何数据）

- 采购订单列表 1680px：修复前 主表行高 `[104,69,44]` vs 固定列 `[41,41,41]`（错位）；修复后两边均为 `[104,69,44]`，
  固定列单元格与主表单元格纵向居中位置逐行一致（253-293 / 339-379 / 396-436）。
- 交互后仍对齐：点搜索（table.reload）、窗口 resize（1680→1500）、宽屏无溢出(2600)→缩窄触发浮层(1400) 三种情况实测均 `[104,69,44]`。
- 计划购货订单页 1200px：修复前 主表 `[104,69,44]` vs 固定列 `[41,41,41]`；修复后一致。
- 两个 JS 文件 `node --check` 通过。

---

# 原始单据（采购/销售）支持任意文件 + 文件名称 + 文件搜索（2026-10-20）

> 涉及文件：`addons/kuerp/migration_add_enclosure_name.sql`（已对 erp_licheng 执行）、`addons/kuerp/install.sql`（1.6.12 段）、
> `application/admin/model/kuerp/Enclosure.php`（新增 `fillFileInfo()`）、
> `application/admin/controller/kuerp/{Enclosure,Enclosuresale}.php`、
> `application/admin/view/kuerp/{enclosure,enclosuresale}/{index,add,edit,selectpage}.html`、
> `public/assets/js/backend/kuerp/{enclosure,enclosuresale}.js`、
> 8 个单据 JS（`goods_purchase` / `goods_exchange` / `goods_purchase_order` / `goods_purchase_order_plan` /
> `goods_sales` / `goods_sales_order` / `goods_sales_exchange` / `goods_sales_outbound`）

## 背景

采购原始单据(`kuerp/enclosure`，type=1) 与销售原始单据(`kuerp/enclosuresale`，type=2) 共用 `fa_kuerp_enclosure` 表与同一个
`Enclosure` 模型，两个控制器此前是逐字复制的两份代码。原实现**只支持图片**（上传按钮 `data-mimetype` 锁死 image/*，
列表/查看器一律 `<img>`），且没有文件名称、没有搜索。

## 数据库

- 新增列 `name varchar(255) NOT NULL DEFAULT '' COMMENT '文件名称'`（`AFTER image`）。
- `image` 列语义由「图片」改为「任意文件路径」，注释同步改为 `原始单据文件路径`（仍是同一个字段，`enclosure_log` 等关联逻辑零改动）。
- 迁移可重复执行（存储过程判 information_schema）；`install.sql` 两处 `CREATE TABLE` 同步 + 末尾追加 1.6.12 ALTER 段。

## 文件类型

上传按钮**不再写 `data-mimetype`** → 回落到 `Config.upload.mimetype`，
本站当前为 `jpg,png,bmp,jpeg,gif,webp,zip,rar,wav,mp4,mp3,webm,xlsx,pdf,docx,doc,xls,csv`，已覆盖图片/PDF/Excel/Word/压缩包/CSV。
要放开更多类型（如 pptx/txt）直接改 `application/extra/upload.php` 的 `mimetype` 即可，**不要**在视图里另写一份白名单（客户端与
`Upload` 库的服务端校验都以该配置为准，写歪了会出现「前端能选、后端报"上传文件格式受限制"」）。

⚠️ **这是文件配置，不是数据库配置**：`application/extra/upload.php` 不被 `fa_config` 管理
（`Config::refreshFile()` 只生成 `extra/site.php`，`fa_config` 里也只有 basic/dictionary/email 三组），
且 `Ajax::upload()` 每次都 `Config::load(APP_PATH.'extra/upload.php','upload')` 重新加载该文件，
所以改后台「系统配置」界面里的东西**不会**影响它，必须改文件本身。

## 服务端展示字段

`Enclosure::fillFileInfo($row)`（模型静态方法，两个控制器共用）就地补 3 个非数据库字段：

| 字段 | 说明 |
|------|------|
| `file_suffix` | 小写后缀（取 `image` 路径的扩展名） |
| `is_image` | 1=可预览图片（jpg/jpeg/png/gif/bmp/webp/ico），0=其它文件 |
| `url` | `cdnurl($path, true)`；同时把 `image` 覆写成同一个绝对地址（保持旧前端兼容） |

`name` 为空时用文件名兜底（`PATHINFO_FILENAME`）。`index()` 的 `searchIds` 分支（单据页「查看原始单据」用）
每行返回 `src/url/name/is_image`，空结果返回 `{"data":[]}`（不是 `[]`）。

## 列表页 / 选择弹窗

- 列：勾选 | 文件名称 | 原始单据 | 上传日期 | 创建时间 | 操作。
  「原始单据」列由 `Controller.api.fileCell(d)` 渲染：**图片显示缩略图**（点击 `.showImg` 弹层看原图），
  **其它类型显示 `ajax/icon?suffix=xxx` 彩色文件图标 + 后缀文字**，整块是指向文件本身的 `<a target="_blank">`（浏览器直接预览/下载）。
- ⚠️ `fileCell` 与文件名称列一律走 `Controller.api.escapeHtml()`：文件名由用户填写，带 `'` / `<` 时直接拼进
  HTML 会打断整行渲染（已实测 `发票'含引号"与<标签>` 正常显示）。
- `.showImg` 的点击处理器改为 `data('src') → find('img').src → attr('src')` 三级兜底（缩略图改成了 `<a data-src><img>` 结构）。

## 搜索

- 搜索栏字段：**文件名称**（`op=LIKE`）、**上传日期**（laydate 日期区间）。
- ⚠️ **`upload_date` 是 varchar('Y-m-d')，日期区间必须用 `op=BETWEEN` + 逗号分隔值**（JS 把 laydate 的
  `'2026-10-01 - 2026-10-21'` 换成 `'2026-10-01,2026-10-21'`）。
  **不能用 `RANGE`**：`buildparams()` 会把 RANGE 编译成 `BETWEEN TIME`，`Builder::parseDateTime()` 对非
  datetime/date 类型（varchar）只做 `strtotime()` 不还原成字符串 → 变成 `upload_date BETWEEN 1790294400 AND ...`，
  字符串列与数字比较恒不匹配（实测区间搜索恒返回 0）。其余 kuerp 页面用 RANGE 没问题是因为它们的列是 bigint 时间戳。
- 选择弹窗(`selectpage`)也加了文件名称搜索（`Controller.api.getSearch()`，只按 name LIKE）。

## 文件名称自动填充

- 视图上传按钮带 `data-upload-success="enclosureName"`；`api.bindevent()` 里注册
  `Upload.api.custom.enclosureName = function(data, ret, up, file){...}`，上传成功后**仅当名称框为空**时填入
  `file.name`（去掉扩展名）。
- ⚠️ 原文件名只能取 dropzone 的 `file.name`：`ajax/upload` 的响应 data **只有 `{url, fullurl}`**，没有 filename
  （`Upload::upload()` 虽然算了 filename，但 `Ajax::upload()` 没往外传）。
- `define` 依赖里加了 `'upload'`（`require-backend.js` 有 `'upload':'require-upload'` 映射）才拿得到 `Upload` 对象。

## 单据页「查看原始单据」查看器（8 个 JS）

原实现把 `res.rows` 直接丢给 `layer.photos` —— 只认图片，上传 PDF/Excel 后会显示裂图。现改为：
按 `is_image` 拆成两组，图片仍走 `layer.photos({photos:{data:images}})`，**非图片另开一个 layer 列出文件名 + 链接**
（`target="_blank"`）。8 个文件是同一段代码的复制，改动逐字相同（`url` 分别是 `kuerp/Enclosure/index`（采购 4 个）
与 `kuerp/enclosuresale/index`（销售 4 个））。

⚠️ **这 8 个 JS 是 CRLF/LF 混用**，用脚本改时务必按字节读写（`open(p,'rb')` + `newline=''`）；
用 Python 文本模式整文件重写会把所有行尾变成 LF，产生 3000+ 行的假 diff。`enclosure.js` / `enclosuresale.js`
与 4+4 个视图原本是**纯 CRLF**，重写后要转回 CRLF。

## 权限

无新增菜单/权限规则（沿用原有 `kuerp/enclosure/*`、`kuerp/enclosuresale/*` 的 index/add/edit/del/selectpage）。
`add()`/`edit()` 新增服务端校验：`image` 为空 → `请上传原始单据文件`；`name` 为空 → `请填写文件名称`
（视图侧 `data-rule="required"` 已够，服务端是兜底）。`del()` 的「已关联业务单据不可删除」守卫未改动。

## 已验证（curl 登录 + headless Chrome/CDP 实测 erp.licheng.test，测试数据已清空）

- 迁移：`fa_kuerp_enclosure` 得到 `name` 列，`image` 注释更新；`enclosure`/`enclosure_log` 两表测试后仍为 0 行。
- 上传：`ajax/upload` 收 pdf/png 成功、csv 被拒（「上传文件格式受限制」，符合配置白名单）；浏览器里把
  `采购发票-测试文件.pdf` 塞进 dropzone → 隐藏 file input 的 `accept` 为配置里的 17 种后缀，
  `#c-name` 自动填 `采购发票-测试文件`（去扩展名）、`#c-image` 得到路径、预览列表 1 项。
- 列表/接口：`index` 返回 `name/file_suffix/is_image/url`；采购接口只出 type=1、销售只出 type=2；
  `searchIds` 混合 pdf+png 返回 `src/url/name/is_image` 四项，空结果 `{"data":[]}`。
- 搜索：`name LIKE 合同`→1 条、`图片`→1 条、不存在→0 条；日期 `BETWEEN 2026-10-01,2026-10-21`→2 条、
  `2026-11-01,2026-11-30`→0 条、`2026-01-01,2026-10-20`→2 条；名称+日期组合→1 条。浏览器点「搜索」按钮实测生效。
- 校验：服务端缺 name/缺 image 均返回对应中文提示；浏览器端空名称提交 → `文件名称不能为空`、无文件 → `原始单据不能为空`，
  且**未发出 POST**（前端已拦）。
- 编辑：`edit?ids=1` 回显 name/image；改名落库；缺 name 报错。
- 查看器：采购订单 add 页模拟关联「1(PDF)+2(PNG)」→ 同时弹出「原始单据文件」层（列 PDF 名 + pdf 链接）与
  layer.photos 图片层；销货订单 add 页关联销售记录(3) → 弹出文件层。两个方向都正确。
- 列表 UI 实测：搜索栏可见、列为 文件名称/原始单据/上传日期/创建时间/操作；PDF 行显示 PDF 图标 + `PDF` 文字，
  文件链接指向 `/uploads/...pdf`；名称含引号与 `<标签>` 的行正常渲染未错位。
- 删除：已插入 `enclosure_log` 关联的记录被拦（`原始单据已关联业务单据，不可删除！`），清掉关联后删除成功。
- 清理：测试用的 `fa_kuerp_enclosure` / `_log` / `fa_attachment` 行与 `/uploads/20261020/` 下测试文件全部删除；
  未改 `fa_admin` 与 `login_captcha`。

---

# 采购单(仓库)：仓库部门只读查看采购单(计划)（2026-10-20）

> 涉及文件：`addons/kuerp/migration_add_goods_purchase_plan_warehouse.sql`（已对 erp_licheng 执行）、
> `application/admin/controller/kuerp/GoodsPurchasePlanWarehouse.php`（新）、
> `application/admin/view/kuerp/goods_purchase_plan_warehouse/{index,printpage}.html`（新）、
> `public/assets/js/backend/kuerp/goods_purchase_plan_warehouse.js`（新）、
> `application/admin/lang/zh-cn/kuerp/goods_purchase_plan_warehouse.php`（新）、`addons/kuerp/Kuerp.php` $menu

## 做法：子类继承，不复制代码

沿用 `GoodsPurchaseOrderPlan extends GoodsPurchaseOrder` 的既有惯例：

```php
class GoodsPurchasePlanWarehouse extends GoodsPurchasePlan   // 同命名空间, 无需 use
{
    protected $noNeedRight = ['printpage'];   // 只留打印公开, 不再暴露录入页用的 getUnit/formatInfo/getBranch/getTime
    public function add($ids = null)  { $this->error('采购单(仓库)为只读页面，不能添加单据'); }
    public function edit($ids = null) { $this->error('采购单(仓库)为只读页面，不能编辑单据'); }
    public function del($ids = "")    { $this->error('采购单(仓库)为只读页面，不能删除单据'); }
}
```

- **无新模型、无新表**：与采购单(计划)共用 `fa_kuerp_goods_purchase` + 模型 `GoodsPurchasePlan`（父类 `_initialize` 已 `new` 好）。
- **只读是服务端强制的**：三个写方法一律 `error()`，且**不建** add/edit/del 的 auth_rule → 非超管直连即无权限、超管点了也报错，UI 入口同时不存在。
- `index()` / `printpage()` 直接继承：`$this->view->fetch()` 按**当前控制器**取模板，所以自动落到
  `view/kuerp/goods_purchase_plan_warehouse/`；`__()` 也按当前控制器取语言包 → 同名 key 在新 lang 文件里取值
  （`Plan_detail` = `采购单(仓库)详情`，另补了父模块缺的 `'Print' => '打印'`，父模块打印页按钮因缺该 key 一直显示英文 "Print"）。
- `nowmenu`（`Goods::getSetData()`）由 `request()->controller()` 推导 = `kuerp/goods_purchase_plan_warehouse`，视图/JS 里的
  `$auth->check($nowmenu.'/...')`、`Fast.api.addtabs('kuerp/...')` 自然指向新控制器。

## 列表页（只读 UI）

对照父页面砍掉所有写入口：工具栏**只留 [刷新]**（无 添加/删除），列**去掉勾选列**，行操作模板换成
`#barDemoPlanWarehouse`（**不能叫 `barDemo`** —— `kuerp/common/menu.html` 自带默认 `#barDemo`，取第一个匹配会顶掉自定义按钮），
里面只有 [打印]（`lay-event="print"`）→ `parent.Fast.api.open("kuerp/goods_purchase_plan_warehouse/printpage?ids="+data.id, __('Print'), {area:['90%','75%']})`，
与 allocation.js / process.js 的打印一致（printpage 自带 `window.print()`）。
搜索栏、列、数量格式化（`Config.set.number_decimal`）与父页面逐字相同 —— 数据源也相同：
父页面 `index()` **本就不过滤** `purchase_type`，采购单(计划)与采购单(仓库)看到的是同一批 GHD 单据（仓库侧只是简化只读视图）。

## 菜单与权限

- `Kuerp.php` $menu 挂在**仓库组**（`kuerp/stores/default`）下，`weigh=78`（其他入库单 80 与出库管理 76 之间）。
- 迁移建两条规则（`WHERE NOT EXISTS` 可重复执行，pid 用子查询解析，不写死 185）：
  `kuerp/goods_purchase_plan_warehouse`（菜单，pid=仓库组）+ `.../index`（查看）。
  **printpage 不建规则**（在 `$noNeedRight`，与采购单(计划)一致）。
- 角色分配：仓储部角色需勾选 仓库组 → 采购单(仓库)（含「查看」）。新增菜单后侧栏可能需清 `runtime/cache`。

## 已验证（curl 登录 + headless Chrome/CDP 实测 erp.licheng.test；测试只读、未改业务数据）

- 迁移落库：id 722（pid 185，采购单(仓库)，weigh 78）+ 723（index）；主页面侧栏渲染出「采购单(仓库)」，位置在其他入库单与出库管理之间。
- `index` HTTP 200、`Config.jsname = backend/kuerp/goods_purchase_plan_warehouse`；ajax 返回 5 条（与父页面 `goods_purchase_plan/index` 同一批数据、条数一致）。
- 只读守卫（curl）：`add`/`edit`/`del` 分别返回「采购单(仓库)为只读页面，不能添加/编辑/删除单据」。
- 浏览器实测（CDP）：工具栏只有「刷新」、无勾选列、列为 单据日期/单据编号/供应商/数量/制单人/备注/操作，
  5 行渲染正常、每行只有 [打印]；点 [打印] → 弹出 `打印` 层，iframe 内为「采购单(仓库)详情 + 明细 + 合计 100」。
- 父模块回归：`goods_purchase_plan/index` 仍 200、5 条数据不变。
- 清理：测试中 printpage 自增的 `print_num`（id=1、999904）已还原为原值（1 / 0）；未动 `fa_admin` 与 `login_captcha`。

---

# 采购入库「操作记录」+ 采购单入库状态（2026-10-20）

> 涉及文件：`addons/kuerp/migration_add_goods_purchase_storage_log.sql`（已对 erp_licheng 执行）、`addons/kuerp/install.sql`（1.6.13 段）、`addons/kuerp/info.ini`（版本 1.6.13）、
> `application/admin/model/kuerp/GoodsPurchase.php`（新增 `storageStatusMap()`）、
> `application/admin/controller/kuerp/{GoodsPurchase,GoodsPurchasePlan,GoodsPurchaseStorage}.php`、
> `application/admin/view/kuerp/goods_purchase_storage/storage.html`、
> `public/assets/js/backend/kuerp/{goods_purchase,goods_purchase_plan,goods_purchase_plan_warehouse,goods_purchase_storage,goods_purchase_storage_layer}.js`、
> 4 个 lang 文件（`goods_purchase[_plan|_plan_warehouse|_storage].php`）

## 一、采购入库页「操作记录」（kuerp/goods_purchase_storage/storage/ids/X）

**需求口径**（2026-10-20 用户确认）：操作记录**只记录每次入库**，每条记录后面跟着**该次入库的原始单据附件**（送货单/发票扫描件），入库页可直接上传。

### 数据

- 新表 `fa_kuerp_goods_purchase_storage_log`：`purchase_id` / `purchase_code` / `total_number`（本次入库总数量）/ `detail`（本次入库明细 JSON：goods_code、goods_name、number、stores_name、unit_name）/ `admin_id` / `createtime`。
  写入点在 `GoodsPurchaseStorage::storage()` POST 的**事务内**（第 8 步，与库存变动同生共死）。
- 附件复用**原始单据**（`fa_kuerp_enclosure` type=1 + `fa_kuerp_enclosure_log`）：
  `enclosure_log.type` 枚举新增 **'7' = 入库记录**，`order_id` 存**入库记录ID**，`purchase_code` 存采购单号。
  这样 `Enclosure::del()` 的「原始单据已关联业务单据，不可删除！」守卫自动覆盖入库附件；上传的文件同时会出现在「采购原始单据」列表里。

### 展示（`storage.html` 表单之后的 `#log-panel`）

每条入库记录一行（入库时间 / 本次入库数量 / 入库明细 / 操作人），**紧跟一行 `tr.enc-row`** 放该次入库的原始单据（图片缩略图、其它类型用 `ajax/icon?suffix=` 图标 + 后缀，点击新窗口打开）+ `[+ 上传原始单据]` + 隐藏 `<input type="file">`（**在 `<form>` 之外**，避免被入库表单的 FormData 一起提交）。

明细摘要文本服务端拼成 `商品编号 商品名称 × 数量单位`，用 `、` 连接（**带编号**：同名不同编号的商品很常见，只显示名称看不出是哪一个）。

### 上传/删除接口

- `uploadenclosure($ids)`：`$ids` = 入库记录ID。走 `\app\common\library\Upload`（服务端校验大小/类型/可执行），成功后插 `kuerp_enclosure`（name 取原文件名去扩展名）+ `kuerp_enclosure_log`(type=7)，返回 `{id,name,url,is_image,file_suffix}`。
- `delenclosure($ids)`：`$ids` = 原始单据ID。**先校验该单据确实挂在 type=7 上**，否则报「该单据不属于入库操作记录，不能在此删除」（防止借这个接口删掉采购单/购货订单的附件）。
- 两个方法都加进 `$noNeedRight`（与同模块的 detail/printstorage/storage 一致：仓库角色不额外配权限规则即可用）。

### 前端（`goods_purchase_storage_layer.js`）

- 面板上用**事件委托**（`panel.addEventListener('click'/'change')`）：`.btn-upload-enc` → 触发同记录隐藏 file input；file input change → FormData POST 上传 → **把返回的单据拼成节点 `insertAdjacentHTML` 追加**到该记录的 `.enc-list`（不整页刷新，避免丢掉用户已填的入库数量）；`.enc-del` → confirm 后 POST 删除并移除节点。
- 服务端渲染的附件节点与 JS 追加的节点**结构必须一致**（`.enc-item > a > img + span.enc-suffix` + `.enc-del`）；JS 侧对文件名/URL 做 HTML 转义。
- URL 从面板的 `data-upload-url` / `data-del-url` / `data-icon-url` 取（用 `{:url(...)}` 渲染成绝对路径），不要用相对路径拼——入库页 URL 带 `/storage/ids/X`，相对路径会算错。
- ⚠️ **入库成功后不再自动关闭弹窗**：改为 `parent.Toastr.success(msg)` + `parent.$('.btn-refresh').click()`（刷新父列表）+ `location.reload()`（本页已入库/待入库数量与操作记录一起刷新），方便紧接着上传本次入库的原始单据。点「关闭」仍走 `closeParentLayer()` → `parent.Fast.api.close()` → 父页面 callback 刷新列表，行为不变。

## 二、采购单列表「入库状态」

- 取值：按采购单号汇总 `kuerp_goods_purchase_info` 的 `SUM(number)` 与 `SUM(storage_number)`：
  `已入库<=0 → 0 未入库`；`0<已入库<采购数量 → 1 部分入库`；`已入库>=采购数量 → 2 已入库`。
  计算集中在 **`GoodsPurchase::storageStatusMap($purchaseCodes)`**（静态、一次查询批量算，`GROUP BY purchase_code`）。
- **退货单（purchase_type!=1）返回空串**，前端显示灰色 `—`（退货单没有入库概念）。
- 4 个列表都下发 `storage_status` 字段 + 加「入库状态」列（`goods_purchase` 采购单、`goods_purchase_plan` 采购单(计划)、`goods_purchase_plan_warehouse` 采购单(仓库)、`goods_purchase_storage` 采购入库）：
  - 采购单/采购单(计划)/采购入库：各自 `index()` 里在已有 foreach 后**追加**一个 foreach 赋值（不改动原循环）；
  - 采购单(仓库) `GoodsPurchasePlanWarehouse` 继承 `GoodsPurchasePlan::index()`，**零改动**自动生效；
  - 三者共用 `fa_kuerp_goods_purchase` 表，所以跨模型全限定调用 `\app\admin\model\kuerp\GoodsPurchase::storageStatusMap(...)`。
- 入库状态是**计算值不是数据库列**，所以只能显示、不能走 `filter/op` 搜索（标准搜索栏按列过滤，加了会查不到）。
- lang：4 个 lang 文件都加 `Storage_status` / `Storage_status 0|1|2`（`Fast.lang()` 会把 key 转小写查表，见上文「交货日期」一节的说明）。

### 已全部入库的采购单不显示 [入库] 按钮（2026-11-05）

- **列表**（`view/kuerp/goods_purchase_storage/index.html` 的 `#barDemoStorage`）：条件由
  `examine_status==1 && check_status==1` 改为再 `&& storage_status != 2`（`storage_status` 由 `index()` 下发，
  `'2'` 用 `!= 2` 松散比较同时兼容字符串/数字）。未对单（`check_status!=1`）的单据本来就没有入库按钮，逻辑不变。
- **服务端兜底**（`GoodsPurchaseStorage::storage()` 取到 `$row` 后、POST/GET 分支之前）：调
  `\app\admin\model\kuerp\GoodsPurchase::storageStatusMap([$row['purchase_code']])`，结果为 `'2'` 时
  `$this->error('该采购单已全部入库，无需再次入库')`（GET 直接开弹窗、POST 直连都会被拦住）。
- 实测（curl + headless Chrome，未改业务数据）：已入库的 `GHD20261103005` / `GHD20261103004` / `GHD20261020003` 行只剩 [详情][打印]；
  部分入库 `GHD20261103006`、未入库且已对单 `GHD20261020004` / `GHD20261020002` 仍有 [入库]；
  `storage/ids/999912`（已入库）GET 返回上述提示，`storage/ids/999913`（部分入库）200 正常打开。

### 入库记录在「详情」页也展示（2026-11-05）

- `GoodsPurchaseStorage::detail()` 末尾补 `$this->view->assign("log_list", $this->buildStorageLogs($row['id']))`
  —— 与入库弹窗底部「操作记录」**同源**（同一方法、同一数据结构：`createtime/total_number/detail_text/admin_name/enclosures`）。
- `view/kuerp/goods_purchase_storage/detail.html` 在备注之后新增只读「入库记录」面板：
  列 `入库时间 / 本次入库数量 / 入库明细 / 原始单据 / 操作人`，原始单据渲染为缩略图或 `ajax/icon?suffix=` 文件图标 + 后缀，
  `<a target="_blank">` 直接打开文件（**只读：没有 × 删除、也没有 + 上传原始单据**，那些仍只在入库弹窗里）；
  无记录时显示「暂无入库记录」。样式沿用详情页自己的内联 CSS（类名前缀 `.log-*` / `.enc-item`），不引 JS。
- 实测（curl + headless Chrome/CDP，未改业务数据）：`detail/ids/999904` 3 条记录（其中 XLSX 图标 + 图片缩略图各一，无附件行显示 `-`）、
  `detail/ids/999913` 1 条多商品记录（J05-0017×12、J08-0001×2）；900px 内表格不溢出、图片 40px 高，无 JS 异常；
  无记录的单据（`detail/ids/999907`）显示「暂无入库记录」。

### 入库/出库明细改为表格展示（2026-11-06）

> 仅三个视图，无控制器/JS/数据库改动：`goods_purchase_storage/{detail,storage,returnoutbound}.html`

- 原来「入库明细」「出库明细」是把 `detail_list` 拼成一串文本（`detail_text`：`编号 名称 × 数量单位、…`），
  现在改为 **每条操作记录下方追加一行 `tr.log-detail-row`（colspan 铺满），内嵌明细子表格**：
  - 入库：`序号 / 商品编号 / 商品名称 / 仓库 / 单位 / 数量`（数据取日志 JSON 的 `goods_code/goods_name/stores_name/unit_name/number`）
  - 退货出库：多一列 `退货单号`（`exchange_code`，一次出库可来自多张退货单）
- 主表列由 5 列缩为 4 列：`入库时间 / 本次入库数量 / 原始单据 / 操作人`（退货出库为 `出库时间 / 退货单号 / 本次出库数量 / 操作人`）——
  明细列只有 ~340px，塞 5 列子表必然挤，改到记录下方整行展开后 900px 页面内可读性最好。
- 子表字段一律写 `{$d.xxx|default=''|htmlentities}`：`default` 在 TP5 编译成 `isset(...)?...:''`，
  历史日志 JSON 缺键时不会抛 `Undefined array key`（PHP8 下会直接 500）。`default` 必须写在 `htmlentities` **前面**
  （后置时 `default` 会退化成 `htmlentities($d['x']) ?: ''`，isset 保护就没了）。
- 明细为空（老数据 `detail` 为 NULL）显示「无明细」，主表照常渲染。
- `buildStorageLogs()` / `ReturnOutbound::logs()` 的 `detail_text` **保留未删**——`purchase_recon/{audit,detail}.html`
  与 `goods_purchase/edit.html` 的「退货出库记录」仍在用它（那几处明细列只有 250px 左右，嵌子表会挤，维持文本）。
- 入库弹窗的有无附件交互不受影响：`goods_purchase_storage_layer.js` 只按 `.enc-list[data-log-id]` / `.enc-item` 取节点。
- 已验证（curl + headless Chrome/CDP 实测 erp.licheng.test，测试数据已还原）：`detail/ids/999913` 3 条记录
  （入库 2 条各 2 商品 + 退货出库 1 条 2 商品）子表渲染正确、表格宽 866px 不溢出；
  `storage/ids/999903` 操作记录 1 条子表正常；`returnoutbound/ids/999913`「出库记录」子表正常；
  把某条 log 的 `detail` 临时置 NULL → 显示「无明细」、无 500（已还原）。

## 三、已验证（curl 登录 + headless Chrome/CDP 实测 erp.licheng.test，测试数据已还原）

- 迁移：新表建立；`enclosure_log.type` 枚举含 '7'。
- 入库：对 GHD20261020004 入库 C13-0001×1 + C13-0002×2 → 返回「入库成功，本次入库3件」；操作记录表写入 1 行（detail JSON 含商品名/仓库/单位）、`goods_stock` 2099→2100 / 1567→1569、`goods.inventory` 同步、`purchase_info.storage_number` 1/2 且 `order_info_id` 回填 15/16、`order_info.library_number` 99/98 且 status=2、`purchase_order.store_number`=3、`purord_info` 桥接行自动创建——**全部符合原有入库逻辑**。
- 页面：入库页 GET 渲染出「操作记录」面板（时间/数量/明细/操作人 + 该记录下的原始单据区 + 上传按钮）；明细摘要显示 `C13-0001 CBB运行电容 × 1只、C13-0002 CBB运行电容 × 2只`。
- 上传：`uploadenclosure/ids/1` 上传 pdf 成功 → `kuerp_enclosure` + `enclosure_log`(type=7, order_id=1) 各 1 行，页面在**该条记录下**渲染出 PDF 图标；`ids=999`（不存在的记录）报「入库记录不存在」。浏览器实测点面板上传：`.enc-item` 0→1，追加的 HTML 与服务端渲染结构一致，file input 已清空。
- 删除：`delenclosure/ids/6`（用户的非入库附件）被拒「该单据不属于入库操作记录」；`ids=8`（入库附件）删除成功，两表行消失。浏览器实测 `.enc-del` 点击后节点 1→0。
- 入库状态：四个列表接口/页面均返回并渲染「入库状态」列；实测 `GHD20261020004`=部分入库、`GHD20261020003`=部分入库、其余=未入库；临时把 `GHD20261020001` 的 storage_number 置为 number 后返回**已入库**（已还原）。
- lang 下发：4 个 controller 的 `ajax/lang` 均含 `"storage_status":"入库状态"` 等 4 个 key。
- 9 个相关页面 HTTP 200 无 Fatal/Parse error；5 个 JS `node --check` 通过。
- **清理**：入库测试产生的库存/明细/订单/桥接表数值**逐行按快照还原**（goods_stock 2099/1567、goods.inventory 2099/1567、purchase_info 14/15 归零且 order_info_id 回 0、order_info 15/16 library_number=100/status='0'、purchase_order store_number=0、purchase 999907 updatetime 还原、purord_info 与 storage_log 清空、测试附件与上传文件删除）；用户自己的 2 个原始单据（id 6、7）与附件未动。
  ⚠️ 还原枚举列时注意：`status` 是 `enum('0','1','2','3')`，写 `status=0`（整数）会落成**空字符串**（enum 的 0 号位），必须写 `status='0'`。

---

# 供应商「合同」附件上传（2026-10-21）

> 涉及文件：`addons/kuerp/migration_add_suppliers_contract.sql`（已对 erp_licheng 执行）、`addons/kuerp/install.sql`（1.6.14 段 + CREATE TABLE）、`addons/kuerp/info.ini`（1.6.14）、
> `application/admin/controller/kuerp/Suppliers.php`（index 下发合同展示字段）、
> `application/admin/view/kuerp/suppliers/{add,edit}.html`、`public/assets/js/backend/kuerp/suppliers.js`、
> `application/admin/lang/zh-cn/kuerp/suppliers.php`

## 需求与口径（2026-10-21 用户确认）

新增/编辑供应商时可上传该供应商的**合同**：**选填**（不传也能保存）、**单文件**、**列表页可见并可点击查看**。

## 数据

- `fa_kuerp_suppliers` 新增 `contract` varchar(255) NOT NULL DEFAULT '' COMMENT '合同附件'（AFTER `bank_number`），存上传后的相对路径（如 `/uploads/20261021/xxx.pdf`）。
- **无新表、无新控制器方法**：add/edit 的 `$params` 是原样 insert/update（`Db::name('kuerp_suppliers')->insertGetId($params)` / `->update($params)`），
  表单多一个 `row[contract]` 即自动落库——**列不存在时保存会直接报 Unknown column，迁移必须先执行**。
- Excel 批量导入（`Suppliers::import()`）**不含**合同列，导入的供应商 `contract` 为空串（可后续在编辑页补传）。

## 表单（add/edit）

- 位置：开户银行/银行卡号那一行之后，独占整行（label `col-sm-2` + 内容 `col-sm-10`）。
- 标准 FastAdmin 上传三件套：`input#c-contract` + `.faupload`（`data-input-id="c-contract" data-multiple="false" data-preview-id="p-contract"`）+ `.fachoose` + `<ul id="p-contract" class="faupload-preview">`，由 `Form.api.bindevent` 自动绑定。
- **不写 `data-mimetype`** → 回落到 `Config.upload.mimetype`（系统配置→上传配置，本站 `application/extra/upload.php`：jpg/png/…/pdf/doc/docx/xls/xlsx/zip/rar 等 17 种，maxsize 30mb）。
  与「原始单据」一节同一约定：**不要在视图里另写白名单**，否则会出现「前端能选、后端报上传文件格式受限制」。
- 预览 `#p-contract` 由 require-upload.js 在 `$("#c-contract").trigger("change")` 时按 input 值渲染，所以**编辑页回显旧合同无需额外代码**（图片显示缩略图，其它类型 onerror 降级为 `ajax/icon?suffix=` 图标）。

## 列表

- `Suppliers::index()` 的 AJAX 分支 foreach 里追加 3 个非数据库字段：`contract_url`（`cdnurl($path, true)` 绝对地址）、`contract_suffix`、`contract_is_image`（jpg/jpeg/png/gif/bmp/webp/ico）。
- 列位置：备注列之后、应付款余额之前；`operate: false`（计算字段不能搜索）。
- 单元格渲染 `Controller.api.contractCell(d)`：图片 → 40px 缩略图；其它类型 → `ajax/icon?suffix=xxx` 图标 + 大写后缀文字；两者都是 `<a target="_blank">` 直连文件；未上传显示灰色 `—`。
  文件名/URL 一律走 `Controller.api.escapeHtml()`（同 enclosure.js）。
- ⚠️ **`table.render` 必须带 `done` 行高同步回调**：该列表有 `fixed:'right'` 操作列，而 kuerp.css 的 `.layui-table-cell{height:auto}` 会让含图片的行高于固定列默认 28px → 操作列逐行上漂。
  已按 goods.js/assemble.js 既有写法加 `done`（主表行高逐行写入 `.layui-table-fixed` 的 tbody/thead 行）。实测图片行 53px、其余 41px，固定列完全一致。

## 已验证（curl 登录 + headless Chrome/CDP 实测 erp.licheng.test，测试数据已清空）

- 迁移落库：`fa_kuerp_suppliers.contract` 存在（`AFTER bank_number`，注释「合同附件」）。
- curl add 传 `row[contract]=/uploads/...pdf` → 落库；edit 改成 `.png` → 落库；edit 页回显 `value="/uploads/..."`。
- 浏览器实测（CDP 真实拖入文件）：编辑页上传 PDF → `#c-contract` 变为上传路径、`#p-contract` 渲染 PDF 图标预览 → 点【确定】发出 POST（`/edit/ids/117`）保存成功；再上传 PNG → 保存 → 列表该行显示图片缩略图（`naturalWidth=160`）。
- 列表：合同列列头「合同」；无合同行显示 `—`；PDF 行显示文件图标 + `PDF` 文字并链接到文件；图片行显示缩略图。
  窄屏（1100px）横向滚动后固定列（操作）行高与主表逐行一致 `[53,41,41,…]`（加 `done` 前会是 `[41,41,41,…]` 错位）。
- lang：`ajax/lang?controllername=kuerp.suppliers` 下发 `"contract":"合同"`；4 个供应商页面 HTTP 200 无 Fatal/Parse error；`node --check suppliers.js` 通过。
- **清理**：测试供应商 117（`fa_kuerp_suppliers`/`_user`）、`fa_kuerp_payment_log`（bill_code=ZZTEST-CONTRACT，5 行 give_money=0）、
  `fa_attachment` 255/256/257 与 `/uploads/20261021/` 下 3 个测试文件全部删除，`AUTO_INCREMENT` 还原为 117（=原 max id 116 + 1）；用户 112 个供应商未动。

---

# 孤儿商品数据导致编辑页 500 的容错（2026-10-21）

> 涉及文件：`application/admin/controller/kuerp/{Allocation,GoodsPurchase,GoodsPurchasePlan,GoodsSales,GoodsSalesOutbound,GoodsSalesSchedule,Warehouse,Warehousing}.php`、
> `addons/kuerp/library/{GoodsPurchase,GoodsSales}.php`（**无数据库迁移**）

## 现象与根因

单据明细行引用的商品已从 `fa_kuerp_goods` 删除（孤儿数据）时，`kuerp/goods_sales/edit?ids=1` 等编辑页 500：
`json_decode() expects parameter 1 to be string, array given`（`GoodsSales.php` 编辑分支）。

链路：`getUnit()` 查不到商品时 `return []`（PHP 数组），而所有调用方都是
`json_decode($this->getUnit(...), true)`（共 10 处 `json_decode(...getUnit...)` 调用点）→ PHP 8 下告警被
TP5 错误处理器（`app_debug=true`）转成 `ErrorException` → 500。前端 ajax 路径同源（正常路径返回的是
`json_encode(...)` 字符串，被 jQuery 解析成字符串后再 `JSON.parse`），所以空分支返回数组本身就与正常分支**形状不一致**。

## 修复

1. **统一空分支返回形状**（11 处 `return [];` → JSON 串，与正常分支逐字同形）：
   - `getUnit`：`json_encode(['unit'=>[],'attr'=>[],'goods'=>[]])`（`GoodsPurchase::getUnit` 多一个 `'supplier'=>null`，与其正常返回一致）
   - `getUnits`：`json_encode([])`
   - 覆盖 `Allocation` / `GoodsPurchase` / `GoodsPurchasePlan` / `GoodsSales`(getUnit+getUnits) /
     `GoodsSalesOutbound`(getUnit+getUnits) / `GoodsSalesSchedule` / `library/GoodsPurchase.php` / `library/GoodsSales.php`。
   - `ProcessOrder::getUnit()` 内部 `json_decode(library getUnit(...))` 一并受益（无需改动）。
2. **另一类同源崩溃**：`$goods = Db::name('kuerp_goods')->where(['id'=>$info_v['goods_id']])->find();` 后直接取
   `$goods['goods_code']` → `Trying to access array offset on value of type null`。`Warehouse.php` ×3、
   `Warehousing.php` ×3 改为 `empty($goods)?['unit'=>[],'attr'=>[],'goods'=>[]]:json_decode(...getUnit(...),true)`。
   （`Warehousing` 序列号盘盈分支因此落到既有 `$this->error('商品信息获取错误')`，比 500 友好。）

## 行为

孤儿行现在**正常渲染**：商品列/单位列为空，数量、单价、金额仍按库中原值显示（模板里商品名/单位来自
`{foreach name="goodsList"}{if $vo.goods_code==$info.goods_code}`，商品不存在时该格自然为空）。
已审核单据为只读，无保存风险；未审核单据保存时该行会走原有"请选择商品"校验。

## 已验证（curl 登录 erp.licheng.test + headless Chrome/CDP 实测；未改动任何业务数据）

- `goods_sales/edit?ids=1`（XHD20260914001，引用已删商品 SP40015 / A03-0001）：修复前 500 → 修复后 200，
  浏览器实测无 JS 异常，两行显示数量 2/1、金额 36/22，商品列为空，单据为「已审核」只读。
- `warehousing/edit?ids=1`（`warehousing_info.goods_id=1` 商品已删）：修复前 500 → 修复后 200，浏览器实测正常渲染。
- 空分支响应形状与正常分支一致：`goods_purchase|goods_sales|goods_sales_outbound|allocation|goods_purchase_plan|
  goods_sales_schedule|process_order` 的 `getUnit`（传不存在的 goods_code）均返回 `200` + 与正常分支同形的 JSON 串；
  `getUnits` 返回 `[]`。
- 110 个 kuerp 控制器 `index` 全量扫描：仅 `payment_account`（模板 `未定义变量: nowmenu`）与 `stone_in`（System Error）
  非 200，**均为本次改动之前就存在**、与本次无关。
- 10 个改动文件 `php -l` 全通过。

## 遗留（未处理，需要时再定）

- 库里现有孤儿商品编号：`SP40015`/`A03-0001`（销货单 XHD20260914001、销货订单 XHDD20260914001）、
  `SP012`（购货退货单 GHTH20260702001 的**残留明细行**，主表已无该单）、`SP50002`（销货退货单 XHTH20260904001，同上）、
  `D06-0005`/`D06-0007`（BOM 100/101 的子件）；`fa_kuerp_warehousing_info.goods_id=1` 亦为已删商品。
  这些都是历史遗留，**未做数据清理**（清理会改动单据金额/明细，需用户确认）。
- 同类未加固点（当前数据未触发）：`Assemble.php` 4 处、`Split.php` 2 处、`Adjustment.php` 1 处的
  `$goods['goods_code']` 直接取值（组装/拆卸/成本调整单）。

---

# 销货订单：仓库为空时提示「商品XX库存不足」（2026-10-21）

> 涉及文件：仅 `application/admin/controller/kuerp/GoodsSalesOrder.php`（`add()` / `edit()` 单据行校验，纯后端）

- **背景**：销货订单 add/edit 页的「仓库」列当时是隐藏的（模板里 `div[data-select-enhance=skip][style=display:none]` 包着 `select.stores_id`；
  **2026-11-05 已改回独立可见列**，见文末「销货预订单 add/edit 恢复可见的「仓库」列」），
  仓库只在选货时由前端从商品档案的 `manager_warehouse_id`（首选仓库）自动带出（`.chose_goods_tr` 的 `data-store` / `Config.goodsList`）。
  商品没维护首选仓库（`manager_warehouse_id=0`）或该仓库没有库存时，行上的 `stores_id` 为空，保存报「请选择仓库」——**用户无从选择**，提示误导。
- **改动**：两处单据行校验里把商品查询提前，仓库为空时改为 `$this->error('商品'.$goods['goods_name'].'库存不足')`；
  「商品不存在」现在先于仓库校验触发（此前空仓库会先报「请选择仓库」掩盖商品不存在）。
- 消息格式沿用同模块既有写法（`GoodsSales.php`/`GoodsSalesOutbound.php` 的 `'商品'.$goods_name.'库存不足'`）。
- **已验证**（curl + headless Chrome/CDP 实测 erp.licheng.test）：
  - 真实 UI 选货（`Z01-0004` 无首选仓库、可用库存 0）→ 行内 `stores_id` 为空、点【确定】返回 `{"code":0,"msg":"商品HS-6-MB-PCBA-V1.6库存不足"}`（改前为「请选择仓库」）。
  - 商品编号不存在 + 空仓库 → 返回「商品不存在」。
  - 正常商品（`C13-0001`，首选仓库=零件仓 6、库存 2099）→ 行内 `stores_id=6`，保存成功建单（测试单 XHDD20261021002 已删除，info 行一并清除，AUTO_INCREMENT 还原；
    同期用户自建的 XHDD20261021001 未动）。
  - `edit` 页 GET 200 无报错；`php -l` 通过。

> ⚠️ **2026-11-03 起本条已被取代**：仓库为空不再报「库存不足」，而是由 `Goods::resolveStoreId()` 按商品类型兜底
> （零件→零件仓、成品→成品仓、半成品→半成品仓），且库存 0/负数允许下单、出库可扣成负数。
> 详见文末「销货预订单：商品无首选仓库按商品类型兜底 + 库存 0/负数可下单/出库（2026-11-03）」。

---

# 启用税金配套修复：采购台账口径对齐 + 单据打印增加税金列（2026-10-21）

> 涉及文件：`application/admin/controller/kuerp/{GoodsPurchase,GoodsExchange}.php`、
> `application/admin/view/kuerp/{goods_purchase,goods_sales}/printpage.html`（**无数据库迁移**）

## 背景

`fa_kuerp_sets` 的 `taxes`(启用税金) + `price_taxes`(商品价格是否含税) 已开启（见设置页 `checkOrder()` 守卫一节）。
开启后暴露两处配套缺失，本次补齐。

## 一、采购台账 `money_tax_price` 与销货侧口径对齐

`fa_kuerp_payment_log.money_tax_price` 是台账「采购金额 / 销售金额」列，**各控制器写入口径原本不一致**：

| 写入点 | 原写法 | 问题 |
|---|---|---|
| `GoodsSales::_examine` :1459/1843 | `$list_v['money_tax_price']` | ✅ 价税合计（优惠前） |
| `GoodsSalesExchange` :1543/1748 | `-$list_v['money_tax_price']` | ✅ 退货取负 |
| **`GoodsPurchase::_examine` :1027** | `$list_v['all_money'] - $list_v['preferential_money']` | ❌ 用的是**不含税金额**，还多减了一次优惠 |
| **`GoodsExchange::_examine` :1474** | `-$list_v['all_money'] + $list_v['preferential_money']` | ❌ 同上 |

**采购单/退货单主表没有 `money_tax_price` 列**，价税合计需用 `金额 + 税额` 还原：
`all_money`(不含税金额) + `tax_money`(税额) = 价税合计。该口径与 `Writeoff.php:743`
（核销时算采购单剩余应付 `$order['all_money']+$order['tax_money']`）完全一致，可作交叉验证。

改为：
- `GoodsPurchase.php` → `'money_tax_price'=> $list_v['all_money']+$list_v['tax_money']`
- `GoodsExchange.php` → `'money_tax_price'=> -($list_v['all_money']+$list_v['tax_money'])`

⚠️ 台账行在**审核时写入、反审核时按 `bill_codes` 整条删除**（`GoodsPurchase.php:1875`、`GoodsExchange.php:2180`），
不存在负数冲销行，所以历史行只能靠重新审核刷新；`money_tax_price` 只用于台账列表展示，
`payment_rate`(付款率/收款率) 用的是 `pay_money/real_money`，**不受影响**；无其他计算方读取该字段。

## 二、单据打印增加税金列

`kuerp/goods_purchase/printpage` 与 `kuerp/goods_sales/printpage` 原只有「单价 / 金额」两列（均为**不含税**），
启用税金后打印出来看不出税——本次在两个模板的「金额」之后**追加 4 列**，并用
`{if condition="isset($set['taxes']['is_checked']) && $set['taxes']['is_checked']==1"}` 整体包住：

`含税单价 | 税率(%) | 税额 | 价税合计`

- 合计行：数量/金额之外补 税额 与 价税合计 合计（`$total_tax` / `$total_tax_price` 在 `{php}` 块里累加）。
- 「无明细数据」行 colspan 随开关变化（采购 10↔14、销货 9↔13）。
- **税金关闭时输出与改动前逐字一致**（实测列头与合计行完全不变）。
- `$set` 在 kuerp 各控制器 `_initialize` 已 assign，printpage 视图可直接用（同 `allocation/assemble` 打印页的先例）。
- **采购单(计划)/(仓库) 打印不含价格列、销货出库单打印无金额**，故未改；`goods_exchange`/`goods_sales_exchange` 无打印页。
- ⚠️ **2026-10-30 采购打印页字段改版**（仅 `goods_purchase/printpage` + `GoodsPurchase::printpage()`，销货打印未动）：
  去掉 类型/制单人/审核人/审核状态/对单状态，以及明细表的 商品编号/仓库名称/采购单价/购货金额/**税额**；
  表头新增 供应商电话（`kuerp_suppliers_user` 首要联系人，优先 `first_connect_mobile` 否则 `first_connect_phone`）、
  交货日期（`delivery_time`）、需求方（`fa_kuerp_sets.company_name`）、需求电话（`company_phone`）——
  这三项放在同一个 `.info-grid .item-wide`（`grid-column:1 / -1` + `display:flex; gap:2px 40px`）里，**保证同一行显示**；
  明细表「商品名称」后新增 型号/规格2-厂商/规格3-封装 三列（JOIN `kuerp_goods` 的 `goods_specification_id`/`vendor_name`/`packaging`，孤儿商品留空）。
  现列数：税金关 9 列、开 12 列（合计行 colspan=7，税额列已移除，仅保留 含税单价/税率/价税合计）。
  同一批字段在**采购单编辑页**（`goods_purchase/edit.html`）也展示：`供应商电话` 与 `需求方|需求电话`（两者合用
  一个 `col-sm-6` 块，label 2 + input 4 + label 2 + input 4，保证同一行）排在第二行，与「选择原始单据」凑满 12 格。
  `GoodsPurchase::supplierPhone()`（private）是两处共用的取数方法；编辑页的 供应商电话/需求方/需求电话 均为只读。
- **采购打印页改 A4 横向**（2026-10-30）：字段变多后竖向打印右侧被裁，`printpage.html` 在 `{include ... printstyle}` **之后**
  追加 `@page{size:A4 landscape}` + `@media screen{#content{width:297mm}}` + `.print-wrap{max-width:none}` 覆盖公共竖向设置
  （只影响本页，其余 18 个打印页仍竖向）。实测 content 1122.5px(297mm)、正文 1047px、PDF MediaBox 841.9×595 = A4 横向、无横向溢出。

## 已验证（curl 登录 erp.licheng.test + headless Chrome 截图）

- 采购打印 `printpage?ids=999907`、销货打印 `printpage?ids=2`：税金开启时 14/13 列正常渲染，合计行逐列对齐
  （截图核对：采购 `合计|200| |0| | |0|0| `、销货 `合计|110| |9550| | |0|9550| `）。
- 把 `taxes.is_checked` 临时置 0 后重取两页：列头与合计行回到原样（10/9 列），无 Fatal；随后已还原为 1。
- `payment_log` / `sales_payment_log` 列表页 200；`goods_purchase_plan`、`goods_purchase_plan_warehouse` 打印页 200。
- 打印测试会把 `print_num` 自增，**已按快照还原**（采购 id=1/999907=1，销货 id=2=1、id=3=3）。
### 台账口径 · 真机端到端验证（2026-10-21 补做）

用 curl 造了一张带税采购单 `ZZTEST-TAX001`（供应商 GYS112、商品 C13-0001、仓库 6 零件仓、
数量 10、含税单价 113、税率 13%），走完整「新增 → 审核 → 反审核 → 删除」：

| 节点 | 数据 |
|---|---|
| 新增后主表 | `all_money=1000`、`tax_money=130`、`real_money=1130` |
| 新增后明细 | `unit_price=100`、`tax_included=113`、`all_money=1000`、`tax_money=130`、`money_tax_price=1130` |
| **审核后台账行** | `purchase_money=1000`、**`money_tax_price=1130`**、`discount_money=0`、`real_money=1130`、`give_money=1130` ✅ |
| 审核后库存 | stock 2099→2109、**`cost_money` 0→1130（含税入库成本）**、`goods.inventory` 同步 |
| 审核后应付 | 供应商 `need_give_account` 9000→10130（+1130，按含税） |
| 审核后收发 | `goods_log.enter_unit=113`、`enter_cost=1130` |
| 反审核后 | 台账行删除、库存/成本/inventory/应付全部回到原值、`goods_log` 行删除 |
| 删除后 | 8 张表逐行 dump 与测试前**字节级一致**（仅 `updatetime` 时间戳，已按快照还原） |

修复前该行的 `money_tax_price` 会是 `all_money - preferential_money = 1000`（不含税且多减优惠），修复后为 1130，与销货侧同口径。

同时用该带税单据截图验证打印页：14 列、`合计|10| |1000| | |130|1130|`、页脚「总金额 ￥1130 欠款 ￥1130」，数字全部正确。

**清理**：测试单据及其明细、台账行、收发明细、库存/成本、应付、invoicing 全部还原；
`AUTO_INCREMENT` 已回滚（purchase 999909 / purchase_info 18 / payment_log 10 / invoicing 5 / goods_log 61）；
测试用 `print_num` 亦已还原。

---

# 其他出库单/其他入库单：调用BOM生成明细（2026-10-22）

> 涉及文件（仅前端，**无数据库/控制器改动**）：
> `application/admin/view/kuerp/{warehouse,warehousing}/{add,edit}.html`、
> `public/assets/js/backend/kuerp/{warehouse,warehousing}.js`

## 需求

其他出库单(`kuerp/warehouse`)、其他入库单(`kuerp/warehousing`) 增加 **调用BOM**：选一个 BOM 模板，
其**子商品即单据明细商品**；输入**父商品数量**自动按 BOM 比例计算每个子商品数量。

## 界面

- 明细表上方 `.table_box` 内新增 `[调用BOM]` 按钮（`class="mb_bom_call"`）+ 隐藏的 `.bom_bar` 信息条：
  `BOM模板：<b class="bom_title">` `父商品：<b class="bom_parent">` `父商品数量：<input class="bom_parent_number">` `[清除]`。
- add 与 edit 页面都加了按钮：审核后的单据由 bindevent 既有 `$('.btn').attr('disabled')` 统一禁用（含清除）。

## 逻辑（`bindevent` 内，两个 JS 逐字复制）

1. 按钮 → `parent.Fast.api.open("kuerp/goods_bom/chosebom", ...)`（与 `process_order.js` 同一套 BOM 选择/详情接口）。
2. 选中后 `POST kuerp/goods_bom/getbomdetail` 拿 `parent` / `children`（children 为 `type='2'` 全部子件）。
3. 生成行前**先清理**：`#purchase_table` 中 `rows[2..]` 里「空行（无 goods_code）」与「上次BOM生成的行（有 `data-bom-ratio`）」，
   **已手工录入的其它商品行原样保留**（与 process_order 的整表替换不同，避免丢用户已填数据）。
4. 以**隐藏模板行 `rows[1]` 克隆**生成子件行：`goods_code/goods_name` + `data-bom-ratio=子件number`，
   调 `changeGoods(子件编号, 子件首选仓库(无则父件首选仓库), '', row, 子件BOM单位, ratio × 父商品数量)`
   → 自动带出型号/规格/单位/仓库/可用库存（warehouse 的价格由 formatInfo 归零，warehousing 带入库价）。
5. **父商品数量联动**：`.bom_parent_number` 的 `input change` → 逐行 `number = ratio × 父数量` 并 `changeGoodsInfo()` 重算金额。
6. **手动改某行数量即解除联动**：既有 `change .number` 处理器里加 `obj.removeAttr('data-bom-ratio')`，
   程序化 `.val()` 不触发 change，故 BOM 填值与联动不会误解除（同 process_order 约定）。
7. `[清除]` 只删 `tr[data-bom-ratio]` 行并隐藏信息条（已手动改数量而解除联动的行会保留）。
8. BOM 数量口径沿用组装单约定：`子件 number` 是 1 个父件所需量，`子件数量 = number × 父商品数量`；单位取 BOM 明细行的 `unit_id`。

## 已验证（headless Chrome + CDP 实测 erp.licheng.test，测试单据已删除并还原自增）

- 两个 add 页 + 两个 edit 页：按钮/信息条渲染、点开 BOM 弹窗按第一行选中并确定 → 子件行生成（含单位/仓库/可用库存）。
- 父商品数量 1→5 → 数量 1/12/2 → 5/60/10；手动把某行改 7 后再改父数量该行保持 7（联动已解除）；[清除] 后 `data-bom-ratio` 行归零、信息条隐藏。
- edit 页（warehousing ids=2）已有商品行保留，BOM 子件行追加在后。
- 表单保存实测：warehouse add 生成 `parent=2` 后提交 → `fa_kuerp_warehouse_info` 3 行 number 2/24/4、goods_unit/stores_id 正确；
  warehousing 同理（2/24/4）。两张测试单（QTCK20261022001 / QTRK20261022001）已删除，AUTO_INCREMENT 还原。
- 全流程无 JS 异常（CDP `Runtime.exceptionThrown` 监听为空）。

---

# 商品导入：商品类型(成品/半成品/零件)支持 + 模板占位仓库名不再误报（2026-10-22）

> 涉及文件：`application/admin/controller/kuerp/Goods.php`（`import()`）、
> `public/assets/addons/kuerp/template/商品导入模板.xlsx`（该目录 gitignore，需随文件树部署）

## 问题

1. 导入表格里商品类型填「成品」，导入后列表显示「零件」——`import()` 组装 `$params` 时**根本没有 product_type**，
   全部走 DB 列默认值 `part`（`fa_kuerp_goods.product_type enum('finished','semi_finished','part') DEFAULT 'part'`）。
2. 下载官方模板 → 填写 → 导入**必然报“默认仓库仓库不存在”**：校验把表头行(第14行)的仓库占位名
   (默认仓库/绵阳/成都)也当成必须存在的仓库，即使对应的「期初数量」全空。

## 修复

- `import()` 新增「商品类型」列解析：值 `成品/半成品/零件`（也接受 `finished/semi_finished/part`），映射到 `product_type`；
  表头别名 `产品类型 → 商品类型`。**留空不写入**：新增时走 DB 默认零件，更新时保留原商品类型（旧模板导入不会把成品改回零件）。
  非法值整单拒绝：`商品XXX的商品类型“YYY”不正确，请填写成品/半成品/零件`（不写库）。
- 模板 `商品导入模板.xlsx` 在 **计量单位(H) 之后插入「商品类型」(I) 列**，带下拉校验（成品/半成品/零件）、说明文字补第10条；
  原「换算率」等列整体右移一位（导入按表头名匹配，位置不敏感，用户自定义模板列序也能用）。
  用 openpyxl 改写时**必须先解除全部合并单元格再 insert_cols，然后按 >=I 右移 1 重建合并区域与列宽**，
  否则 openpyxl 会丢合并区里的值（实测 L14「价格管理」直接消失）。
- 表头仓库名校验改为**只校验本期初数量列确实填了数量(≠0)的仓库**：占位名留空即忽略，
  真在这类列下填了数量仍会明确报 `XXX仓库不存在`。`首选仓库` 列校验逻辑不变。

## 已验证（curl 登录 erp.licheng.test 实测，测试数据已删净并还原自增）

- 新模板 3 行（零件/成品/半成品）→ `part/finished/semi_finished` ✅；旧模板（无该列）→ 仍为 `part`，不报错。
- 更新路径：旧模板更新已有成品 → 类型保持 `finished`；新模板同单改「半成品」→ `semi_finished` ✅。
- 非法值「成品X」→ 整个导入被拒、未落库 ✅。
- 官方模板**原样**（占位仓库名、不填期初数量）导入成功 ✅；在「默认仓库」列填期初数量 → 仍报 `默认仓库仓库不存在`；
  标题行改成真实仓库名(零件仓)并填 6/成本1.25 → 库存行正确写入 ✅。
- 用户的真实模板布局（无「换算率」列、商品类型在 I、表头「规格型号」）用一行「成品」实测 → `finished` ✅。
- ⚠️ 历史上已导入成 `part` 的商品**不会自动纠正**，需重新导入一次（勾选更新）或到商品编辑页手改商品类型。

---

# 商品BOM模板列表：父商品行 + 子件行折叠（2026-10-22）

> 涉及文件：`application/admin/controller/kuerp/GoodsBom.php`（`index()`）、
> `public/assets/js/backend/kuerp/goods_bom.js`（`index`）、`application/admin/view/kuerp/goods_bom/index.html`（样式）

## 行为

- 列表页每个模板显示为**一条父商品行**（`模板名称 / 子商品` 列：折叠箭头 + 模板名称 + `N 个子件` 徽标，
  另列父商品名称/编号/备注、行操作 编辑|打印|删除），其子件行在下方、**默认折叠**；
  点箭头或模板名称展开/折叠（`expanded` 对象按模板 id 记状态，翻页/搜索后保持）。
- 子件行：缩进 + `-` 图标 + 子件名称、商品编号、单耗数量、子件备注；**无勾选、无行操作**
  （勾选父行即代表整个模板）。

## 实现要点

- 后端 `index()` 读 `tree` 参数：传 `tree=1` 时每个模板先输出一条 `row_type=1` 的父商品行（多 `child_count` 字段），
  再输出 `row_type=2` 的子件行；**BOM选择弹窗 `chosebom` 不传该参数**，仍是原「每行一条子件、父信息按 id 合并」布局（`tableMerge` 未动）。
- 前端 `url` 写成 `'kuerp/goods_bom/index?tree=1'`（**不要放 `where`**：搜索/刷新等 `table.reload` 会整体替换 `where`，query 参数不会丢）。
- 折叠是**数据行隐藏**（不是拼 DOM）：`done` 里给主表与右侧固定列的行打 `data-bom-parent` / `data-bom-child`，按 `expanded`
  用 `show()/hide()` 同步两边；同时按 `goods.js` 同一写法逐行同步固定列行高，避免展开后操作列错位（narrow 宽度实测 main/fixed 行 top 完全一致）。
- 行操作按钮改用列 `templet` 输出 `$('#barDemoBom').html()`（仅 `row_type=1` 输出，子件行为空），`lay-event` 事件照常可用。
- 分页总数仍是**模板数**（`$list->total()`），一页 10 个模板，折叠后即 10 行。

---

# BOM明细新增「放置位置」（2026-10-22）

> 涉及文件：`addons/kuerp/migration_add_goods_bom_location.sql`（已对 erp_licheng 执行）、
> `application/admin/controller/kuerp/GoodsBom.php`、`application/admin/view/kuerp/goods_bom/{add,edit,printpage}.html`、
> `public/assets/js/backend/kuerp/goods_bom.js`、`application/admin/lang/zh-cn/kuerp/goods_bom.php`

## 字段

- `fa_kuerp_goods_bom_info.location` **varchar(255) NOT NULL DEFAULT ''**（AFTER `number`），
  **父商品行(type=1) 与子商品行(type=2) 各自一条值**——父件的放置位置存在父行上，不是主表字段。
- 迁移可重复执行；`fa_kuerp_goods_bom[_info]` 两张表**不在 `install.sql` 里**（历史遗留），故本次只加迁移文件，
  未动 `install.sql` / `info.ini`。

## 录入与展示

- 建/改 BOM 表单：父商品表格「单位」后新增 `放置位置` 输入框（`name="row[location]"`），
  子商品表格「单位」与「备注」之间新增（`name="child_location[]"`，与 `child_goods_id[]` 并行下标对齐）。
  `addChildRow()` 克隆新行时清空 `.child_location`。
- 列表页（`tree=1`）：~~父行显示父件放置位置、子件行显示各自放置位置~~ → **2026-11-03 起「放置位置」列已从列表页移除**，
  改为在「商品编号」与「单耗数量」之间展示 3 列 **型号 / 规格2-厂商 / 规格3-封装**（lang key `Goods_specification_id` / `Vendor_name` / `Packaging`），
  取值来自 `kuerp_goods.goods_specification_id / vendor_name / packaging`：**父商品行取父商品、子件行取子商品**
  （2026-11-04 修正：此前只在父行显示、子件行留空，用户反馈子件看不到型号/规格，已为子件查询补取这三列并逐行下发）；
  `GoodsBom::index()` 的父/子商品查询都取这三列。放置位置字段本身仍在 add/edit/打印页保留。
- 打印页：父级商品信息块增加「放置位置」一项；子件表增加「放置位置」列，空数据行 colspan 7→8。
- **Excel 导入（preview/createBom）不含放置位置**，导入的 BOM 该字段为空串，可在编辑页补填。

## 已验证（curl 登录 + headless Chrome/CDP 实测 erp.licheng.test，测试 BOM 已删净并还原自增）

- add 落库：父 `A区-01货架` + 子 `B区-02箱`/`C区-03柜` 三行 location 正确；edit 改值后重新落库正确；
  edit 页回显（父 1 个 + 子件每行 1 个 input 均带值）。
- 列表接口/页面：`放置位置` 列渲染父子行各自的值；展开子件行正常。
- 打印页：父信息块出现「放置位置：A区-99货架」，子件表「放置位置」列有值。
- ⚠️ `edit()` 解析并行数组时必须同时读 `child_location/a`（本次踩过：只改了 `add()` 后编辑保存会把子件放置位置清空）。
- 清理：测试模板 ZZTEST-LOC（bom 113 / info 5001-5003 及编辑产生的重建行）已删除，`AUTO_INCREMENT` 回到 113 / 5001。

---

# 其他出库单/其他入库单：Excel 模板下载 + 明细导入（2026-10-22）

> 涉及文件：`application/admin/library/traits/WarehouseExcel.php`（新，共用 trait）、
> `application/admin/controller/kuerp/{Warehouse,Warehousing}.php`（`use WarehouseExcel` + `$noNeedRight`）、
> `application/admin/view/kuerp/{warehouse,warehousing}/{add,edit}.html`、
> `public/assets/js/backend/kuerp/{warehouse,warehousing}.js`（**无数据库迁移**）

## 入口

明细表上方的 BOM 工具条里，`[调用BOM]` 右侧新增 `[下载模板]` `[导入Excel]`（+ 隐藏 `<input type="file" id="import_excel_file">`），
add / edit 页都有；已审核单据由 `bindevent` 的 `$('.btn').attr('disabled')` 一并禁用（下载模板也改成了按钮，否则 `<a>` 的 disabled 不生效仍可点）。

## 模板（`WarehouseExcel::template()`，GET，$noNeedRight）

- 3 个 sheet：`出入库明细`（第 1 行表头、数据从第 2 行、预留 500 行）/ 隐藏的 `Lists`（下拉源）/ `填写说明`。
- 列：**商品编号\*** / 商品名称（VLOOKUP 公式自动带出，导入以编号为准）/ 商品单位 / 仓库 / 数量\* /（**仅入库单**）入库单价 / 备注。
- 商品编号、商品单位、仓库三列为跨表区间下拉（`DataValidation::TYPE_LIST` + `setShowDropDown(true)`，同购货订单模板的坑：不设会被隐藏）；
  数量 >0 校验、单价 ≥0 校验；商品编号/单位/仓库一律 `setCellValueExplicit(TYPE_STRING)` 防前导零丢失。
- **PHP 7.4 的 trait 不能定义常量**（`Traits cannot have constants` 是 8.2 才支持）——本 trait 用 `protected $excelOptSheet/$excelTemplateRows/$excelSheetTitle` 属性；
  同理 trait 的 `$excelDocType` 默认值不能由使用方覆盖（PHP 要求默认值完全一致），故改成 **方法** `excelDocType()`，
  `Warehouse` 返回 `'out'`（无单价列）、`Warehousing` 返回 `'in'`（含「入库单价」列）。

## 导入（`WarehouseExcel::parseimport()`，POST multipart `file`，$noNeedRight）

- 前端：`[导入Excel]` → 隐藏 file input → **FormData 直接 POST** `kuerp/{warehouse|warehousing}/parseimport`（一次请求，不走 ajax/upload）。
- 后端只解析校验、**不写库**：ext 仅 xlsx/xls、≤10M；sheet 先按名 `出入库明细`、取不到回退 sheet 0；表头扫描前 10 行找「商品编号」，按名称/alias 容错匹配。
- 硬性必需列只有 `商品编号`、`数量`；**有错整单拒绝**（前 8 条带行号中文提示）：商品不存在 / 数量非大于 0 / 仓库名不存在 / 单价非数字或负数。
- 软性降级为 `warnings`（仍导入）：仓库留空→商品默认仓库（无默认仓库则报错）/ 单位名不适用→默认单位 / 启用批次·序列号的商品提示导入后补齐。
- 返回 `{rows:[{goods_code,goods_name,goods_unit(单位ID,0=默认),stores_id,number,unit_price,remark}], warnings:[]}`。

## 前端落行（`applyImportedRows`）

- 保留 `#purchase_table` 第 0 行（表头）与第 1 行（隐藏模板行），其余行整体移除后用模板 HTML 重建（guest 手工行会被替换，**有数据时先 `Layer.confirm` 确认**）。
- 每行先填 `goods_code/goods_name/remark`，再调 `changeGoods(编号, 仓库, '', row, 单位, 数量, callback)` 复用原有的单位/库存/价格计算链。
- **入库单价必须在 `changeGoods` 之后写入**，且不能写在它的 success 末尾——`changeGoods` 内部还会发 `formatInfo` 请求，
  该请求返回更晚会把单价/金额覆盖成商品档案价格。故 `changeGoods` 新增可选第 7 参 `callback`：内部 `obj.data('warehouseAfterChange', callback)`，
  **由 `formatInfo` 的 success 末尾统一触发**（`var afterChange = obj.data('warehouseAfterChange')` …），这时才能可靠地覆盖为导入单价。
- 出库单模板无单价列（`unit_price` 为空串），落行后单价/金额仍为 0，与手工选货一致。

## 已验证（curl + headless Chrome/CDP 实测 erp.licheng.test，测试单据已删除、AUTO_INCREMENT 已还原）

- 模板：两单据均 200 + attachment + 中文文件名（`其他出库单导入模板_时间戳.xlsx`）；3 sheet、Lists 隐藏；列头 6/7 列；
  下拉 `showDropDown="0"`（=显示）区间正确（商品 1132 / 单位 15 / 仓库 6）；数量/单价数字校验；商品名称 VLOOKUP 有缓存值。
- 导入解析：正常两行（仓库留空→默认仓库 warning、备注、入库单价 12.34 均正确）；错误路径 4 类一次返回（商品不存在/仓库不存在/数量为 0/单价为负）；
  空模板、无文件、.csv 均返回可读中文提示。
- 浏览器：`DOM.setFileInputFiles` 触发 change → 行生成（单位/仓库/库存/备注齐）；入库单价 12.34×3=37.02 正确落行；
  edit 页有数据时弹「导入会替换当前全部明细行」确认；点「下载模板」CDP 实测产生下载、页面不跳转；全流程无 JS 异常。
- 保存：两单据各按导入行提交成功，`number/unit_price/price/remark` 落库正确（入库 12.34→37.02）。
- ⚠️ 本站 `number_decimal=0`（数量取整），所以 Excel 里的 2.5 会被 `formatInfo` 四舍五入成 3——这是既有设置行为，与手工录入一致。

---

# 销货订单「代发/直发」标记（2026-10-22）

> 涉及文件：`addons/kuerp/migration_add_delivery_mode.sql`（已对 erp_licheng 执行）、`addons/kuerp/install.sql`（1.6.15 段 + CREATE TABLE）、`addons/kuerp/info.ini`（1.6.15）、
> `application/admin/controller/kuerp/{GoodsSalesOrder,GoodsSales,GoodsSalesSchedule,GoodsSalesOutbound,GoodsOutboundApply}.php`、
> `application/admin/model/kuerp/{GoodsSalesOrder,GoodsSales,GoodsSalesSchedule}.php`、
> `application/admin/view/kuerp/goods_sales_order/{add,edit}.html`、
> `public/assets/js/backend/kuerp/{goods_sales_order,goods_sales,goods_sales_schedule,goods_sales_outbound,goods_outbound_apply}.js`、
> 5 个 lang 文件

## 字段与录入

- `delivery_mode` enum('direct','dropship') NOT NULL DEFAULT 'direct'，含义：`direct`=直发、`dropship`=代发。
  四张表同名字段：`fa_kuerp_goods_sales_order[_info]`、`fa_kuerp_goods_sales[_info]`（销货单与计划排期共用表）。
- 销货订单 add/edit 表头新增「代发/直发」下拉，**整单一个标记，默认直发**；保存时写入订单主表 + 每条明细。
- 编辑页若表单被禁用（已审核/已生成销货单/退货单时 JS 统一 `disabled`）导致参数缺失，**保留原值**，不能回落成直发
  （`normalizeDeliveryMode()` 的 default 传当前 `$row['delivery_mode']`）。

## 同步链路

- **生成销货单**：`GoodsSales::add($ids)` 中 `$ids` 即销货订单 id，按订单的 `delivery_mode` 写入销货单主表 + 明细；
  无关联订单默认直发。销货单 edit 未提交该字段时保留原值。
- **计划排期 / 销货单 / 销货出库列表**：`delivery_mode` 字段随行返回；有 `purchase_order_id`（销货订单号）关联时
  批量查订单并**以订单值为准动态覆盖**（排期行自身值兜底），无关联默认直发。
- **出库管理列表**：按 `sales_id` 查 `kuerp_goods_sales.delivery_mode` 后回填。
- 迁移会把已有销货单/计划排期/明细按 `purchase_order_id` 从订单回填一次。

## 列表列

销货订单、销货单、计划排期、销货出库、出库管理 5 个列表均新增「代发/直发」列；`dropship` 用橙色 `#ff5722` 标出，其余显示「直发」。

## 已验证（curl 登录 + headless Chrome/CDP 实测 erp.licheng.test，测试数据已删净并还原自增）

- 订单 add：不带参数默认 `direct`；带 `dropship` 落主表+明细均为 `dropship`；edit 改代发落库；edit 不传参数保留代发。
- 生成销货单（订单12→销货单14）：销货单主表+明细均为 `dropship`（`purchase_order_id` 关联正确）。
- 5 个列表接口/页面：订单/销货单/排期/出库均返回 `delivery_mode`+`delivery_mode_text`（直发/代发），出库管理按 `sales_id` 派生正确；
  表头渲染「代发/直发」，浏览器无 JS 异常；add 页下拉被 select-enhancer 转为 bootstrap-select（无搜索框），默认「直发」。
- 清理：测试订单/明细/销货单/明细/关联表/sales_log/order_log 全部删除，`AUTO_INCREMENT` 按快照还原（订单 12、明细 53、销货单 14、销货明细 33、saleord 8、saleord_info 14、order_log 78、sales_log 18）。

## 详情/打印页展示（2026-10-22 补充）

在上述 5 个模块的**详情/编辑/打印**页面也展示「代发/直发」（只读文本，取值口径与列表一致）：

| 页面 | 展示位置 |
|------|---------|
| 销货单 `goods_sales/edit.html`、`printpage.html` | 表头信息区「代发/直发」 |
| 计划排期 `goods_sales_schedule/edit.html`、`printpage.html` | 表头信息区「代发/直发」（按关联销货订单取值） |
| 销货出库 `goods_sales_outbound/edit.html`、`printpage.html`、`apply.html`（申请弹窗）、`outbound.html`（实际出库弹窗） | 表头信息区 |
| 出库管理 `goods_outbound_apply/detail.html`、`edit.html` | 基本信息区 |
| 销货订单 `goods_sales_order/edit.html` | 表头下拉框（可编辑，原已实现） |

实现：`GoodsSales` / `GoodsSalesOutbound` / `GoodsSalesSchedule` 各新增 `appendDeliveryModeText($row)`，
有 `purchase_order_id` 时以销货订单值为准并覆写 `$row['delivery_mode']`（`delivery_mode_text` 交给模型 append 访问器计算，
**不要再手工写 `delivery_mode_text`**：访问器把传入值当作 mode 代码，写中文会导致回落成"直发"）；`GoodsOutboundApply::assignApplyDetail()` 按
`sales_id→销货单→销货订单` 解析（apply 模型无该 append，文本直接赋值）。`goods_sales_outbound/outbound.html` 与 `apply.html` 共用 `buildOutboundRows()`，因此一处补齐两页生效。
打印页沿用原 `info-grid` 布局；打印会自增 `print_num`，测试后已按快照还原（id11=0、id13=1）。

---

# 销货订单填写「客户承担费用」并带入销货单（2026-10-22）

> 涉及文件：`addons/kuerp/migration_add_sales_order_customers_money.sql`（已对 erp_licheng 执行）、`addons/kuerp/install.sql`（1.6.16 段 + CREATE TABLE）、`addons/kuerp/info.ini`（1.6.16）、
> `application/admin/controller/kuerp/GoodsSalesOrder.php`、`GoodsSales.php`、
> `application/admin/view/kuerp/goods_sales_order/{add,edit}.html`、
> `public/assets/js/backend/kuerp/goods_sales_order.js`

## 字段

- `fa_kuerp_goods_sales_order[_info]` 新增 `customers_money double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '客户承担费用'`。
- 原先该费用只能在**销货单** add/edit 填写（`fa_kuerp_goods_sales.customers_money`）；现在销货订单即可填写。

## 录入与带出

- 销货订单 add/edit 表单底部「金额」右侧新增「客户承担费用」输入框（`row[customers_money]`，`price_decimal`）。
- 订单保存：主表 + 明细都写该值；编辑时若表单未提交（只读/禁用）保留原值。
- 销货订单列表新增「客户承担费用」列（`price_decimal` 格式化）。
- 生成销货单：`GoodsSales::add($ids)` 的 add 页 `$row` 直接取自销货订单，视图 `#c-customers_money` 自动预填；
  POST 未携带该字段时后端再从订单兜底读取（显式传空串视为用户清空，按 0 处理）。
- 销货单侧金额口径不变：`customers_money` 不计入 `real_money`，计入应收/欠款（`real_money + customers_money`）。

## 「本次欠款/总欠款」预填包含客户承担费用（2026-10-28）

**问题**：从销货订单生成销货单时，页面初始「本次欠款」只取 `$row.real_money`，漏掉客户承担费用 70 → 显示 1130（应为 1200），
直接提交就落库 `arrears=1130`，客户欠款少算。**原因**：`goods_sales.js` 的 `changeInfo()` 里确实有
`money = money + customers_money` 再算 arrears，但 `changeInfo()` 只在 change 事件（改商品/费用/收款）时触发，
**页面加载时不会执行**，所以初始值只能靠服务端渲染，而服务端预填用的是裸 `{$row.real_money}`。

**修复**（`GoodsSales::add()` + `goods_sales/add.html`）：

```php
// 客户承担费用需计入「本次欠款/总欠款」，与前端 changeInfo() 的口径保持一致
$customers_money_default = isset($row['customers_money']) ? (float)$row['customers_money'] : 0;
$this->view->assign('arrears_default', round((float)$row['real_money'] + $customers_money_default, 4));
$give_account = (float)$give_account + $customers_money_default;   // 总欠款同理
```

视图 `#c-arrears` 的 `value` 由 `{$row.real_money|default=''}` 改为 `{$arrears_default|default=''}`；
总欠款走既有 `$give_account`（该变量稍后 `assign` 给视图，故必须在 `assign` 之前加）。

⚠️ `add.html` 里的 `$row.real_money` 是「本次未生成部分的金额」（`$real_moneys`，已扣除 `saleord_info` 已生成数量），
不是整单金额；`$arrears_default` 必须在同一位置基于它计算，**不能**改成读 `$row['arrears']`（那是订单的值，口径不同）。

**已验证**（headless Chrome + CDP 实测 erp.licheng.test，测试数据已删净并还原自增）：
订单 `customers_money=70`、剩余金额 1130 → add 页 本次欠款 `1200`、总欠款 `3201`（客户余额 2001+1130+70）；
浏览器点提交落库 `arrears=1200 / all_arrears=3201`（修复前为 1130/3131）；
改费用 70→200 触发 change → 1217，收款 100 → 1117（JS 联动不受影响）；无订单直接新建仍为 0/0，HTTP 200。


## 已验证（curl 登录 erp.licheng.test，测试数据已删净并还原自增）

- 订单 add 传 `customers_money=50` → 主表/明细均 50；订单列表接口返回 50、编辑页回显 50。
- 从该订单打开销货单 add → `#c-customers_money` 预填 50；提交后销货单 `customers_money=50`、`arrears=163`（113+50）。
- 订单/销货单 add/index 页面 HTTP 200 无报错；测试单据与日志全部删除，`AUTO_INCREMENT` 按快照还原
  （order 12、order_info 53、sales 14、sales_info 33、saleord 8、saleord_info 14、order_log 78、sales_log 18）。

---

# 采购入库弹窗：本次入库数量默认待入库数量 + 入库前选择原始单据（2026-10-23，2026-11-06 默认值改回待入库数量）

> 涉及文件：`application/admin/controller/kuerp/GoodsPurchaseStorage.php`、`application/admin/view/kuerp/goods_purchase_storage/{storage,enclosurepicker}.html`（后者新增）、
> `public/assets/js/backend/kuerp/{goods_purchase_storage,goods_purchase_storage_layer}.js`（**无数据库迁移**）

## 改动

- **本次入库数量默认 = 待入库数量**（2026-11-06 按用户要求由 `value="0"` 改回 `value="{$remainNum}"`）：
  `storage.html` 的 `.storage-number` 默认填该行待入库数量，直接点【确认入库】= 整批入库，要部分入库就手工改小；
  `max` 仍为待入库数量（`max="{$remainNum}"`），JS 的「至少一项 >0」校验与「部分入库」确认（`confirm`）行为不变
  （默认值下 `allFull` 为真，不会弹部分入库确认）。
  `goods_purchase_storage_layer.js` 的 `init()` 里在绑定完 input 监听后**补一次 `updateTotal()`**：
  汇总行「本次入库总数量」`#storage-total` 原先只在 input/change 时刷新，默认有值时会一直显示 0，必须初始化时就汇总一次。
- **入库前选原始单据**：表单底部（汇总行与 [确认入库] 之间）新增「原始单据：……」区域 =
  `#sel-enc-list`（已选预览：缩略图/文件图标 + 文件名 + × 移除）+ `[+ 选择原始单据]` +
  隐藏域 `<input type="hidden" name="enclosure_ids">`（逗号分隔 ID，随 FormData 一起提交）。
  选中后页面内即时回显，提交成功后随整页 `location.reload()` 归零。
- **提交即建立关联**：`storage()` POST 读 `enclosure_ids`（顶格字段，不在 `row/a` 里），
  在**写入 `kuerp_goods_purchase_storage_log` 的同一事务内**（第 9 步）逐条插 `kuerp_enclosure_log`
  （`type='7'`、`order_id`=本次入库记录ID、`purchase_code`=采购单号），并先用 `kuerp_enclosure` 校验 id 确实存在且 `type=1`。
  入库成功后「操作记录」面板（`buildStorageLogs`）照旧把该记录下的原始单据渲染出来。
- 原有「操作记录每条后面的 [+ 上传原始单据]」保留（入库后补传），两条路径互不冲突。
- **入库前直接上传附件（2026-11-03）**：「原始单据：」行的 `[+ 选择原始单据]` 旁新增 **`[+ 添加单据附件]`**
  （`#btn-upload-new` + 隐藏 `#enc-upload-file`，同属 `#enc-select`，其 `data-upload-url` 指向 `kuerp/goods_purchase_storage/uploadenclosure`）。
  点击 → 选文件 → `goods_purchase_storage_layer.js` 的 `initEnclosureSelect()` 用原生 `XMLHttpRequest` + `FormData` 直接 POST
  `uploadenclosure`（**不带 ids**）→ 后端只建 `kuerp_enclosure`（type=1）**不建 enclosure_log**，返回 id/name/url/is_image/file_suffix →
  前端 push 进已选列表并渲染（缩略图 + 文件名 + ×），id 进入隐藏域 `enclosure_ids`，提交入库时由第 9 步统一关联（type=7）。
  因此 `uploadenclosure($ids=null)` 现在 `$ids` 为空时**不再报「入库记录不存在」**，只为「带 ids 上传」保留该守卫。
  上传成功前按钮显示「上传中…」并禁用；失败 alert 后端 msg。附件若最终未提交入库，会作为一条普通采购原始单据留在列表里（可在原始单据页删除）。
- **操作记录表：原始单据与本次记录同处一行**（2026-10-23 按用户要求）。原来附件单独占一个 `tr.enc-row`（跨列表头下方），
  现改为 5 列：`入库时间 | 本次入库数量 | 入库明细 | 原始单据 | 操作人`，附件缩略图/图标 + `×` 删除 + `[+ 上传原始单据]`
  都放在「原始单据」单元格里（`.enc-list` 由 `<div>` 改 `<span>` 以与按钮同行；`.enc-item img` 由 60px 降到 40px）。
  `goods_purchase_storage_layer.js` 的 `initLogPanel` 只按 `data-log-id` / `.enc-list[data-log-id]` 取节点，结构变化无需改 JS。
- ⚠️ **`storage.html` 的 `<script src>` 必须带版本号**：该页是裸 HTML（不走 require.js，没有 `urlArgs`），
  而 nginx 对 `/assets/` 返回 `Cache-Control: max-age=43200`（12 小时）。不带 `?v=` 时浏览器会用缓存里的旧 JS，
  表现为「按钮在页面上、点了没反应」（本次用户即因此报"选择原始单据无法用"）。现写成
  `<script src="/assets/js/backend/kuerp/goods_purchase_storage_layer.js?v={:time()}"></script>`，别再改回裸路径。

## 选择弹窗实现（避免依赖 enclosure 权限）

仓库角色通常没有 `kuerp/enclosure/*` 的权限，直接复用 `kuerp/enclosure/selectpage` 会因 `kuerp/enclosure/index` 无权限而空白，
故在本控制器内单独做了一套（全部在 `$noNeedRight`，无需新建 auth_rule）：

| 方法 | 作用 |
|------|------|
| `enclosurelist()` | AJAX：`type=1` 的原始单据；支持 `name` 模糊搜索、`page/limit` 分页、`ids` 回查（回查不分页） |
| `enclosurepicker()` | 渲染 `goods_purchase_storage/enclosurepicker.html`（layui 复选列表 + 确定/取消） |

- 弹窗 JS：`goods_purchase_storage.js` 新增 `Controller.enclosurepicker`（`table.render` → `enclosurelist`，
  文件列用 `Fast.api.fixurl('ajax/icon')` 拼图标，`确定` → `Fast.api.close(ids)`）。
- `storage.html` 打开方式：`window.parent.Fast.api.open('kuerp/goods_purchase_storage/enclosurepicker', ...)` 的 `callback` 收 ids；
  入库页本身是**纯 HTML 页面（无 jQuery）**，回显用原生 `XMLHttpRequest` POST `enclosurelist`（`ids=1,2,3`）拿名称/缩略图。
- ⚠️ `fillFileInfo()` 只接受**模型对象**（内部 `$row->getData()`），所以 `enclosurelist` 必须用
  `\app\admin\model\kuerp\Enclosure` 查询（`Db::name()` 返回的数组会报错）。

## 已验证（curl + headless Chrome/CDP 实测 erp.licheng.test，测试数据已逐项还原）

- 接口：`enclosurelist` 分页（3 条）、`name=微信` 搜索（1 条）、`ids=7,8` 回查（2 条）均正确返回 `name/url/is_image/file_suffix`。
- curl 提交 `GHD20261020004`：`row[storage_number]=1,2` + `enclosure_ids=7` → 入库成功；
  `fa_kuerp_goods_purchase_storage_log` 新增记录、`fa_kuerp_enclosure_log` 新增 `type=7 / order_id=新记录ID / enclosure_id=7`，
  库存/明细/订单/桥接表全部按原逻辑更新；入库页「操作记录」渲染出该记录 + 图片附件。
- 浏览器（CDP）实测：弹窗两个数量框均为 `0`、原始单据区显示「未选择（可留空）」→ 点 [选择原始单据] 弹出候选列表（3 行）
  → 勾选第一行点确定 → 回到入库弹窗，隐藏域 `enclosure_ids=8`、预览出现缩略图 + 文件名 → 填 1 件提交
  （「本次为部分入库」确认框）→ 页面自动重载，「操作记录」首行即为本次入库（时间/数量/明细/操作人）且下方带刚选的图片附件；
  提交后隐藏域自动清空。
- 清理：测试产生的库存/成本/inventory/purchase_info/order_info/purchase_order/purord_info 全部按快照还原，
  `storage_log` 与 `enclosure_log` 的测试行（2、3）删除，`AUTO_INCREMENT` 回到 2；用户已有的入库记录（id=1）与附件未动。
- 同处一行的效果（CDP 截图核对）：操作记录表 5 列，附件缩略图、`×`、`[+ 上传原始单据]` 全在「原始单据」单元格内，
  与时间/数量/明细/操作人同一行的垂直中线对齐。
- 缓存问题复现与修复：修复前页面 `<script src>` 无版本号（nginx `max-age=43200`），浏览器继续用旧 JS → 点按钮无反应；
  加 `?v={:time()}` 后，在 **主窗口 → 标签页 iframe（列表）→ 入库弹层** 的真实三层结构下实测，弹窗内 script 的 `src`
  带上了新版本号，点 [选择原始单据] 正常弹出候选列表（列出 `kuerp/enclosure` 同一批 type=1 单据，2 条）。
  ⚠️ 用户端若仍看到旧行为，强刷（Cmd+Shift+R）一次即可。

## 入库详情/打印页增加商品规格三列（2026-11-03）

- `goods_purchase_storage/detail.html`（入库单详情弹窗）与 `printstorage.html`（入库单打印）明细表在「商品名称」之后新增
  **型号 / 规格2/厂商 / 规格3/封装** 三列（值取 `kuerp_goods.goods_specification_id / vendor_name / packaging`，商品已删则留空）。
- 控制器 `detail()` / `printstorage()` 的 `kuerp_goods` 查询 field 补这三列，并逐行写进 `$purchase_info[$k]`（两处是复制代码，需同步改）。
- 列数 11 → 14：空数据行 `colspan` 改 14；合计行 `<th colspan="7">合计</th>` 改 **10**（序号…库位 = 10 列）。
- `lang/zh-cn/kuerp/goods_purchase_storage.php` 补 `Goods_specification_id` / `Vendor_name` / `Packaging` 三个 key
  （不加则页面上直接显示英文 key）。A4 竖版 718px 内 14 列无横向溢出（CDP 实测 `tableW = wrapW = 718.125`），表头会换行显示。

---

# 采购对账 + 付款申请（2026-10-23）

> 需求文档：`Desc/采购对账与付款申请-需求规格.md`
> 迁移：`addons/kuerp/migration_add_purchase_recon.sql`（已对 erp_licheng 执行，可重复执行）；
> `addons/kuerp/install.sql` 1.6.17 段 + `info.ini` 版本 1.6.17。
> 新增：`PurchaseRecon.php` / `PurchasePaymentApply.php` 控制器、5 个模型
> （`PurchaseRecon` / `PurchaseReconInfo` / `PurchaseReconInvoice` / `PurchasePaymentApply` / `PurchasePaymentApplyInfo`）、
> `view/kuerp/purchase_recon/*`、`view/kuerp/purchase_payment_apply/*`、两个 JS。

## 流程

```
采购：勾选采购单(已审核/未对账) → 生成对账单(供应商+期间) → 采购单回写 recon_id(已对账)
    → 上传对账单原始单据 → 提交财务审核
财务：详情页核对 供应商合同 + 各采购订单原始单据 + 每次入库记录及入库原始单据 + 对账单附件
    → 通过(status=2) / 驳回(status=3，采购可编辑后重提)
采购：上传发票(文件+发票号+金额+开票日期) + 账期天数 → due_date = 上传日 + 账期天数
    → 到期前 N 天标黄 / 逾期标红（N=设置项 purchase_payment_remind_days，默认 7）
采购：发起付款申请(同供应商多张对账单) → 财务 → 审计 →（金额≥阈值时）领导 → 出纳
出纳：选账户/日期/流水号 + 上传回单 → 自动生成付款单并审核入账(账户扣款/应付减少/采购单核销)
```

## 关键口径

- 对账金额 = 采购单 `real_money`（优惠后价税合计，**整单金额，不看入库进度**；部分入库的采购单也可对账）。
- 对账单候选采购单：`purchase_type=1`、`examine_status=1`、`recon_id=0`，且未结账（`Checkout::checkOutTime`）。
- 已对账标记新增 `fa_kuerp_goods_purchase.recon_id/recon_code`，**不覆盖原有 `check_status`(对单)语义**。
- 付款申请金额 = 所选对账单 `total_money` 合计；出纳打款时按 `real_money - pay_money` 逐单封顶核销，
  与申请金额不一致（存在线下已付款）时明确报错并整体回滚。
- 领导审核阈值 = 设置项 `purchase_payment_leader_money`（默认 50000，0=不启用）；
  `need_leader` 在提交时按阈值固化，避免审批中途改配置导致流程跳变。
- 付款落地复用 `Payment::examine()`：程序内调用并解析 `HttpResponseException`（成功也抛），
  失败则整体回滚（事务嵌套走 MySQL SAVEPOINT）。
- 附件仍走 `fa_kuerp_enclosure` + `fa_kuerp_enclosure_log`，新增 type：**8=对账单附件、9=发票、10=付款回单**。
- 单号前缀：30=采购对账单 `DZD`、31=付款申请 `FKSQ`（`fa_kuerp_prefix`，设置页「单号设置」可见）。
- 设置项：`purchase_payment_remind_days`、`purchase_payment_leader_money`（均为 type=1，设置页「基础设置」可见）。

## 状态

- 对账单 `status`：0 草稿 / 1 待财务审核 / 2 已审核 / 3 已驳回；
  `invoice_status`：0 未开票 / 1 已开票；`pay_status`：0 未付款 / 1 付款申请中 / 2 已付款。
- 付款申请 `status`：0 草稿 / 1 待财务 / 2 待审计 / 3 待领导 / 4 待出纳 / 5 已付款 / 6 已驳回。
- 驳回后对账单/付款申请可编辑重提；删除付款申请会把关联对账单 `pay_status` 释放回 0；
  删除对账单会把采购单 `recon_id` 清 0。

## 权限

菜单与规则已由迁移插入（采购组下「采购对账」，资金组下「付款申请」），
**角色勾选需在「角色管理」里分配**：采购（对账 add/edit/del/submit/invoice + 付款申请 add/edit/del/submit）、
财务（recon audit + payment financeexamine）、审计（auditexamine）、领导（leaderexamine）、出纳（pay）。
AJAX 辅助方法 `purchaselist` / `reconlist` / `uploadfile` / `uploadvoucher` 在 `$noNeedRight`（仍需登录）。
新增菜单后如侧栏不显示，删 `runtime/cache` 下的 `__menu__` 缓存（本次已清一次）。

## 顺带修复

- `Payment::examine()` 在**没有采购开票记录**时 `$income['status']` 对 null 取下标（PHP 8 报
  `Trying to access array offset on value of type null`，导致自动生成付款单审核失败）→ 改 `$income ? $income['status'] : 0`（两处）。

## 已验证（curl + headless Chrome/CDP 实测 erp.licheng.test，测试数据已全部清理）

- 全流程：建对账单(DZD20261023001) → 提交 → 财务通过 → 上传发票+30天账期(到期日 2026-11-22) →
  付款申请(FKSQ…) → 财务 → 审计 → 出纳打款 → 自动生成付款单 FK20261023001 并已审核：
  账户余额 -1500→-2500、供应商应付 0→-1000、采购单 pay_money 1000/pay_status 1、对账单 pay_status 2、申请单 status 5。
- 领导分支：60000 元对账单 → 财务/审计通过后 status=3（need_leader=1）→ 领导通过 → status=4。
- 驳回/编辑/删除：对账单驳回→可编辑→重提；付款申请财务驳回 status=6 → 删除 → 对账单 pay_status 回到 0；
  对账单删除 → 采购单 recon_id 清 0。
- 重复对账拦截（同一采购单不可进两张对账单）。
- 浏览器实测：对账列表(3 行/按钮/到期日颜色)、新增页(选供应商→加载候选、勾选→金额合计、隐藏 purchase_ids)、
  编辑页(已选采购单自动勾选)、详情页(合同/入库单/对账单/发票/付款/日志 8 个面板)、付款申请列表/详情、预设对账单进入付款申请自动勾选。
- 设置页渲染出两个新设置项；侧栏出现「采购对账」「付款申请」。

## 补充页面与资料展示（2026-10-23 第二轮）

> 迁移：`addons/kuerp/migration_add_purchase_recon_pages.sql`（已执行）；
> `PurchaseRecon.php` 新增 `purchases()` / `supplierstat()` / `materials()`；
> `PurchaseRecon` 模型新增静态 `materials($reconIds)`；
> 视图 `purchase_recon/{purchases,supplierstat}.html`、`audit.html` 内嵌资料区；
> `purchase_payment_apply/{add,edit,detail}.html` 资料列/资料行；两个 JS 同步。

- **采购单列表**（`purchase_recon/purchases`）：全供应商采购单，筛选 日期区间/单号/供应商/对账状态/开票状态/付款状态；
  列含 入库状态(未/部分/已入库)、对账状态(+单号)、开票状态、付款状态、已付金额；已对账行可跳详情。
  需求原文「是否已出库」按采购业务实现为入库状态。
- **供应商待付款统计**（`purchase_recon/supplierstat`）：按供应商汇总 采购额/已对账/未对账/已开票/待开票/已付款/待付款/逾期，
  顶部显示筛选结果合计，按待付款倒序；无分页。
- **资料集中装配**：`\app\admin\model\kuerp\PurchaseRecon::materials($reconIds)` 返回每个对账单的
  `files`(type=8) / `contract`(供应商合同) / `orders`(采购订单 type=1) / `storages`(每次入库 type=7) / `invoices`(type=9)；
  财务审核弹窗与付款申请详情服务端渲染，付款申请新增/编辑页「资料」按钮 AJAX 展开（`purchase_recon/materials`，已加入 `$noNeedRight`）。
- **守卫**：已对账采购单禁止 编辑/删除/反审核（`GoodsPurchase::edit/del/_examine`）；
  付款申请生成的付款单禁止在付款单模块反审核（`Payment::examine` 反审核分支校验 `purchase_payment_apply.payment_id`）。
- **注意**：`purchases()` 的 count 与列表使用 `buildPurchaseQuery()` 每次新建 Query，**不能** `clone $query` 后 count 再复用
  （TP5 会报 `No data supplied for parameters in prepared statement`）。
- 已验证：两个新页面/接口 200 与筛选结果正确、资料 API 含入库历次单据与发票、审核弹窗/付款申请详情/新增页资料展示正常、
  草稿付款申请详情资料行渲染、菜单已出现在侧栏（清过 `__menu__` 缓存）；测试数据已清理。

---

# 销货对账（客户对账单）（2026-10-27）

> 需求规格：`Desc/销货对账-需求规格.md`；迁移：`addons/kuerp/migration_add_sales_recon.sql`（已对 erp_licheng 执行）；
> `install.sql` 1.6.18 段 + `info.ini` 1.6.18。
> 新增：`SalesRecon.php` 控制器、5 个模型（`SalesRecon` / `SalesReconDoc` / `SalesReconInfo` / `SalesReconInvoice` / `SalesReconLog`）、
> `view/kuerp/sales_recon/{index,items,edit,detail,invoice,printpage}.html`、`sales_recon.js`、lang。

## 流程

```
工作台(items) 筛选已审核未对账的销货单+销货退货单 → [生成对账单] 按客户拆分 → 销货对账(index)
  → 财务[一审] recon_status=1（锁定单据） → 主管/领导[二审] recon_status=2
  → [开票] 可多张发票（0未开/1部分/2已开） → 收款单核销 → pay_status 自动派生
  → 异议：[二审驳回] 或 [撤销对账] → 改单据（goods_sales_log 留痕）→ [刷新明细] → 重新一审二审
```

状态字段：`recon_status`(0待对账/1待二审/2已对账)、`invoice_status`(0/1/2)、`pay_status`(0/1/2，派生)。
列表业务状态由三者组合（`SalesRecon::businessStatusText()`）。

## 关键口径

- 对账来源：**销货单 + 销货退货单**（均已审核）；退货单各金额、数量**取负冲减**。
- 纳入粒度：整单纳入；一张单据同时只能属于一张对账单（`recon_id`）。
- 销货合计 = Σ`goods_sales.real_money` − Σ`goods_sales_exchange.real_money`；
  客户费用同口径；总金额 = 两者相加。
- 已收金额 = Σ销货 `pay_money` − Σ退货 `retreat_money`，据此派生 `pay_status`。
- 开票基数 = **总金额 `total_amount`**（= 销货合计 + 客户承担费用，客户承担费用同样开票，
  2026-10-29 由「只开销货合计」改来）；台账写回按 `doc_time,doc_code` **FIFO 全量重算**，
  删除发票后按剩余集合重算；无台账行的历史单据会自动补建（台账每张单据的可开票额仍按单据自身
  `real_money` 分摊，多开的客户承担费用部分不落到单据台账上）。
- 版式严格按 `资料/财务功能/对账模板.xls` 15 列 + 本期合计 + 各月未付款合计 + 总未付款 + 页脚声明；
  标题取 `kuerp_sets.company_name`。

## 实现要点

- **快照三层**：`sales_recon`(头) → `sales_recon_doc`(单据级：客户费用/总金额/物流单号/出库状态) →
  `sales_recon_info`(商品行，含 `line_amount` 已按比例分摊单据优惠、末行吃分差)。
  客户费用/总金额在模板里**只在单据首行显示**，页面与导出都按 `$k==0` 处理。
- **物流单号**：销货单 `purchase_order_id` → `kuerp_goods_sales_order.purchase_code` → 该订单
  `kuerp_goods_sales_order_delivery` 最近一条的 `express_no`（退货留空）。
- **锁定**：`recon_status≠0` 时 `GoodsSales` / `GoodsSalesExchange` 的 edit / del / 反审核均拦截，
  提示"先撤销对账"。
- **TP5 where 语法**：本项目 TP5 只认 `['in', $ids]`（数字键），写成 `['in' => $ids]` 会在
  `Builder::parseWhereItem` 报 `Undefined array key 0`。
- **NOT NULL 快照字段**：源明细的 `purchase_order_id` / `remark` 可能为 NULL，落库前必须 `(string)` 兜底。
- 行操作模板 ID 用 `#barDemoRecon`（避开 menu.html 全局 `#barDemo`）。
- 导出用隐藏 form POST（ajax 拿不到二进制流），文件名 `客户名称+期间.xlsx`，多选取首个客户名。
- **发票 / 对账单附件上传**走 `SalesRecon::uploadfile()`（`$noNeedRight`，POST `file`）→ `\app\common\library\Upload`，
  允许的类型完全由全局 `application/extra/upload.php` 的 `mimetype` 决定（当前含 `csv`，2026-10-27 按用户要求补上），
  控制器里没有、也不该有单独白名单；采购对账 `PurchaseRecon::uploadfile/uploadvoucher` 同理同步受益。

## 去收款：客户端已预选 + 源单自动带入（2026-10-27）

> 涉及文件：`application/admin/view/kuerp/sales_recon/index.html`、`public/assets/js/backend/kuerp/sales_recon.js`、
> `application/admin/controller/kuerp/Collection.php`（`add()` GET 分支 + 新增 `buildReconSources()`）、
> `public/assets/js/backend/kuerp/collection.js`（`appendSources()` 提取 + 初始化预填）。**无数据库迁移**。

- **列表页按钮收敛**：工具栏只剩 [刷新] / [导出选中] / **[生成对账单]**，「销货对账明细」按钮已删除
  （该页仍可从侧栏进入，JS 里 `gotoitems2` 分支同步删除）。
- **去收款**：行按钮 `collect` 由 `Toastr.info + addtabs('kuerp/collection/add')` 改为
  `Fast.api.addtabs('kuerp/collection/add?recon_id=' + d.id, '新增收款单')`。
- **`Collection::add()` GET** 读 `recon_id` → `buildReconSources()`：取 `kuerp_sales_recon_doc` 的 `doc_code` 集合，
  到 `kuerp_writeoff_source`（`purchase_type='3'`）里找**未核销完**的源单（与 `Writeoff::chosereceivable` 完全同口径：
  `id NOT IN (所有有 log 的) OR id IN (log.write_off<>0)`），逐行算 `purchase_number/purchase_type_id/business_type/
  times/sale_money/write_off/write_off_price/purchase_numbers/remark/type`——**字段名与勾选弹窗回传数组一致**，
  所以前端复用同一段落行代码。退货单源单是负数，合计自然冲减。
  服务端再 `assign('row', ['customers_id'=>...])` 预选客户、`assignconfig('reconId')` + `assignconfig('presetSources')`。
- **collection.js**：原 `#chose` 回调里的落行逻辑提取为 `appendSources(goodsData)`（弹窗回调与预填共用）；
  `bindevent` 末尾若 `Config.reconId` 则 `appendSources(Config.presetSources)` + `$('#customers_id').trigger('change')`
  拉取总欠款（`presetSources` 为空时 `Toastr.warning('该对账单所含销货单已全部核销，无需再次收款')`）。
  预填后 `changeSaleInfo()` 会把「收款金额」也带成核销合计，用户只需选结算账户即可保存。
- **已验证**（curl + headless Chrome/CDP 实测 erp.licheng.test，测试行已删净）：列表页无「销货对账明细」按钮；
  `add?recon_id=1` 页面客户预选（19）、总欠款 800.00、源单行 `XHD20261018001 普通销售 800/0/800/800`、合计 800；
  临时插入退货源单（-200）后两行齐出、合计 600（之后已删除并还原自增）；`collection/add`（无 recon_id）无回归。

## 收款口径统一（同步改造）

销货单应收总额全系统统一为 **`real_money + customers_money`**，8 处改基数：
`GoodsSales`/`GoodsSalesExchange` 写 `writeoff_source.price`、
`Collection::examine` 超额校验与判满、
`Writeoff::examine` 销售/退货判满。
只对新业务生效，历史 `pay_money/pay_status` 不回算。

## 开票基数改为含客户承担费用（2026-10-29）

> 涉及文件：`application/admin/controller/kuerp/SalesRecon.php`、
> `application/admin/view/kuerp/sales_recon/invoice.html`、
> `addons/kuerp/migration_fix_sales_recon_invoice_base.sql`（已对 erp_licheng 执行）

原口径「开票基数 = 销货合计净额 `total_money`，客户费用不开票」改为
**「开票基数 = 总金额 `total_amount` = `total_money` + `total_customers_money`」**，客户承担费用同样要开票。

改动点（全部在 `SalesRecon` 内，返回给前端的字段名不变）：

| 位置 | 原 | 现 |
|------|-----|-----|
| `invoice()` POST 剩余可开 | `total_money - $invoiced` | `total_amount - $invoiced` |
| `invoice()` POST「已全部开票」判定 | `$row['invoice_status'] == '2'` | `$remainingBefore <= 0.0001`（不再依赖存储值） |
| `invoice()` GET 渲染的 `invoice_status` | 直接读库 | 按 `invoice_amount` vs `total_amount` **在内存里重算**（不写库） |
| `refreshInvoiceStatus()` 的 `$should` | `total_money` | `total_amount` |
| `detail()` 的 `$shouldInvoice` | `total_money` | `total_amount` |
| `invoice.html`「应开票金额」 | `$row['total_money']` | `$row['total_amount']` |

- ⚠️ **`syncInvoiceLedger()` 保持按单据自身 `real_money` 分摊**，没有把客户承担费用塞进去：
  台账（`kuerp_sales_invoicing`）的 `money/real_money` 是单据自身的金额，若改成含费用会出现
  「已开票金额 > 价税合计」的显示错位。对账单多开出的费用部分不落到单据台账上，
  完全开票后台账 `give_money` 仍为 0（不会重复出现在「开票源单」里）。
- ⚠️ 历史数据：旧口径下 `invoice_status='2'` 的对账单在新口径下可能只是「部分开票」。
  `migration_fix_sales_recon_invoice_base.sql` 一次性把「`invoice_amount + 0.0001 < total_amount`
  且 `total_customers_money<>0`」的对账单改判为 `1`（部分开票），只改派生状态、不动任何金额，
  让列表重新出现「开票」按钮，可以把客户承担费用补开掉。本库执行后 `KHDZ20261028001`
  由「已开票」变为「部分开票 / 剩余 71」。

**已验证**（curl 登录 erp.licheng.test 实测，测试数据已删净并还原自增）：
用销货单 `XHD20260914001`（real 58 + 客户承担费用 8 = 66）造对账单 → 二审通过 → 发票页显示
应开票 `66.00`、剩余 `66.00`；开 100 被拒（`不能超过剩余可开票金额 66`）；开 64（> 旧口径 58）成功；
再开 2 补满 → 对账单 `invoice_amount=66 / invoice_status=2`；补满后再开报「该对账单已全部开票」；
删票 → 状态回 `0`；部分开票 50 → 页显示 应开票 66 / 已开票 50 / 剩余 16，台账 `pay_money=50, give_money=8, invoice_status=0`；
`detail` 页「应开票 ￥1,201.00」（原 1,130）。

## 已验证（curl 登录 erp.licheng.test 实测，测试数据已清理并还原自增）

工作台筛选与合计（6 单 10 行，19418/8/19426）→ 生成对账单（3 单，10108+8=10116）→
详情/编辑/打印/发票页 200 → 一审(锁定) → 二审 → 反审核被拦截 → 上传发票 + 部分开票 5000
（FIFO：58 全额、9550 分 4942）→ 导出版式与模板一致 → 已开票撤销被拒 → 删票 → 撤销解锁 →
再审 → 二审驳回(原因落库、解锁) → 刷新明细 → 编辑移除单据释放 `recon_id` → 删除对账单清空快照。

---

# 出库管理（出库申请）打印申请单（2026-10-27）

> 涉及文件：`application/admin/controller/kuerp/GoodsOutboundApply.php`、
> `application/admin/view/kuerp/goods_outbound_apply/{index,detail,edit,printpage}.html`、
> `public/assets/js/backend/kuerp/goods_outbound_apply.js`、`application/admin/lang/zh-cn/kuerp/goods_outbound_apply.php`（**无数据库迁移**）

## 入口

出库管理列表每行新增 **[打印]** 按钮（模板 `#barDemoApply`，所有状态均显示，操作列宽 300→360）：
`Fast.api.open("kuerp/goods_outbound_apply/printpage/ids/"+data.id, __('Print'), {area:['90%','80%']})`。

## 控制器 `printpage($ids)`

- 加入本控制器新增的 `$noNeedRight = ['printpage']`（与各单据打印页一致：需登录、无需单独授权规则）。
- `_initialize` 把 `$setData['setList']` 额外存一份到 `$this->setList`（打印页判定属性/批次列是否显示）。
- 明细复用 `assignApplyDetail()`（**该方法末尾新增 `return $infoList;`**，原有 assign 行为不变），
  再按 `out_data`（同意出库时写入的 `{sales_info_id: 数量}` JSON）补 `actual_number`，并汇总 `totalApply` / `totalOut`。
- `colCount` / `detailColCount` / `editColCount` 均在服务端算好传模板（空明细行 colspan）：
  属性列(`auxiliary` 开启)与批次列(`batch` 开启)关闭时该列 `display:none` **不占列位**，colspan 必须跟着减（`featureOn()` 判开关）；
  打印页 12 = 11 基础列 + 属性 + 批次 + 实际出库列(仅 `status=='1'`)；detail 15 / edit 14 再各自减去隐藏的可选列。

## 商品规格三列（型号 / 规格2·厂商 / 规格3·封装）

- 明细页(detail)、编辑页(edit)、打印页(printpage)的「本次出库商品列表」在**商品名称之后**都加了
  **型号 / 规格2/厂商 / 规格3/封装** 三列（与组装单 `outbound_edit` 同口径）。
- 数据来源：`kuerp_goods` 的 `goods_specification_id`（型号，varchar 存文本非外键）/ `vendor_name` / `packaging`。
  `assignApplyDetail()` 里把原来的两次 `column()` 合并成**一次** `field('id,goods_code,goods_name,goods_specification_id,vendor_name,packaging')->select()`
  建 `$goodsMap`/`$goodsIdMap`/`$goodsSpecMap` 三个索引，正常分支与「销货单明细已变更」分支都赋值，商品不存在时为空串。

## 打印页版式

- 标题「出库申请单」；信息区：申请单号 / 销货单号 / 客户 / 代发·直发 / 申请人 / 申请时间 / 审核人 / 审核时间 / 状态 / 仓库(详见明细表)。
- 有 `remark` 打印备注行；`status=='2'` 且有 `refuse_reason` 时红字打印拒绝原因。
- 明细列：序号 / 商品编号 / 商品名称 / 型号 / 规格2/厂商 / 规格3/封装 / [属性] / 单位 / 仓库 / [批次] / 计划数量 / 已出库数量 / 申请出库数量 / [实际出库数量]。
  属性/批次列用 `goods_attr_sd` / `goods_batch_sd` + 顶部条件 `<style>` 隐藏（同 assemble/allocation 打印页）。
- 合计：申请出库合计（已同意出库时另加实际出库合计）；页脚三处签字栏（申请人/仓库审核（发料）/领料人）+ 打印时间
  （`footer-info span` 用 `margin-right:24px` + `nowrap`，800px 宽一页内三栏不换行）。
- 注：本表**没有 print_num 列**，故 printpage 不做 `setInc('print_num')`（不要照抄其他打印页）。

## 已验证（curl + headless Chrome/CDP 实测 erp.licheng.test，测试数据已清理）

- 列表每行 6 个按钮（详情/打印/审核出库/拒绝/编辑/删除）在 360px 内单行不换行，主表与右侧固定列行高一致（均 41px）。
- 点 [打印] → 弹出「打印」层，iframe 为 `printpage/ids/14?dialog=1`，标题「出库申请单」、明细/合计/签字栏/打印按钮齐全，无 JS 异常。
- 状态分支：待审核（id14）、已拒绝（id5，红字拒绝原因）、已同意（临时造 status=1+out_data 记录，实际出库数量列与两项合计正确，渲染后已删除并还原自增）。
- 规格三列：临时造商品 `ZZTEST-SPEC`（型号 FFM-2026A / 厂商 台力电容 / 封装 SMD-0805）+ 临时申请单渲染，
  detail / edit / printpage 三页三列均正确输出（商品不存在时为空），渲染后已删除并还原自增（goods 2238 / apply 15 / apply_info 24）。
- 空明细 colspan 实测 12（打印页、状态=1、属性/批次关闭）；属性/批次关闭时 detail 13、edit 12；
  800px 宽打印截图中 12 列不溢出、表头两行换行、签字栏一行；`php -l` / `node --check` 通过。

## 采购对账对齐销货对账规则 + 导出/打印（2026-10-27）

> 迁移：`addons/kuerp/migration_align_purchase_recon_sales_rules.sql`（已执行）；`install.sql` 1.6.18；`info.ini` 1.6.18。
> 规则来源：`Desc/销货对账-需求规格.md`（退货冲减、多张发票/部分开票、二审、单据锁定、口径统一）。

### 状态机（已改，注意与旧数据/旧代码区分）

- `fa_kuerp_purchase_recon.status`：**0 待对账 / 1 待财务审核 / 2 待二审 / 3 已对账 / 4 已驳回**
  （迁移把旧 2 已审核映射为 3、旧 3 已驳回映射为 4）。
- 源单据 `recon_status`：0 未对账（可编辑）/ 1 一审通过锁定 / 2 二审通过已对账。
- `invoice_status`：0 未开票 / 1 部分开票 / 2 已开票（按 `invoice_amount` 累计派生）。

### 关键实现

- 对账明细 `purchase_recon_info.doc_type`：1 采购单、2 购货退货单；退货行的 `number/real_money` **存负数**。
  主表 `total_number/total_money` 为净额；`purchase_count/return_count` 分开计数。
- 新增/编辑对账：`purchase_ids` + `exchange_ids` 两个字段；提交前 `refreshDocs()` 按源单据重算金额；
  草稿不锁定（源单据可改），一审通过后 `lockDocs()` 锁定，二审驳回/撤销解锁。
- 付款申请：`PurchasePaymentApply::checkRecons()` 要求 status=3、invoice_status=2、net>0；
  `pay()` 按对账净额分摊到采购单核销（退货单不重复核销）。
- 导出/打印：`PurchaseRecon::export()/printpage()` + `buildTemplateData()`；
  14 列（无物流单号），含本期合计/往期各月未付款/总未付款/页脚；导出用 PhpSpreadsheet。

### 踩坑（必看）

- **TP5 模板会吃掉内联 JS 里的 `{url:`**：`Fast.api.ajax({url: ...})` 会被模板引擎当成 URL 标签替换，
  生成 `Fast.api.ajax(/eXvpGbzZYD.php/...` → `SyntaxError: Invalid regular expression flags`。
  内联脚本里改用：
  ```js
  var opts = {}; opts.url = '...'; opts.data = {...};
  Fast.api.ajax(opts, cb);
  ```
  更推荐把逻辑放进 JS 模块（`Controller.detail` 等），视图只放 `data-*` 参数。
- **detail 视图不要在 `<script>` 里直接用 `$` / `require`**：普通 kuerp 视图由 RequireJS 异步加载 jQuery，
  内联脚本执行时可能 `$ is not defined` / `require is not a function`。本次已改为 JS 模块 + `data-*`。
- 采购应付口径按代码现状为 **`goods_purchase.real_money`**（价税合计 − 优惠），
  不是销货规格里写的 `all_money + tax_money`。

### 已验证（curl + headless Chrome 实测，测试数据已清理）

- 采购单 1000 + 退货单 -300 → 对账净额 700、数量 7、单据数 1/1；明细负数正确。
- 提交流程：0→1→2（一审锁定 recon_status=1）→3（二审 recon_status=2）；锁定后编辑/删除/反审核/对单均被拦截。
- 发票：400（部分开票）→ 超额 400 被拒 → 重复发票号被拒 → 300（已开票），累计 700。
- 付款申请 700：财务→审计→出纳打款，自动生成付款单，采购单 pay_money=700，对账单 pay_status=2。
- 撤销对账：已对账未开票时可回 0 并解锁单据。
- 导出 xlsx（7KB，含采购单/退货单行、本期合计/往期/总未付款，无物流单号）；打印页 200 且无物流单号。
- 采购单列表/供应商待付款统计均按净额（采购 1000 − 退货 300 = 700）正确汇总。

### 详情页增加「对账单明细」商品行 + 打印改 A4 横向（2026-11-03）

- `PurchaseRecon::detail()` 复用 `buildTemplateData($row)`，额外 assign：
  `itemRows`（商品行：goods_name/spec/number/tax_included/unit_price/tax_rate/line_amount/doc_cost/doc_total/remark）、
  `itemSumLine` / `itemSumTotal` / `itemPriceDecimal` / `itemNumberDecimal`。
- `purchase_recon/detail.html` 在「对账单据明细」（单据级，原样保留）**之前**新增 **「对账单明细」** 面板：
  **序号 / 采购单号 / 商品名称 / 型号规格 / 数量 / 含税单价 / 未税单价 / 税点(%) / 采购合计金额 / 采购费用 / 总金额 / 备注**（12 列）+ 表尾「本期合计」
  （采购单号取 `itemRows[].doc_code`，退货单行显示退货单号；空数据行 colspan=12，合计行 `colspan=8`）。
  列与客户模板（`资料/财务功能/对账模板.xls`）一致；**采购费用/总金额是单据级金额，只在该单据第一行有值**（`doc_cost/doc_total` 为 null 时留空），
  所以表尾合计取 `sum_line`（采购合计金额列）与 `sum_total`（总金额列）。
- `purchase_recon/printpage.html`：
  1. **改 A4 横向** —— 在 `{include file="kuerp/common/printstyle" /}` **之后**追加
     `@page{size:A4 landscape;margin:10mm}` + `@media screen{#content{width:297mm}}` + `.wrap{max-width:none}`
     （公共版式是竖版，后写覆盖；不要改 `printstyle.html`，19 个打印页共用）。屏幕 `#content`=1122.5px(297mm)、表格 1047px(277mm)，14 列不溢出。
  2. 金额列改为 `number_format` 输出（价格列 `$data.price_decimal`、数量 `$data.number_decimal`、税点固定 2 位），
     与图片模板/导出 xlsx 口径一致（原先是裸 float，显示 `113` 而不是 `113.00`）。
- 已验证：`detail?ids=2/4`、`printpage?ids=2/4` 列与数值正确（本期合计 452 / 5000），
  `Page.printToPDF{preferCSSPageSize:true}` → MediaBox `841.92×594.96`（A4 横向）。

### 财务一审 / 二审弹窗显示对账单明细（默认折叠，2026-11-03）

- `PurchaseRecon` 新增 `assignItemRows($row)`（detail/audit/secondaudit 共用，内部调 `buildTemplateData`），
  `detail()` 改用它；`audit()` 与 `secondaudit()` 也各调一次（二审复用 `audit.html`）。
- `purchase_recon/audit.html` 在基本信息面板之后、「对账资料」之前新增 **「对账单明细」折叠面板**：
  标题 `#recon-items-toggle`（内含 `{:count($itemRows)} 行商品，点击展开/收起` + 右侧 `glyphicon-chevron-right` 箭头），
  表体 `#recon-items-body` **`display:none` 默认折叠**；12 列与详情页一致（序号/采购单号/商品名称/型号规格/数量/含税单价/
  未税单价/税点/采购合计金额/采购费用/总金额/备注 + 本期合计），外层 `.table-responsive` 窄屏横向滚动。
  ⚠️ 该面板**不受 `$materials` 是否为空控制**（看清楚：模板里对账单明细面板在 `{if !empty($materials)}` 之前）。
- `purchase_recon.js` 的 `Controller.audit()` 绑定标题点击切换 `.toggle()` + 箭头类 `glyphicon-chevron-down/right`（二审共用）。
- 弹窗尺寸：列表行 `audit`/`secondaudit` 与详情页 `openStep()` 的 `area` 都由 `['65%','75%']` 改为 **`['82%','86%']`**，方便看全 12 列。
- 已验证（headless Chrome）：默认 `$('#recon-items-body').is(':visible')=false`、2 行商品；点标题展开（表格 1568px 与容器同宽）、
  再点收起；箭头类同步切换；无 JS 异常。`audit?ids=2` / `secondaudit?ids=2` 均渲染该面板。

---

# 组装单：打印按钮移入行操作栏 + 打印页增「型号/规格2/规格3」（2026-10-27）

> 涉及文件：`application/admin/view/kuerp/assemble/index.html`、`public/assets/js/backend/kuerp/assemble.js`、
> `application/admin/controller/kuerp/Assemble.php`（`printpage()`）、`application/admin/view/kuerp/assemble/printpage.html`
> （**无数据库迁移**）

- **打印按钮从工具栏移到行操作栏**：`index.html` 内新增 `#barDemoAssemble` 模板（编辑 / 删除 / 打印），
  `assemble.js` 的 `cols` 操作列 `toolbar` 由全局 `#barDemo` 改为 `#barDemoAssemble`、`width:150→200`（三个按钮实测同行）。
  工具栏中原 `lay-event="print"` 按钮删除；`table.on('toolbar')` 的 `print` 分支一并移除，
  改在 `table.on('tool(table)')` 里加 `print` 分支：`parent.Fast.api.open("kuerp/assemble/printpage?ids="+data.id, "打印", {area:['90%','75%']})`。
  权限仍用 `$auth->check($nowmenu.'/printpage')`（规则 id 699 已存在）。
- **打印页增三列**：`printpage()` 里原来逐行只取 `goods_name`，现同一查询顺带取
  `goods_specification_id`(型号) / `vendor_name`(规格2/厂商) / `packaging`(规格3/封装)，字段缺失时置空串（孤儿商品不报错）。
  `printpage.html` 的 **组合件表与子件表**都在「商品名称」后插入这三列（与 `pickingpage.html` 口径一致），
  子件无数据行 `colspan` 8→11。
- 已实测（curl + headless Chrome/CDP）：工具栏不再有打印按钮；行操作三按钮同一行（`offsetTop` 相同）；
  点行内 [打印] 弹出「打印」层并加载 `printpage?ids=<该行id>`；打印页两表列头均为
  `序号/商品编号/商品名称/型号/规格2厂商/规格3封装/属性/单位/仓库/数量/备注`，型号列有值（如 `2018（永邦)-下层`）；
  表宽 = 容器宽（900px，无横向溢出）。`php -l` / `node --check` 通过；`print_num` 在字段为 NULL 时 `setInc` 仍为 NULL，未产生数据变更。

---

# 采购预订单按部门隔离 + 新增「采购预订单(仓库)」（2026-10-27）

> 涉及文件：`addons/kuerp/migration_add_purchase_order_source.sql`（已对 erp_licheng 执行）、`install.sql`（1.6.19 段 + CREATE TABLE）、`info.ini`（1.6.19）、
> `addons/kuerp/Kuerp.php` $menu、`application/admin/controller/kuerp/GoodsPurchaseOrder.php`、
> `GoodsPurchaseOrderPlan.php`、`GoodsPurchaseOrderPlanWarehouse.php`（新）、
> `view/kuerp/goods_purchase_order_plan_warehouse/{index,add,edit}.html`（新）、
> `public/assets/js/backend/kuerp/goods_purchase_order_plan_warehouse.js`（新）、
> `lang/zh-cn/kuerp/goods_purchase_order_plan_warehouse.php`（新）

## 字段与隔离口径

- `fa_kuerp_goods_purchase_order` 新增 **`order_source` enum('plan','warehouse','purchase') NOT NULL DEFAULT 'plan'**（AFTER `purchase_type`）：
  `plan`=计划部门（采购预订单(计划)）、`warehouse`=仓库部门（采购预订单(仓库)）、`purchase`=购货订单页。
  迁移把历史单据按默认值 **plan** 处理（此前只由计划部门录入）。
- **列表隔离**：`GoodsPurchaseOrder::index()` 读 `orderSourceListFilter()`，非 null 时追加
  `where('order_source','in',[...])`；购货订单页返回 null = 不过滤（采购部能看到全部预订单去生成采购单）。
- **写入标记**：`orderSource()` 决定写库值——`GoodsPurchaseOrder`='purchase'、`GoodsPurchaseOrderPlan`='plan'、
  `GoodsPurchaseOrderPlanWarehouse`='warehouse'。只写 add() 主表；**edit() 不写**（编辑不改来源，天然保留）。
- **越权守卫**：`GoodsPurchaseOrderPlan::checkOrderSource($row)`（编辑 GET/POST 前置、`batchGeneratePurchase`/`generateBySupplier`），
  `GoodsPurchaseOrder::del()` 循环里同样校验 → 提示「单据[单号]不属于当前部门，不能操作/删除」。

## 新页面 = 子类继承，不复制代码

```php
class GoodsPurchaseOrderPlanWarehouse extends GoodsPurchaseOrderPlan
{
    protected function orderSource()          { return 'warehouse'; }
    protected function orderSourceListFilter() { return ['warehouse']; }
    protected function docTitle()              { return '采购预订单(仓库)'; }  // 提示文案
}
```

- 视图/JS/lang 是 `goods_purchase_order_plan` 的复制（视图 3 个、JS 1316 行仅把 13 处
  `kuerp/goods_purchase_order_plan` 换成 `kuerp/goods_purchase_order_plan_warehouse`，`node --check` 通过）。
- 菜单挂在**仓库组**（`kuerp/stores/default`，pid=185）weigh=77（采购单(仓库) 78 与出库管理 76 之间），含 查看/添加/编辑/删除 四子规则；
  `migration_add_purchase_order_source.sql` 用 `WHERE NOT EXISTS` 生成，可重复执行。计划购货订单在 `Kuerp.php` 的菜单标题同步为
  「采购预订单(计划)」（与 DB 里 fa_auth_rule id=663 的实际标题一致，避免 upgrade 时被改回旧名）。
- 角色：迁移后已把 766~770 追加到「仓储部」角色 rules（追加前判 `FIND_IN_SET`）；其它角色需在 角色管理 勾选。
- 页面功能与「采购预订单(计划)」完全一致：不显示金额列、不自动审核（`examine()`/`secondExamine()` 一律报错），
  可 [生成采购单]/[生成退货单]（走 `$noNeedRight` 的三个检测/批量生成接口）。

## 已验证（curl + headless Chrome/CDP 实测 erp.licheng.test，测试数据已清理并还原自增）

- 迁移：`order_source` 列存在（默认 plan，历史 6 单全为 plan）；菜单 766~770 落库，侧栏出现「采购预订单(仓库)」。
- 列表：`plan/index` 6 条、`goods_purchase_order_plan_warehouse/index` 0 条、`goods_purchase_order/index` 6 条（不过滤）。
- 建档：仓库页 add 落库 `order_source=warehouse`（明细 number/stores 正确）→ 仅仓库列表可见；购货订单页 add 落库 `order_source=purchase` → 两个预订单页都不可见。
- 编辑：仓库页 edit 自己的单据成功且 `order_source` 仍为 warehouse（delivery_time/明细同步更新）。
- 越权：仓库页 `edit?ids=<计划单>`、`plan/edit?ids=<仓库单>`、仓库页 `del?ids=<计划单>` 均被守卫拦截；
  计划页 edit/add、仓库页 edit 自己的单据 200。
- 浏览器（CDP）：仓库列表 layui 表格 1 行（单号/类型/供应商/关联采购单/数量/状态/制单人/备注 + 编辑删除），
  工具栏 刷新/添加/删除，无 JS 异常；add 页自动生成单号 `GHDD...`、明细表 6 行、提交按钮存在。
- 清理：`ZZTEST-*` 单据与明细全部删除，`AUTO_INCREMENT` 还原（order=12 / order_info=24）。

## 计划部门录入页隐藏「供应商」列（2026-11-05）

- **只改计划部门这一个页面**：`view/kuerp/goods_purchase_order_plan/{add,edit}.html` 的明细表「供应商」`th` 与每个行 `td`
  加 `class="supplier_sd"`，样式（`display:none`）在 `view/kuerp/common/menu.html`（与 `goods_multi_unit_sd` 等隐藏列同处）。
- **仅隐藏展示，字段与逻辑全部保留**：隐藏的 `select.row-supplyer_code` 仍然随表单提交，`changeGoods` 照样按商品默认供应商
  自动带出（`GoodsPurchase::getUnit` 返回的 `supplier`）；`edit` 打开历史单据时原供应商值原样回填（实测 GYS002/GYS005 保存后不变），
  所以「生成采购单」按供应商分组的行为不受影响。
- **仓库侧不受影响**：`goods_purchase_order_plan_warehouse/{add,edit}.html`（采购预订单(仓库)）仍显示供应商列；
  列表页（plan/index）也保留供应商列展示。
- 恢复：删掉 `common/menu.html` 里 `.supplier_sd{display:none}` 那段即可。

---

# 单据打印统一版式：屏幕预览 = 打印输出（2026-10-27）

> 涉及文件：新增 `application/admin/view/kuerp/common/printstyle.html`；
> `application/admin/view/kuerp/**/print*.html`、`assemble/pickingpage.html`、`goods_purchase_storage/printstorage.html`、
> `sales_recon/detail.html` 共 19 个打印页在 `</head>` 前 `{include file="kuerp/common/printstyle" /}`
> （`sales_recon/detail.html` 无 `<head>`，include 放在文件首行）。
> **无数据库迁移、无控制器改动。**

## 之前为什么"预览和打印差很多"（实测数据）

| 现象 | 根因 |
|------|------|
| 打印出来整页重排、列被压窄、表头折成两行 | 预览 `.print-wrap` 是 800/900 **px**，A4 扣掉 10mm 页边距后可用宽只有 190mm ≈ **718px**，打印时整页按 718px 重新排版（实测 `.print-wrap` 800→688） |
| 表头灰底、合计行底色全部消失，勾“背景图形”也没用 | 打印页实际被 **后台框架套了 Bootstrap 3**，它的 `@media print{ *,*:before,*:after{background:transparent!important;color:#000!important} }` 用 `!important` 清掉一切背景 |
| 打印多出网址/日期/页码 | 浏览器打印对话框默认勾选「页眉和页脚」，页面无法控制 |
| 明细多时被从行中间劈开、第二页没表头 | 19 个打印页里只有 5 个写过 `@page`/`thead` 重复/行不跨页 |
| 纸张尺寸不定 | 未声明 `@page` 时跟随浏览器/系统默认（实测本机默认出 **Letter 215.9mm**） |

## 做法

`common/printstyle.html` 一段公共样式，屏幕把内容区当 A4 纸、打印用 `@page` 同宽：

```css
@page { size: A4 portrait; margin: 10mm; }   /* 纸张固定 A4，边距 10mm */
@media screen {
    #content { width:210mm; padding:10mm; background:#fff; box-shadow:...; }  /* 屏幕=一张 A4 纸，内容宽 190mm */
}
@media print {
    body,#main,.tab-content,#content { width:auto!important; margin:0!important; padding:0!important; background:transparent!important; }
    th { background:#f0f0f0 !important; -webkit-print-color-adjust:exact; }   /* 反杀 Bootstrap 的打印重置 */
    thead th { border-bottom:2px solid #333 !important; }                     /* 不勾背景图形时也能分辨表头 */
    thead { display:table-header-group; } tr { page-break-inside:avoid; }     /* 跨页重复表头、不劈行 */
}
```

屏幕预览里 `.print-wrap` 因此算下来正好 **718px（=190mm）**，与打印内容宽度一致 → 排版逐字相同。

## 关键坑（务必勿改）

- ⚠️ **打印页里的 `<body>` 标签是无效嵌套**：模板虽以 `<!DOCTYPE html>` 开头，实际由后台框架渲染成
  `body.inside-header > #main > .tab-content > #content > .row > .col-xs-12 > .content > [模板内容]`，
  模板自己的 `<body>` 被浏览器忽略。**所以纸张样式必须写在 `#content` 上，加 `body.print-doc` 之类的 class 不会生效。**
- ⚠️ **外层栅格 `.row > [class*="col-"] > .content` 各带 15px 内边距**，必须在预览和打印里都清零，否则两边差 30px。
- ⚠️ **清零宽度必须写 `width:100%`，不能写 `width:auto`**：`.col-*` 是 `float:left`，`auto` 会变成收缩到内容宽度
  （实测某页 `.print-wrap` 从 718px 缩成 513px）。
- 选择器用 `#content > .row...` 这种**直属子元素**限定，避免影响模板内部自身的 `.row/.col-*`。
- `sales_recon/detail.html` 是**后台详情页不是独立打印页**，其自身 `<style>` 里加了 `@media screen{#content{width:auto!important...}}`
  抵消纸张模拟，只保留打印媒体规则。
- 背景色能否打印最终仍取决于用户是否勾选「背景图形」（页面无法强制），故表头另有加粗 + 深色下边框兜底；
  预览页 `.no-print::after` 有一行提示文字（缩放选默认、勾选背景图形、取消页眉页脚）。

## 已验证（headless Chrome + printToPDF 实测 erp.licheng.test，测试后 print_num 已按快照还原）

- 15 个 `.print-wrap` 页面：屏幕 `#content` 均为 794px（210mm）、`.print-wrap` 均为 718px、无横向溢出；
  打印媒体下 `th` 计算背景 `rgb(240,240,240)`、`thead` 为 `table-header-group`。
- PDF 实测（采购单/销货单/组装单）：纸张 **209.9×297mm = A4**，正文横向 10.1mm→200.3mm（=190mm，与屏幕一致），
  PDF 图形里出现 `#f0f0f0` 填充（表头灰底回来了），打印按钮与被 Bootstrap 清掉的底色问题均消失。
- 长单据（组装单 ZZ20260930005，48 行子件）打印成 **5 页**，第 2 页起表头自动重复、行未被劈开。
- `dialog=1`（弹层打开）与直接访问两种方式下 `#content` 宽度一致。
- 屏幕截图与 PDF 首页并排比对：列宽、折行位置、字号一致。

---

# 对账差异金额 + 客户信用账期 + 应收账款分析（2026-10-28）

> 迁移：`addons/kuerp/migration_add_recon_diff_and_ar_analysis.sql`（已对 erp_licheng 执行，可重复执行）；
> `install.sql` 1.6.20 段 + `info.ini` 1.6.20。
> 新增：`SalesReconDiff` 模型、`ArAnalysis` 库（`application/admin/library/kuerp/ArAnalysis.php`）、
> `ArAnalysis` 控制器 + console 命令、`sales_recon/diff.html`、`ar_analysis/index.html` + 两个 JS + lang。

## 一、对账差异金额（异议调整）

需求：「对账有异议 → 调整销货单并保留操作记录」在实际业务里落地为**给单张销货单加差异金额**，
而不是去改写已审核的销货单（改单据会牵动库存/成本/开票，且已审核单据本就锁死）。

- 入口：销货对账列表行 **[差异金额]**（`recon_status != '2'` 时显示，权限 `kuerp/sales_recon/diff`）。
- 一张单据可登记**多条**差异（表 `fa_kuerp_sales_recon_diff`），每条各有金额/原因/附件，
  因此天然保留操作记录（对账单日志同时写一条「登记差异金额/删除差异金额」）。
- 金额语义：**正数 = 客户多付，负数 = 客户少付**（折让、抹零）。退货单也允许（正数=减少退货冲减）。
- 附件走 `fa_kuerp_enclosure` + `fa_kuerp_enclosure_log`，新增 `type=13`；上传接口 `uploaddiff`
  （先上传拿 `enclosure_id`，保存差异时再关联，避免半途放弃留下孤儿）。
- 写入链路（`refreshDiffTotals()`）：差异表 → `sales_recon_diff` 明细 → `sales_recon.diff_money`（合计）
  → `sales_recon_doc.diff_money`（单据级）→ 源单据 `goods_sales[_exchange].diff_money`
  → `writeoff_source.price/write_off`（**缺失时自动补建源单**，否则收款单「选择源单」里看不到该单据）
  → `syncInvoiceLedger()` + `calcPay()` 重算开票与收款状态。
- 收款口径：`SalesRecon::calcPay()` 的应收总额改为 `total_amount + diff_money`；
  `Collection::examine` / `Writeoff::examine` 的核销上限：销货单 `+diff_money`、
  **退货单 `-diff_money`**（退货金额越大冲减越多，差异方向相反）。
- 已对账（`recon_status=2`）或已收款完成时禁止增删差异，提示先撤销对账。

## 二、客户信用账期

`fa_kuerp_customers.credit_days`（天，0=不判断逾期），客户 add/edit 表单在「余额日期」右侧。
逾期判断口径（对齐 应收账款及考核.xlsx 备注「销售单在开票之后算起超过设定的账期即视为逾期」）：

```
逾期 = 开票日 + credit_days < 分析时点，且该销货单尚未收完
开票日取：对账单开票 → sales_recon_invoice.invoice_date
          开票管理单独开票 → sales_invoicing_log.times
          都取不到 → sales_invoicing.bill_time（单据日期）
逾期金额 = real_money + customers_money + diff_money − pay_money
```

## 三、应收账款分析（月度快照）

页面：资金 → **应收账款分析**（`kuerp/ar_analysis`，权限 index/rebuild/export，`periodlist` 在 `$noNeedRight`）。
列与口径严格按 `资料/财务功能/应收账款及考核.xlsx`：销售负责人 / 客户名称 / 客户信用账期 /
期初（上月末余额）/ 本月含税销售总额 / 本月实际回款总额 / 本月退货红冲 / 本月账面应收余额 /
其中逾期 / 本月销售回款率 / 对账差异金额 / 差异原因备注。

```
期初        = 上月快照的 end_money；无上月快照时按「截至本月1日0点的滚动余额」兜底
              （滚动 = 已审核销货(含差异) − 已审核退货 − 已审核收款）
本月销售    = 本月已审核销货单 Σ(real_money + customers_money + diff_money)
本月回款    = 本月已审核收款单 Σ(money)（与单据无关，整单金额）
本月退货    = 本月已审核退货单 Σ(real_money + customers_money − diff_money)，正数
账面余额    = 期初 + 本月销售 − 本月回款 − 本月退货
回款率      = 本月回款 / 本月销售（销售为 0 时记 0）
对账差异    = 本月登记的差异合计（**月度新增**，不是累计）
```

- **只显示有账客户**（2026-10-28 用户要求）：列表/合计/导出统一加 `end_money > 0`，
  余额为 0（已结清）或负数（预付）的客户不出现；因字段名是 `end_money` 而页面上叫「本月账面应收余额」。
- **月份只走 GET `period`**，不进 `filter`；客户名称/销售负责人走 FastAdmin `filter`+`op=LIKE`。
  两者混用会与 `buildparams()` 叠加成 `period='A' AND period='B'`（首版即踩此坑，表现为切月份无效）。
- **每月 1 日 0 点**：`php think kuerp:ar_analysis`（不带参数 = 上个月），命令在
  `application/admin/command/ArAnalysis.php` 并注册于 `application/command.php`。
  crontab 示例：`0 0 1 * * cd /path/to/erp && /path/to/php think kuerp:ar_analysis >> runtime/ar_analysis.log 2>&1`
- 后台 [重新分析] 按钮可手动补算指定月份（会覆盖该月快照）；导出 xlsx 用隐藏 form POST。
- ⚠️ **CLI 必须用 PHP 8.0**（`/opt/homebrew/Cellar/php@8.0/8.0.30_11/bin/php`）：本机默认 `php` 是 8.4，
  TP5 的 console 在 8.4 下 `Stack::__construct()` 的隐式 nullable 触发 Deprecated → Fatal。
- ⚠️ **TP5 不能 `clone $query` 后复用**（报 `No data supplied for parameters in prepared statement`）：
  count 与列表各建一个 Query（已在 `ArAnalysis::index` 修正）。

## 四、已验证（curl + headless Chrome/CDP 实测 erp.licheng.test，测试数据已清空并还原自增）

- 差异：+120 / −30 / 浏览器拖文件上传 PDF 各一条；对账单合计 208、单据级差异、源单据 `diff_money`、
  `writeoff_source`（含**缺失时自动补建**）全部同步；漏建时收款单「选择源单」看不到该单据的问题已修。
- 收款：按含差异上限 5520 核销 → 销货单 `pay_money=5520/pay_status=1`、对账单 `pay_status=1`、
  `sales_invoicing` 状态同步、`need_receive_account` 正确递减。
- 分析：`--period=2026-10` 139 个客户；期初兜底 10116/8510 正确；`diff_money=-30` 与原因正确带入；
  单独验证逾期：`credit_days=3` 时 800 元判为逾期，改回 30 天后为 0。
- 页面：分析页 12 列、工具栏（刷新/重新分析/导出Excel）、按余额倒序；差异弹窗列表/新增/删除/已对账禁用提示正常；
  导出 xlsx 142 行 × 12 列含合计行。

---

# 批发价字段下线 + 客户等级隐藏（2026-10-28）

> 涉及文件（**无数据库迁移**，`wholesale_price` 列与历史数据全部保留）：
> `application/admin/view/kuerp/goods/{add,edit}.html`、`public/assets/js/backend/kuerp/goods.js`、
> `application/admin/view/kuerp/customers/{add,edit}.html`、`public/assets/js/backend/kuerp/customers.js`、
> `application/admin/view/index/index.html`（侧栏隐藏）、
> `application/admin/controller/kuerp/{Goods,Customers,GoodsSales,GoodsSalesOutbound}.php`、
> `addons/kuerp/library/GoodsSales.php`、`addons/kuerp/controller/api/Base.php`、
> `public/assets/addons/kuerp/template/{商品,客户}导入模板.xlsx`（该目录 gitignore，随文件树部署）

## 一、批发价（wholesale_price）全链路下线

用户口径：商品编辑页及其后所有与「批发价」相关的字段一并去掉；客户等级改为只保留零售客户。

### 界面（改动点）

| 位置 | 改动 |
|------|------|
| 商品 add/edit 多单位价格表 | 去掉「批发价」th 与每行 `more_unit[wholesale_price][]` 输入框（表头与单元格同步减 1 列，现 7 列） |
| 商品 add/edit 价格策略区 | 去掉「批发价」输入框 `row[wholesale_price]`（零售价独占一行） |
| 商品 add/edit 辅助属性表 | 去掉「批发价」th 与 `auxiliary[wholesale_price][]` 输入框（表头与单元格同步减 1 列，现 9 列） |
| 商品列表 JS | 注释掉/删除 `wholesale_price` 列定义（原本就已被注掉，本次连注释一起清掉） |
| `goods.js` 多单位动态行模板 | 克隆模板串里去掉 `more_unit[wholesale_price][]` 那一格，否则新增副单位行会多一列 |

⚠️ 改多单位/属性表时**表头 th 与每行 td 必须同增同减**，否则整表错位（本次已用脚本核对：多单位 7/7、属性 9/9）。

### 后端（未传字段不能报未定义索引）

表单不再提交 `wholesale_price`，因此 `Goods::add()/edit()` 里原本 `(float)$more_unit['wholesale_price'][0]`
会变成 `Undefined array key`（PHP 8 直接 500）。现统一写死：

- 主表/多单位行：`$params['wholesale_price'] = 0;`、`'wholesale_price'=>0`
- 辅助属性行：删掉读表单的那段 if（保留注释「批发价字段已从界面移除，不再从表单读取」）

### 取价规则（关键：不能留 0 元报价）

批发价没了，但「批发客户」等级（`fa_kuerp_customerlevel` id=2，库中有 3 个客户）原本按 `wholesale_price` 取价，
不改会变成 **0 元报价**。故 7 处 `switch($customers['customerlevel_id'])` 的 `case 2` 全部改为 `market_sell_price`：

`GoodsSales.php`(getUnit/formatInfo2) / `Goods.php`(chosegoods) / `GoodsSalesOutbound.php`×2 /
`library/GoodsSales.php`×2 / `api/Base.php::getPriceField()`（小程序端 case 2 直接删掉，落 default 零售价）。

### Excel 导入模板

商品模板：**示例区（S 列）与正式录入区（O 列）的「批发价」不在同一列**，不能整列 `delete_cols`——
必须按行段分别定位再压缩重建（本次做法：解全部合并单元格 → 按 `r<=12 ? 19 : 15` 取要删的列 → 重写单元格/样式/合并区/列宽）。
- 旧模板（仍带「批发价」列）导入依然可用：`$insert_v['批发价'] ?? ''` 兜底，值一律按 0 入库。
- 说明文字第 6 条由「零售价、批发价分别对应零售客户、批发客户」改为「零售价对应零售客户」。

## 二、客户等级隐藏（统一零售客户）

- 客户 add/edit 表单：`build_select('row[customerlevel_id]', ...)` 换成
  `<input type="hidden" name="row[customerlevel_id]" value="1">` + 静态文字「零售客户」。
  **保留 hidden 域**（后端 insert/update 原样收 `row/a`，字段缺失会落 DB 默认 1，但显式传更稳）。
- 客户列表 JS：去掉「客户等级」列。
- 侧栏：`application/admin/view/index/index.html` 的隐藏样式块追加
  `.sidebar-menu li:has(> a[url$="kuerp/customerlevel"]), .sidebar-menu a[url$="kuerp/customerlevel"] { display: none; }`
  （与「商品辅助属性」同一做法：菜单数据 `fa_auth_rule` 不动，恢复时删这段即可）。
- 客户 Excel 导入：模板去掉「客户等级」列（H 列，含 `hidden_5` 下拉源 sheet 一并删除），
  `Customers::import()` 读 `客户等级` 改为 `$insert_v['客户等级'] ?? ''` 兜底，未填 → `customerlevel_id=1`。
  ⚠️ 删列时数据校验区也要重排（原 `R` 列「首要联系人」→ `Q`）。

## 已验证（curl 登录 + headless Chrome/CDP 实测 erp.licheng.test；测试数据已删净并还原自增）

- 页面：商品 add/edit 页 DOM 中 `[name*=wholesale]` 为 0、无「批发价」表头；多单位表 7 列、
  属性表 9 列（headless Chrome 实测 head/tbody 单元格数一致）；商品列表 18 列无批发价。
- 客户 add/edit：无 `#c-customerlevel_id` 下拉，显示「零售客户」+ hidden=1，无 required 校验；列表 13 列无「客户等级」。
- 侧栏：`kuerp/customerlevel` 链接 `getBoundingClientRect` 宽高均为 0（CSS 隐藏生效，与 auxiliary 同级一致）。
- 保存：单单位新增（只有零售价 12.5/采购价 8）→ 落库 `market_sell_price=12.5, wholesale_price=0`；
  切多单位（主单位 10 / 副单位 11）→ 主表 + `kuerp_goods_price` 两行均 `wholesale_price=0`，无 500。
- 导入：用新商品模板实测 1 行 → `add:1`，`market_sell_price=25 / purchase=10 / wholesale_price=0`、
  型号/厂商/封装/商品类型均正确；用新客户模板实测 1 行 → `add:1`、`customerlevel_id=1`（自动零售客户）。
- `php -l` 5 个 PHP 文件、`node --check` goods.js/customers.js 全通过；两个模板 HTTP 200 且 openpyxl 可正常打开。

## 注意事项

- **数据库列未删**：`fa_kuerp_goods` / `fa_kuerp_goods_price` / `fa_kuerp_goods_attr` 的 `wholesale_price` 保留，
  所有 `field(...)` 查询/小程序 API 返回字段不动，只是值永远为 0。
- **历史数据不自动纠正**：库里 1132 个商品、1055 条多单位价格、属性表的批发价原本就全是 0（实测），无脏数据。
- 恢复批发价：视图加回 th/td、`goods.js` 模板行加回、`Goods.php` 4 处 `wholesale_price=>0` 换回读表单、
  7 处 `case 2` 改回 `wholesale_price`、两个模板加回列（商品模板注意两个区列号不同）。
- 恢复客户等级：表单换回 `build_select`、列表 JS 加回列、`index/index.html` 删 CSS 段、导入模板加回列与 `hidden_5`。

---

# 收发存汇总表（月度快照，2026-10-28）

> 需求：`资料/财务功能/收发存汇总表.xlsx`（第 9 行字段备注 + C9「每月第一天自动计算，未满月份期末这类字段空白」）
> 规格文档：`Desc/收发存汇总表-需求规格.md`
> 迁移：`addons/kuerp/migration_add_stock_summary.sql`（已对 erp_licheng 执行）；`install.sql` 1.6.21 段 + `info.ini` 1.6.21。
> 新增：`application/admin/library/kuerp/StockSummary.php`、`controller/kuerp/StockSummary.php`、
> `command/StockSummary.php`（注册于 `application/command.php`）、`view/kuerp/stock_summary/index.html`、
> `public/assets/js/backend/kuerp/stock_summary.js`、lang。菜单挂**资金组**（weigh=33，利润表 35 之后）。

## 定时任务

```bash
php think kuerp:stock_summary                  # 算上个月（每月1日0点跑的就是这个）
php think kuerp:stock_summary --period=2026-09 # 指定月份
# crontab: 0 0 1 * * cd /path/to/erp && /path/to/php think kuerp:stock_summary >> runtime/stock_summary.log 2>&1
```

⚠️ CLI 必须用 **PHP 8.0**（本机默认 8.4 会让 TP5 console 的 `Stack::__construct()` 隐式 nullable 触发 Deprecated → Fatal，见 应收账款分析 一节）。

## 口径

| 列 | 计算 |
|----|------|
| 期初数量/单价/金额 | 上一期期末 |
| 本期入库数量 | 采购(2)+销售退回(5)+盘盈(8)+其他入库(9)+组装入库(12)+拆卸入库(14)+加工入库(17) |
| 本期入库单价 | 本期入库金额 / 本期入库数量 |
| 本期入库金额 | 上述入库单据 `enter_cost` 合计 + 成本调整单(7) 的 `enter_cost` |
| 本期出库数量 | 采购退回(3)+销售(4)+盘亏(10)+其他出库(11)+组装出库(13)+拆卸出库(15)+加工出库(16) |
| 本期出库单价 | **(期初金额+本期入库金额)/(期初数量+本期入库数量)** ← 移动平均，不是 出库金额/出库数量 |
| 本期出库金额 | 上述出库单据 `out_cost` 合计 |
| 期末数量/单价/金额 | 期初+入库−出库；期末单价=期末金额/期末数量 |

## 关键设计（改动前务必先读）

1. **快照粒度 = 商品 + 仓库**（唯一键 `period,goods_id,stores_id`）。页面「仓库」下拉默认**全部仓库**，此时按商品合并成一行（对齐模板版式）；选具体仓库时只看该仓库。合并行的单价必须在合并后的数量/金额上重算（控制器 `calcPrices()`），**不能**直接取某一仓库的单价。
2. **调拨单(6) 单独存 `transfer_in_*/transfer_out_*` 四列**：全部仓库合并时一进一出抵消、不计入本期收发；单仓库查看时才并入 `in_*`/`out_*`。库里的 `in_qty`/`out_qty` 字段本身**不含**调拨。`StockSummaryLib::mergeRows($rows, $merge)` 是唯一合并入口，页面与导出共用。
3. **期末用「当前库存倒推」锚定**，不要改成「期初+本期收发」直算：
   ```
   期末 = 商品库存余额表当前库存(该商品+仓库) − 本期结束后发生的收发
   期初 = 期末 − 本期净收发
   ```
   这样最新一期期末恒等于「商品库存余额表」，不会因历史日志成本缺失而漂移；首期差额自动落成期初。
4. **未满月份期末写 NULL**（不是 0）：`is_complete = period_end <= time()`，页面/导出/合计都显示空白并附提示。判断以**月份**为准（空数据的未来月份 `is_complete` 也是 0），不要改成「看行是否有 null」。
5. **缺快照自动补算**：`index()` 打开没有快照的月份会现场 `build()`（仅 `<= 当月`），未来月份不建。导出不自动补算，无数据直接提示。
6. 零行不落库：期初/入库/出库/期末全为 0 的 商品+仓库 组合跳过。

## 已知数据现状（不是 bug）

- 组装单(12/13)、拆卸单(14/15) 写 `kuerp_goods_log` 时 `enter_cost`/`out_cost` 被注释掉（写 NULL），所以这些单据在报表里**只有数量没有金额**；补金额属单据侧改造，本次未做。
- 本库 `fa_kuerp_goods_purchase_info.unit_price` 全为 0，采购成本主要靠 `fa_kuerp_goods_stock.cost_money`；报表金额列以 `kuerp_goods_log` 的 `enter_cost`/`out_cost` 为准（与库存表成本同源）。

## 已验证

curl + headless Chrome/CDP 实测 erp.licheng.test（测试数据已还原）：三个月行数 675/675/677；9 月期末 3,124,421 与库存表 3,124,428 − 10 月净收发 3 吻合；临时造 +10×50 元采购 → 9 月 `in_money=500`、`out_price=0.2488`、`end_money=500`、`end_price=0.2493` 且正确结转 10 月期初；18 列表头、合计栏在表格上方、未满月份期末空白、fixed 列行高 41px 一致（1600/1200/900 宽）；单仓库/关键字过滤、导出 xlsx 692×16 含合计与 13 行字段说明、删快照后自动重建、侧栏菜单位置正常。

---

# 销货对账：账期起算 + 收款到期日 + 到期提醒（2026-10-29）

> 迁移：`addons/kuerp/migration_add_sales_recon_due_date.sql`（已对 erp_licheng 执行，可重复执行）；`install.sql` 1.6.22 段 + `info.ini` 1.6.22。
> 涉及文件：`application/admin/controller/kuerp/SalesRecon.php`、`Collection.php`、`Writeoff.php`、`Customers.php`、
> `application/admin/model/kuerp/SalesRecon.php`、`application/admin/library/kuerp/ArAnalysis.php`、
> `view/kuerp/sales_recon/{index,invoice,detail}.html`、`view/kuerp/customers/{add,edit}.html`、
> `public/assets/js/backend/kuerp/{sales_recon,customers}.js`

## 一、三条需求的落地口径

1. **录客户时设账期天数**：`fa_kuerp_customers.credit_days`（2026-10-28 已有表单字段），本次补齐
   列表列「信用账期天数」（0 显示 `—`）与 Excel 导入（表头 `信用账期天数`/`信用账期`/`账期天数`，
   **表里有这列才写**，旧模板导入不会把已有账期清 0）。
2. **对账单开票时开始算账期**：开票（`SalesRecon::invoice()`）时把客户档案的 `credit_days` **快照**到发票行
   `sales_recon_invoice.term_days`，并算 `due_date = invoice_date + term_days*86400`。
   `term_days=0` → `due_date=NULL`（= 不计到期日，与客户档案「0=不判断逾期」语义一致）。
   开票弹窗允许临时改账期天数（默认取客户档案），改的是**这一张发票**，不回写客户档案。
3. **到期前 N 天提醒**：新设置项 `sales_collection_remind_days`（默认 7，`fa_kuerp_sets` type=1，
   设置页「基础设置」可见）。**不要复用 `purchase_payment_remind_days`**（那是采购付款口径）。

## 二、表结构与派生字段

| 表 | 字段 | 说明 |
|---|---|---|
| `sales_recon_invoice` | `term_days` int(11) | 开票时快照的账期天数 |
| `sales_recon_invoice` | `due_date` bigint(16) + KEY | 收款到期日 = 开票日 + term_days |
| `sales_recon` | `due_date` bigint(16) + KEY | 派生 = 所含发票里**最早**的到期日；列表提醒/筛选/排序直接用它（不 JOIN 子查询） |

`refreshInvoiceStatus()` 是唯一的重算入口（新增/删除发票都走它），里面调用 `syncReconDueDate()` 重算主表 `due_date`。

## 三、列表提醒与筛选

- 列表新增「收款到期日」列（`sales_recon.js`）：已收款绿、逾期红（`逾期N天`）、N 天内到期黄（`剩N天`）、其余灰色 `—`。
- `index()` 每行计算 `due_date / due_date_text / days_left / term_state`（0 正常 1 即将到期 2 逾期 3 已收款）。
- 顶部提醒条：`GET kuerp/sales_recon/reminder` 返回 `{due_soon, overdue, remind_days}`，
  页面渲染两条 alert，点「查看逾期 / 查看即将到期」自动把筛选置为 `term_state=2/1` 并刷新。
- **`term_state` 是派生筛选，不能进 `filter`**（`getSearch()/getOp()` 里显式跳过该字段），
  它走独立查询参数 `term_state`，SQL 侧用 `due_date + pay_status` 冗余字段过滤，所以翻页/搜索不受影响。

## 四、踩坑与必须遵守的约定

- ⚠️ **`pay_status` 是派生值**：`SalesRecon::calcPay()` 每次列表/详情/打印/导出都会重算并写库，
  它只是 `SalesReconModel::recalcPay()` 的转发。任何「手动置 pay_status」都会被覆盖。
- ⚠️ **收款/核销后必须同步对账单收款状态**：`Collection::examine()`（审核+反审核）与
  `Writeoff::examine()`（审核+反审核）提交后都会调
  `SalesRecon::recalcPayByDocCodes($codes)`（`Collection` 用 `kuerp_collection_sale.purchase_number`，
  `Writeoff` 用 `kuerp_writeoff_info.purchase_number`）。**不加这步，到期提醒会把已收完的对账单一直标成逾期**
  （`pay_status` 只在打开对账列表时才刷新）。
- ⚠️ `overdueMap()`（应收账款分析）现在**优先取对账单固化的 `due_date`**，取不到才回退
  「开票日 + 客户当前账期」。因为 `due_date >= invoice_date` 恒成立，不会把「分析时点之后才开的票」误判成逾期。
- ⚠️ `Writeoff.php` 是 **CRLF** 文件，用脚本改必须按字节读写（`open(p,'rb')` + `'\r\n'.join`）。
- ⚠️ `index()` 里 count 与列表各建一个 Query（`$build()` 闭包），**不能** `clone $query` 复用（TP5 会报
  `No data supplied for parameters`）。

## 五、已验证（curl + headless Chrome/CDP 实测 erp.licheng.test，测试数据已还原）

- 客户：列表返回并渲染 `credit_days`（0 → `—`）；开票弹窗默认账期取客户档案（客户 30 天 → 输入框 30，提示「到期前 7 天」）。
- 开票：POST `term_days=15` + 开票日 2026-10-29 → 发票行 `term_days=15`、`due_date=2026-11-13`，
  对账单头 `due_date=2026-11-13`，开票台账未被打乱。
- 列表/提醒：`due_date=今天+3` → 行显示 `2026-11-01（剩3天）`、提醒条「有 1 张对账单将在 7 天内到期」、
  `term_state=1` 过滤命中 1 条；改成 `今天-2` → 行 `（逾期2天）`、提醒条「已逾期未收款」、`term_state=2` 命中 1 条、
  `term_state=1` 归零；点提醒条链接后下拉变为 2 且表格只剩 1 行。
- 详情页/开票页：发票表新增「账期天数 / 收款到期日」两列并按 已收款/逾期/即将到期 着色；表头「收款到期日」行同样着色。
- 应收账款分析：`recalcPayByDocCodes` 正常（含不存在的单号不报错）；人为给对账单 `due_date=2026-08-01`
  重算 2026-08 快照 → 客户 19 `overdue_money=1201`（1130+71），证明「优先用固化到期日」分支生效且不受
  客户当前 `credit_days=0` 影响。测试快照已删、`AUTO_INCREMENT` 还原。
- 设置页出现「销货收款提前提醒天数」；`sales_recon` / `customers` 列表浏览器实测列头与行渲染正常。

---

# 计划排期：排产填写「生产日期」+ 预计交付日期更名「预计生产完成日期」（2026-10-29）

> 迁移：`addons/kuerp/migration_add_goods_sales_production_date.sql`（已对 erp_licheng 执行，可重复执行）；
> `install.sql` 1.6.23 段 + `info.ini` 1.6.23。
> 涉及文件：`application/admin/controller/kuerp/GoodsSalesSchedule.php`（`setproduction()`）、`Assemble.php`（`add()` 预填 config）、
> `public/assets/js/backend/kuerp/goods_sales_schedule.js`、`assemble.js`、两个 lang（`goods_sales_schedule.php` / `goods_sales.php`）

## 字段

- `fa_kuerp_goods_sales` 新增 `production_date` varchar(10)（AFTER `delivery_time`），存 `YYYY-MM-DD`，
  计划排期列表勾选「排产」时弹窗填写（销货单/计划排期共用该表，字段随行下发）。
- `expected_delivery_date` **只改语义不改结构**：字段名与历史值不动，界面上「预计交付日期」全部改为「预计生产完成日期」
  （lang key `Expected_delivery_date` 两处已改名，列表列头、审核弹窗、行内编辑提示、接口提示同步）。

## 交互

- **排产**（计划排期列表工具栏）：原直接 ajax → 改为 layer 弹窗（`520×250`，默认今天，laydate，必填）
  填生产日期后 POST `setproduction?status=1&ids=..&production_date=..`；批量选中的排期统一写入同一日期。
  `setproduction()` 现在 status=1 时**必须**带合法日期（`请选择生产日期` / `生产日期格式错误`），
  **取消排产（status=0）不改生产日期**。
- **审核**：原「审核 - 填写预计交付日期」弹窗改名「审核 - 填写预计生产完成日期」，尺寸 `430×220 → 520×250`。
- 列表在「是否排产」后新增「生产日期」列（空值显示灰色 `—`）。
- **带入组装单**：排期「生成组装单」→ `kuerp/assemble/add?schedule_id=..` 时，`Assemble::add()` 下发
  `scheduleProduceDate`，`assemble.js` 的 `initScheduleBom` 预填 `#c-produce_date`（仅 add 页有该输入框，edit 页不受影响）。

---

# 销货订单状态改为「按销货单实际出库数量」派生（2026-10-29）

> 涉及文件：`application/admin/model/kuerp/GoodsSalesOrder.php`（新增 `syncStatusByOutbound()`）、
> `application/admin/controller/kuerp/GoodsSalesOrder.php`（`index()` 同步 + `ship()`/`delDelivery()` 不再改状态）、
> `GoodsSales.php` / `GoodsSalesOutbound.php` / `GoodsSalesExchange.php`（审核/反审核时不再按"生成/退货数量"改状态）

## 规则

- 订单状态 `fa_kuerp_goods_sales_order.status`：**0 未出库 / 1 全部出库 / 2 部分出库** 由
  「该订单下所有销货单 `fa_kuerp_goods_sales_info.out_number` 合计」对比订单 `number` 派生：
  合计 <=0 → 0；合计 >= 订单数量 → 1；否则 → 2。
- **3 已取消 / 4 已收货 为人工状态**，`syncStatusByOutbound()` 直接跳过不覆盖。
- 单一入口 `GoodsSalesOrder::syncStatusByOutbound($orderCodes)`（静态，批量、只写有变化的行，
  返回 `purchase_code => status` 映射）。调用点：
  1. `GoodsSalesOutbound::saveOutbound()` 出库成功后（出库申请审核走同一方法）；
  2. `GoodsSalesOrder::index()` 列表加载时（保证历史数据/漏更新数据在列表上始终正确，顺带落库使状态筛选一致）。
- **不再改状态的旧入口**（此前会误把"发货/生成销货单"当成出库）：
  `ship()`（原写 status=1）、`delDelivery()`（原恢复 status=0）、
  `GoodsSales`/`GoodsSalesOutbound`/`GoodsSalesExchange` 的审核/反审核里按 `store_number`（已生成/已退货数量）算 status 的循环
  —— 这些循环现在只补 `order_count[]['id']` 供 `store_number` 的 inc/dec 使用。
- 列表「发货/确认收货」按钮模板条件仍为 `status != 0/3/4`，因此**必须等销货单出库后**（部分/全部出库）才出现；
  后端 `ship()` 的 3/4 守卫、`confirmReceipt()` 的 `status==0` 守卫未改。
- 小程序接口 `addons/kuerp/controller/api/Order.php::calcDisplayStatus()` 本来就按 0/1/2 映射
  （待发货/已发货/部分发货），依赖同一字段，行为随之一致。

## 已验证（curl 登录 erp.licheng.test 实测，测试数据已还原）

- 订单列表 10 行状态与 `out_number` 合计一致；临时把 XHD20260914001 的 out_number 改 1 → 列表/库 status=2，
  改 3 → status=1，改回 0 → status=0（`status_text` 同步）；按 status=1 筛选返回 4 条且与展示一致。
- 对 XHDD20260914001 调 `ship`（专业配送）→ 生成发货记录但 status 仍为 0；`delDelivery` 删除记录后 status 仍为 0。
- 测试产生的发货记录、订单日志（id 90/91）与自增值已还原；未改 `fa_admin` / `login_captcha`。

---

# 收款单未审核导致对账单一直「未收款」的提示（2026-10-29）

> 涉及文件：`application/admin/view/kuerp/collection/{add,edit}.html`、
> `public/assets/js/backend/kuerp/collection.js`（**无数据库迁移、无控制器改动**）

## 现象与根因

用户反馈：在销货对账「去收款」→ 收款单保存后，对账单仍显示「未收款」。

根因不是代码 bug：**对账单的收款状态取自销货单的 `kuerp_goods_sales.pay_money`**
（`SalesRecon::recalcPay()`：已收 = Σ销货 `pay_money` − Σ退货 `retreat_money`），
而 `pay_money` 只在 **收款单审核**（`Collection::examine()`）时回写。开关 `fa_kuerp_sets.examine.is_checked=1`
（启用单据审核）时，保存收款单只是草稿：`examine_status=0`、不写 `kuerp_sales_payment_log`、
不动账户余额、不动销货单 `pay_money` → 对账单自然一直是「未收款」。

实操确认（本库 SK20261028001 1201 元 / KHDZ20261028001）：收款单 `examine_status=0` 时对账单 `pay_money=0`；
在【收款单】列表勾选该行点【审核】后 `pay_money=1201`、`pay_status=2`（已完成），反审核后立即回到「未收款」，
说明 `Collection::examine()` 里的 `SalesRecon::recalcPayByDocCodes()` 双向同步正常。

## 本次改动（只补提示，不改业务规则）

- `collection/add.html`、`collection/edit.html` 表单最上方新增 `alert-warning` 提示条，
  仅 `$set['examine']['is_checked']==1`（启用审核）时显示：
  「收款单保存后为『未审核』草稿，需到【收款单】列表勾选该单据点【审核】后，收款金额才会核销到销货单/销货对账单」。
- `collection.js` 的 `Form.api.bindevent` 成功回调里，同样条件下
  `parent.Toastr.success('收款单已保存，需到【收款单】列表点【审核】后才会核销到销货单/销货对账单', null, {timeOut:8000})`
  （包在 try/catch 里，不传用时零影响）。注意 add 页保存后既有 `parent.location.reload()` 会立刻刷新父窗口，
  Toastr 可能一闪而过——所以以页面上的静态提示条为主，Toastr 为辅。
- 关闭审核（`examine.is_checked != 1`）时两条提示都不出现，行为与改动前一致。

## 已验证

- `collection/add`、`collection/edit/ids/1` HTTP 200，提示条渲染一次、无 Parse error；
  headless Chrome 实测提示条宽度撑满内容区（1140px，高 52px），表单 81 个字段不受影响。
- `Config.set.examine.is_checked` 在页面里为 `1`（parseInt 后 `===1` 成立），`parent.Toastr` 可用。
- 全程用 curl 走 `kuerp/collection/examine?examine=1&ids=1` 与真实 UI 点击（勾选 → 反审核 → 审核）
  各验证一遍：对账单 `pay_money` / `pay_status` 与 `business_status`（已完成 ↔ 已开票·待收款）同步正确。
  测试后收款单保持「已审核」状态（即用户原本想要的收款已生效）。

---

# 销货单列表「开票状态」支持部分开票（2026-10-29）

> 涉及文件：`application/admin/controller/kuerp/GoodsSales.php`（`index()` 开票状态派生）、
> `public/assets/js/backend/kuerp/goods_sales.js`（列表列渲染）。**无数据库迁移。**

## 现象与根因

销货对账「去开票」开了一部分（如对账单 1201 只开了 20），销货单列表的开票状态仍显示「未开票」；
把金额开满后才变成「已开票」——表现为「部分开票状态没有回传」。

根因是**字段只有二值**：`fa_kuerp_sales_invoicing.invoice_status` 是 `enum('0','1')`（0 未开 / 1 已开），
`SalesRecon::syncInvoiceLedger()` 只在「该单据自身金额被完全覆盖」时才写 `1`，部分开票写 `0`；
而销货单列表原先直接用这个字段（`invoice_status == 1 ? 已开票 : 未开票`），部分开票自然显示成「未开票」。

## 改法

- **不动表结构与台账写入逻辑**（`invoice_status` 仍是二值，开票管理模块等既有读写零影响），
  只在 `GoodsSales::index()` 里按台账金额派生三态下发：
  ```
  invoice_status = '2'  台账 invoice_status=='1' 且 give_money<=0   → 已开票
  invoice_status = '1'  已开票金额 pay_money>0（但未开满）            → 部分开票
  invoice_status = '0'  其余（无台账行 / pay_money=0）                → 未开票
  ```
  列表查询改为一次取 `bill_code,invoice_status,pay_money,give_money` 后建 `bill_code => 状态` 映射。
- `goods_sales.js` 列渲染同步三态：`2` 绿「已开票」、`1` 橙 `#ffb800`「部分开票」、其余灰「未开票」
  （与 `purchase_recon.js` 的三态写法一致）。

## 口径说明（不是 bug）

`syncInvoiceLedger()` 的单据台账**只按单据自身 `real_money` 分摊**（历史决策，见上文「开票基数改为含客户承担费用」一节）：
对账单里属于「客户承担费用」的那部分多开金额不落到单据台账上。所以
**含客户承担费用的对账单**开到 `real_money` 时，单据台账 `give_money` 就归 0 → 销货单显示「已开票」，
而对账单仍是「部分开票」（还差客户承担费用那部分），把剩余部分也开掉后两者才都变「已开票」。

## 已验证（curl + headless Chrome 实测 erp.licheng.test，测试数据已还原）

- `goods_sales/index` 对 `XHD20261028001`（台账 pay=1130/give=0/status=1）返回 `invoice_status=2`（已开票）、
  `XHD20261018001` 返回 `2`、未开票单据返回 `0`。
- 部分开票：临时把台账行 `XHD20260930003` 改为 `pay_money=1000, give_money=1960, invoice_status=0`
  → 接口返回 `1`，浏览器上该行显示橙色「部分开票」；测完已改回 `0 / 2960`。
- `php -l` / `node --check` 通过；开票管理页面（`sales_invoicing`）未受影响（其列表只展示金额，无该状态列）。

---

# 申请出库弹窗明细增加规格与生产信息列（2026-10-29）

> 涉及文件：`application/admin/controller/kuerp/GoodsSalesOutbound.php`（`buildOutboundRows()`）、
> `application/admin/view/kuerp/goods_sales_outbound/apply.html`

- 销货出库列表 → [申请出库] 弹窗的「出库商品列表」在**商品名称之后**新增
  **型号 / 规格2/厂商 / 规格3/封装** 三列（取 `kuerp_goods` 的 `goods_specification_id` / `vendor_name` / `packaging`），
  在**仓库之后**新增 **品号与功率 / 生产备注** 两列（取销货单明细 `kuerp_goods_sales_info.remark` / `production_remark`，
  即从销货订单带过来的值；列头与销货订单/销货单一致，「品号与功率」沿用 `remark` 字段）。
- 数据在 `buildOutboundRows()` 统一装配（该方法是 `apply()` 与已停用的 `outbound()` 共用）：
  原来两次 `column()` 查询改为一次 `field('id,goods_code,goods_name,goods_specification_id,vendor_name,packaging')->select()`
  建 `$goodsMap/$goodsIdMap/$goodsSpecMap`；商品已删除（孤儿明细）时三列留空不报错。
- 空明细行 `colspan` 12 → 17。`outbound.html`（实际出库弹窗，列表已无入口）未改列。

# 销货对账明细改版：单据卡片式（单据头 + 折叠商品明细）（2026-10-29）

> 涉及文件：`application/admin/controller/kuerp/SalesRecon.php`（`itemlist()` 分组返回 + 新增 `groupItemDocs()`）、
> `application/admin/view/kuerp/sales_recon/items.html`、`public/assets/js/backend/kuerp/sales_recon.js`（`Controller.items`）。**无数据库迁移。**

## 版式变化

原「一商品一行」的 layui 平铺表格改为**按销货单/退货单分卡片**：

```
[✓] ▸ XHD20261028001 [销货单] 2026-10-28 客户：xx 销售员：xx  出库/对账/开票/收款状态      总金额 1,201.00
     销货合计：1,130.00  客户承担费用：71.00  商品 4 项  关联销售单号：XHDD...  物流单号：...  对账单号：...
     ┌ 展开后：商品编号/商品名称(含属性)/型号规格/单位/数量/含税单价/未税单价/税点/销货合计金额/关联销售单号/备注 ┐
```

- **商品明细默认折叠**，点单据头任意位置（点复选框除外）展开/收起；箭头旋转提示状态。
- **勾选 = 勾选该单据**：每张卡片一个复选框，`value` 仍是 `doc_key`（`1:销货单id` / `2:退货单id`），
  与原来「生成对账单」的 `docs` 参数逐字一致；`已对账`（`recon_id>0`）的卡片复选框 `disabled` 并显示对账单号。
- 工具栏新增 **全选本页**（自动与卡片勾选状态联动）+ 「共 N 张单据」。
- 底部合计（销货合计/客户费用/总金额总计）仍按**整批筛选结果**汇总，不受分页影响。

## 服务端

- `itemlist()` 不再返回平铺行，改为 `rows` = 单据数组：单据级字段（`doc_key/doc_code/doc_time_text/doc_type/customer_name/
  sale_user_name/customers_money/real_money/total_amount/express_no/out_status/recon_status/recon_code/invoice_status/
  pay_status/recon_id/purchase_order_id`）+ `info_list`（商品行）+ `info_count`。
- **分页按单据**（`total` = 单据数，默认 `limit=50`）：原来按行切页，明细多的单据会被分页从中间切断，本次顺带修掉。
- `groupItemDocs()` 只做分组，行级数据仍由 `buildItemRows()` 生成（退货单负数、优惠分摊、孤儿明细逻辑零改动）。
  孤儿明细（`goods_code` 与 `goods_name` 都为空）不产生商品行，卡片显示「该单据没有可显示的商品明细」。

## 前端注意事项

- ⚠️ **`itemlist` 返回裸 JSON（无 FastAdmin `code` 包裹），不能用 `Fast.api.ajax`**：它只认 `code===1`，
  否则静默走 error 分支、页面一片空白（本次首版即踩此坑）。用 `$.ajax` + `Fast.api.fixurl(...)`。
- 分页控件用 layui `laypage`（`layout: ['prev','page','next','skip','limit']`）。**`#items-pager` 必须加
  `data-select-enhance="skip"`**：否则 select-enhancer 会把「每页 N 条」下拉转成 bootstrap-select（宽度撑到 220px），
  在右对齐的分页栏里溢出一截、右侧出现一个被裁掉的白框。
- 卡片内容整体重新渲染（`$('#items-list').html(...)`），所以点击事件全部**委托**在 `#items-list` 上。
- 页面级 15px 横向溢出是框架 `.row` 负边距所致（`sales_recon/index`、`goods_sales/index` 等所有 kuerp 页同样存在），非本页引入。

## 已验证（curl + headless Chrome/CDP 实测 erp.licheng.test，未改动任何业务数据）

- `itemlist`：5 张单据分组正确、`info_list` 商品行数/金额与改造前一致；`total` = 单据数；`page=2&limit=2` 取到第 3、4 张单据
  （不再出现半张单据）；`recon_status=2` 返回的 2 张单据 `recon_id>0`；`all_money/all_customers_money/all_amount` 不变。
- 浏览器（1680/1200/1000 三种宽度）：默认全部折叠、点单据头展开/收起、点复选框不触发展开、
  勾选后卡片蓝色描边、全选/取消全选与「共 5 张单据」联动、展开后商品表 11 列无横向溢出、分页无溢出、
  底部合计 18618.00 / 8.00 / 18626.00 正确；无 JS 异常。
- 生成对账单：勾选 2 张 → 请求体 `docs=1:2,1:4`；未勾选 → 弹确认框，确定后 `all=1` 且带上当前筛选（实测拦截 `Fast.api.ajax` 抓参数，未真正建单）。

---

# 组装单：展示关联销货订单的品号与功率/生产备注 + 打印改 A4 横向（2026-10-30）

> 涉及文件：`application/admin/controller/kuerp/Assemble.php`（新增 `getSalesOrderInfo()` + add/edit/printpage 三处 assign）、
> `application/admin/view/kuerp/assemble/salesorder_info.html`（新增，add/edit 共用）、
> `assemble/{add,edit,printpage}.html`、`public/assets/js/backend/kuerp/assemble.js`（打印弹层尺寸）。
> **无数据库迁移。**

## 展示内容

组装单 `fa_kuerp_assemble.sales_order_id` 存的是**销货订单号**（`fa_kuerp_goods_sales_order.purchase_code`）。
`Assemble::getSalesOrderInfo($code)` 返回：

- `purchase_code` 销货订单号
- `note` 销货订单主表 `note`（销货订单页底部的「备注信息」）
- `items[]` 明细行：`goods_code` / `goods_name` / `remark`（**品号与功率**）/ `production_remark`（**生产备注**）

明细表 `kuerp_goods_sales_order_info` **没有 goods_id / goods_name**，商品名按 `goods_code LEFT JOIN kuerp_goods` 取。
订单不存在（历史脏数据）时返回 `null`，各页面自动不显示该面板，不报错。

## 渲染位置

| 页面 | 位置 |
|------|------|
| add / edit（`{include file="kuerp/assemble/salesorder_info" /}`） | 表头（单号/日期/生产日期）之后、`.table_box` 之前，Bootstrap `panel-info` 面板：标题行显示销货订单号，正文显示「销货订单备注」+ 明细表（商品编号/商品名称/品号与功率/生产备注） |
| printpage | ① 信息区 grid 末尾加「销货订单号」；② `.info-grid` 之后新增「销货订单信息」区块（订单号 + 备注 + 明细表，列同上） |

- add 页：销货订单号来自 `schedule_id` 对应排期的 `purchase_order_id`（`$salesOrderId`，未排期时为空 → 不显示面板）。
- edit / printpage：读 `$row['sales_order_id']`。

## 打印改 A4 横向

`printpage.html` 在 `{include file="kuerp/common/printstyle" /}` **之后**追加一段覆盖样式：

```css
@page { size: A4 landscape; margin: 10mm; }
@media screen { #content { width: 297mm; } }
.print-wrap { max-width: none; }
```

- `@page` 同特异性**后写者生效**，故公共版式的 `A4 portrait` 被覆盖为横向；屏幕侧 `#content` 由 210mm 改为 297mm
  （内容宽 277mm ≈ 1047px），打印可用宽度同为 190→277mm，预览仍与输出一致。
- `.print-wrap` 原本 `max-width:900px` 必须放开，否则横向纸左侧留白。
- `assemble.js` 里 [打印] 的弹层尺寸 `['90%','75%'] → ['95%','90%']`，避免 277mm 内容在弹层里被挤压。

⚠️ 公共 `printstyle.html` **不要改**：它被 19 个打印页共用，改成横向会连带改掉采购单/销货单等全部单据。

## 已验证（curl 登录 + headless Chrome/CDP 实测 erp.licheng.test；未改动业务数据）

- edit?ids=3 / add?schedule_id=5&goods_code=A02-0003 均渲染面板，品号与功率 = `250W 17-山藤/R 黑红白蓝黄绿`；
  普通 add 页（无排期）不渲染面板、无 500。
- 临时把该组装单关联到带备注的销货订单 `XHDD20261028001` → 打印页与编辑页均出现「销货订单备注：测试多得多」，
  验证后已把 `sales_order_id` 还原为 `XHDD20260930002`。
- printpage 屏幕预览实测：`#content` 1122.5px（=297mm）、`.print-wrap` 1047px（=277mm）、三张表均无横向溢出；
  HTML 中 `@page portrait`(82 行) 之后才是 `@page landscape`(156 行)，覆盖顺序正确。
- 打印会 `setInc('print_num')`，本库这些单据 `print_num` 原为 NULL（SQL 对 NULL 自增仍为 NULL），数据未变。

## 补充（2026-10-30 第二轮）：组合件行内直接带出品号与功率 / 生产备注

顶部面板之外，**「组合件」表格每行末尾新增两列只读输入框**（`class="so_col"`）：

| 列 | 字段 | 来源 |
|----|------|------|
| 品号与功率 | `.so_remark` | 销货订单明细 `remark` |
| 生产备注 | `.so_production_remark` | 销货订单明细 `production_remark` |

- **服务端**：`Assemble::edit()` 用 `buildSalesOrderItemMap($salesOrderInfo)` 把订单明细按 `goods_code` 归并
  （同商品多行去重后「；」拼接），只挂到 **type=1（组合件）** 行 → `so_remark` / `so_production_remark`，模板直接渲染。
- **前端**：`getSalesOrderItem(goods_code)`（`bindevent` 内）+ `changeGoods()` success 里回填，覆盖「新增页选商品 /
  换商品」的场景（排期带入的组装单靠这条生效）。
- 两列 input **没有 name、只读**，不参与提交；`sales_order_id` 为空时页面顶部输出 `<style>.so_col{display:none;}</style>`
  整列隐藏（add/edit 各一处），无关联订单时界面与改动前一致。
- `add()` / `edit()` 都把 `salesOrderInfo` 同时 `assignconfig` 出去，供 JS 取 `Config.salesOrderInfo`。
- 已验证：`edit?ids=3` 组合件行显示 `250W 17-山藤/R 黑红白蓝黄绿`，表头 th 与行 td 均 18 列、无 JS 异常；
  `add?schedule_id=5&goods_code=A02-0003` 由 JS 回填（16 列，新增页无「已入库/剩余入库」两列）；
  `edit?ids=12`（无销货订单）两列 `display:none`、表头 18/行 18 仍对齐、无面板。

---

# 销货订单发货入口调整 + 申请出库填写快递信息 + 送货单打印（2026-10-31）

> 迁移：`addons/kuerp/migration_add_outbound_express.sql`（已对 erp_licheng 执行，可重复执行）；
> `install.sql` 1.6.24 段 + `info.ini` 1.6.24。
> 涉及文件：`application/admin/view/kuerp/goods_sales_order/index.html`、
> `GoodsSalesOutbound.php`（`apply()` + `parseApplyExpress()`）、`goods_sales_outbound/{apply.html,js}`、
> `GoodsOutboundApply.php`（`deliverynote()` + `assignApplyDetail()`）、
> `goods_outbound_apply/{index.html,detail.html,deliverynote.html,js}`。

## 一、销货订单列表「发货」按钮隐藏

- `goods_sales_order/index.html` 的 `#barDemoSales` 删除了 `lay-event="ship"` 的 [发货] 按钮（后端 `ship()` 保留未删，仅入口隐藏）。
- 发货数量与快递信息改到 **销货出库页（kuerp/goods_sales_outbound）→ [申请出库]** 弹窗中填写。
- 「确认收货」按钮保留：订单状态本来就按销货单实际出库数量派生（见上文「销货订单状态改为按销货单实际出库数量派生」），
  出库后仍可确认收货。

## 二、申请出库填写快递信息（一次出库可多条）

- 字段：`fa_kuerp_goods_outbound_apply.express_data` text，存 JSON 数组
  `[{"code":"SF","company":"顺丰速运","no":"SF123456"}, ...]`（`code`=快递公司编码，`company`=名称快照，`no`=快递单号）。
- `GoodsSalesOutbound::apply()` POST 新增 `parseApplyExpress()`：读取 `express_company[]`（编码）+ `express_no[]`（单号）平行数组，
  空行跳过；只填其一会报「请选择快递公司（快递单号：xxx）」/「请填写快递单号（快递公司：xxx）」；两个都不填 = 不记录快递信息（合法）。
- `apply.html` 明细表下方新增「快递信息」区块（`#express_wrap`）+ [添加快递] 按钮，JS 在 `Controller.apply` 中动态增删行、重新编号；
  校验在 `Form.api.bindevent` 第 4 参（提交前置回调）里与「申请数量至少一项 >0」一起做。
- ⚠️ 区块容器带 `data-select-enhance="skip"`：**快递公司下拉保持原生 select**，避免 select-enhancer 把动态行包成 bootstrap-select 后克隆/取值错乱。
- 历次申请记录表新增「快递信息」列；`apply()` GET 解析 `express_data` 为 `express_summary`（`顺丰速运 SF123、中通快递 ZTO456`）。

## 三、送货单打印（版式参照 `资料/财务功能/送货单模板.xlsx`）

- 入口（~~三处~~ 2026-11-05 起两处）：
  - ~~**销货出库列表（kuerp/goods_sales_outbound）行操作 [送货单]**~~：**2026-11-05 已按用户要求移除**
    （送货单对应的是「每一次出库申请」，行级按钮会把多张申请混成一个入口）。同时删了 `index()` 里的
    `printable_apply_id` / `printable_apply_count` 计算与 JS 的 `deliverynote` 行事件分支，操作列宽 250→180（只剩 1~2 个按钮）。
    历史上的「有多条申请时打印最新一条」逻辑随之作废，请一律到申请记录里逐条打印。
  - **销货出库 → [申请出库/申请记录] 弹窗 → 历次申请记录**：每条 `status IN ('0','1')` 的记录有 [打印送货单]（**唯一逐条打印入口**）；
  - **出库管理（kuerp/goods_outbound_apply）**：原 [打印]（出库申请单）改为——`status` 0/1 行显示 **[送货单]**（与销货出库同一版式，
    直接打开 `deliverynote`），`status` 2/3（已拒绝/已撤销）保留 [打印申请单]（旧 `printpage`）；
    详情弹窗 `status` 0/1 均显示 [打印送货单]。
- 路由：`kuerp/goods_outbound_apply/deliverynote/ids/<申请ID>`（`$noNeedRight`）。
  **待审核(0) 也可打印**（数量取申请数量、快递信息取申请时填写的）；已同意出库(1) 取实际出库数量；已拒绝/已撤销(2/3) 提示不能打印。
- 版式：公司名+送货单标题、公司地址、客户名称／销货日期／业务员／联络人／联系电话／品号（客户物料编码）、
  明细（序/产品名称/型号规格/品号与功率/数量/[单价]/[金额]/备注）、
  页脚（备注、快递公司、快递单号、[销售价格]/[税额]/[合计]）、签字栏、四联说明。
- **价格勾选**：打印页顶部复选框「显示价格」，**默认不勾选 = 隐藏** 单价/金额列与销售价格/税额/合计；
  勾选后由 JS 移除 `.price-hide`（`.col-price` / `.label-price` 显示）。屏幕预览与打印共用同一 DOM，勾选状态即打印结果。
- 数据口径：
  - 数量取 `out_data`（仓库同意时写入的**实际出库数量**），历史无 `out_data` 时回退申请数量；数量 ≤0 的行不打印。
  - 单价 = 未税单价；金额 = 销货明细 `all_money × (实际出库数/计划数)`（按比例分摊，尊重优惠）；
    税额 = `tax_money × 比例`；合计 = 金额 + 税额。页脚 销售价格 = Σ金额、税额 = Σ税额、合计 = Σ合计。
  - 业务员取销货单 `sale_user → kuerp_staff_member.name`；联络人/电话优先 `kuerp_customers_user` 首选联系人，回退客户档案字段。
  - **明细「品号与功率」列 = 销货订单商品列表的「品号与功率」**（`kuerp_goods_sales_order_info.remark`，按 `kuerp_goods_sales.purchase_order_id` + `goods_code` 匹配）；
    销货单明细 `remark` 本就由订单带入，优先取它，为空时回退到订单明细（同一商品取第一条非空值）。
  - 表头「品号：」取 `kuerp_customer_goodscoding.customer_code`（按客户+商品编号），多个去重后用「、」连接。
- ⚠️ **不要把类名写成 `label`**：后台框架加载了 Bootstrap 3，`.label` 是白色文字标签样式，会让表头/页脚标签“消失”
  （本次踩坑：标签文字实为 `rgb(255,255,255)`，且 `display:inline` 打乱表格布局）。统一用 `.lb`。
- 打印页 `{include file="kuerp/common/printstyle" /}` 与其余 19 个打印页一致：屏幕按 A4（内容宽 190mm≈718px），打印同样 718px。

## 四、已验证（curl + headless Chrome/CDP 实测 erp.licheng.test，测试数据已删净并还原自增）

- 申请出库 POST：带 2 条快递 → `express_data` JSON 正确落库；只填公司/只填单号分别返回对应中文校验错误；两条都空仍可提交（`NULL`）。
- 申请弹窗：默认 1 行快递，[添加快递] 后 2 行且序号 1/2、新 select 9 个选项（8 家公司+占位）、下拉未被 bootstrap-select 包裹。
- 历次记录：快递摘要渲染正确；`status=1` 记录 [打印送货单] 点击打开 `deliverynote?ids=..&dialog=1` 层。
- 出库管理：列表 `status=0/1` 行显示 [送货单]（点击打开对应申请的送货单层，实测待审核 id=20）、`status=2` 行显示 [打印申请单]；
  详情 `status=0/1` 均显示 [打印送货单]（status=2 不显示）；详情显示快递信息/未填写提示。
- 销货出库列表：~~有可打印申请的行出现 [送货单]（无申请的 2 行不显示），3 个按钮在 250px 操作列内不换行；
  点击后打开最新一条申请的 `deliverynote` 层~~（**该行级入口已于 2026-11-05 移除**，见本节开头说明）。
- 申请记录弹窗：待审核记录同样显示 [打印送货单]（实测 sales_id=15 的 2 条待审记录均有按钮）。
- 送货单：默认价格为 `display:none`，勾选「显示价格」后恢复；金额实测（C13-0004 单价4×5=20，税 2.60，合计 22.60）；
  多条快递在「快递公司/快递单号」行以「、」分隔；表头标签颜色正常、表格无横向溢出。
- `php -l` / `node --check` 通过；销货订单列表 `lay-event="ship"` 数量为 0。
- 测试清理：临时申请单及其明细删除，`AUTO_INCREMENT` 还原（apply=20 / apply_info=36）；未改动库存与用户数据。

---

# 计划排期编辑不再重建销货明细（修复出库申请断链/已出库数量丢失，2026-11-02）

> 涉及文件：`application/admin/controller/kuerp/GoodsSalesSchedule.php`（`edit()`）、
> `application/admin/controller/kuerp/GoodsOutboundApply.php`（`deliverynote()` / `assignApplyDetail()`）。
> **无数据库迁移。** 已部署线上（118.31.20.155，FTP），线上与本地 MD5 一致。

## 问题

计划排期与销货单共用 `fa_kuerp_goods_sales` 表，`GoodsSalesSchedule::edit()` 原来是
「按 purchase_code 删除全部明细 + 重新 insert」（新行新 id）。排期页面不含金额字段，
因此每次在「计划排期」里保存（哪怕只改备注）都会：

- 销货明细主键变化（如 id 60 → 61），`fa_kuerp_goods_outbound_apply_info.sales_info_id`
  与申请 `out_data` 的键全部悬空 → 申请详情判"明细已变更"、送货单商品名称/规格/单价空白；
- 已出库数量 `out_number` 归 0（新行不带该字段）→ 销货单/销货订单显示"没有出库记录"，
  还能继续申请出库 → **重复出库/超发**；
- 明细价格金额（`unit_price/all_money/tax_money/...`）归 0，与表头金额不一致。

## 修复

1. `GoodsSalesSchedule::edit()` 改为**差异更新**：
   - 旧明细按 `goods_code|stores_id|goods_attr|goods_unit` 建索引，命中则 `UPDATE` 原行（id 不变，
     `out_number`/价格金额/`purchase_order_id` 等未提交字段原样保留），未命中才 `INSERT`；
   - 本次未匹配到的旧行才删除，且 `out_number>0` 或存在 `status=0` 待审核出库申请时拒绝删除；
   - 命中行若计划数量 < `out_number`（或 < 已出库+待审核占用）则拒绝保存；
   - 提交行全为空时报「请至少保留一行商品」（原代码此处会产生未定义变量）。
2. `GoodsOutboundApply::deliverynote()`：商品编号兜底取 `apply_info.goods_code`，销货明细已被删除时
   仍能从商品档案带出名称/型号/规格（单位取商品默认单位）；`assignApplyDetail()` 的 `sales_missing`
   分支同样补默认单位。

## 已验证（本地 erp.licheng.test curl 实测，测试数据已还原）

- 只改备注保存 → 明细 id 不变、`out_number` 与价格金额保留；新增商品 → 只 insert 新行，旧行 id 不变；
  删除未出库行 → 仅删该行；`out_number>0` 行删除/减量 → 分别被两条守卫拦截；待审核申请占用时减量/删除 → 拦截。
- 把 `apply_info.sales_info_id` 临时指向不存在行后渲染送货单 → 商品名称/型号仍正常带出（修复前为空白）。

## 线上数据修正（2026-11-02，已执行，快照在 ~/erp-db-backup/fix2_*.sql）

销货单 `XHD20261102001` 被上述问题影响：`CKSQ20261102001/002/003` 三条申请的
`sales_info_id` 统一指向当前有效行 62，`out_data` 均改为 `{"62":1}`；明细行 62 恢复价格
（100/200/26/226）与 `purchase_order_id`，`out_number=2`；销货订单 `XHDD20261102001` 状态置 1（全部出库）。
⚠️ 该单实际已出库 3 件（goods_log id 93/94/95），订单计划只有 2 件。按用户选择（客户只要 2 件），
2026-11-03 已补一张**其他入库单** `QTRK20261103001`（warehousing id=3 / info id=3，
business_type=2 其他入库，A01-0001 ×1，仓库 5，单价/金额 0）冲回：库存 18→19，
`goods_log` id=100（business_type=9），2026-11 收发存快照该行 `in_qty` 同步 +1。
该单的 CKSQ20261102003 申请/送货单仍保留作审计痕迹（不再用于对客结算）。

---

# 销货对账详情：未付款按月合计改为「该客户所有欠款月份」（2026-11-03）

> 涉及文件：`application/admin/controller/kuerp/SalesRecon.php`（`pastUnpaid()` + `detail()` / `printpage()` / `export()`）。
> **无数据库迁移、无视图改动**（`sales_recon/detail.html`、`printpage.html`、导出模板的 `{$month}` 标签原本就是动态的）。

## 口径

对账单详情/打印/导出底部，按模板 `资料/财务功能/对账模板.xls` 输出若干行：

```
YYYY年M月已对账已开票（或者未开票）未付款合计：   金额
...
总未付款金额合计：                              金额
```

- 列出该客户**所有仍有未付款的对账单月份**（`recon_status='2'` 已对账），同一月份多张对账单合并为一行；
  月份**带年份**（`date('Y年n月')`），不再用旧的 `n月`（跨年会把 2025-10 与 2026-10 合并成「10月」）。
- **本单所属月份那一行包含当前对账单自身**（即使它尚未二审也计入），因此 **各行金额相加 = 总未付款金额合计**。
- 单行金额 = Σ(`total_amount + diff_money - pay_money`)，`pay_money` 由 `calcPay()`（模型 `recalcPay`）按所含销货单
  `pay_money` − 退货单 `retreat_money` 实时算出；`diff_money`（对账差异，正=客户多付）一并计入，与 `recalcPay` 的应收口径一致。
- 金额 ≤0 的月份不出行；客户全部结清时只有「总未付款金额合计 0.00」一行。
- 月份按 `period_end`（为空回退 `createtime`）归属，正序（`ksort` 于 `Y-m` 键）从早到晚。

## 已验证（本地 erp.licheng.test curl + 导出 xlsx 实测，测试数据已删净并还原自增）

- 临时插入客户 19 的 2025-09（300）、2025-10（500）两张已对账未付单后，`detail?ids=5`、`printpage?ids=5`、
  导出 xlsx 三处均输出 `2025年9月 300 / 2025年10月 500 / 2026年10月 800 / 总未付款 1600`，三行相加 = 合计 ✅。
- 删除测试行后：`ids=5`（本单已收清）只出 `2026年10月 800`（同客户另一张未付的 `KHDZ20261027001`），合计 800；
  `ids=6`（客户已结清）无月份行、合计 0.00。
- 测试单据（`ZZTEST-RC-*`）已删除，`AUTO_INCREMENT` 还原为 7；原 3 张对账单 `pay_money/pay_status` 未变。

---

# 组装单新增：仓库自动取商品默认仓库，不再手动选择（2026-11-03）

> 涉及文件：`application/admin/view/kuerp/assemble/add.html`、`public/assets/js/backend/kuerp/assemble.js`
> （**无数据库迁移、无控制器改动**；`edit.html` 保持原样，仓库仍可手动选择）

## 需求与行为

组装单**新增页**选择商品时不再需要手动选仓库：每个商品行自动取该商品档案的**默认仓库**
（`fa_kuerp_goods.manager_warehouse_id`）。组装单本身**没有供应商字段**，无需处理（商品/GYS 与组装业务无关）。

- 仓库列改为**只读文本**（`.stores_name`，灰底、placeholder「默认仓库」），实际提交值仍是隐藏的
  `<select name="row[stores_id][]" class="stores_id">`（放在 `.stores_select_wrap` + `data-select-enhance="skip"` + `display:none` 里，
  保持后端 `请选择仓库` 校验与 `.serializeArray()` 提交不受影响）。
- `assemble.js` 的 `changeGoods()` 在新增页（`$('#add-form').length>0`）把入参 `store` 强制为 `''`
  → `kuerp/goods_purchase/getUnit` 内部回落到 `manager_warehouse_id`，返回的可用库存就是默认仓库的库存；
  success 里再把 `.stores_id` 显式写成 `res.goods.manager_warehouse_id` 并刷新只读文本。
  这样「选商品 / 联想点击 / 扫码枪 / 选择商品弹窗 / 使用BOM模版 / 选择模板 / 排期生成组装单」全部统一走默认仓库。
- **商品未维护默认仓库时回退为原生下拉手动选择**（`.stores_select_wrap` 显示、只读文本隐藏），
  同时 `Toastr.warning('商品XXX未设置默认仓库，请选择仓库（或在商品档案中设置首选仓库）')`，避免该行无法保存。
- 编辑页不受影响：`edit.html` 仍是 5 个可选仓库下拉，`changeGoods` 的强制逻辑用 `#add-form` 判定，编辑页走原分支。

## 已验证（headless Chrome + CDP 实测 erp.licheng.test，未改业务数据）

- 组合件/子件行选 `C13-0001`（默认仓库=零件仓 6）→ `.stores_id=6`、只读列显示「零件仓」、下拉保持隐藏、可用库存按该仓库计算；
  `$('#add-form').serializeArray()` 的 `row[stores_id][]` 与 `row[goods_code][]` 逐行一一对应。
- 选无默认仓库的商品 `Z01-0004` → 只读列隐藏、下拉显示（7 个选项）、并弹出未设置默认仓库的提示；
  再切回有默认仓库的商品 → 下拉重新隐藏、只读列恢复。
- 表格列数仍对齐（组合件表头/行 18 列、子件 17 列）；编辑页 `assemble/edit/ids/3` 的 5 个仓库下拉仍正常显示且带原值。
- `node --check assemble.js` 通过；新增页 HTTP 200 无 Parse error。

---

# 销货预订单：商品无首选仓库按商品类型兜底 + 库存 0/负数可下单/出库（2026-11-03）

> 迁移：`addons/kuerp/migration_allow_negative_stock.sql`（已对 erp_licheng 执行，可重复执行）；
> `install.sql` 1.6.25 段；`info.ini` 1.6.25。
> 涉及文件：`addons/kuerp/library/Goods.php`（新增 `resolveStoreId()`）、
> `application/admin/controller/kuerp/GoodsSalesOrder.php`（add/edit 仓库解析）、
> `application/admin/controller/kuerp/GoodsSales.php`（add/edit 仓库解析）、
> `application/admin/controller/kuerp/GoodsSalesOutbound.php`（`saveOutbound()` 允许负库存）

## 需求

「添加销货预订单，商品仓库 0 或者负数也能正常下单，也就是对应仓库扣减为负数；当组装单、采购单、第三方加工单做好入库后，就补充上。」
用户口径（2026-11-03）：

- 商品**没有首选仓库**（`fa_kuerp_goods.manager_warehouse_id = 0`）时按**商品类型**默认仓库：
  **零件(part) → 零件仓、成品(finished) → 成品仓、半成品(semi_finished) → 半成品仓**；
  对应名称的启用仓库不存在时退化为启用仓库中 id 最小的一个。
- 库存为 **0 或负数**时不拦截下单；出库时允许把仓库库存扣成**负数**，采购单/组装单/外协加工单入库后自然补平。

## 一、仓库解析（`Goods::resolveStoreId($storesId, $goods)`）

`addons/kuerp/library/Goods.php` 新增静态方法，优先级：

```
表单/单据行的仓库(>0) → 商品首选仓库 manager_warehouse_id → 按 product_type 取同名仓库 → 启用仓库中 id 最小
```

- 销货预订单/销货单的仓库列是**隐藏的**（`.stores_id` select 在 `display:none` 里），用户无法手选，
  所以商品没维护首选仓库时此前直接报「商品XX库存不足」（`GoodsSalesOrder::add/edit`）或「请选择仓库」（`GoodsSales::add/edit`）。
- 现在两处都改为按类型兜底；一个启用仓库都没有才报「商品X未设置仓库，请先在仓库资料中启用仓库」。
- 只在 `stores_id` 为空时生效，已带仓库的单据行（含从销货订单生成的销货单）**行为不变**。
- `GoodsSalesOrder::resolveStoresId()` 是薄封装，规则集中在 library，避免 Controller 复制。

## 二、负库存（`GoodsSalesOutbound::saveOutbound()`）

- `fa_kuerp_goods_stock.stock` 原为 **`double(15,4) unsigned`** —— 写负数会被 MySQL 截断为 0，
  因此迁移把它改成**有符号** `double(15,4)`（注释同步）。`fa_kuerp_goods.inventory` 本来就是有符号，无需改。
- `saveOutbound()` 删除了三处拦截：
  1. `inventory < 0 → 商品X库存不足`；
  2. 该 商品+仓库+属性 **没有库存行** 时直接报「商品X仓库库存不足」→ 改为**新建一条负库存行**（`$newstock`，
     同单据同商品+仓库+属性合并累加，事务内 insert）；
  3. `editstock < 0 → X库存不足,操作失败`。
- 批次库存校验（`批次库存不足`）**保持不变**：批次功能关闭时走不到；开启批次后仍要求批次库存充足。
- 入库侧（采购入库/组装/外协）都是 `inc('stock', ...)`，负数+正数自然补平，无需改动。
- 出库申请（`apply()`）/出库管理审核（`GoodsOutboundApply::examine()`）本来就不校验库存，只有展示用的「可用库存」，
  因此放开 `saveOutbound()` 后整条链路即可出负数。

## 三、已验证（curl 登录 erp.licheng.test 实测，测试数据已按快照还原）

- 仓库兜底：`A01-0002`（成品，首选仓库 0）→ 存 `stores_id=5`（成品仓）；
  `Z01-0021`（半成品，0）→ `9`（半成品仓）；临时把 `R09-0002`（零件）首选仓库改 0 → `6`（零件仓），测后已还原。
- 负库存：把 `C13-0005/零件仓` 的库存与 `goods.inventory` 临时置 5，对已审核销货单
  `XHD20261029001` 出库 10 → 「出库成功」，`goods_stock.stock = -5`、`goods.inventory = -5`、
  `sales_info.out_number = 10`、`goods_log`(business_type=4) 正常、销货订单状态派生为 2（部分出库）。
- 无库存行分支：把该库存行临时挪到「待减仓」后再次出库 3 → 自动新建 `C13-0005/零件仓` 行，`stock = -3`。
- 测试后已删除临时库存行、还原 stock/inventory/out_number/订单状态/`goods_log` 与各表 `AUTO_INCREMENT`，
  并删除 `ZZTEST-*` 测试销货订单（含明细与操作日志）。
- 5 个相关页面（销货订单 add/edit、销货单 add、销货出库列表、销货单列表）HTTP 200 无报错；`php -l` 全通过。

# 销货对账开票金额扣减对账差异（2026-11-03）

> 涉及文件：`application/admin/controller/kuerp/SalesRecon.php`、`application/admin/view/kuerp/sales_recon/invoice.html`
> （**无数据库迁移**）。已部署线上（118.31.20.155）并按本节口径修正线上 `KHDZ20261102001` 的数据。

## 问题

「应收」口径是 `total_amount + diff_money`，但**开票基数只取 `total_amount`**，对账差异没参与：
线上对账单 `KHDZ20261102001` 总金额 10566 + 差异 -300 → 应收 10266，开票页却显示应开票 10566。

## 改动

- 新增 `SalesRecon::invoiceBase($recon)` = `total_amount + diff_money`（差异正=客户多付、负=客户少付），
  统一用于：开票弹窗「应开票/剩余可开」（`invoice()` GET+POST）、超额校验、`refreshInvoiceStatus()`、`detail()` 的「应开票」。
  `invoice.html` 的「应开票金额」改用控制器下发的 `$row['invoice_base']`。
- `syncInvoiceLedger()` 的单据可开票额由 `real_money` 改为 **`max(0, real_money + 该单 diff_money)`**
  （`sales_recon_doc.diff_money`）。不改这一处的话，扣掉差异后 FIFO 分摊会把后面几张销货单错标成「部分开票」。
  客户承担费用部分仍不落单据台账（既有口径）。
- 边界：开票基数 ≤ 0 → 「该对账单应开票金额为 0，无需开票」；剩余可开为负（历史超开）展示钳到 0。
- 时序前提（无需额外处理）：差异只能在 `recon_status != '2'` 时增删，而开票要求 `recon_status == '2'`，
  且「已开票的对账单不能撤销对账」→ 差异不会在开票后变动。

## 线上数据修正（2026-11-03 已执行，备份在 ~/erp-db-backup/20261103_recon_invoice_fix/）

`KHDZ20261102001` 当时已按旧基数登记发票 10566（发票号 123），改后基数 10266：

```sql
UPDATE fa_kuerp_sales_recon_invoice SET invoice_amount=10266 WHERE id=1;   -- 发票 10566 → 10266
UPDATE fa_kuerp_sales_recon SET invoice_amount=10266 WHERE id=1;           -- 头部已开票金额
UPDATE fa_kuerp_sales_invoicing SET pay_money=200, give_money=0, invoice_status='1'
  WHERE bill_code='XHD20260930004';                                        -- 该单差异 -300：500 → 200
```

台账复核：58 + 9550 + 200 + 200 + 220 = 10228，另客户承担费用 38 → 10266，各单均「已开票」。

## 注意

- 线上 `SalesRecon.php` 是**按最小补丁**上线的（只含本节改动），本地还有 2026-11-03 的
  「未付款按月合计 `pastUnpaid()` 改造」等改动**尚未上线**，下次整文件部署时才会一起带过去。
- 开票台账的 `should` 含单据差异后，`GoodsSales::index()` 的三态开票状态（台账 pay/give 派生）会自动跟着变。

---

# 采购预订单（goods_purchase_order）添加页：调用BOM模板生成明细 + 父商品数量联动（2026-11-03）

> 涉及文件：`application/admin/view/kuerp/goods_purchase_order/add.html`、
> `public/assets/js/backend/kuerp/goods_purchase_order.js`（**仅前端，无数据库/控制器改动**）

## 入口

采购预订单 **添加页**（`kuerp/goods_purchase_order/add`）明细表上方新增 **[选择BOM模板]** 按钮 +
`.bom_bar` 信息条：`BOM模板：<名称>　父商品：<编号 名称>　父商品数量：[输入框]　清除BOM明细`。
编辑页（`edit.html`）**没有**该按钮（避免重建已生成购货单/退货单的明细行），要复用请另行确认。

## 行为

1. 点 [选择BOM模板] → `parent.Fast.api.open("kuerp/goods_bom/chosebom")`（与组装单/其他出入库单同一弹窗）→
   选中后 `POST kuerp/goods_bom/getbomdetail` 取 `bom / parent / children`（children=`type='2'` 全部子件）。
2. **子商品即明细商品**：以 `#goods_table` 隐藏模板行（`rows[1]`，即 `.goods_id0`）克隆生成行，
   行上记 `data-bom-ratio` = BOM 子件单耗数量（`goods_bom_info.number`，1 个父件所需量）。
   仓库优先取子件 `manager_warehouse_id`，为空回退父商品的；单位取 BOM 明细 `unit_id`（0 → 商品默认单位）；
   每行调用既有 `changeGoods()` 带出型号/规格/供应商默认值/可用库存/价格。
3. **父商品数量联动**：`.bom_parent_number`（默认 1）change → 所有 `tr[data-bom-ratio]` 的
   `number = ratio × 父商品数量`，并 `changeGoodsInfo(row,'number')` 重算金额。
4. **手动改某行数量即解除联动**：`.number` 的 change 处理器里 `obj.removeAttr('data-bom-ratio')`
   （程序化 `.val()` 不触发 change，所以联动赋值不会误解除），之后改父商品数量不会覆盖该行。
5. **[清除BOM明细]**：只删 `tr[data-bom-ratio]` 行并隐藏信息条、父数量归 1。
6. 生成行前**先清理**：`#goods_table` 中 `rows[2..]` 里「空行（无 goods_code）」与「上次BOM生成的行」，
   **已手工录入的其它商品行原样保留**；行全部生成后 `changeWidth()` 统一列宽。

## 已验证（headless Chrome + CDP 实测 erp.licheng.test，测试单据已删除并还原自增）

- BOM 112「3.96线束」（单耗 1 / 12 / 2）：选模板 → 生成 3 行（H01-0003×1、J05-0017×12、J08-0001×2），
  仓库 6（零件仓）、单位带入、父商品显示 `Z07-0001 3.96线束`、信息条正常显示。
- 父商品数量改 3 → 3 / 36 / 6；手动把首行改 7 后再把父数量改 5 → 首行保持 7，其余变 60 / 10（联动已解除）。
- 真实提交表单：`goods_purchase_order/examine_status=0` 新单 GHDD20261103001 落库，
  明细 `number = 2 / 24 / 4`、`stores_id=6`（父子数量倍率正确）；测试单与明细已删除，
  `fa_kuerp_goods_purchase_order(_info)` 行数与 `AUTO_INCREMENT` 已还原。
- `.add` / `.del` / 扫码枪 / 行内联想等既有路径未改；页面无 JS 异常，`node --check` 通过。

## 未做

- 采购预订单(计划) `goods_purchase_order_plan`、采购预订单(仓库) `goods_purchase_order_plan_warehouse`
  是**独立的视图/JS 副本**，本次未加（如需同功能，按本节的 3 处改动照搬过去即可）。
- 编辑页不支持调用BOM。

# 2026-11-03 线上部署记录（最近改动 + 结构同步）

> 目标：线上 `118.31.20.155`（`dyerp_shengbokj_`，后台 `/eXvpGbzZYD.php`）。

## 一、代码（45 个文件，逐个 SIZE 校验 + 关键文件 MD5 复核）

按「本地 mtime 晚于线上」筛选后逐一比对内容，上传的是本地较新版本：

- 控制器：`GoodsBom` / `GoodsPurchase` / `GoodsPurchaseStorage` / `GoodsSales` / `GoodsSalesOrder` /
  `GoodsSalesOutbound`（允许负库存出库）/ `PurchaseRecon` / `SalesRecon`（含 11-03 未付款按月合计改造）
- `addons/kuerp/library/Goods.php`、`info.ini`(1.6.25)、`install.sql`(1.6.25 段)、新增 `migration_allow_negative_stock.sql`
- 视图 22 个（组装单默认仓库、出库/入库/对账详情、采购订单导入按钮、销货单/退货单、BOM、入库三规格列等）
- JS 7 个（assemble / goods_bom / goods_exchange_info / goods_purchase_order / goods_purchase_storage_layer /
  goods_sales_exchange_info / purchase_recon）

**刻意未部署（本地与线上不同，且线上是对的）**：

| 文件 | 原因 |
|------|------|
| `application/extra/addons.php` | 线上注册了 `cms` 插件（钩子/路由），本地没有；覆盖会禁用 CMS |
| `application/extra/site.php` | 线上站点名「大雁进销存」、备案号、`version 1.0.3`，本地是占位值 |
| `public/assets/js/backend/kuerp/goods_log.js` | **线上版本更新**（2026-11-03 01:32 上传），收发存多了 `enter_basics`/`out_basics`「基本单位数量」两列（colspan 4）；本地没有这两列，覆盖会退回旧功能，需业务确认后再定 |

## 二、数据库结构（唯一差异，已执行）

`fa_kuerp_goods_stock.stock`：`double(15,4) unsigned` → **`double(15,4)`（允许负数）**，
与 `migration_allow_negative_stock.sql` 一致。执行前整表备份：
`~/erp-db-backup/20261103_deploy/fa_kuerp_goods_stock_before.sql`。
执行后 `information_schema.COLUMNS/STATISTICS/TABLES` 全库比对：本地与线上**零差异**（表 182 / 列 2845 / 索引全同）。

## 三、部署后

- 递归清空 `runtime/cache` 与 `runtime/temp`（含 hash 子目录），模板/菜单缓存重建。
- 冒烟：登录页、销货对账、销货出库、采购订单 add、组装单 add、收发存、采购对账均 200（未登录为「温馨提示」页）。
- 访问端需**强刷**（Cmd/Ctrl+Shift+R）：`site.version` 仍是 1.0.3，nginx 对 `/assets/` 有 12h 缓存。

---

# 成品入库成本计算：组装单 / 外协加工单（2026-11-04）

> 涉及文件：`addons/kuerp/library/Goods.php`（新增 `getCostPrice()`）、
> `application/admin/controller/kuerp/Assemble.php`（`batchLineCost()` / `applyAssembleCost()` / `ensureAssembleCost()` + 成本写入）、
> `Process.php`（`applyProcessMaterialCost()` / `ensureProcessMaterialCost()` + 入库成本）、
> `application/admin/view/kuerp/process/outbound_edit.html`（入库成本默认留空）、
> `GoodsStock.php`（组装成本汇总修正）。**无数据库迁移。**

## 取价口径 `Goods::getCostPrice($goods_code, $stores_id, $attr)`

按基本单位返回成本单价（4 位小数），优先级：

1. **最近一次采购入库成本**：`kuerp_goods_log` 中 `business_type='2'` 最近一条的 `enter_cost / enter_basics`（优先同仓库，其次任意仓库）
2. **期初库存成本**：`kuerp_goods_period` 的 `price / stock`（优先同仓库）
3. **库存移动平均**：`kuerp_goods_stock` 的 `SUM(cost_money) / SUM(stock)`（需 `stock>0` 且 `cost_money>0`）
4. **商品档案采购价**：`kuerp_goods.purchase`（辅助属性商品优先 `kuerp_goods_attr.purchase`）
5. 都取不到返回 **0**

⚠️ 当前库成本基础数据很少（`goods.purchase` 1132 个商品全为 0、`goods_price` 全为 0、
有库存的 679 行里只有 11 行 `cost_money>0`、采购入库日志只有 10 条有成本），
所以现阶段大量商品算出来仍是 0，需要先维护采购价/采购入库成本。

## 组装单（kuerp/assemble）

- **保存时**（add/edit 重建明细后调 `applyAssembleCost()`）：
  - 子件行 `cost_money = 成本单价 × basic_number`
  - 组合件行 `cost_money = Σ子件成本`（多组合件按 `basic_number` 比例分摊、末行吃尾差），`unit_cost = cost_money / basic_number`
  - **组装费用（`row[costs]` / 主表 `money`）不计入成本**（页面入口本来就是注释状态，本次未恢复）
- **分批出/入库**（`saveOutbound()`，用 `batchLineCost()`）：
  - 本批成本 = 整行 `cost_money × 本批基本数量 / 整行基本数量`
  - 最后一批 = 整行成本 − 已结转成本（已结转金额取 `goods_log` 里该 `purchase_id` 的 `out_cost/enter_cost` 合计，避免四舍五入尾差）
- **库存与台账**：`goods_stock.cost_money` 子件出库减、组合件入库加；`goods_log` 的 `business_type=13` 写 `out_unit/out_cost`、
  `business_type=12` 写 `enter_unit/enter_cost`（这些字段此前被注释，本次恢复）。
- **历史单据**：`examine()` / `examineInbound()` / `saveOutbound()` 开头调 `ensureAssembleCost()` 自动补算；
  组合件行 `cost_money>0` 时直接跳过，避免重复审核导致成本漂移。

## 外协加工单（kuerp/process）

- **原材料出库**：`process_info.unit_cost` 未填（≤0）时按 `getCostPrice()` 补算，`out_cost = 单价 × basic_number`。
- **成品入库**（`saveOutbound()`）：
  - 原材料成本按计划入库数量分摊：`本行分摊 = Σ原材料成本 × 本行计划数量 / 全部成品计划数量`，最后一批吃尾差
  - 加工费按本批入库数量占本次提交总数量的比例分摊
  - `本批成本 = 分摊原材料成本 + 本批加工费`；`unit_cost = 本批成本 / 本批 basic_number`
  - **入库成本输入框默认留空**（`outbound_edit.html`），留空走自动计算；手工填 >0 时以「手填单价 × basic_number」为准
  - `process_info.cost_money` 累计改用 `IFNULL(cost_money,0)+x`（原来 `NULL + x = NULL`，累计永远为空）
- **加工费仍单独记账**（`kuerp_process_fee` + 账户流水 + `kuerp_cost`），**未从费用表移除**。
  若担心与存货成本重复反映，需要另行确认是否停记 `kuerp_cost`。

## 商品库存余额表（GoodsStock）

`getGoodsUnitPrice()` 的组装成本汇总原为 `pi.goods_code = 商品ID`（永不命中）且未过滤子件行，
已改为按 `pi.goods_id` 汇总并只取 `pi.type='1'`（子件成本已合并进组合件成本），`buildCostPreload()` 同步修改。

## 已验证（curl 登录 erp.licheng.test，测试数据已按快照还原、自增已回滚）

- 组装单：子件 C13-0001(20)×4 + C13-0002(30)×2 → 组合件成本 140、单位成本 70；
  两批出库各 40/30、入库各 70，`goods_stock.cost_money` 子件 -140 / 成品 +140 守恒；`goods_log` 12/13 成本正确。
- 尾差：113 元分 3 批入库 → 37.6667 / 37.6667 / 37.6666，合计 113.0000。
- 外协单：原材料 C13-0001 成本 40 + 两批加工费 60 → 成品 2 件，每件成本 50、`cost_money=100`（NULL 累计已修复）；
  `goods_log` 16/17 成本、库存成本同步正确。

---

# 出库管理审核出库时校验库存（2026-11-04）

> 涉及文件：仅 `application/admin/controller/kuerp/GoodsSalesOutbound.php`（新增 `checkOutboundStock()` + `saveOutbound()` 第 3 参 `$checkStock`）。
> **无数据库迁移、无视图/JS 改动。**

## 规则

- **下单不校验库存**：销货预订单(`GoodsSalesOrder::add/edit`)、销货单(`GoodsSales::add/edit`) 库存为 0/负数照常保存
  （仓库按 `Goods::resolveStoreId()` 兜底，见上文 2026-11-03 一节）。
- **实际出库必须校验库存**：仓库在「出库管理」审核出库（`GoodsOutboundApply::examine` → `runSaveOutbound` →
  `GoodsSalesOutbound::saveOutbound`）时，按 `商品+仓库+属性` 汇总本次出库的**基本单位数量**，
  与**可用库存**（`Goods::getAvailableStockBatch($items, '')` = 物理库存 − 所有生效锁定，与出库管理/申请弹窗
  「可用库存」列同口径）比较；不足则整单拒绝、事务回滚（申请仍为待审核，可改小数量重审）。
- 提示格式：`销货出库执行失败：库存不足，不能出库：商品名(编号)【仓库】可用库存 X，本次需出库 Y；…`（多商品用；拼接）。
- 误差容差 `0.00005`；同一 商品+仓库+属性 多行合并汇总后比较（避免同一份库存被多行重复计入）。

## 实现要点

- `saveOutbound($ids = null, $outData = null, $checkStock = true)`：默认校验，供未来需要时显式传 `false` 绕过。
  该方法的全部外部入口（出库管理审核、旧的 `outbound/saveOutbound` 直连）都走校验。
- 校验在**进入扣减循环之前**完成；后续扣减循环仍保留「允许负数 / 无库存行新建」的容错代码，
  但校验通过后（`物理库存 ≥ 可用库存 ≥ 需求`）实际不会再产生负数。
- 本库 `fa_kuerp_goods_stock.stock` 已于 2026-11-03 改为有符号 `double(15,4)`（`migration_allow_negative_stock.sql`），
  本次未回退该列类型（无副作用）。

## 已验证（curl 登录 erp.licheng.test 实测，测试数据已按快照还原）

- 不足：临时把 `C13-0004/零件仓` 库存改为 5，审核出库申请 `CKSQ20261030001`（需 C13-0004×20、C13-0005×10）
  → 返回 `库存不足，不能出库：CBB运行电容(C13-0004)【零件仓】可用库存 5，本次需出库 20`；
  申请仍 `status=0`、`out_number=0`、库存/收发/日志零变动（事务回滚）。
- 充足：恢复库存后同一条申请按 1/1 出库 → 成功，库存 1398→1397 / 6000→5999、`out_number=1`、
  `goods_log`(business_type=4) 两条、销货订单状态派生为 2（部分出库）。测试后已逐行还原（含日志、`AUTO_INCREMENT` 边缘行）。
- `php -l` 通过。

---

# 计划排期审核未生成核销源单 → 销货对账「去收款」无单源（2026-11-04）

> 涉及文件：`application/admin/controller/kuerp/GoodsSalesSchedule.php`（`examine()`）、
> `application/admin/controller/kuerp/Collection.php`（新增 `ensureReconSources()`）。**无数据库迁移。**

## 现象与根因

销货对账点【去收款】进入 `kuerp/collection/add?recon_id=X`，`Collection::buildReconSources()`
从 `fa_kuerp_writeoff_source`（`purchase_type='3'`）取该对账单所含单据的源单预填。若单据没有源单行，
页面就是空的（JS 提示「该对账单所含销货单已全部核销，无需再次收款」）。

源单本应在**销货单审核**时写入（`GoodsSales::examine()` / `GoodsSalesOutbound::examine()`）。
但计划排期列表的【审核】走的是 `GoodsSalesSchedule::examine()`（排期与销货单共用 `fa_kuerp_goods_sales` 表），
该方法此前**只改 `examine_status` + 客户 `need_receive_account`**，既不写 `kuerp_writeoff_source`、
也不写 `kuerp_accounts_receivable`、也不写审核日志 → 从排期审核的单据在收款时拉不出单源。

判定方法：`fa_kuerp_goods_sales_log` 里该单据只有「创建」没有「审核」，且 `writeoff_source` 无对应行。

## 改动

1. `GoodsSalesSchedule::examine()` 审核分支：按 `GoodsSales::examine()` 同口径补建
   - `kuerp_writeoff_source`：`purchase_type=3, type=1, price=real_money+customers_money, money=pay_money, write_off=arrears`（仅 `arrears>0`）
   - `kuerp_accounts_receivable`：`business_type='6'`，每张单据都写
   写入前按单号删一遍同类型旧行，避免重复审核产生重复源单。
2. 反审核分支：
   - 删除上面的源单与应收明细；
   - **冲回客户 `need_receive_account`**（此前漏做，反复审核/反审核会让客户欠款重复累加）；
   - 新增守卫：`recon_status != 0` 时禁止反审核（与 `GoodsSales::examine()` 一致）。
3. `Collection::ensureReconSources()`（`buildReconSources()` 开头调用）：对账单所含单据缺源单时按单据明细补建，
   只补缺、不改已有源单，金额口径同 `SalesRecon::writebackDiff()`：
   销货单 `price = real_money + customers_money + diff_money`、`pay = pay_money`；
   退货单 `price = -(real_money + customers_money) + diff_money`、`pay = -retreat_money`。

## 历史数据（本库已修复，备份 `~/erp-db-backup/20261104_recon_source_fix/before.sql`）

补建源单 + 应收明细的 7 张单据：`XHD20261103003`(56.5)、`XHD20261103002`(380)、`XHD20260930004`(500)、
`XHD20260930003`(2960)、`XHD20260930002`(5550)、`XHD20260930001`(9550)、`XHD20260914001`(66)。
补建前确认这些单据在 `writeoff_info` / `collection_sale` 里均无核销/收款记录，`write_off` 按全额写入。

## 已验证（curl 登录 erp.licheng.test 实测，测试单据已删净）

- `collection/add?recon_id=7` 页面 `Config.presetSources` 出现 `XHD20261103003 / 56.5`（修复前为空）。
- 造测试单，经 `goods_sales_schedule/examine` 审核 → 源单 110(100+客户承担10)、应收明细、客户欠款 +110 均正确；
  反审核 → 三者全部还原、客户欠款回到原值。
- 对已对账单据（`recon_status!=0`）调排期反审核 → 返回「单据XHD20261103003已进入对账流程，请先撤销对账后再反审核」。
- `php -l` 通过。

---

# 付款申请详情「查看对账单明细」按钮（2026-11-04）

> 涉及文件：`application/admin/view/kuerp/purchase_payment_apply/detail.html`、
> `public/assets/js/backend/kuerp/purchase_payment_apply.js`（`Controller.detail`）、
> `addons/kuerp/migration_add_payment_apply_recon_detail_auth.sql`（已对 erp_licheng 执行，可重复执行）

## 功能

付款申请详情页「关联对账单」表格最右新增 **操作** 列，每行一个 **[查看明细]** 按钮
（`class="btn-recon-detail" data-recon-id`），点击 `Fast.api.open('kuerp/purchase_recon/detail/ids/'+id, '采购对账单详情', {area:['82%','86%']})`
打开该对账单详情（含「对账单明细」商品行、对账资料、发票账期、付款情况）。
材料行 `tr.mat-row` 的 `colspan` 同步由 4 改为 5。

## 权限（关键口径）

按钮用 **采购对账 → 详情（`kuerp/purchase_recon/detail`，规则 id 729）** 控制，
**不新建**付款申请下的规则：`PurchaseRecon::detail` 页面本身由 `Backend` 按该节点鉴权，
另建规则会造成「看得到按钮但打开页面 403」。复用后按钮可见性与页面访问权完全一致。
无权限时该格显示灰色 `--`。

`migration_add_payment_apply_recon_detail_auth.sql` 做两件事（均可重复执行）：
① 防御式确保规则 729 存在（`pid` 子查询解析采购对账菜单）；
② 把 729 **增量追加**给已有「付款申请」菜单或「付款申请 → 详情」权限的角色（只加不删，不动其它权限）。
其它角色需在 角色管理 → 采购对账 勾选「详情」。

## 已验证（curl + headless Chrome/CDP 实测 erp.licheng.test，测试用临时账号/角色已删除并还原自增）

- 超管：详情页该列渲染 [查看明细]，点击弹出「采购对账单详情」层（`purchase_recon/detail/ids/2?dialog=1`），
  内含「对账单明细」2 行商品 + 对账单据明细 + 资料/发票/付款面板，无 JS 异常。
- 临时角色仅给 付款申请(733/index734/detail738)：按钮不渲染（显示 `--`），直接访问 `purchase_recon/detail` 返回「你没有权限」。
- 临时角色追加 729 后重新登录：按钮渲染、对账单详情正常打开（非超管，未开 `$noNeedRight`）。
- `node --check purchase_payment_apply.js` 通过；临时 `fa_admin`/`fa_auth_group`/`fa_auth_group_access` 行已删除，
  `AUTO_INCREMENT` 已还原（admin 24、auth_group 27）。

---

# 2026-11-04 线上部署记录（1.6.26）

> 版本标记：`addons/kuerp/info.ini` version = 1.6.26；线上根目录 `DEPLOY_VERSION.txt`（版本 + 已部署文件清单）
> 部署/更新流程与固定不部署清单见仓库根目录 **`DEPLOY.md`**。

- **代码**：33 个文件（FTP 逐个 md5 校验通过），较 11-03 部署新增：
  成品入库成本计算（`addons/kuerp/library/Goods.php`、`Assemble.php`、`Process.php`、`process/outbound_edit.html`、`GoodsStock.php`）、
  出库管理审核出库校验库存（`GoodsSalesOutbound.php`）、计划排期审核补建核销源单（`GoodsSalesSchedule.php`、`Collection.php`）、
  付款申请详情 [查看明细]（`purchase_payment_apply/detail.html` + js + 迁移）、
  采购单列表按采购订单分组排序（`GoodsPurchase.php`/`GoodsPurchasePlan.php`/`GoodsPurchaseStorage.php` + `model/kuerp/GoodsPurchase::orderSqlByPurchaseOrder()`）、
  商品「预计采购价」改名「预估成本」（goods add/edit + lang + 导入表头别名）、
  组装单列表生产日期/生成日期/预计生产完成日期列与排序（`Assemble.php` + `assemble/index.html` + js）、
  组装单打印「待入库/待出库数量」、入库单详情/打印三规格列、销货对账详情收款单金额口径、采购预订单调用BOM（计划版视图/JS）。
- **数据库**：结构与线上**逐表逐列一致**（`mysqldump --no-data` 去 AUTO_INCREMENT 后 diff 为空），本次无 DDL；
  执行 `migration_add_payment_apply_recon_detail_auth.sql`（幂等，线上无角色拥有付款申请权限 → 无实际变更）；
  `fa_auth_rule` 702 标题同步为「一键生成采购预订单(计划)」。
- **未部署（有意保留线上版本）**：`application/extra/addons.php`、`application/extra/site.php`、
  `public/assets/js/backend/kuerp/goods_log.js`（线上版本多「基本单位数量」两列）、`application/config.php`。
- 部署后已清 `runtime/cache`、`runtime/temp`；登录页 / kuerp 列表页均 200。
- ⚠️ 本次部署曾误把新文件（远端不存在）当成「目录不可写」走了目录重建兜底，`/addons/kuerp` 被改名重建、
  子目录 `controller/library/model/uniapp` 一度消失；已通过还原 `kuerp__deploybak_*` 目录并补传 `library/Goods.php` 恢复。
  脚本判分支前**必须先确认远端文件是否存在**（详见 `DEPLOY.md` 第二节第 3 步）。

---

# 采购单打印按客户模板改版（2026-11-04）

> 模板：`资料/财务功能/采购单打印模板.xlsx`
> 迁移：`addons/kuerp/migration_add_purchase_print_terms.sql`（已对 erp_licheng 执行，可重复执行）；
> `install.sql` 1.6.27 段 + `info.ini` 1.6.27。
> 涉及文件：`application/admin/controller/kuerp/GoodsPurchase.php`（`printpage()`）、
> `application/admin/view/kuerp/goods_purchase/{printpage,index,edit}.html`、`public/assets/js/backend/kuerp/goods_purchase.js`（整页重写）。
> 入口：采购单**列表行操作栏**的 [打印]（`lay-event="print"` → `Fast.api.open("kuerp/goods_purchase/printpage?ids=X", "打印", {area:['90%','85%']})`）。
> 2026-11-04 起**编辑页**顶部那个 [打印] 链接已移除（按钮移到列表，避免每张单必须先打开编辑页）。

## 一、版式（与 Excel 模板逐列对齐）

- **A4 竖版**（2026-10-30 那次改的横向 `@page{size:A4 landscape}` 已移除，模板本就是竖版宽度）。
- 整页一张 `<table>`，`<colgroup>` 11 列按模板列宽换算成百分比（5/13/8/6.5/6.5/9/10.5/9/9/11/12.5）。
- 标题行 + 供方/需方两行 + 列名行放在 **`<thead>`** 里：多页时每页自动重复表头（依赖公共
  `common/printstyle.html` 的 `thead{display:table-header-group}`，**不要**再给 `<tr>` 写 `display:table-header-group`，对 tr 无效）。
- 列：`序号 | 产品名称 | 规格(跨2列) | 封装 | 数量（个） | 含税价（元） | 税率（%） | 金额 | 交货期 | 备注`。
- 表头信息：
  - 供方 = 供应商名称；联系方式 = 供应商首要联系人手机（`supplierPhone()`，回退座机）；下单日期 = `purchase_time`。
  - 需方 = 设置 `company_name`；联系方式 = `company_phone`；采购编号 = `purchase_code`。
  - 标题 = **公司名称 + 「采购订货单」**（`purchase_type != 1` 时为「采购退货单」）。
  - 三格分列比例 `A:D | E:H | I:K`（`采购编号：GHD…` 17 位单号需要 3 列宽，早先 2 列会折行）。
- 合计行：`合计数量：`（A–E 合并）+ 数量列数值；`合计金额：`（含税价列）+ 空税率列 + 金额列数值。
- 明细之后依次：单据备注（`note` 非空时）、**订货说明 1–5 条**、签字栏（需方代表 / 供方代表 / 签定日期）。
- 日期一律输出 `Y.m.d`（模板口径）；价格/数量按设置项 `price_decimal` / `number_decimal` 格式化。

## 二、字段口径

| 列 | 取值 |
|----|------|
| 产品名称 | `kuerp_goods.goods_name`（孤儿明细留空） |
| 规格 | `goods_specification_id`(型号)；`vendor_name`(规格2/厂商) 非空时拼成 `型号 / 厂商`（模板只有「规格」「封装」两列，原三列合并） |
| 封装 | `packaging`(规格3) |
| 数量（个） | `kuerp_goods_purchase_info.number` |
| 含税价（元） | `tax_included`，为空回退 `unit_price`（未启用税金时两者相同） |
| 税率（%） | `tax_rate` |
| 金额 | `money_tax_price`（价税合计金额）；为 0 而 `all_money` 非 0 时回退 `all_money` |
| 交货期 | 明细行 `delivery_time`，为空回退单据头 `delivery_time` |
| 备注 | 明细行 `remark` |

⚠️ 模板没有「单位」列 —— 页面上不再输出单位名称，表头固定写「数量（个）」。若日后要按单位显示，需改表头或把单位拼进数量格。

## 三、可配置项（模板里标注"可更改"的两条）

`fa_kuerp_sets` 新增两个 type=1 设置项，在 **设置 → 基础设置** 里可改（`Sets::index` 对 type=1 自动渲染成文本框）：

| name | 昵称 | 打印页位置 |
|------|------|-----------|
| `purchase_print_receipt` | 采购单打印收货信息 | 订货说明第 2 条「收货地址：」 |
| `purchase_print_payment` | 采购单打印付款方式 | 订货说明第 3 条「付款方式：」 |

- 取不到/为空时自动兜底（**不需要**先执行迁移也能正常打印）：
  收货信息 = `仓库  {company_phone}   {company_address}（走货梯）`；付款方式 = 内置默认文案。
- 订货说明第 1/4/5 条（交货方式、质量保证、合同声明）是固定文案，写死在视图里。

## 四、其它

- **列表 [打印] 按钮**：`goods_purchase/index.html` 新增行操作模板 `#barDemoPurchase`（编辑 / 打印 / 删除），
  `goods_purchase.js` 的操作列 `toolbar` 由全局 `#barDemo` 改为 `#barDemoPurchase`、`width 150 → 180`；
  `table.on('tool(table)')` 新增 `print` 分支打开打印弹层。
  - ⚠️ 模板 ID **不能叫 `barDemo`**：`kuerp/common/menu.html` 自带同名默认模板（只有编辑/删除），
    layui 取第一个匹配 → 自定义按钮被顶掉（症状：点了没反应）。
  - 打印按钮**不加 `$auth->check`**：`printpage` 在 `$noNeedRight`、`fa_auth_rule` 里没有对应规则，加了会让除超管外的角色看不到按钮
    （与 `goods_purchase_plan_warehouse` 的 [打印] 同做法）。
  - 按钮用 `lay-event="print"`，**不要**加 `class="print"`——`goods_purchase.js` 里已有 `$(document).on("click",".print")` 的 LODOP 打印处理器，会重复触发。
- 打印次数照旧 `setInc('print_num')`，但页面上**不再显示**「打印时间 / 打印次数」。
- `goods_purchase_plan` / `goods_purchase_plan_warehouse` 的 printpage 是独立视图，**未改动**（仍是不含价格的旧版式）。
- 已验证（curl + headless Chrome/CDP 实测 erp.licheng.test）：无税单（GHD20261020004）、含税单（GHD20261103006，13%）、
  32 行长单选打印 → A4 竖版 595×842pt、第 2 页重复表头、合计/备注/条款/签字栏位置正确；
  设置项在基础设置页可见可改，清空后兜底文案生效；临时测试单已删除、`AUTO_INCREMENT` 已还原。

---

# BOM 选择器统一为「树形：模板 + 可折叠子件」（2026-11-04）

> 涉及文件：`public/assets/js/backend/kuerp/goods_bom.js`（`index` / `chosebom`）、
> `application/admin/view/kuerp/goods_bom/chosebom.html`、
> `public/assets/js/backend/kuerp/goods_calculator.js` + `application/admin/view/kuerp/goods_calculator/index.html` +
> `application/admin/controller/kuerp/GoodsCalculator.php`、
> `application/admin/view/kuerp/goods_purchase_order_plan_warehouse/add.html` +
> `public/assets/js/backend/kuerp/goods_purchase_order_plan_warehouse.js`、`application/admin/controller/kuerp/GoodsBom.php`（仅注释）。
> **无数据库迁移。**

## 一、选择弹窗改为树形（与 BOM 列表页一致）

- 入口不变（`kuerp/goods_bom/chosebom`），但表格数据改为 `kuerp/goods_bom/index?tree=1`，
  与 BOM 列表页同一份「父商品行(row_type=1) + 子商品行(row_type=2)」数据。
- 默认**只显示模板名 + 父商品**（一行一个模板，含「N 个子件」徽标），点子件箭头 / 模板名展开子件行（再点折叠）；
  子件行缩进展示，带单耗数量、备注。
- 选择方式：**父商品行上的单选按钮**（子件行无单选）；点行任意位置也能选中（点中某条子件行 = 选中它所属的模板）。
  点子件箭头/模板名只展开不选中。
- 确定 → `Fast.api.close([{id: 模板id}])`，与旧实现回传结构完全一致——6 个调用方
  （组装单 `assemble.js`、外协加工 `process_order.js`、其他出库 `warehouse.js`、其他入库 `warehousing.js`、
  采购预订单 `goods_purchase_order.js` / `goods_purchase_order_plan.js` / `goods_purchase_order_plan_warehouse.js`）
  均只取 `goodsData[0].id`，**零改动**。
- 树形渲染/折叠/行高同步的逻辑提取为模块级函数（`bomEscHtml` / `bomTreeTemplet` / `bomRenderTree` /
  `bomToggleGroup` / `bomSyncRowHeight` / `bomBindTreeToggle`），`index` 与 `chosebom` 共用，避免两份复制代码。
- 搜索（模板名称）跨页保留已选模板；重新渲染后自动恢复单选状态。

## 二、全后台 BOM 选择入口统一（已核对）

grep 全量核对后，后台**只有两处** BOM 选择界面，现已全部走同一个 `chosebom`：

| 位置 | 原实现 | 现实现 |
|------|--------|--------|
| 组装单 / 外协加工 / 其他出库 / 其他入库 / 采购预订单(含计划、仓库) | `chosebom`（旧的平铺+tableMerge 合并单元格） | `chosebom`（树形） |
| **商品计算器** `kuerp/goods_calculator` | 页面自带 `<select id="bom-select">` 下拉（控制器下发 `bomList`） | ![选择BOM模板] 按钮 → `chosebom`，只读输入框显示「模板名 (父商品编号)」，隐藏域 `#bom-id` 提交给 `calculate` |

- `GoodsCalculator::index()` 不再查询/下发 `bomList`（`getbomlist` 接口保留未删，当前无人调用）。
- 采购预订单(仓库) `goods_purchase_order_plan_warehouse` 此前**没有**「选择BOM模板」按钮（复制自 plan 时该功能尚未加入），
  本次按 plan 的实现补齐：add 页 `[选择BOM模板]` + `.bom_bar`（父商品数量联动 / 清除BOM明细），
  JS 逻辑与 `goods_purchase_order_plan.js` 逐字一致，并在 `.number` 的 change 里补 `obj.removeAttr('data-bom-ratio')`（手改即解除联动）。
- ⚠️ `goods_bom/index` 的 `tree` 参数仍按请求参数决定（不传 = 旧平铺结构），所以没有其它遗留调用受影响。

## 三、已验证（curl + headless Chrome/CDP 实测 erp.licheng.test，未改动业务数据）

- 选择弹窗：10 个模板默认 10 行（子件隐藏）；点箭头展开 → 12 行；点子件行 → 选中其父模板（radio=父模板id）；
  点另一个父行 → 选中切换；`确定` 关闭并回传 id；无 JS 异常。
- BOM 列表页回归：32 行（10 父 + 22 子）、默认 10 行可见、展开正常、操作列/行高正常。
- 采购预订单 add：点 [选择BOM模板] → 弹层 → 选「DY2403(HS-7)」→ 确定 → 生成 2 行（C13-0010 / C13-0009，数量 1/1），
  `.bom_bar` 显示模板名与父商品。
- 采购预订单(仓库) add：同上生成 2 行；父商品数量 1→3 → 子件数量 3/3；手改首行为 7 后联动解除，父数量改 5 → 7/5。
- 商品计算器：无 `#bom-select`、有 [选择BOM模板]；选择后自动计算，`#bom-id=113`、显示「DY2403(HS-7) (A01-0003)」、
  结果区「最大可组装数 417」正常。
- `node --check`（4 个 JS）/ `php -l`（2 个控制器）通过。

## 子件行规格三列修正（2026-11-04）

- **问题**：BOM 列表/选择弹窗展开子件后，型号、规格2/厂商、规格3/封装三列为空。
- **原因**：`GoodsBom::index()` 的子商品查询只取 `a.number,a.location,a.remark,g.goods_name,g.goods_code,g.product_type`，
  没有这三列；前端 templet 也写死 `d.row_type == '1' ? ... : ''`。
- **修复**：子商品查询补 `g.goods_specification_id,g.vendor_name,g.packaging` 并写入每条子件行（`$base_data` 先给三列空串兜底），
  前端 `index` 与 `chosebom` 的 templet 改为直接输出该行的三列值（父行=父商品、子件行=子商品）。
- 已验证：BOM 63（HS2000-MB-V1.6物料清单）子件 `R02-0001` 显示 型号「贴片电阻5.1Ω,5%,1/10W」/ 封装「0603」；
  `L02-0001` 显示 型号「贴片磁珠GZ1608D601TF」/ 规格2「顺络」/ 规格3「0603」；列表页与选择弹窗一致，无 JS 异常。

---

# 销货出库 / 出库管理 搜索栏「出库状态」筛选（2026-11-04）

> 涉及文件：`addons/kuerp/library/Goods.php`（新增 `outboundStatusCodes()`）、
> `application/admin/controller/kuerp/{GoodsSalesOutbound,GoodsOutboundApply}.php`（`index()`）、
> `application/admin/view/kuerp/{goods_sales_outbound,goods_outbound_apply}/index.html`、
> `public/assets/js/backend/kuerp/{goods_sales_outbound,goods_outbound_apply}.js`、两个 lang。**无数据库迁移。**

## 口径

`outbound_status` 是**派生值**（按 `kuerp_goods_sales_info` 的 `purchase_code` 汇总 `SUM(number)/SUM(out_number)`），
不是数据库列，所以**不能进 `filter`**（标准搜索栏按列过滤会查不到），走独立查询参数 `outbound_status`：

| 值 | 含义 | 判定 |
|---|---|---|
| 0 | 未出库 | 已出库合计 <= 0（含无明细的单据，用 `NOT IN 已出库单号` 实现） |
| 1 | 部分出库 | 0 < 已出库合计 < 计划数量 |
| 2 | 全部出库 | 已出库合计 >= 计划数量 |

## 实现

- `Goods::outboundStatusCodes($status)` 返回 `['mode' => 'in'|'not in', 'codes' => [...]]`，控制器里直接
  `$query->where('purchase_code', $mode, $codes)`；状态为空/非法时返回 `null`（不筛选、不查库）。
  ⚠️ 无匹配时用哨兵单号 `__no_outbound_match__` 占位，避免 TP5 生成 `IN ()` 语法错误。
- 两处 `index()` 都改成分步建 Query（`$query = ...; if ($filter) $query->where(...); $list = $query->paginate()`）。
- 出库管理（`goods_outbound_apply`）的 `purchase_code` 就是销货单号，故同一份单号集合可直接复用；
  即"该申请所属销货单的出库进度"。
- 前端：`table.reload` 的 `where` 里除 `filter/op` 外单独带 `outbound_status`（同 `sales_recon` 的 `term_state` 写法），
  `getSearch()/getOp()` 保持不动（它是按 id 显式取值的，不会把该字段带进 filter）。

## 已验证（curl + headless Chrome/CDP 实测 erp.licheng.test，未改动业务数据）

- 销货出库：不筛 11 条；状态 0/1/2 分别 4/2/5 条，与行内 `outbound_status` 完全一致，三者相加 = 11。
- 出库管理：不筛 13 条；状态 0/1/2 分别 5/2/6 条，相加 = 13；单号级状态与销货出库页一致。
- 浏览器实测两页搜索栏出现「请选择出库状态」（未出库/部分出库/全部出库），与既有下拉同一 layui 样式；
  选 1 点搜索 → 销货出库只剩 XHD20261103002 / XHD20261103001；选 2 → 出库管理只剩 6 条，列表无 JS 异常。

## 搜索栏「重置」按钮（2026-11-04）

- 两个页面的搜索按钮后面各加一个 `layui-btn-primary` 的 **[重置]**（`#reset`，紧跟在 `#search` 后的
  `layui-input-inline` 里，宽度 80px）：
  `goods_sales_outbound/index.html` 与 `goods_outbound_apply/index.html`。
- JS（两个文件的 `bindevent`/layui.use 块内）：清空 `.layui-search` 下所有 `input`/`select` 的值 →
  `layui.form.render('select')` 刷新 layui 下拉显示 → 调已有的 `reloadTable()`（自然带上空的
  `filter/op` 与 `outbound_status=''`），页码回到第 1 页。
- ⚠️ 不用 `form.reset()`：layui 的 select 是渲染出来的 `.layui-form-select` 副本，原生 reset 后显示不会跟着变，
  必须 `layui.form.render('select')`。
- 已验证（headless Chrome/CDP 实测，未改业务数据）：两页选「全部出库」搜索 → 行收窄（销货出库 5 行 / 出库管理 6 行），
  点 [重置] → 行恢复初始列表、`#outbound_status` 值与所有输入框均为空、下拉显示回到「请选择出库状态」，无 JS 异常。

---

# 销货出库：必须先在计划排期「排产」才能申请出库（2026-11-05）

> 涉及文件：`application/admin/view/kuerp/goods_sales_outbound/index.html`（行按钮模板）、
> `application/admin/controller/kuerp/GoodsSalesOutbound.php`（`apply()` GET/POST 守卫）。**无数据库迁移。**

- **判定字段**：`fa_kuerp_goods_sales.production_status`（`enum('0','1')`）。计划排期列表勾选单据点【排产】
  （`GoodsSalesSchedule::setproduction()`，`status=1` 并必填生产日期）后置 `'1'`；排期与销货单共用同一张表，
  所以销货出库列表每行直接能读到该值。取消排产（status=0）不会清生产日期，但会把 `production_status` 归 `'0'` → 重新禁止申请。
- **列表**：`#barDemoOutbound` 分支改为
  `outbound_status != 2 && production_status != '1'` → 输出灰色不可点 `<span class="layui-btn layui-btn-xs layui-btn-disabled" title="请先在销货排期中完成排产，排产后才可申请出库">未排产</span>`；
  已排产且未全部出库 → [申请出库]；已全部出库 → 原 [申请记录]（不受排产状态影响，历史记录仍可查）。
- **服务端兜底**：`apply()` POST 在「已审核」校验后加 `production_status !== '1'` → `该销货单尚未排产，请先在销货排期中完成排产后申请出库`；
  GET 同理，但 **`view=1`（只读查看历次申请/出库记录）放行**，避免撤销排产后连历史记录都打不开。
- **列表「排产状态」列**（`goods_sales_outbound.js`，位置在「代发/直发」之后、「交货日期」之前，width 100）：
  已排产绿字 `已排产`（有生产日期时 `title="生产日期：YYYY-MM-DD"` 悬停提示），未排产灰字 `未排产`。
  lang 新增 `Production_status` / `Production_status 0|1` / `Production_date`（`goods_sales_outbound.php`），
  JS 侧 `__('Production_status 1')` 与其它状态列同一写法（`Fast.lang` 会转小写查表）。
- **不影响**：仓库侧「出库管理」（`goods_outbound_apply`）对已存在的待审申请照常审核/拒绝；送货单打印、撤销申请均无新增门槛。
- 已验证（curl + headless Chrome/CDP 实测 erp.licheng.test，未新增/未修改业务数据）：
  未排产单据 `apply/ids/15` GET/POST 均返回上述提示且 `fa_kuerp_goods_outbound_apply` 行数不变（13）；`view=1` 只读页 200；
  已排产单据 `apply/ids/17` 过了排产校验（空数量时提示「请填写出库申请数量」且未落库）；
  列表行按钮实测：已排产未出完 → 申请出库、未排产 → 灰色「未排产」（`cursor:not-allowed`、title 提示）、已全部出库 → 申请记录 / 仅编辑；
  主表与右侧固定列行高均 41px 无错位，无 JS 异常。

---

# 后加按钮缺失的权限规则（2026-11-05）

> 迁移：`addons/kuerp/migration_add_missing_button_auth_rules.sql`（已对 erp_licheng 执行，可重复执行）；
> `install.sql` 1.6.29 段。**无代码改动。**

## 为什么会出现“按钮加了、权限表没有”

FastAdmin 的按钮显隐靠视图里的 `$auth->check($nowmenu.'/xxx')`，而它对**非超管**只在
`fa_auth_rule` 里存在同名规则、且该角色被勾选时返回 true；后端的 `Backend` 鉴权也按同一张表。
所以新加按钮时：

- 方法**没有**写进 `$noNeedRight` → 缺规则时**除超管外谁都调不通**（提示“你没有权限”）；
- 方法**写了** `$noNeedRight` → 后端不校验，但视图里仍用 `$auth->check` → 缺规则时**非超管看不到按钮**。

## 本次补齐的 5 条

| 规则 | 位置 | 症状 |
|------|------|------|
| `kuerp/goods_bom/printpage` | 商品BOM模板 列表行 [打印] | `Kuerp.php` 的 `$menu` 里早已有该条，数据库漏装 → 非超管看不到 |
| `kuerp/goods_purchase/summary` | 采购单 列表 [汇总]（采购汇总页） | 同上（`summary` 在 `$noNeedRight`） |
| `kuerp/process_order/printpage` | 外协订单 列表 [打印] | 同上（`printpage` 在 `$noNeedRight`） |
| `kuerp/sales_recon/deldiff` | 销货对账 → 差异金额弹窗 [删除] | **后端真实鉴权**，缺规则时非超管完全无法删除差异 |
| `kuerp/sales_recon/uploaddiff` | 销货对账 → 差异金额弹窗 [上传附件] | **后端真实鉴权**，缺规则时非超管完全无法上传 |

## 仍然是 `$noNeedRight`、无法按角色控制的按钮

这些功能正常但权限表里没有粒度，任何能进该页面的登录用户都能用（如需按角色控制，要同时加规则 +
从 `$noNeedRight` 移除，否则加了规则也不会生效）：
采购单打印 `goods_purchase/printpage`、采购单汇总 `goods_purchase/summary`、
BOM打印 `goods_bom/printpage`、外协订单打印 `process_order/printpage`、组装单打印 `assemble/printpage`、
送货单/出库申请单 `goods_outbound_apply/deliverynote|printpage`、
采购入库 `goods_purchase_storage/storage|detail|printstorage`、
采购单(仓库)打印 `goods_purchase_plan_warehouse/printpage`、
确认收货 `goods_sales_order/confirmReceipt`（发货 `ship`）、
计划排期「排产/预计完成日期/锁定零件/导出」`goods_sales_schedule/setproduction|setexpecteddelivery|lockGoods|unlockGoods|export`、
采购预订单「一键生成采购单/导出/导入」`goods_purchase_order/batchGeneratePurchase|generateBySupplier|export|parseimport`、
小程序用户 `mini_user/*`（整个控制器 `$noNeedRight=['*']`）。

## 排查方法（下次新增按钮后自查）

```bash
# 1. 控制器公开方法（含继承/trait）
grep -n "public function" application/admin/controller/kuerp/*.php
# 2. 库里已有规则
mysql -N -e "SELECT name FROM fa_auth_rule WHERE name LIKE 'kuerp/%'"
# 3. 视图里用了 $auth->check 的按钮
grep -rn "auth->check" application/admin/view/kuerp/
```
方法不在 `$noNeedRight` 且权限表无同名规则 = 必须补；方法在 `$noNeedRight` 但视图用了
`$auth->check` = 建议补（否则按钮对非超管不显示）。

## 注意

- 新增规则后**仍需到 角色管理 勾选**才对该角色生效（超管 `rules='*'` 天然全通）。
- `kuerp/stone_in|stone_out|stone_stock` 整套菜单/规则在库中都不存在（`fa_kuerp_stone_*` 表也不存在），
  但 `Kuerp.php` 的 `$menu` 里有定义、控制器又是 `$noNeedRight=['*']` —— 属历史遗留死代码，本次未处理。

## 补充（2026-11-05）：采购单入库「详情 / 打印 / 入库」纳入权限表

> 迁移：`addons/kuerp/migration_add_goods_purchase_storage_auth_rules.sql`（已对 erp_licheng 执行，可重复执行）；
> 代码：`GoodsPurchaseStorage.php` 的 `$noNeedRight` 去掉 `detail|printstorage|storage`；
> `view/kuerp/goods_purchase_storage/index.html` 的 `#barDemoStorage` 三个按钮加 `$auth->check($nowmenu.'/...')`。

- 原来是“能点但管不了”：三个方法在 `$noNeedRight`、权限表也没有记录，任何能进列表的角色都能看/打/入库。
- 现在新增 `kuerp/goods_purchase_storage/detail`(详情)、`/printstorage`(打印)、`/storage`(入库) 三条规则（pid=628 采购单入库），
  并**增量授予**所有已拥有 `kuerp/goods_purchase_storage/index` 的角色（仓储部/采购部/仓库专员/总经理…9 个），
  升级后现有角色行为不变；之后可在 角色管理 → 采购单入库 里按需取消。
- 仍在 `$noNeedRight`：`uploadenclosure|delenclosure|enclosurepicker|enclosurelist`（仓库角色没有原始单据模块权限，必须放行）
  以及 `checkpurchase|getsuppliers|getunit|formatinfo*|getbranch|gettime` 等 AJAX 辅助接口、`printpage`（本控制器未使用）。
- 实测（临时角色+临时账号，测后已删除并还原自增）：仅有 `index` 时列表能打开，三个动作均返回「你没有权限访问」，
  行按钮带 `hiddens` 不显示；追加三条规则后三页 200、按钮出现；超管不受影响。

---

# 2026-11-05 线上部署记录（1.6.30）

> 版本标记：`addons/kuerp/info.ini` = 1.6.30；线上根目录 `DEPLOY_VERSION.txt`（版本 + 19 个文件清单 + 执行的迁移）
> 1.6.27~1.6.29 由其它会话于 11-04 分批上线（见 `DEPLOY.md` 版本清单），本次为 11-05 增量。

- **代码 19 个文件**（FTP 逐个 md5 校验通过）：销货出库/出库管理「出库状态」派生筛选（`addons/kuerp/library/Goods::outboundStatusCodes()` + 两个控制器/视图/JS/lang）、
  未排产禁止申请出库（`production_status` 列 + 按钮置灰）、送货单按钮从行上移到「申请记录」弹窗、
  采购入库列表已全部入库隐藏「入库」按钮、采购入库详情「入库记录」面板、采购预订单(计划/仓库)隐藏供应商列（`common/menu.html` 的 `.supplier_sd`）、
  BOM 打印页 A4 竖版列宽（`colgroup`）、`goods_purchase_storage` 详情/打印/入库纳入权限表（`$noNeedRight` 移除三项）。
- **数据库**：结构逐表逐列一致（无 DDL）；执行 4 个迁移：
  `migration_add_purchase_print_terms.sql`（2 个设置项，幂等）、`migration_widen_goods_specification_id.sql`（线上已 255，空操作）、
  `migration_add_missing_button_auth_rules.sql`（新增规则 780-784）、
  `migration_add_goods_purchase_storage_auth_rules.sql`（新增规则 785-787，并把三条增量授予已有 `goods_purchase_storage/index` 的 11 个角色，保证升级后行为不变）。
- `application/extra/site.php` 线上仅改 `version` 1.0.7→1.0.8（刷新浏览器缓存），其余内容保持线上原样。
- 部署后已清 `runtime/cache`、`runtime/temp`；登录页与 5 个 kuerp 列表页均 200；线上无 `.deploybak_/.tmpnew_` 残留。
- 部署期间有并发编辑：`GoodsPurchaseStorage` 与新增迁移在 10:12-10:13 被再次修改，最终版已补传并校验。

## 补充（2026-11-05 第二批）：「后来新增的按钮」统一纳入权限表

> 迁移：`addons/kuerp/migration_add_buttons_auth_rules_batch2.sql`（已对 erp_licheng 执行，可重复执行）；
> `install.sql` 1.6.29（续二）段。
> 原则：**加规则 → 控制器 $noNeedRight 移除该方法 → 视图/JS 加 `$auth->check` 隐藏按钮 → 规则增量授予
> 「已拥有该模块查看权限」的角色**（升级后现有角色行为不变，后续可在 角色管理 里按需取消）。

| 模块 | 动作（规则） | 视图/JS 显隐 | 授权给（拥有该 index 的角色） |
|------|-------------|-------------|------------------------------|
| 采购单 | `printpage` 打印、`summary` 汇总、`export` 导出 | 行模板 [打印] + 工具栏 [汇总]；汇总页导出按钮由 `summary()` 下发 `canExport` | `goods_purchase/index` |
| 组装单 | `printpage` 打印、`outboundEdit` 实际出入库、`saveOutbound` 保存实际出入库 | 已有 `$auth->check` | `assemble/index` |
| 外协订单 | `printpage` 打印 | 已有 `$auth->check` | `process_order/index` |
| 商品BOM | `printpage` 打印 | 已有 `$auth->check` | `goods_bom/index` |
| 出库管理 | `printpage` 打印申请单、`deliverynote` 打印送货单 | 行模板 + 详情页 | `goods_outbound_apply/index`；`deliverynote` 另含 `goods_sales_outbound/index`（计划部从销货出库页打印） |
| 销货订单 | `confirmReceipt` 确认收货 | 行模板 | `goods_sales_order/index` |
| 计划排期 | `setproduction` 排产、`setexpecteddelivery` 改完成日期、`lockGoods`/`unlockGoods` 零件锁定、`export` 导出 | 工具栏按钮加 `$auth->check`；BOM 表格的锁定/解锁按钮由 `edit()` 下发 `Config.canLockGoods/canUnlockGoods`，`goods_sales_schedule.js` 据此渲染 | `goods_sales_schedule/index` |
| 采购预订单 | `export` 导出明细、`parseimport` 导入明细 | add/edit 页 `.po-io-bar` 两个按钮包 `{if $auth->check(...)}` | `goods_purchase_order/index` |
| 采购单(仓库) | `printpage` 打印 | 行模板 | `goods_purchase_plan_warehouse/index`（当前无角色拥有，仅超管） |
| 小程序用户 | `add`（并补齐 index/edit/del 授权） | 工具栏 add 按钮 + 行模板改由 `Config.canEditMiniUser/canDelMiniUser` 控制 | `mini_user/index` |

- `MiniUser` 原为 `$noNeedRight=['*']`，现已改为 `[]`（菜单规则 index/edit/del 本就存在，本次只新增了 add）。
- 计划排期工具栏的 审核/反审核、对单/反对单 按钮这次也一并补上了 `$auth->check`（之前完全没有，按钮对所有人可见）；
  这两个方法**仍在 `$noNeedRight`**，本次只对齐了 UI。
- 出库管理的 [打印申请单] 当前**没有任何角色拥有** `goods_outbound_apply/index`，故该规则暂未授予任何角色（仅超管）。
- 实测（临时角色 + 临时账号，测后已删除并还原自增）：未授权时 6 个代表接口全部返回「你没有权限访问」、按钮带 `hiddens`；
  授权后全部 200、按钮出现；超管各页面 200、模板无残留 `{if}`；汇总页导出按钮在授权/未授权间正确切换。

### 顺带修复：小程序「轮播管理 / 首页导航」按钮权限名大小写不匹配（2026-11-05）

`view/kuerp/mini_banner/index.html`、`mini_nav/index.html` 里写的是
`$auth->check('kuerp/MiniBanner/add')`（驼峰），而 `fa_auth_rule` 里存的是 `kuerp/mini_banner/add`。
`fast\Auth::check()` 只做 `strtolower` 精确比对，不会把驼峰转下划线，所以这些 check **恒为 false** →
除超管外所有角色的 [添加]/[编辑]/[删除] 按钮都不显示。已把两个视图里的 `kuerp/MiniBanner/…`、`kuerp/MiniNav/…`
统一改成 `kuerp/mini_banner/…`、`kuerp/mini_nav/…`（共 12 处）。

> 同类未处理：`suppliers_user`（控制器 `$noNeedRight='*'` 且权限表无规则，页面无入口、button 恒隐藏）与
> `stone_in/out/stock`（库中无规则/无表的历史死代码），两者都不影响现有业务。

---

# 销货单详情底部展示「出库记录」（2026-11-05）

> 涉及文件：`application/admin/model/kuerp/GoodsOutboundApply.php`（新增静态 `buildHistory()`）、
> `application/admin/controller/kuerp/GoodsSales.php`（`edit()` assign `outboundHistory`）、
> `application/admin/controller/kuerp/GoodsSalesOutbound.php`（`apply()` 改为调用 `buildHistory()`）、
> `application/admin/view/kuerp/goods_sales/edit.html`（末尾新增「出库记录」面板）。**无数据库迁移、无新权限。**

- 口径：销货单详情（`kuerp/goods_sales/edit`，列表行 [编辑] 即详情页）底部，在「操作日志」面板之后新增 **「出库记录」** 面板，
  内容与「销货出库」列表 → [申请出库 / 申请记录] 弹窗里的 **「历次申请记录」完全同源**
  （`fa_kuerp_goods_outbound_apply` + `_info` + `out_data` 实际出库数量 + `express_data` 快递信息，最新在前）。
- 列：申请单号 / 申请人 / 申请时间 / 申请内容 / 实际出库数量 / 快递信息 / 状态 / 审核人 / 审核时间 / 拒绝原因。
  `实际出库数量` 仅 `status='1'`（已同意出库）才有值，其余显示灰色 `--`；状态 待审核（蓝）/ 已同意出库（绿）/ 已拒绝（红）/ 已撤销（灰）。
- 两个页面共用 **`GoodsOutboundApply::buildHistory($salesId)`**（唯一数据装配入口，含 `goods_summary`/`out_summary`/
  `express_list`/`express_summary`/`apply_admin_name`/`examine_admin_name`），**不要再复制一份**到任一侧。
- 无记录时显示「暂无出库记录」；表格外包一层 `overflow-x:auto`，窄屏横向滚动不撑破页面（1600px 下单行显示不溢出）。
- 面板在 `</form>` 之后，纯展示、无 input，不影响单据表单提交（销货出库页的申请弹窗逻辑与字段零改动）。

---

# 销货预订单 add/edit 恢复可见的「仓库」列（2026-11-05）

> 涉及文件：`application/admin/view/kuerp/goods_sales_order/{add,edit}.html`（**纯前端，无控制器/JS/数据库改动**）

- **背景**：`select.stores_id` 原先被塞在「型号」单元格里、用 `div[data-select-enhance="skip"][style="display:none"]` 藏起来，
  仓库只能由选货时的商品首选仓库自动带出，用户改不了。
- **改动**：把 `select.stores_id` 从「型号」单元格移到 **「商品单位」之后、「可用库存」之前**的独立列，
  去掉 `data-select-enhance="skip"` 与 `display:none`（由 select-enhancer 转成 bootstrap-select，带关键字搜索），
  表头新增 `<th style="min-width:110px">{:__('Stores_id')}</th>`（lang key 早已存在=仓库）。
  - `min-width` 必须保留：不加时该列被挤到 55px，下拉只剩一个箭头看不到仓库名；加后列宽 110px、表格总宽不变（其余列自动收窄）。
  - edit 页历史行仍带 `value="{$info.stores_id}"` 的 `selected`，回显不受影响；已审核单据整行 `disabled` 时同样只读。
- **数据流零改动**：`row[stores_id][]` 与 `row[goods_code][]` 一样按 DOM 顺序序列化，字段名与提交结构完全不变；
  `.chose_goods_tr` 选货 / `.goods_code` change 仍把 `manager_warehouse_id` 写进该 select，
  手动改仓库走既有 `.stores_id` change 处理器刷新「可用库存」（`kuerp/goods_purchase/getUnit`）。
- **商品无首选仓库时该列显示空**，保存仍由服务端 `Goods::resolveStoreId()` 按商品类型兜底（零件仓/成品仓/半成品仓），
  与 2026-11-03 规则一致 —— 空值 = 自动，不是错误。
- **已实测**（headless Chrome + CDP）：add 页 17 列（th 与各行 td 数量一致）、仓库列宽 110px、转成 bootstrap-select；
  选 `C13-0001` 自动带出「零件仓」、可用库存 1994；手动改「成品仓」→ 可用库存刷新为 0、按钮文案同步；
  用页面自身 `$('#add-form').serializeArray()` 真实提交一张两行单据（第 2 行手动选成品仓）→ 落库
  `stores_id=6 / 5` 与界面一致（测试单 XHDD20261105001 与 info、log 已删除，`AUTO_INCREMENT` 还原为 18/87/116）。
  edit 页（已审核单 id=16）17 列、仓库列显示原值 `5` 且为只读。
- **未改动**：销货单 `goods_sales`、计划排期 `goods_sales_schedule`、组装单等页面的仓库列保持原样。

---

# 明细行过多导致表单被 PHP `max_input_vars` 静默截断（2026-11-05）

> 涉及文件：`application/admin/controller/kuerp/GoodsPurchaseOrder.php`（新增 `checkFormTruncated()`）、
> `GoodsPurchaseOrderPlan.php`（继承同名方法，add/edit 各调用一次）

## 现象

采购预订单（`kuerp/goods_purchase_order/add`）选择「HS-15C扩展板BOM」（**52 个子件**）后保存，
报 `Undefined array key "preferential_rate"`（PHP7 中文：未定义数组索引）/ 500。行数少时正常。

## 根因

PHP 的 `max_input_vars`（**默认 1000**）在解析表单阶段就**静默丢弃超出部分**，业务代码拿不到：
每行明细提交 20 个字段（goods_code/goods_note/goods_attr/goods_unit/stores_id/row_supplyer_code/allow_stock/
number/default_unit/basic_nmber/unit_price/tax_included/discount/dis_money/all_money/tax_rate/tax_money/
money_tax_price/remark/sales_order_id），表头 7 个、表单尾部 4 个（note/preferential_rate/preferential_money/real_money）。
**总字段数 = 11 + 20×行数**，所以约 **≥50 行**就超限；尾部字段（表单最后提交的）先被丢弃 → 第 256 行取不到 → 500。
本地实测：≤49 行完整，52 行时尾部全丢、明细列长度也不一致（goods_code 50 个 / all_money 49 个）。

## 修复

1. **服务器**：`public/.user.ini` 追加 `max_input_vars=10000`（约可容纳 1000 行明细）。
   ⚠️ 该文件线上属主是 `root:root 644`，**FTP 无法重命名/覆盖/删除**（553 / 451），必须在宝塔面板改；
   PHP-FPM 读 .user.ini 有 `user_ini.cache_ttl`（默认 300s）缓存，改完等 5 分钟或重启 PHP。
2. **代码兜底**：`GoodsPurchaseOrder::checkFormTruncated($params)` 在 `preExcludeFields()` 之后、逐行解析之前调用，
   校验①尾部 4 个字段是否都存在 ②每条明细的 19 个数组长度是否都等于 `goods_code` 的数量；
   命中则 `error('提交的明细行过多，服务器已截断表单数据（PHP max_input_vars=xxx）…')`，
   避免 500，**也避免静默保存成少了明细行的单据**（截断只丢尾部，不报错时前端毫无感知，最危险）。
   已在 `GoodsPurchaseOrder::add/edit` 与 `GoodsPurchaseOrderPlan::add/edit` 共 4 处调用
   （`GoodsPurchaseOrderPlanWarehouse` 是只读子类，add/edit 直接 error，不涉及）。
3. 其它单据（销货单/采购单/组装单…）同样是 `row[x][]` 数组提交，行数极多时同理会被截断；
   已提高 `max_input_vars` 后不再是问题，新增大批量单据表单时留意该上限。

---

# 采购退货出库：库存扣减从「退货单审核」移到仓库「退货出库」（2026-11-05）

> 迁移：`addons/kuerp/migration_add_goods_return_outbound.sql`（已对 erp_licheng 执行，可重复执行）；
> `install.sql` 1.6.33 段 + `info.ini` 1.6.33。
> 新增：`addons/kuerp/library/ReturnOutbound.php`、
> `application/admin/view/kuerp/goods_purchase_storage/returnoutbound.html`、
> `public/assets/js/backend/kuerp/goods_return_outbound_layer.js`。
> 改动：`GoodsExchange.php`、`GoodsPurchaseStorage.php`、`GoodsPurchase.php`、三个视图、`goods_purchase_storage.js`。

## 流程

```
购货退货单 add → 审核（只做账务） → 采购单入库列表行出现 [退货出库]
   → 弹窗（退货单明细 + 本次出库数量，可分批） → 确认
   → 扣库存/成本 + 写收发明细 + 写「退货出库操作记录」
   → 该退货单全部出完 → 列表按钮消失；采购单详情/采购入库详情可见出库记录
```

## 关键口径

- **审核不再动库存**：`GoodsExchange::examine()` 里库存/成本/`kuerp_goods_log`(business_type=3)/
  序列号(`store_status=2`)/批次库存 的全部代码已删除（审核仍做：应付、收付款、台账、开票、采购汇总、订单 `store_number`）。
  反审核同理不再恢复库存；两者都只改账务。
- **守卫**：`out_number>0`（已有出库）时禁止**反审核**、禁止**删除**退货单；再次打开出库页提示「该采购单没有待出库的退货单」。
- **关联关系**：采购单 → 退货单有两套：`kuerp_goods_excpur`（表头级，`purchase_id`=采购单ID，`type='2'`）与
  `kuerp_goods_excpur_info`（行级，`purchase_id`=采购单明细行ID）。`ReturnOutbound::relatedInfoIds()` 取并集。
- **累计出库**：`fa_kuerp_goods_exchange_info.out_number`（单据单位）。剩余可出 = `retreat_number - out_number`。
- **基础单位换算**：与审核旧逻辑一致 —— 单位 `type=2 && is_main!=1` 时乘 `kuerp_unit_conversion.advance_rate`。
- **成本**：按 `all_money × 本次/retreat_number` 分摊，**最后一批吃尾差**（整行成本恒等于 `all_money`）；
  已结转成本取 `kuerp_goods_log` 该行 `out_cost` 合计。
- **库存校验**：按 `商品+仓库+属性` 汇总本次各行的基础单位数量，超出可用库存整单拒绝并回滚（与旧审核口径一致）。
  无库存行直接报「未查询到商品X在仓库[Y]的库存，不能退货出库」。
- **操作记录**：`fa_kuerp_goods_return_outbound_log`（purchase_id/purchase_code/exchange_codes/total_number/
  total_basics/detail JSON/admin_id/createtime），`ReturnOutbound::logs()` 供三个页面复用。

## 页面

| 页面 | 改动 |
|------|------|
| 采购单入库列表 `goods_purchase_storage` | `index()` 下发 `return_outbound_pending`（该采购单下已审核且未出完的退货单明细行数）；`#barDemoStorage` 加 `{{# if(d.return_outbound_pending > 0) }}` 的 [退货出库]（`$auth->check($nowmenu.'/returnoutbound')`）；操作列宽 180→240 |
| 退货出库弹窗 `returnoutbound.html` | 明细列：退货单号/商品编号/名称/型号/规格2/规格3/属性/单位/仓库/退货数量/已出库/剩余可出/**本次出库数量**（默认 0，max=剩余）；下方「出库记录」面板 |
| 采购入库详情 `goods_purchase_storage/detail.html` | 末尾新增「退货出库记录」面板 |
| 采购单详情 `goods_purchase/edit.html` | `</form>` 之后新增「退货出库记录」表格（无记录时整块不渲染，`GoodsPurchase::edit()` assign `return_log_list`） |

## 权限

`kuerp/goods_purchase_storage/returnoutbound`（迁移新建，pid=628 采购单入库），**不在 `$noNeedRight`**，后端真实鉴权；
迁移同时增量授予「已拥有 `goods_purchase_storage/index`」的角色（只加不删）。

## 已验证（curl 登录 erp.licheng.test 实测，测试数据已按快照还原）

- 审核：退货单审核后 `goods_stock.stock`/`cost_money`、`goods.inventory`、`goods_log` 全部零变动（改前会立刻扣减）。
- 列表：关联采购单行 `return_outbound_pending=1`、`returns_order_id` 正确；出完变 0。
- 分批出库：2/5 → 库存 10036→10034、成本 240→222.3（44.25×2/5）、`out_number=2`、日志行写入；
  再出 3 → 库存 10031、成本 195.75、`out_number=5`，两次成本合计 = 44.25（尾差正确）。
- 校验：超量「本次出库数量(99)超过剩余可出库数量(2)」、全 0「请填写本次退货出库数量」、无待出库「该采购单没有待出库的退货单」、反审核被拦截、删除被拦截。
- 页面：采购单详情与采购入库详情均渲染两条出库记录（时间/退货单号/数量/明细含仓库/操作人）。
- 审核→反审核往返库存零变动；改前 `unset` 缺失导致的 `fields not exists:[out_number]`（退货单明细行的 out_number 被带进
  `kuerp_goods_purchase_info` / `kuerp_goods_purchase_summary` 的 insertAll）已修复。
- ⚠️ 若日后启用序列号/批次：序列号在**整行出完**时统一标记 `store_status=2` 并写 `kuerp_goods_serial_log`；
  批次按本次基础单位数量扣 `kuerp_goods_batch_stock` 并写 `kuerp_goods_batch_log`，整行出完时把
  `kuerp_goods_batch_exchange_info.status` 置 '1'（当前本库 `batch`/`serial` 均为关闭，未实机验证）。

---

# 销货对账明细：收款状态显示反了 + 单号可复制（2026-11-05）

> 涉及文件：`application/admin/controller/kuerp/SalesRecon.php`（新增 `docPayStatus()`）、
> `public/assets/js/backend/kuerp/sales_recon.js`（`Controller.items`）、
> `application/admin/view/kuerp/sales_recon/items.html`。**无数据库迁移、无权限变更。**

## 一、收款状态显示反了（线上现象：全额收款显示「部分收款」）

**两套 `pay_status` 字段的值语义相反**，`buildItemRows()` 之前把单据值直接透传给工作台（`items` 页）用：

| 字段 | 0 | 1 | 2 | 写入方 |
|---|---|---|---|---|
| `kuerp_goods_sales.pay_status`、`kuerp_goods_sales_exchange.retreat_status` | 未收(退) | **全部**收(退) | **部分**收(退) | `Collection::examine()`、`Writeoff::examine()`（`$more_money<=0?1:2`），lang `Pay_status 1=全部收款` |
| `kuerp_sales_recon.pay_status`（对账单） | 未收款 | **部分**收款 | **已**收款 | `SalesRecon` 模型 `recalcPay()`，`sales_recon.js` 的 `payTag()`、筛选下拉 |

工作台卡片/筛选用的是第二套口径，但取的是第一套的字段值 → **全部收款显示「部分收款」、部分收款显示「已收款」**。
线上实例：`XHD20261104001`（real 317 + 客户承担 10 = 327，pay_money 327，`pay_status=1`）在「销货对账明细」里显示「部分收款」。

**修复**：`SalesRecon::docPayStatus($status)` 做 1↔2 互换（0 不变），`buildItemRows()` 的销货单与退货单两处都过它，
返回给前端的 `pay_status` 统一为对账口径；工作台「收款状态」筛选（值 0/1/2 仍取自 items.html 下拉）随之正确。
`applyItemFilters()` / `filteredDocKeys()` 共用同一份行数据，无需另改。

**已验证**（curl 登录 erp.licheng.test 实测）：`itemlist?customers_id=19&recon_status=2` 中
`XHD20261028001`（1201 全额已收）返回 `pay_status=2`（改前为 1）；`pay_status=2` 筛选命中该单、`pay_status=1` 为空（改前相反）。

## 二、销货单号可复制

`.recon-doc-head` 有 `user-select:none`（点击展开/收起），单号选不中也复制不了。现：

- `items.html`：`.recon-doc-code` 单独放开 `user-select:text` + `cursor:copy`；
- `sales_recon.js`：单号加 `title="点击复制单号"`，点击事件 `.recon-doc-code` → `copyText()`（Clipboard API，失败回退
  `textarea + execCommand`）+ `Toastr.success('已复制：单号')`，并 `stopPropagation()` 不触发展开；
  `.recon-doc-head` 的展开处理器同时跳过单号（双保险）。

⚠️ 线上部署后需递增 `application/extra/site.php` 的 `version`（该文件有意不随代码部署），否则
`/assets/js/backend/kuerp/sales_recon.js` 会被 nginx 12h 缓存挡住，用户看到的仍是旧行为（或强刷）。

---

# 采购入库「入库状态」筛选 + 重置按钮 / 收发明细·汇总·库存余额表「型号规格」搜索（2026-11-06）

> 无数据库迁移、无新权限（沿用原有 index）。

## 一、采购单入库（kuerp/goods_purchase_storage）搜索栏

- **入库状态筛选**：搜索栏新增下拉 `#storage_status`（未入库/部分入库/已入库）。
  `storage_status` 是**派生值**（按采购单号汇总 `kuerp_goods_purchase_info` 的 `SUM(number)/SUM(storage_number)`），
  不是数据库列 → **不能进 `filter`**，下拉刻意**不写 `name`**（该页 `getSearch()/getOp()` 是按 `.layui-search [name]`
  全量收集的，写了 name 就会拼进 filter 导致 SQL 报错），改由 `reloadTable()` 单独传 `storage_status`。
- **反查实现**：`\app\admin\model\kuerp\GoodsPurchase::storageStatusCodes($status)`（口径与 `storageStatusMap()` 一致）
  返回 `['mode'=>'in'|'not in','codes'=>[...]]`；控制器 `index()` 里 `$query->where('purchase_type',1)->where('purchase_code',$mode,$codes)`
  —— 退货单没有入库概念（列表显示 `—`），筛选时一并排除。空匹配用哨兵 `__no_storage_match__` 占位避免 `IN ()`。
- **重置按钮**：`#search` 右侧新增 `#reset`（`layui-btn-primary`，宽 80px）。
  ⚠️ 不能 `form.reset()`：layui 的 select 是渲染出来的副本，原生 reset 后显示不变，必须
  `$('.layui-search').find('input/select').val('')` + `layui.form.render('select')` 再 `reloadTable()`。
- 已验证（curl + headless Chrome/CDP）：不筛 11 条；`storage_status=2` → 4 条、`=0` → 7 条（4+7=11）；下拉转 layui 样式且隐藏原生 select；
  选「已入库」点搜索 → 共 4 条；点 [重置] → 回到共 11 条、下拉值清空；无 JS 异常。

## 二、型号规格搜索（goods_log 收发明细表 / goods_log_summary 收发汇总表 / goods_stock 商品库存余额表）

- 三页搜索栏都新增文本框 `#goods_specification_id`（placeholder「型号规格」），模糊匹配商品档案
  `kuerp_goods.goods_specification_id`（varchar，存的是型号文本，不是外键）。
- ⚠️ **这三张表都没有该列，不能进 `filter`**（`buildparams` 会把 key 前缀成 `goods_log.`/`goods_stock.` → 未知列），
  一律走**独立查询参数** `goods_specification_id`，JS 里与 `filter` 一起放进 `table.reload` 的 `where`：
  - `GoodsLog::index()`：先 `Db::name('kuerp_goods')->where('goods_specification_id','like','%x%')->column('id')`，
    再 `where('goods_log.goods_id','in',ids)`（匹配不到用 `[0]` 占位）；
  - `GoodsLogSummary::index()`：分组查询本就 `join kuerp_goods goods`，直接 `where('goods.goods_specification_id','like',...)`
    （`getTotalRow()` 按分组键汇总，自动跟着过滤）；
  - `GoodsStock::getSearchWhere()`：追加 `$where['goods.goods_specification_id'] = ['LIKE',"%x%"]`；
    该方法被 `index()` 与顶部合计 `total()` **共用**，所以 JS 的 `loadTotal()` 也必须带 `goods_specification_id`
    （`currentSpec` 变量，搜索时更新），否则合计栏与列表口径不一致。
- 已验证（curl + headless Chrome/CDP，未改动任何业务数据）：`CBB` → goods_log 75→26 条、goods_log_summary 38→7 条、
  goods_stock 680→53 条且顶部合计同步 680/3,124,461 → 53/111,674；不存在型号三页均 0 条；无 JS 异常。

---

# 组装单列表默认排序改为「单据日期倒序」（2026-11-06）

> 涉及文件：仅 `application/admin/controller/kuerp/Assemble.php`（`index()` 默认排序）。**无数据库迁移、无视图/JS 改动。**

- 默认排序由「生产日期升序（未排产排最后）→ 生成日期升序」改为 **`purchase_time DESC, id DESC`**：
  按**单据日期**倒序，最近的排最前；同一天的按 id 倒序（同一单号的多行是同一批插入，id 相邻，
  保证列表 `merge:['purchase_code']` 的合并单元格不被拆开）。
- 前端点表头排序时仍走 `if ($this->request->param('sort'))` 分支，行为不变；`assemble.js` 的 `table.render`
  没有 `initSort`/`autoSort`，所以不点表头时一定走这个默认排序。
- `purchase_time` 为 0/NULL 的历史脏数据在 DESC 下自然排到最后，无需额外处理。
- 已验证（curl 登录 erp.licheng.test）：`kuerp/assemble/index` 13 条返回顺序
  `ZZ20261103002(11-03) → ZZ20261103001(11-03) → ZZ20261027001(10-27) → ZZ20261013001(10-13) → …→ ZZ20260926001(09-26)`，
  同日的 14 在 13 前、10 在 9 前（id 倒序正确）。


---

# 分类管理列表：图片缩略图统一 40×40 + 固定列行高同步（2026-11-06）

> 涉及文件（**纯前端，无数据库/控制器改动**）：6 个分类列表的 JS 与 index.html ——
> `goods_type`(商品分类)、`supplyertype`(供应商分类)、`customertype`(客户分类)、
> `expend_type`(支出类别)、`income_type`(收入类别)、`settlementtype`(结算方式)

## 现象与根因

线上商品分类加到 30 多个、且有的分类图片很大（如「柑橘」），列表出现「类别名称和右边字段对不上」，
行高也高低不一。

根因是 layui 的两件事叠加：

1. 图片列 `templet` 直接输出 `<img src=xxx>`，**没有约束尺寸** → 行高由原图自然高度决定（900×900 的图能把行撑到几百 px）。
2. `checkbox` 与「类别名称」两列是 `fixed:'left'`，layui 把它们渲染成**另一张独立的表**（`.layui-table-fixed-l`），
   行高各自独立。主表行被大图撑高而固定列仍是 28px → 越往下错得越多（实测第 2 行 top 182 vs 194，第 10 行 554 vs 618）。

## 修复

- 图片列统一走 `Controller.api.imageCell(url)`：输出 `<img class='cat-thumb'>`，
  CSS（各视图 index.html 内联 `<style>`）`width/height:40px + object-fit:contain` →
  **原图再大也固定 40×40**；**没有图片时输出同尺寸占位 `<span class='cat-thumb'>`**，行高完全一致。
- `table.render` 增加 `done` 回调，按主表行高逐行写回 `.layui-table-fixed` 的 tbody/thead 行（与 `goods.js`/`suppliers.js` 同一写法），
  即使以后某个单元格再变高也不会错位（`table.reload`/搜索/翻页后自动重跑）。
- 顺带：图片包的 `<a>` 原来点了没反应，现在点缩略图 `Layer.photos` 弹原图（`.cat-thumb-preview`，事件委托在 document 上）。
- 空图片原来输出 `<img src=>`（浏览器显示破图图标），现在是无图的浅灰占位块。

## 已验证（headless Chrome + CDP 实测 erp.licheng.test，临时造的测试图片已删除）

- 临时放 900×900 / 800×120 / 400×200 / 60×60 四张图后：6 个页面所有行高恒为 **53px**、
  主表与固定列每行 `top/height` **完全一致**；缩略图实测一律 40×40（naturalWidth 900 → 渲染 40）。
- 反证：把缩略图样式还原成原始尺寸后立刻复现错位（main `[141,182,245…]` vs fixed `[141,194,247…]`）。
- 点击缩略图弹出层 `.layui-layer-photos`，`img.src` 为原图地址；6 个页面均无 JS 异常；`node --check` 全通过。

⚠️ 部署后需递增 `application/extra/site.php` 的 `version`（该文件有意不随代码部署），否则浏览器会继续用缓存的旧 JS。
