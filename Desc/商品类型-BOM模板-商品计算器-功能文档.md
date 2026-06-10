# 商品类型分类 + BOM模板 + 商品计算器 — 功能文档

> **版本**: v1.0  
> **日期**: 2026-06-04  
> **涉及模块**: 商品管理(资料)、仓库管理(仓库)  
> **核心目标**: 区分商品生产类型，建立物料清单(BOM)模板，基于实时库存自动计算最大可组装成品数量

---

## 目录

1. [功能概述](#1-功能概述)
2. [数据库设计](#2-数据库设计)
3. [商品类型分类](#3-商品类型分类)
4. [BOM模板管理](#4-bom模板管理)
5. [商品计算器](#5-商品计算器)
6. [核心算法](#6-核心算法)
7. [文件清单](#7-文件清单)
8. [数据库迁移SQL](#8-数据库迁移sql)
9. [安装与部署](#9-安装与部署)
10. [业务场景示例](#10-业务场景示例)
11. [边界情况与异常处理](#11-边界情况与异常处理)
12. [后续扩展建议](#12-后续扩展建议)

---

## 1. 功能概述

### 1.1 解决的问题

原 kuerp 进销存系统没有区分商品的生产制造类型（成品/半成品/零件），也没有 BOM（Bill of Materials，物料清单）管理能力和基于库存的产能计算功能。本次改造解决以下三个核心需求：

| 需求 | 说明 |
|------|------|
| **商品类型分类** | 将商品分为**成品**(finished)、**半成品**(semi_finished)、**零件**(part) 三种类型 |
| **BOM模板** | 定义一个成品由哪些子件组成，每个子件的单耗数量是多少 |
| **商品计算器** | 根据 BOM 模板和当前库存，自动计算"最大可组装成品数量" |

### 1.2 业务流程图

```
┌──────────────┐    ┌──────────────────┐    ┌──────────────────┐
│  1.商品分类   │───▶│  2.创建BOM模板    │───▶│  3.商品计算器     │
│              │    │                  │    │                  │
│ 设置商品类型  │    │ 选父商品(成品)    │    │ 选择BOM模板       │
│ · 成品       │    │ 添加子商品       │    │ 查询各子件库存     │
│ · 半成品     │    │ 设置单耗数量     │    │ 计算最大可组装数   │
│ · 零件       │    │ 保存为模板       │    │ 识别瓶颈商品      │
└──────────────┘    └──────────────────┘    └──────────────────┘
```

### 1.3 菜单位置

```
后台左侧菜单
├── 资料
│   └── 商品管理 (新增「商品类型」字段)
│
└── 仓库
    ├── 组装单 (已有)
    ├── 拆卸单 (已有)
    ├── 组装单模板 (已有, ismenu=0)
    ├── 拆卸单模板 (已有, ismenu=0)
    ├── 商品BOM模板  ← 新增 (weigh=14)
    └── 商品计算器   ← 新增 (weigh=12)
```

---

## 2. 数据库设计

### 2.1 表结构变更：`fa_kuerp_goods`

在已有 `goods_type_id` 字段之后新增一个字段：

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `product_type` | `enum('finished','semi_finished','part')` | `'part'` | 商品类型：finished=成品, semi_finished=半成品, part=零件 |

> **设计决策**: 选用 `enum` 而非 `varchar` 或关联外键表，因为类型固定三种且不会频繁变化，enum 查询效率高、存储空间小。

### 2.2 新建表：`fa_kuerp_goods_bom`（BOM模板主表）

| 字段名 | 类型 | 说明 |
|--------|------|------|
| `id` | `int(11) PK AUTO_INCREMENT` | 主键 |
| `admin_id` | `int(11)` | 创建人 |
| `bom_name` | `varchar(255) NOT NULL` | 模板名称（如"A型发电机BOM"） |
| `goods_id` | `int(11) NOT NULL` | 父商品ID（成品） |
| `goods_code` | `varchar(50)` | 父商品编号（冗余，便于查询） |
| `remark` | `varchar(255)` | 备注 |
| `createtime` | `bigint(16)` | 创建时间（Unix时间戳） |
| `updatetime` | `bigint(16)` | 更新时间 |

### 2.3 新建表：`fa_kuerp_goods_bom_info`（BOM模板明细表）

| 字段名 | 类型 | 说明 |
|--------|------|------|
| `id` | `int(11) PK AUTO_INCREMENT` | 主键 |
| `admin_id` | `int(11)` | 创建人 |
| `bom_id` | `int(11) NOT NULL` (索引) | 关联 `kuerp_goods_bom.id` |
| `type` | `enum('1','2')` 默认'2' | **1=父商品行, 2=子商品行** |
| `goods_id` | `int(11) NOT NULL` | 商品ID |
| `goods_code` | `varchar(50)` | 商品编号（冗余） |
| `goods_name` | `varchar(255)` | 商品名称（冗余，便于列表展示） |
| `unit_id` | `int(11)` | 单位ID |
| `number` | `double(15,4)` 默认0 | 单耗数量（每个成品需要该子件的数量） |
| `remark` | `varchar(255)` | 备注 |
| `createtime` | `bigint(16)` | 创建时间 |
| `updatetime` | `bigint(16)` | 更新时间 |

> **设计决策**: 
> - 用 `type` 字段区分父商品行和子商品行，所有数据存在同一张表中，查询方便
> - `goods_code` 和 `goods_name` 冗余存储，避免每次列表展示都需要 JOIN 商品表
> - BOM 明细表比组装单模板表 (`kuerp_assemble_info_temp`) 更简洁，不需要批次/序列号/审批等制造执行层字段

### 2.4 ER 关系图

```
┌─────────────────────┐
│   kuerp_goods       │
│─────────────────────│
│ id (PK)             │
│ goods_code          │
│ goods_name          │
│ goods_type_id (FK)  │
│ product_type (NEW)  │◀── 成品 / 半成品 / 零件
│ inventory           │
│ ...                 │
└──────┬──────────────┘
       │
       │ 1:N (父商品)
       ▼
┌─────────────────────────┐
│   kuerp_goods_bom       │
│─────────────────────────│
│ id (PK)                 │
│ bom_name                │
│ goods_id (FK → goods)   │─── 父商品(成品)
│ goods_code              │
│ remark                  │
└──────┬──────────────────┘
       │
       │ 1:N (子商品明细)
       ▼
┌──────────────────────────┐
│   kuerp_goods_bom_info   │
│──────────────────────────│
│ id (PK)                  │
│ bom_id (FK → bom, INDEX) │
│ type (1=父 / 2=子)        │
│ goods_id (FK → goods)    │─── 子商品
│ goods_code / goods_name  │
│ unit_id                  │
│ number (单耗数量)         │
└──────────────────────────┘
       │
       │ 计算器查询库存
       ▼
┌──────────────────────┐
│   kuerp_goods_stock  │
│──────────────────────│
│ goods_code           │
│ stores_id            │
│ stock (库存数量)      │
│ ...                  │
└──────────────────────┘
```

---

## 3. 商品类型分类

### 3.1 三种类型定义

| 类型值 | 中文名称 | 含义 | 典型场景 |
|--------|----------|------|----------|
| `finished` | 成品 | 可直接销售/出库的最终产品 | A型发电机、B型水泵 |
| `semi_finished` | 半成品 | 需要进一步加工/组装，也可作为子件 | 电机绕组、泵体铸件 |
| `part` | 零件 | 原材料/零部件，用于组装成品或半成品 | 螺栓、密封圈、漆包线 |

### 3.2 涉及文件

| 文件路径 | 作用 |
|----------|------|
| `application/admin/model/kuerp/Goods.php` | 定义 `getProductTypeList()` 枚举映射、`getProductTypeTextAttr()` 文本转换、`$append` 追加属性 |
| `application/admin/controller/kuerp/Goods.php` | `_initialize()` 向视图传递 `productTypeList` |
| `application/admin/view/kuerp/goods/add.html` | 添加商品表单 → 商品类型下拉选择框 |
| `application/admin/view/kuerp/goods/edit.html` | 编辑商品表单 → 商品类型下拉选择框（预填当前值） |
| `application/admin/view/kuerp/goods/index.html` | 列表页搜索栏 → 商品类型筛选下拉 |
| `public/assets/js/backend/kuerp/goods.js` | 表格列定义 `product_type` 字段、搜索参数收集 |
| `application/admin/lang/zh-cn/kuerp/goods.php` | 语言包：`Product_type`、`Product_type finished` 等 |

### 3.3 模型关键代码

```php
// Goods.php Model
protected $append = [
    'more_unit_text',
    'is_offten_text',
    'status_text',
    'product_type_text'    // 新增：自动追加文本值
];

public function getProductTypeList()
{
    return [
        'finished'       => __('Product_type finished'),       // 成品
        'semi_finished'  => __('Product_type semi_finished'),  // 半成品
        'part'           => __('Product_type part')            // 零件
    ];
}
```

### 3.4 用户操作流程

1. 进入 **资料 → 商品管理**
2. 点击 **添加商品** 或 **编辑已有商品**
3. 在 "商品类别" 下方找到新的 **"商品类型"** 下拉框
4. 选择 **成品 / 半成品 / 零件**
5. 保存

---

## 4. BOM模板管理

### 4.1 功能说明

BOM（物料清单）模板定义一个成品由哪些子件（半成品/零件）组成，每个子件需要多少数量（单耗）。

**核心规则**：
- 一个 BOM 模板的**父商品只有一个**（通常是成品，也可以是半成品）
- **子商品可以有多个**，子商品可以是成品、半成品或零件（无类型限制）
- 每个子商品需要填写**单耗数量**（生产1个单位父商品需要多少该子件）

### 4.2 涉及文件

| 文件路径 | 作用 |
|----------|------|
| `application/admin/model/kuerp/GoodsBom.php` | BOM主表 ORM 模型，关联 `admin`（创建人）和 `bomInfo`（明细） |
| `application/admin/model/kuerp/GoodsBomInfo.php` | BOM明细表 ORM 模型 |
| `application/admin/controller/kuerp/GoodsBom.php` | CRUD 控制器 + `getbomdetail()`（供计算器调用）+ `chosegoods()`（商品搜索） |
| `application/admin/view/kuerp/goods_bom/index.html` | 列表页，合并单元格展示模板 |
| `application/admin/view/kuerp/goods_bom/add.html` | 添加页：模板名 + 父商品选择 + 动态子商品表格 |
| `application/admin/view/kuerp/goods_bom/edit.html` | 编辑页：预填已有数据，结构同 add |
| `public/assets/js/backend/kuerp/goods_bom.js` | 前端交互：表格渲染、商品搜索下拉、动态增删子商品行 |
| `application/admin/lang/zh-cn/kuerp/goods_bom.php` | 语言包 |

### 4.3 控制器核心方法

```
GoodsBom 控制器:
├── index()         → 列表页：查询所有BOM + JOIN子商品信息 + 合并单元格展示
├── add()           → 添加：POST接收 row(主表) + children(子商品数组)，事务写入
├── edit($ids)      → 编辑：先删旧明细，再写入新明细（事务）
├── del($ids)       → 删除：同时删除主表和明细表记录
├── getbomdetail()  → 供计算器调用：返回BOM完整结构（父+子商品列表）
└── chosegoods()    → 供前端搜索：按编号/名称/条码模糊查询商品
```

### 4.4 添加/编辑数据结构

**POST 数据格式**（add/edit 提交时）：

```json
{
  "row": {
    "bom_name": "A型发电机BOM",
    "goods_id": "1001",
    "remark": "标准配置"
  },
  "children": [
    {
      "goods_id": "2001",
      "number": "3",
      "unit_id": "5",
      "remark": "b配件"
    },
    {
      "goods_id": "2002",
      "number": "4",
      "unit_id": "5",
      "remark": "c配件"
    },
    {
      "goods_id": "2003",
      "number": "8",
      "unit_id": "5",
      "remark": "d配件"
    }
  ]
}
```

**写入数据库后的记录**：

```
kuerp_goods_bom:
  id=1, bom_name="A型发电机BOM", goods_id=1001, goods_code="A-GEN-001"

kuerp_goods_bom_info:
  id=1, bom_id=1, type=1, goods_id=1001, goods_code="A-GEN-001", number=1      (父商品行)
  id=2, bom_id=1, type=2, goods_id=2001, goods_code="B-PART-001", number=3     (b配件)
  id=3, bom_id=1, type=2, goods_id=2002, goods_code="C-PART-001", number=4     (c配件)
  id=4, bom_id=1, type=2, goods_id=2003, goods_code="D-PART-001", number=8     (d配件)
```

### 4.5 前端交互

**商品搜索下拉**：
1. 用户在父商品/子商品输入框输入编号或名称
2. 前端 300ms 防抖后 AJAX 调用 `kuerp/goods_bom/chosegoods?search=xxx`
3. 返回匹配的商品列表（含商品类型标签：`[成品]`/`[半成品]`/`[零件]`）
4. 用户点击选中商品，自动填充隐藏的 `goods_id` 和显示的类型标签

**动态子商品行**：
- 添加页面默认显示 4 行空行
- 点击 **"+添加子商品"** 按钮可新增行
- 每行有独立的商品搜索、单耗数量、备注和删除按钮
- 删除行后自动重新索引剩余行

### 4.6 用户操作流程

1. 进入 **仓库 → 商品BOM模板**
2. 点击 **添加**，输入模板名称（如"A型发电机BOM"）
3. 在 **父商品** 搜索框中输入成品编号/名称，选择目标成品
4. 在 **子商品列表** 中逐行搜索并选择子件，填写单耗数量
5. 点击 **确定** 保存
6. 列表页显示所有 BOM 模板，可编辑/删除

---

## 5. 商品计算器

### 5.1 功能说明

选择一个 BOM 模板后，系统自动查询各子件的当前库存，计算理论最大可组装成品数量。

**页面结构**：

```
┌────────────────────────────────────────────┐
│  选择BOM模板: [下拉选择]  [选择仓库]  [开始计算] │
├────────────────────────────────────────────┤
│                                            │
│       最大可组装成品数量                      │
│                                            │
│          ┌─────────────┐                   │
│          │     42      │  ← 大号绿色数字     │
│          └─────────────┘                   │
│                                            │
│        父商品: A型发电机 (A-GEN-001)         │
│                                            │
│  ⚠ 瓶颈提示: b配件 库存不足，限制了最大产量    │
│                                            │
├────────────────────────────────────────────┤
│  子商品    │ 单耗 │ 当前库存 │ 可支持生产 │ 瓶颈 │
│───────────┼──────┼─────────┼──────────┼─────│
│  b配件     │  3   │   128   │    42    │ ⚠是 │  ← 红色高亮行
│  c配件     │  4   │   500   │   125    │  否 │
│  d配件     │  8   │   800   │   100    │  否 │
└────────────────────────────────────────────┘
```

### 5.2 涉及文件

| 文件路径 | 作用 |
|----------|------|
| `application/admin/controller/kuerp/GoodsCalculator.php` | 计算器控制器：`index()` 页面渲染 + `calculate()` 核心计算 + `getbomlist()` |
| `application/admin/view/kuerp/goods_calculator/index.html` | 计算器页面：BOM选择器 + 仓库筛选 + 结果卡片 + 明细表格 |
| `public/assets/js/backend/kuerp/goods_calculator.js` | 前端交互：AJAX计算请求、结果渲染、瓶颈高亮 |
| `application/admin/lang/zh-cn/kuerp/goods_calculator.php` | 语言包 |

### 5.3 控制器核心方法

```
GoodsCalculator 控制器:
├── index()       → 渲染页面，传入BOM列表和仓库列表
├── calculate()   → 核心计算逻辑（详见第6章）
└── getbomlist()  → 返回BOM模板下拉列表
```

### 5.4 `calculate()` 详细流程

```
输入: bom_id (BOM模板ID), stores_id (可选，仓库ID)
输出: JSON { bom_name, parent_goods_name, max_assemble_count,
             bottleneck[], children[] }

步骤:
  1. 根据 bom_id 查询 kuerp_goods_bom 主表
  2. 查询 type=1 的行获取父商品信息
  3. 查询 type=2 的行获取所有子商品及单耗
  4. 遍历每个子商品:
     a. 查询 kuerp_goods_stock 表:
        - WHERE goods_code = 子商品编号
        - WHERE stores_id = 指定仓库 (如果传了)
        - SUM(stock) 汇总总库存
     b. 可生产数 = floor(总库存 / 单耗)
  5. 最大可组装数 = MIN(所有可生产数)
  6. 瓶颈 = 可生产数 == 最大值的所有子商品
  7. 组装结果返回前端
```

### 5.5 用户操作流程

1. 进入 **仓库 → 商品计算器**
2. 在 **"选择BOM模板"** 下拉框中选择一个模板（选择后自动触发计算）
3. （可选）选择特定仓库筛选
4. 点击 **"开始计算"** 或等待自动计算
5. 查看结果：
   - 顶部卡片显示 **最大可组装数量**
   - 瓶颈提示列出限制产量的子件
   - 明细表格展示每个子件的库存和可支持生产数

---

## 6. 核心算法

### 6.1 数学公式

设 BOM 模板中有 n 个子件，子件 i 的：
- 单耗数量为 $C_i$ (consumption)
- 当前总库存为 $S_i$ (stock)

则该子件可支持的成品生产数：  
$$P_i = \lfloor \frac{S_i}{C_i} \rfloor$$

**最大可组装成品数量**：  
$$M = \min(P_1, P_2, ..., P_n)$$

**瓶颈商品集合**：  
$$B = \{ i \mid P_i = M \}$$

### 6.2 代码实现（PHP）

```php
// GoodsCalculator.php → calculate()

$children = Db::name('kuerp_goods_bom_info')->alias('a')
    ->join('kuerp_goods g', 'g.id=a.goods_id')
    ->where(['a.bom_id' => $bomId, 'a.type' => '2'])
    ->field('a.*,g.goods_name,g.product_type')
    ->select();

$maxProducibleList = [];
foreach ($children as $child) {
    // SUM 所有仓库库存
    $totalStock = Db::name('kuerp_goods_stock')
        ->where(['goods_code' => $child['goods_code']])
        ->sum('stock');
    $totalStock = floatval($totalStock ?: 0);

    $consumption = floatval($child['number']);
    $producible = $consumption > 0 ? floor($totalStock / $consumption) : 0;
    $maxProducibleList[] = $producible;
}

// 核心: 取最小值
$maxAssembleCount = !empty($maxProducibleList) ? min($maxProducibleList) : 0;
```

### 6.3 计算示例

**场景**: A型发电机 = 3个b配件 + 4个c配件 + 8个d配件

| 子商品 | 单耗 | 当前库存 | 可支持生产数 | 计算 |
|--------|------|----------|-------------|------|
| b配件  | 3    | 128      | **42**      | floor(128/3) = 42 |
| c配件  | 4    | 500      | 125         | floor(500/4) = 125 |
| d配件  | 8    | 800      | 100         | floor(800/8) = 100 |

**最大可组装数** = MIN(42, 125, 100) = **42 个**  
**瓶颈商品** = b配件（库存仅够生产 42 个，其他配件还有余量）

---

## 7. 文件清单

### 7.1 新建文件（12个）

```
application/admin/
├── controller/kuerp/
│   ├── GoodsBom.php              # BOM模板控制器 (CRUD + getbomdetail + chosegoods)
│   └── GoodsCalculator.php       # 商品计算器控制器 (index + calculate + getbomlist)
│
├── model/kuerp/
│   ├── GoodsBom.php              # BOM主表模型
│   └── GoodsBomInfo.php          # BOM明细表模型
│
├── view/kuerp/
│   ├── goods_bom/
│   │   ├── index.html            # BOM模板列表页（合并单元格）
│   │   ├── add.html              # BOM模板添加页
│   │   └── edit.html             # BOM模板编辑页
│   └── goods_calculator/
│       └── index.html            # 商品计算器页面
│
└── lang/zh-cn/kuerp/
    ├── goods_bom.php             # BOM模板语言包
    └── goods_calculator.php      # 计算器语言包

public/assets/js/backend/kuerp/
├── goods_bom.js                  # BOM模板前端JS
└── goods_calculator.js           # 计算器前端JS
```

### 7.2 修改文件（9个）

```
application/admin/
├── model/kuerp/Goods.php         # 新增 product_type_text 属性 + 枚举方法
├── controller/kuerp/Goods.php    # _initialize 传 productTypeList 到视图
├── view/kuerp/goods/
│   ├── add.html                  # 新增商品类型下拉框
│   ├── edit.html                 # 新增商品类型下拉框
│   └── index.html                # 搜索栏新增商品类型筛选
└── lang/zh-cn/kuerp/goods.php    # 新增 4 个语言项

public/assets/js/backend/kuerp/
└── goods.js                      # 表格列 + 搜索逻辑新增 product_type

addons/kuerp/
├── Kuerp.php                     # 仓库菜单下新增 BOM模板 + 计算器 两个菜单
└── install_stone.sql             # 追加 ALTER TABLE + CREATE TABLE x2
```

---

## 8. 数据库迁移SQL

```sql
-- ============================================================
-- 商品类型分类 + BOM模板 + 商品计算器
-- ============================================================

-- 1. 商品类型字段
ALTER TABLE `fa_kuerp_goods`
  ADD COLUMN `product_type` enum('finished','semi_finished','part')
  DEFAULT 'part'
  COMMENT '商品类型:finished=成品,semi_finished=半成品,part=零件'
  AFTER `goods_type_id`;

-- 2. BOM模板主表
CREATE TABLE `fa_kuerp_goods_bom` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `bom_name` varchar(255) NOT NULL COMMENT '模板名称',
  `goods_id` int(11) NOT NULL COMMENT '父商品ID(成品)',
  `goods_code` varchar(50) DEFAULT NULL COMMENT '父商品编号',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品BOM模板主表';

-- 3. BOM模板明细表
CREATE TABLE `fa_kuerp_goods_bom_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `bom_id` int(11) NOT NULL COMMENT 'BOM模板ID',
  `type` enum('1','2') NOT NULL DEFAULT '2' COMMENT '类型:1=父商品,2=子商品',
  `goods_id` int(11) NOT NULL COMMENT '商品ID',
  `goods_code` varchar(50) DEFAULT NULL COMMENT '商品编号',
  `goods_name` varchar(255) DEFAULT NULL COMMENT '商品名称(冗余)',
  `unit_id` int(11) DEFAULT NULL COMMENT '单位ID',
  `number` double(15,4) NOT NULL DEFAULT '0.0000' COMMENT '单耗数量(每个成品需要的子件数)',
  `remark` varchar(255) DEFAULT NULL COMMENT '备注',
  `createtime` bigint(16) DEFAULT NULL,
  `updatetime` bigint(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bom_id` (`bom_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品BOM模板明细表';
```

---

## 9. 安装与部署

### 9.1 初次安装

1. **执行数据库迁移** — 在 MySQL 中运行第8章的 SQL
2. **升级插件菜单** — 后台 → 插件管理 → kuerp → 升级（触发 `Menu::upgrade()` 写入新菜单）
3. **清除缓存** — 删除 `runtime/cache/` 和 `runtime/temp/` 目录内容
4. **刷新后台** — 重新登录后台，左侧菜单"仓库"下应出现两个新菜单项

### 9.2 权限配置

新菜单会自动创建对应的 auth_rule 记录。管理员需要在 **权限管理 → 角色组** 中勾选以下权限：

- `kuerp/goods_bom` 及其子权限（index/add/edit/del/getbomdetail/chosegoods）
- `kuerp/goods_calculator` 及其子权限（index/calculate/getbomlist）

### 9.3 插件重装/升级

`install_stone.sql` 已追加相关 SQL，插件重新安装或通过 `install_stone.sql` 导入时会自动创建表结构。

---

## 10. 业务场景示例

### 场景一：简单BOM（全部零件）

> **成品**: A型发电机  
> **BOM**: 3个b配件 + 4个c配件 + 8个d配件

| 步骤 | 操作 |
|------|------|
| 1 | 商品管理 → 设置 A型发电机 类型=成品, b/c/d配件 类型=零件 |
| 2 | 仓库 → BOM模板 → 新建 "A型发电机BOM"，父商品=A型发电机，子商品=b(3)+c(4)+d(8) |
| 3 | 仓库 → 商品计算器 → 选 "A型发电机BOM" → 自动显示最大可组装数 |

### 场景二：含半成品的BOM

> **成品**: B型水泵  
> **BOM**: 2个电机绕组(半成品) + 6个密封圈(零件) + 1个泵体铸件(半成品)  
> **电机绕组BOM**: 5个漆包线(零件) + 1个铁芯(零件)

> **注意**: 当前版本仅支持单层BOM。半成品"电机绕组"自身的BOM需要作为独立模板管理，计算器不会自动递归展开。如需计算B型水泵的最终产能，需要先确保半成品有足够库存。

### 场景三：库存为零

> **成品**: C型控制柜  
> **BOM**: 2个PLC模块(零件) + 1个触摸屏(零件)  
> **当前库存**: PLC模块=10, 触摸屏=0

| 子商品 | 单耗 | 库存 | 可生产 |
|--------|------|------|--------|
| PLC模块 | 2 | 10 | 5 |
| 触摸屏  | 1 | **0** | **0** ← 瓶颈 |

**最大可组装数 = 0**，瓶颈为触摸屏。页面显示红色 0 和警告提示。

---

## 11. 边界情况与异常处理

| 边界情况 | 处理方式 |
|----------|----------|
| **子件库存为 0** | 该子件的 `producible = 0`，整体 `max = 0`，标记为瓶颈 |
| **单耗数量为 0 或空** | 计算时 `consumption > 0` 检查，为 0 时 `producible = 0` |
| **BOM 模板无子商品** | 提交时验证 `children` 数组非空；计算时返回错误提示 |
| **BOM 模板被删除** | 删除主表时联删明细表（事务）；计算器列表不显示已删除模板 |
| **商品被删除/下架** | `chosegoods()` 仅返回 `status=1` 的商品；历史 BOM 中的商品仍可计算 |
| **子商品有辅助属性（多规格）** | 库存按 `goods_code` 汇总（不区分 attribute_id），取所有规格总库存 |
| **子商品开启批次管理** | 计算器不关心批次，汇总 `kuerp_goods_stock.stock` 总量 |
| **跨仓库库存** | 默认汇总所有仓库库存；分仓模式下可筛选指定仓库 |
| **多单位商品** | 使用基础单位库存；BOM 中的单耗也基于基础单位 |
| **同一子商品在 BOM 中重复出现** | 各自独立计算（两条明细行），互不影响 |

---

## 12. 后续扩展建议

1. **多层BOM递归计算**  
   当前仅支持单层 BOM。可以扩展为：当子商品是半成品时，自动查找该半成品自身的 BOM，递归展开到零件层级再汇总计算。

2. **BOM版本管理**  
   支持同一成品的多个 BOM 版本（如 v1.0/v2.0），可追溯历史变更。

3. **一键生成组装单**  
   计算器结果页增加"生成组装单"按钮，根据计算出的最大数量自动创建组装单（复用现有 `Assemble` 控制器逻辑）。

4. **缺料预警**  
   结合销售订单，自动计算还需要采购多少零件才能满足订单需求。

5. **成本汇总**  
   在 BOM 模板中展示各子件的参考成本，自动汇总成品的物料成本。

6. **Excel导入导出**  
   支持从 Excel 批量导入 BOM 模板，导出计算结果为 Excel。

7. **库存预留**  
   计算最大可组装数时，可选择是否排除已下销售订单但未出库的库存（可用库存 vs 实际库存）。

---

> **文档维护**: 本功能涉及的所有代码变更均已完成于 2026-06-04。如需了解更细粒度的实现细节，请直接阅读上述文件清单中的源代码。
