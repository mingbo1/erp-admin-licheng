# 酷柚易汛进销存 (kuerp) 插件运行逻辑

> 基于 FastAdmin (ThinkPHP 5.0) 插件机制开发的开源进销存管理系统，解决采购、销售、仓库、资金四大经营问题。

---

## 1. 插件架构概览

```
addons/kuerp/
├── Kuerp.php          # 插件入口，菜单定义，生命周期钩子
├── config.php         # 插件配置
├── info.ini           # 插件元信息（名称、版本、作者）
├── .addonrc           # 插件文件清单与授权信息
├── install.sql        # 安装SQL（建表语句）
├── controller/        # 前台控制器
│   └── Index.php      # 前台入口（无前台页面）
├── library/           # 核心业务逻辑库
│   ├── Goods.php      # 公用方法 + 成本计算
│   ├── GoodsPurchase.php  # 采购业务逻辑
│   ├── GoodsSales.php     # 销售业务逻辑
│   └── Checkout.php   # 结账/反结账业务
└── model/             # 数据模型层
    ├── Goods.php      # 商品模型
    ├── GoodsStock.php # 库存模型
    ├── Checkout.php   # 结账模型
    ├── Sets.php       # 系统设置模型
    ├── ...            # 其他业务模型
```

### 1.1 插件生命周期

| 方法 | 触发时机 | 行为 |
|------|---------|------|
| `install()` | 插件安装 | `Menu::create($this->menu)` 创建菜单 |
| `uninstall()` | 插件卸载 | `Menu::delete("kuerp")` 删除菜单 |
| `enable()` | 插件启用 | `Menu::enable("kuerp")` 启用菜单 |
| `disable()` | 插件禁用 | `Menu::disable("kuerp")` 禁用菜单 |
| `upgrade()` | 插件升级 | `Menu::upgrade('kuerp', $this->menu)` 同步菜单变更 |

### 1.2 数据流向

```
用户操作 → 后台Controller → Model (数据层)
                              └→ Library (业务逻辑层)
                                    ├→ 成本计算 (Goods::getCost_Price)
                                    ├→ 价格计算 (GoodsSales::formatInfo2)
                                    ├→ 单据审核 (Controller examine 方法)
                                    └→ 结账计算 (Checkout::checkOutGoods)
```

---

## 2. 菜单系统

菜单定义在 `Kuerp.php` 的 `$menu` 属性中，为 6 级嵌套结构：

```
进销存管理 (kuerp)
├── 数据面板 (datainfo)
├── 购货 (buy)
│   ├── 购货订单 → 查看/添加/编辑/删除/审核
│   ├── 购货单 → 查看/添加/编辑/删除/审核/对单
│   ├── 购货退货单 → 查看/添加/编辑/删除/审核/对单
│   ├── 采购明细表/采购订单跟踪表
│   ├── 采购汇总表(按商品/供应商)
│   ├── 采购付款一览表
│   ├── 采购开票管理
│   ├── 以销定购看板
│   ├── 智能补货
│   └── 原始单据
├── 销货 (sales)
│   ├── 销货订单 → 查看/添加/编辑/删除/审核
│   ├── 销货单 → 查看/添加/编辑/删除/审核/对单
│   ├── 销货退货单 → 查看/添加/编辑/删除/审核/对单
│   ├── 销售订单跟踪表/商品销售明细
│   ├── 销货开票管理/销货收款一览表
│   ├── 销售汇总表(按商品/客户/销售员)
│   ├── 销售利润表/销售排行表
│   ├── 往来单位欠款表
│   └── 原始单据
├── 仓库 (stores)
│   ├── 调拨单 → 查看/添加/编辑/删除/审核
│   ├── 成本调整单 → 查看/添加/编辑/删除/批量更新
│   ├── 盘点/序列号盘点 → 导入/查看/添加/编辑/删除/导出
│   ├── 其他入库单/其他出库单
│   ├── 组装单/拆卸单 + 模板保存
│   ├── 库存预警/商品库存余额表
│   ├── 批次跟踪表/商品收发明细表/收发汇总表
│   └── 序列号跟踪表/序列号状态表/批次保质期清单
├── 资金 (capital)
│   ├── 付款单/收款单/核销单
│   ├── 应收账款明细表/应付账款明细表
│   ├── 其他收入单/其他支出单
│   ├── 采购销售费用清单/资金转账单
│   ├── 现金银行报表
│   ├── 客户对账单/供应商对账单
│   ├── 其他收支明细表/利润表
├── 资料 (defaultset)
│   ├── 客户/供应商/账户/商品/仓库/职员/发货地址
│   └── 辅助资料（客户等级/分类、商品分类、支出/收入类别、结算方式、计量单位、辅助属性、客户物料编码）
└── 设置 (sets)
    ├── 系统参数
    ├── 系统初始化
    └── 结账/反结账
```

---

## 3. 单据业务流

### 3.1 采购业务流程

```
购货订单 → 审核 → 生成购货单（部分/全部）
                                       
购货单 → 审核 → 更新库存 + 生成采购明细
       → 对单 → 标记对账状态
                                       
购货退货单 → 审核 → 冲减库存
```

### 3.2 销售业务流程

```
销货订单 → 审核 → 生成销货单（部分/全部）

销货单 → 审核 → 计算成本 + 更新库存 + 生成销售明细
       → 对单 → 标记对账状态

销货退货单 → 审核 → 冲减库存 + 冲销成本
```

### 3.3 资金业务流程

```
付款单/收款单 → 审核 → 更新账户余额
核销单        → 审核 → 核销应收/应付账款
资金转账单    → 审核 → 账户间资金转移
其他收入/支出 → 审核 → 其他收支记录
```

### 3.4 仓库业务流程

```
调拨单    → 审核 → 源仓库扣减 + 目标仓库增加
组装单    → 审核 → 子件出库 + 成品入库
拆卸单    → 审核 → 成品出库 + 子件入库
盘点单    → 审核 → 盘盈/盘亏调整
成本调整单 → 审核 → 加权平均成本调整
其他出入库 → 审核 → 非采购/销售的库存变动
```

---

## 4. 核心业务逻辑

### 4.1 成本计算 (`Goods::getCost_Price`)

支持两种计价方式：

**移动平均法** (`valuation=1`)：
- 每次采购入库后重新计算加权平均成本
- 销售出库时按当前加权平均成本结转
- 公式：`(期初金额 + 本期入库金额) / (期初数量 + 本期入库数量)`

**先进先出法** (`valuation=2`)：
- 按采购入库的时间顺序依次出库
- 先入库的商品成本先被销售出库
- 逐笔匹配采购入库记录与销售出库记录

核心算法（`getCost_Price` 方法）：
```php
while ($now_nmber > 0) {  // 循环扣减库存数量
    foreach ($purchase_info as $v) {
        if ($now_nmber >= $v['basic_nmber']) {
            // 全部消耗该批采购
        } else {
            // 部分消耗，计算加权/个别单价
        }
    }
}
$cost = $now_all_money / $now_nmbers;  // 单位成本
```

### 4.2 结账/反结账机制

**结账**时执行以下操作：
1. 遍历已审核的销售单据
2. 按商品代码逐一计算销售成本
3. 计算毛利、毛利率、净利等财务指标
4. 保存结账快照（`kuerp_checkout` + `kuerp_checkout_info`）
5. 时间锁定：不能修改小于上次结账日期的数据

**反结账**撤销上次结账快照，恢复到结账前状态。

### 4.3 价格与税金计算 (`GoodsSales::formatInfo2`)

价格联动逻辑（可修改字段触发连锁计算）：
```
修改字段 → 联动计算的字段
─────────────────────────────────
商品单位 → 含税单价/单价/价税合计/折扣额/购货金额/税额
数量     → 价税合计/折扣额/购货金额/税额
单价     → 含税单价/价税合计/折扣额/购货金额/税额
含税单价 → 单价/价税合计/折扣额/购货金额/税额
税率     → 含税单价/价税合计/折扣额/购货金额/税额
折扣率   → 折扣额/价税合计/购货金额/税额
折扣额   → 折扣率/价税合计/购货金额/税额
购货金额 → 价税合计/税额/含税单价/单价/折扣额
价税合计 → 购货金额/税额/含税单价/单价/折扣额
```

核心公式：
```
单价(P) = 含税单价 * 100 / (100 + 税率)
购货金额(A) = 价税合计 * 100 / (100 + 税率)
税额(T) = 购货金额 * 税率 / 100
折扣额 = 价税合计 / (100 + 税率) * 折扣率
```

含税启用判断（`price_taxes` 设置）：
- 含税模式：录入含税单价，反算不含税单价
- 不含税模式：录入不含税单价，自动计算含税单价

### 4.4 自动编号生成 (`Goods::getCodes`)

编号规则（支持自定义前缀）：
```
[前缀][年(YYYY)][月(MM)][日(DD)][时(HH)][分(ii)][秒(ss)][流水号]
```

配置字段：
- `prefix`：编号前缀
- `year/month/day/hour/minute/second`：是否启用对应时间戳
- `strlen`：流水号长度
- `start`：起始数字
- `dbname/field`：查重的数据库表和字段

生成策略：
1. 查当前表最大编号
2. 按规则截取数字部分并 +1
3. 用前缀和时间戳补全
4. 检查数据库中是否已存在，若存在则递归+1

---

## 5. 核心数据表结构

| 表名 | 说明 | 关键字段 |
|------|------|---------|
| `kuerp_goods` | 商品主表 | goods_code, purchase, unit_id, more_unit, auxiliary, batch, serial |
| `kuerp_goods_stock` | 商品库存 | goods_code, stores_id, stock |
| `kuerp_goods_purchase` | 购货单 | purchase_code, suppliers_code, examine_status, all_money |
| `kuerp_goods_purchase_info` | 购货明细 | purchase_code, goods_code, basic_nmber, unit_price, all_money |
| `kuerp_goods_purchase_order` | 购货订单 | purchase_code, examine_status |
| `kuerp_goods_sales` | 销货单 | purchase_code, examine_status, all_money |
| `kuerp_goods_sales_info` | 销货明细 | purchase_code, goods_code, basic_nmber, all_money |
| `kuerp_goods_sales_profit` | 销售利润 | purchase_code, sales_cost, profit |
| `kuerp_goods_exchange` | 购货退货 | purchase_code, examine_status |
| `kuerp_goods_sales_exchange` | 销货退货 | purchase_code, examine_status |
| `kuerp_goods_batch` | 批次信息 | goods_code, batch_name, manufacture, guarantee, validity |
| `kuerp_goods_serial` | 序列号 | goods_code, serial_no, stores_id, status |
| `kuerp_checkout` | 结账记录 | checkdate, checktime |
| `kuerp_checkout_info` | 结账快照 | checkout_id, goods_code, purchase_id, basic_nmber, all_money |
| `kuerp_sets` | 系统设置 | name, value, is_checked |
| `kuerp_prefix` | 编号规则 | type, prefix, year, month, day, strlen, start |
| `kuerp_stores` | 仓库 | name, status |
| `kuerp_customers` | 客户 | customer_code, name, customerlevel_id |
| `kuerp_suppliers` | 供应商 | supplyer_code, name, vat_rate |
| `kuerp_accounts` | 账户 | name, money |
| `kuerp_unit` | 计量单位 | name |
| `kuerp_unit_conversion` | 单位换算 | main_unit, vice_unit, advance_rate |
| `kuerp_goods_price` | 商品价格 | goods_id, unit, purchase, market_sell_price |
| `kuerp_goods_attr` | 商品属性 | goods_id, attribute_id, purchase |
| `kuerp_auxiliary` | 辅助属性 | name, status |
| `kuerp_aux_combination` | 属性组合 | attr_ids |
| `kuerp_adjustment_info` | 成本调整 | goods_id, stores_id, money, purchase_time |
| `kuerp_goods_period` | 期初库存 | goods_code, period, price |
| `kuerp_payment` | 付款单 | payment_code, examine_status |
| `kuerp_collection` | 收款单 | collection_code, examine_status |
| `kuerp_writeoff` | 核销单 | writeoff_code, examine_status |
| `kuerp_allocation` | 调拨单 | allocation_code, examine_status |
| `kuerp_inventory` | 盘点单 | inventory_code, examine_status |
| `kuerp_assemble` | 组装单 | assemble_code, examine_status |
| `kuerp_split` | 拆卸单 | split_code, examine_status |

---

## 6. 审核与状态流转

### 6.1 审核/反审核

每个单据都有 `examine_status` 字段，控制状态：
- 每个单据Controller统一提供 `examine` 方法处理审核/反审核
- 审核后触发库存变动、成本计算等下游操作
- 结账日期前的数据不可操作（`Checkout::checkOutTime`）

### 6.2 对单/反对单

采购和销售单据支持对账标记，用于与供应商/客户确认单据一致性。

---

## 7. 系统特性

### 7.1 多计量单位
- 单计量单位：一个商品只有一个基本单位
- 多计量单位：通过 `kuerp_unit_conversion` 表配置多单位间的换算进率
- 前端自动根据选择的单位换算单价和库存

### 7.2 商品辅助属性
- 自定义属性维度（颜色、尺寸等）
- 属性值笛卡尔积生成完整SKU
- 每个SKU可单独设价

### 7.3 批次与序列号
- 批次管理：生产日期、保质期、有效期跟踪
- 序列号管理：一物一码，全流程追踪

### 7.4 分仓核算
- 通过 `stores` 设置控制是否启用分仓核算
- 启用后每个仓库独立核算库存成本

### 7.5 系统初始化
- 清除所有业务数据，保留基础资料
- 重置期初库存、系统参数

---

## 8. 单元/集成测试规范

测试遵循以下分层结构：

### 8.1 测试目录
```
tests/
├── Unit/           # 单元测试
│   ├── Library/
│   │   ├── GoodsTest.php            # 成本计算、编码生成
│   │   ├── GoodsPurchaseTest.php    # 采购价格计算
│   │   ├── GoodsSalesTest.php       # 销售价格计算
│   │   └── CheckoutTest.php         # 结账逻辑
│   └── Model/
│       ├── GoodsTest.php
│       └── GoodsStockTest.php
├── Feature/        # 功能/集成测试
│   ├── PurchaseFlowTest.php         # 采购全流程
│   ├── SalesFlowTest.php            # 销售全流程
│   ├── InventoryFlowTest.php        # 库存相关流程
│   ├── CapitalFlowTest.php          # 资金相关流程
│   └── CheckoutFlowTest.php         # 结账全流程
└── TestCase.php    # 基础测试类
```

### 8.2 测试规范

- **命名**：测试方法名使用 `testXxx_描述` 格式
- **隔离**：每个测试独立事务，测试结束后回滚
- **数据**：使用 `setUp()` 创建测试数据，`tearDown()` 清理
- **断言**：优先断言业务结果（如库存变动、金额计算），而非SQL执行
