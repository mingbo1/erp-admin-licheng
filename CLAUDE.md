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
购货订单 → 审核 → 购货单(部分/全部) → 审核 → 库存+ → 采购明细
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
- ⚠️ 购货单/销货单: **不受锁定影响**（后端不传 `sales_order_id` 参数时不触发调整）

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
| 购货单/销货单/销货订单 | (不传参 → 内部用空串) | physical_stock - all_locks | 无关联订单，扣除所有锁定 |
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

### 6.1 执行命令

```bash
mysql -uroot -proot -D erp_licheng < addons/kuerp/migration_add_assemble_sales_order_id.sql
mysql -uroot -proot -D erp_licheng < addons/kuerp/migration_add_goods_lock.sql
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

只取 `product_type='part'`（零件）的子件。`finished`（成品）和 `semi_finished`（半成品）不参与计算。这在 `kuerp_goods_bom_info` JOIN `kuerp_goods` 时过滤。

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
