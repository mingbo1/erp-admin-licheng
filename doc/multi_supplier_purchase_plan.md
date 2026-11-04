# 采购订单多供应商改造方案

> 最后更新: 2026-07-12
> 设计原则: 复杂度隔离在"采购订单→采购单"这一步，采购单及之后流程（付款/核销/发票）保持原样不变

---

## 一、现状分析

### 1.1 当前数据流

```
采购订单 (1个供应商)
  │ fa_kuerp_goods_purchase_order.supplyer_code = "GYS001"
  │ fa_kuerp_goods_purchase_order_info.supplyer_code = "GYS001" (每条行)
  │
  └── 生成采购单 (手动点击) → 1个采购单
       │ fa_kuerp_goods_purchase.supplyer_code = "GYS001"
       │
       ├── 审核(_examine) →
       │   ├── payment_log (1条, 按suppliers_code)
       │   ├── invoicing (1条, 按bill_code=采购单号)
       │   ├── writeoff_source (1条, 核销源单)
       │   └── accounts_copewith (1条, 应付账款)
       │
       └── 生成付款单 →
           └── kuerp_payment_sale (1条, purchase_number=采购单号)
```

**核心问题**: `supplyer_code` 在采购订单层面是表单级单值（前端 `$('#supplyer_code').val()` 全局读取），不能表达"一个订单里不同商品来自不同供应商"。

### 1.2 关键代码现状（已验证）

| 层 | 文件 | 关键发现 |
|---|------|---------|
| 前端JS | `goods_purchase_order.js` | 所有 Ajax 调用（`changeGoods`、`formatInfo`、`changeGoodsInfo`、`initlist`）都读全局 `$('#supplyer_code').val()`，**无逐行供应商概念** |
| 前端HTML | `goods_purchase_order/add.html` | 商品表无供应商列，只有表头 `<select id="supplyer_code" data-rule="required">` |
| 后端 | `GoodsPurchaseOrder.php` | `add()`/`edit()` 从 `$params['supplyer_code']` 取单值写入所有行 |
| 后端 | `GoodsPurchaseOrder.php::index()` | 已通过 `kuerp_goods_purord` 桥表动态查询关联采购单列表 |
| 后端 | `GoodsPurchase.php::add($ids)` | GET 预填时仅读取表头 `$row['supplyer_code']`，无分组概念 |
| 后端 | `GoodsPurchase.php::_examine()` | 每个采购单独立审核，`supplyer_code` 来自采购单自身表头 |
| 数据库 | `goods_purchase_order_info` | **已有** `supplyer_code` 列，但当前存的是表头值 |
| 数据库 | `kuerp_goods` | **已有** `supplier_id` 列（来自 migration_add_goods_supplier_id.sql） |
| 数据库 | `goods_purchase_order.purchase_id` | varchar(50) 单值字段，历史遗留。**不依赖此字段**，统一走 `kuerp_goods_purord` 桥表 |

---

## 二、改造目标

1. **采购订单**支持每行商品绑定不同供应商
2. 生成采购单时，**按供应商分组**，每个供应商生成一个独立的采购单
3. 每个采购单独立走付款流程（付款单 → 核销），**此部分代码零改动**
4. 采购订单列表可查看关联的采购单和按供应商维度的状态

---

## 三、数据库改造

### 3.1 fa_kuerp_goods_purchase_order（采购订单主表）

| 变更 | 字段 | 说明 |
|------|------|------|
| 保留 | `supplyer_code` | 单供应商时存供应商编号，多供应商时留空 |
| 新增 | `supplier_count` int(11) DEFAULT 0 | 涉及的供应商数量（冗余，便于列表展示） |

### 3.2 fa_kuerp_goods_purchase_order_info（采购订单明细）

**无需改表结构**。已有 `supplyer_code` 字段，改造后每行独立存储各自供应商。

### 3.3 迁移 SQL

```sql
-- 采购订单主表新增 supplier_count 字段
ALTER TABLE `fa_kuerp_goods_purchase_order`
ADD COLUMN `supplier_count` int(11) DEFAULT 0 COMMENT '涉及供应商数量' AFTER `supplyer_code`;
```

### 3.4 无需改动的表

| 表 | 说明 |
|---|------|
| `fa_kuerp_goods_purchase` | 采购单主表——一个采购单一个供应商，不变 |
| `fa_kuerp_goods_purchase_info` | 采购单明细——不变 |
| `fa_kuerp_goods_purord` | 已支持 1:N（order_id → 多个 purchase_id） |
| `fa_kuerp_goods_purord_info` | 已支持行级追踪（order_info_id → purchase_info_id） |
| `fa_kuerp_payment_sale` | 付款单关联采购单号，采购单已拆分即可 |
| `fa_kuerp_invoicing` | 开票按采购单号，同上 |
| `fa_kuerp_writeoff_source` | 核销源单，同上 |
| `fa_kuerp_accounts_copewith` | 应付账款，同上 |

### 3.5 关于 `goods_purchase_order.purchase_id` 字段

该字段是 varchar(50) 单值，历史遗留。当前 `index()` 列表展示关联采购单时**已经不依赖此字段**，而是通过 `kuerp_goods_purord` 桥表动态查询：

```php
// GoodsPurchaseOrder.php::index() 第 97-108 行（已验证）
$purchase_id = Db::name('kuerp_goods_purord')
    ->where(['order_id' => $list_v['id']])
    ->column('purchase_id');
// 然后查 kuerp_goods_purchase 获取 purchase_code 列表
```

改造后统一走桥表，该字段保留但不作为主要数据源。同样，`goods_purchase.purchase_order_id` 存的是订单号（purchase_code），多采购单场景下每个采购单都正确记录其来源订单号。

---

## 四、控制器改造

---

### 4.1 GoodsPurchaseOrder.php（采购订单）

#### 4.1.1 `add()` POST — 保存行级供应商

**改造点**：商品行供应商从表单逐行读取，不再使用统一的表头值。

```php
// 遍历商品行（已验证现有位置: GoodsPurchaseOrder.php 第138-198行）
$rowSupplierCodes = [];
foreach ($params['goods_code'] as $k => $v) {
    if (!empty($v)) {
        $lineSupplier = $params['row_supplyer_code'][$k] ?? '';
        $rowSupplierCodes[] = $lineSupplier;
        
        $data[] = [
            // ... 现有字段 ...
            'supplyer_code' => $lineSupplier,  // 行级供应商
        ];
    }
}

// 表头汇总
$uniqueSuppliers = array_unique(array_filter($rowSupplierCodes));
$row['supplyer_code'] = count($uniqueSuppliers) === 1 ? reset($uniqueSuppliers) : '';
$row['supplier_count'] = count($uniqueSuppliers);
```

#### 4.1.2 `add()` GET — BOM 预填时自动读取商品供应商

**改造点**（已验证位置: 第 406-414 行 `$scheduleBomGoods` 构建处）：

当前完全没有设置供应商。需要从 `fa_kuerp_goods.supplier_id` 读取。

```php
// scheduleBomGoods 中增加 supplier_code
$goodsInfo = Db::name('kuerp_goods')
    ->where(['goods_code' => $p['goods_code']])
    ->field('supplier_id')
    ->find();
$supplierCode = '';
if (!empty($goodsInfo['supplier_id'])) {
    $supplierCode = Db::name('kuerp_suppliers')
        ->where(['id' => $goodsInfo['supplier_id']])
        ->value('supplyer_code');
}
$scheduleBomGoods[] = [
    'goods_code'    => $p['goods_code'],
    'goods_name'    => $p['goods_name'],
    'number'        => round($p['number'], $numberDecimal),
    'stores_id'     => $p['stores_id'],
    'unit_id'       => $p['unit_id'],
    'supplyer_code' => $supplierCode,  // 新增
];
```

#### 4.1.3 `edit()` POST — 保存行级供应商

与 `add()` POST 相同的改造。此外需要调整编辑权限判断：

**当前逻辑**（已验证位置: 第 458-467 行）：
```php
// 只要有一个采购单已生成，整个订单拒绝编辑
$purchase_id = Db::name('kuerp_goods_purord')
    ->where(['order_id' => $ids])->find();
if ($purchase_id) { $this->error('不能编辑已生成采购单的数据'); }
```

**改造后**：改为行级控制——已生成采购单的行锁定不可编辑，未生成的行可正常修改。
- 前端：查询 `kuerp_goods_purord_info` 标记哪些 `order_info_id` 已有转换记录，对应行设 `disabled`
- 后端：保存时过滤掉已生成采购单的行，仅更新/新增未生成的行

#### 4.1.4 `index()` 列表供应商搜索

**当前逻辑**（已验证）：搜索过滤对表头 `supplyer_code` 做精确匹配。

**改造后**：供应商筛选需要搜索明细表。

```php
// 如果传入了供应商筛选条件
if (!empty($supplyerCodeFilter)) {
    $matchedCodes = Db::name('kuerp_goods_purchase_order_info')
        ->where(['supplyer_code' => $supplyerCodeFilter])
        ->distinct('purchase_code')
        ->column('purchase_code');
    $where['purchase_code'] = ['in', $matchedCodes ?: ['']];
}
```

列表增加 `supplier_count` 列显示涉及的供应商数量。已有的 `purchase_id` 列（通过 `kuerp_goods_purord` 动态查询关联采购单）已支持展示多个采购单，无需改动。

---

### 4.2 GoodsPurchase.php（采购单）— 按供应商分组生成

#### 4.2.1 新增批量生成方法

在 `GoodsPurchaseOrder.php` 或 `GoodsPurchase.php` 中新增方法，替代原来的 `add($ids)` GET 跳转模式。

**流程**：

```
采购订单列表 → 点击"生成采购单"
  ↓ 后端检测供应商数量
  ├── 1个供应商 → 照旧，重定向到 goods_purchase/add?ids=XX
  │                （现有流程，改动量为0）
  │
  └── 多个供应商 → 弹窗展示分组信息：
        ┌──────────────────────────────────────┐
        │ 该订单涉及 N 个供应商，将分别生成采购单   │
        │                                      │
        │ 供应商A: X种商品, 金额 ¥XX,XXX         │
        │ 供应商B: Y种商品, 金额 ¥XX,XXX         │
        │ ...                                  │
        │                                      │
        │ [取消]  [确认生成]                      │
        └──────────────────────────────────────┘
              ↓ 确认
         遍历供应商分组，每个生成一个采购单
```

**伪代码**：

```php
/**
 * 从采购订单批量生成采购单（按供应商分组）
 * @param int $orderId 采购订单ID
 */
public function batchGeneratePurchase($orderId) {
    // 1. 查询订单和明细
    $order = Db::name('kuerp_goods_purchase_order')->where(['id' => $orderId])->find();
    if (!$order || $order['examine_status'] != '1') {
        $this->error('订单未审核或不存在');
    }
    
    $lines = Db::name('kuerp_goods_purchase_order_info')
        ->where(['purchase_code' => $order['purchase_code']])
        ->select();
    
    // 过滤已全部转换的行
    $lines = array_filter($lines, function($line) {
        $converted = Db::name('kuerp_goods_purord_info')
            ->where(['order_id' => $line['id']])
            ->sum('number');
        return ($line['number'] - $converted) > 0;
    });
    
    // 2. 按供应商分组
    $groups = [];
    foreach ($lines as $line) {
        $sc = $line['supplyer_code'] ?: '未指定供应商';
        $groups[$sc][] = $line;
    }
    
    // 3. 单供应商 → 走原来流程
    if (count($groups) <= 1) {
        return redirect("kuerp/goods_purchase/add?ids={$orderId}");
    }
    
    // 4. 多供应商 → 批量生成
    $results = [];
    Db::startTrans();
    try {
        foreach ($groups as $supplierCode => $items) {
            $purchaseCode = \addons\kuerp\library\Goods::getCodes('', 8);
            
            // 统计该供应商的金额
            $totalNumber = 0;
            $totalAllMoney = 0;
            $totalTaxMoney = 0;
            $totalMoneyTaxPrice = 0;
            
            $detailData = [];
            foreach ($items as $item) {
                $remaining = $item['number'] - Db::name('kuerp_goods_purord_info')
                    ->where(['order_id' => $item['id']])
                    ->sum('number');
                if ($remaining <= 0) continue;
                
                $totalNumber += $remaining;
                $lineAllMoney = $remaining * $item['unit_price'];
                $totalAllMoney += $lineAllMoney;
                $totalMoneyTaxPrice += $remaining * $item['tax_included'];
                
                $detailData[] = [
                    'purchase_code'    => $purchaseCode,
                    'supplyer_code'    => $supplierCode,
                    'goods_code'       => $item['goods_code'],
                    'goods_unit'       => $item['goods_unit'],
                    'stores_id'        => $item['stores_id'],
                    'number'           => $remaining,
                    'default_unit'     => $item['default_unit'],
                    'basic_nmber'      => $item['basic_nmber'],
                    'unit_price'       => $item['unit_price'],
                    'tax_included'     => $item['tax_included'],
                    'discount'         => $item['discount'],
                    'dis_money'        => $item['dis_money'],
                    'all_money'        => $lineAllMoney,
                    'tax_rate'         => $item['tax_rate'],
                    'tax_money'        => $item['tax_money'],
                    'money_tax_price'  => $remaining * $item['tax_included'],
                    'preferential_rate'=> $item['preferential_rate'],
                    'preferential_money'=> $item['preferential_money'],
                    'order_info_id'    => $item['id'],       // 关联订单明细行
                    'purchase_order_id'=> $order['purchase_code'],
                    'sales_order_id'   => $item['sales_order_id'],
                ];
            }
            
            // 插入采购单主表
            $purchaseMainId = Db::name('kuerp_goods_purchase')->insertGetId([
                'purchase_code'      => $purchaseCode,
                'purchase_type'      => $order['purchase_type'],
                'purchase_time'      => time(),
                'delivery_time'      => $order['delivery_time'],
                'supplyer_code'      => $supplierCode,
                'number'             => $totalNumber,
                'all_money'          => $totalAllMoney,
                'tax_money'          => $totalTaxMoney,
                'real_money'         => $totalMoneyTaxPrice,
                'purchase_order_id'  => $order['purchase_code'],
                'sales_order_id'     => $order['sales_order_id'],
                'preparation_id'     => $this->auth->id,
                'operation_id'       => $this->auth->id,
                'pay_status'         => '0',
                'examine_status'     => '0',
                'createtime'         => time(),
                'updatetime'         => time(),
            ]);
            
            // 插入采购单明细 + purord_info
            foreach ($detailData as $dv) {
                $detailId = Db::name('kuerp_goods_purchase_info')->insertGetId($dv);
                if (!empty($dv['order_info_id'])) {
                    Db::name('kuerp_goods_purord_info')->insert([
                        'order_id'        => $dv['order_info_id'],
                        'purchase_id'     => $detailId,
                        'number'          => $dv['number'],
                        'all_money'       => $dv['all_money'],
                        'tax_rate'        => $dv['tax_rate'],
                        'tax_money'       => $dv['tax_money'],
                        'money_tax_price' => $dv['money_tax_price'],
                        'status'          => '0',
                    ]);
                }
            }
            
            // 插入 purord 关联
            Db::name('kuerp_goods_purord')->insert([
                'order_id'    => $orderId,
                'purchase_id' => $purchaseMainId,
            ]);
            
            // 写入销货订单日志（如果有）
            if (!empty($order['sales_order_id'])) {
                \app\admin\model\kuerp\GoodsSalesOrderLog::writeLogByCode(
                    $order['sales_order_id'],
                    '生成采购单',
                    "生成采购单：{$purchaseCode}（供应商：{$supplierCode}）",
                    'purchase',
                    $purchaseMainId
                );
            }
            
            $results[] = [
                'purchase_code' => $purchaseCode,
                'supplier_code' => $supplierCode,
                'item_count'    => count($detailData),
            ];
        }
        
        // 更新订单表头
        Db::name('kuerp_goods_purchase_order')->where(['id' => $orderId])->update([
            'supplier_count' => count($groups),
        ]);
        
        Db::commit();
    } catch (\Exception $e) {
        Db::rollback();
        $this->error('生成采购单失败：' . $e->getMessage());
    }
    
    return json(['status' => 1, 'results' => $results]);
}
```

#### 4.2.2 `add($ids)` GET — 单供应商时不变

当订单只有一个供应商（或历史订单）时，完全走原有流程 `goods_purchase/add?ids=XX`，**零改动**。

---

### 4.3 `_examine()` 审核 — 零改动

每个采购单审核时仍是单供应商，`payment_log`、`invoicing`、`writeoff_source`、`accounts_copewith` 的写入逻辑完全不变。审核时入库数量通过 `kuerp_goods_purord_info` 反向更新采购订单的 `store_number` 和 `status`，这部分逻辑已验证支持多条采购单汇总。

---

### 4.4 Payment.php（付款单）— 零改动

付款单基于采购单号（`purchase_number`），采购单已按供应商拆分，付款流程完全不变。

---

### 4.5 GoodsPurchase::getUnit() — 增加返回供应商信息

**改造点**（已验证位置: 第 1980-2128 行）：

在返回数据中增加商品绑定的默认供应商，前端换商品时自动填充行级供应商下拉。

```php
// 在 getUnit() 返回数据中增加
$goodsInfo = Db::name('kuerp_goods')
    ->where(['goods_code' => $goods_code])
    ->field('supplier_id')
    ->find();
if (!empty($goodsInfo['supplier_id'])) {
    $supplier = Db::name('kuerp_suppliers')
        ->where(['id' => $goodsInfo['supplier_id']])
        ->field('id, supplyer_code, supplyer_name')
        ->find();
    $result['supplier'] = $supplier ?: null;
}
```

---

## 五、前端改造

### 5.1 goods_purchase_order.js + add.html/edit.html

#### 5.1.1 表头供应商

- 移除 `data-rule="required"`
- 所有行供应商相同时自动同步填充表头
- 多供应商时保持表头为空

#### 5.1.2 商品行增加供应商列

每行商品增加供应商下拉（SelectPage），数据源 `kuerp/suppliers/index`：
- 新增行：自动从 `getUnit()` 返回的 `supplier` 填充
- BOM预填行：从后端 `scheduleBomGoods.supplyer_code` 获取
- 已有行：显示已绑定的供应商

#### 5.1.3 JS 改动点（关键）

当前所有 Ajax 调用都读全局 `$('#supplyer_code').val()`，需要改为从**当前行**读取：

```javascript
// 原来（全局）
var supplyer_code = $('#supplyer_code').val();

// 改为（行级）
var supplyer_code = $(obj).closest('tr').find('.row-supplyer_code').val();
```

**涉及函数**（均在 `goods_purchase_order.js` bindevent 闭包内）：
- `changeGoods()` — 第 491 行
- `changeGoodsInfo()` — 第 585 行
- `formatInfo()` — 第 492 行（通过 changeGoods 间接调用）
- `initlist()` — 第 379 行（写死 `''`，需改为行级）
- `initScheduleBom()` — 第 414 行（当前不传 supplier，需改为传行级）

**新增事件**：行级供应商下拉切换时重新获取价格：
```javascript
$(document).on('change', '.row-supplyer_code', function() {
    var obj = $(this).closest('tr');
    var goods_code = obj.find('.goods_code').val();
    if (goods_code) {
        changeGoods(goods_code, obj.find('.stores_id').val(), '', obj,
            obj.find('.goods_unit').val(), obj.find('.number').val());
    }
});
```

### 5.2 goods_purchase.js（采购单前端）— 零改动

采购单生成后就是一个供应商对应一个采购单，回到现有流程，无需改动。

### 5.3 goods_purchase_order/index.html 列表

增加 `supplier_count` 列，显示涉及的供应商数量。已有的 `purchase_id` 列（关联采购单列表）保持不变，已支持显示多个采购单号及审核状态。

---

## 六、改造范围汇总

| 改动层级 | 文件 | 改动量 | 说明 |
|---------|------|--------|------|
| 前端 | `goods_purchase_order.js` | **大** | 全局→行级供应商，~8处 Ajax 调用 |
| 前端 | `goods_purchase_order/add.html` | 中 | 商品表增加供应商列，表头供应商改为非必填 |
| 前端 | `goods_purchase_order/edit.html` | 中 | 同上 + 已生成行锁定 |
| 前端 | `goods_purchase_order/index.html` | 小 | 增加 supplier_count 列 |
| 后端 | `GoodsPurchaseOrder.php` | 中 | add/edit 行级保存、index 搜索适配 |
| 后端 | `GoodsPurchaseOrder.php` | **新增** | batchGeneratePurchase() 批量生成方法 |
| 后端 | `GoodsPurchase.php::getUnit()` | 小 | 返回商品默认供应商 |
| 后端 | `GoodsPurchase.php::add()` GET | 小 | 多供应商时跳转弹窗而非预填表单 |
| 数据库 | `goods_purchase_order` | 小 | 新增 supplier_count 字段 |
| 不动 | `goods_purchase.js` | **0** | — |
| 不动 | `GoodsPurchase::_examine()` | **0** | — |
| 不动 | `Payment.php` | **0** | — |
| 不动 | 所有付款/核销/发票相关表和控制器 | **0** | — |

---

## 七、实施步骤

| 步骤 | 内容 | 依赖 | 涉及文件 |
|------|------|------|---------|
| 1 | 数据库: `goods_purchase_order` 新增 `supplier_count` 字段 | — | 迁移SQL |
| 2 | 后端: `getUnit()` 返回商品默认供应商 | 1 | GoodsPurchase.php |
| 3 | 前端: add.html/edit.html 商品表增加行级供应商列 | 2 | 两个模板文件 |
| 4 | 前端: JS 全部 Ajax 调用改为行级读取 (`$('#supplyer_code')`→行级) | 3 | goods_purchase_order.js |
| 5 | 后端: add()/edit() 保存行级供应商，表头汇总 | 4 | GoodsPurchaseOrder.php |
| 6 | 后端: BOM预填 (`add()` GET) 读取商品默认供应商 | 5 | GoodsPurchaseOrder.php |
| 7 | 前端: `initScheduleBom()` 逐行设置供应商下拉 | 6 | goods_purchase_order.js |
| 8 | 后端: 新增 `batchGeneratePurchase()` 批量生成方法 | 5 | GoodsPurchaseOrder.php |
| 9 | 前端: 多供应商弹窗 + 批量生成交互 | 8 | goods_purchase_order.js |
| 10 | 后端: index() 供应商搜索适配明细表 | 5 | GoodsPurchaseOrder.php |
| 11 | 前端: index.html 增加 `supplier_count` 列 | 10 | index.html |
| 12 | 编辑权限细化：已生成行锁定，未生成行可编辑 | 8 | edit.html + GoodsPurchaseOrder.php |

---

## 八、销货排期 → 采购订单 链路分析

### 8.1 当前流程

```
销货排期编辑页 (goods_sales_schedule/edit.html)
  │ 顶部按钮: "生成采购订单" → data-id="排期ID"
  ↓
goods_sales_schedule.js 第 1121 行:
  Fast.api.open("kuerp/goods_purchase_order/add?schedule_id=XX", ...)
  ↓
GoodsPurchaseOrder::add() GET (第 317-421 行):
  1. 查 kuerp_goods_sales WHERE id=schedule_id
  2. 查 kuerp_goods_sales_info (排期商品行)
  3. 对每个商品，查 BOM (kuerp_goods_bom → kuerp_goods_bom_info)
     过滤 type='2'(子件) AND product_type='part'(零件)
  4. 计算需求量: bom_number × schedule_basic_number
  5. 按 goods_id 汇总 → 选库存最多的零件仓库
  6. 构建 $scheduleBomGoods 数组（当前无供应商字段）
  7. assignconfig 传给前端
  ↓
goods_purchase_order.js initScheduleBom():
  - 克隆模板行，预填 goods_code、stores_id、number、sales_order_id
  - 调用 changeGoods() 获取单位/库存/价格
```

**关键代码位置**：`GoodsPurchaseOrder.php` 第 416 行注释：
```php
// 默认供应商从排期客户的反推？不，购货订单要选供应商，这里留空让用户选
```

### 8.2 多供应商改造影响

**销货排期模块本身：零改动。**

"生成采购订单"按钮只是把 `schedule_id` 传给采购订单添加页，不涉及供应商逻辑。供应商的分组处理发生在采购订单→采购单这一步。

**采购订单 BOM 预填：小幅改动（已在方案 4.1.2 中规划）**

在构建 `$scheduleBomGoods` 数组时，从 `kuerp_goods.supplier_id` 自动读取每个零件的默认供应商：

```php
// 在现有 foreach ($partMap as $p) 循环中（第 406 行），增加:
$goodsInfo = Db::name('kuerp_goods')
    ->where(['goods_code' => $p['goods_code']])
    ->field('supplier_id')
    ->find();
$supplierCode = '';
if (!empty($goodsInfo['supplier_id'])) {
    $supplierCode = Db::name('kuerp_suppliers')
        ->where(['id' => $goodsInfo['supplier_id']])
        ->value('supplyer_code');
}
$scheduleBomGoods[] = [
    // ... 现有字段 ...
    'supplyer_code' => $supplierCode,  // 新增
];
```

前端 `initScheduleBom()` 同步改动：预填行级供应商下拉。

### 8.3 一个典型场景

```
销货排期: 成品A×5, 成品B×3
         ↓ BOM拆分
零件a: 需求1000 (供应商: GYS001) ─┐
零件b: 需求600  (供应商: GYS001) ─┤→ 生成采购订单
零件c: 需求300  (供应商: GYS002) ─┘   每行自动带出供应商
         ↓ 按供应商分组生成采购单
采购单-CG001 (GYS001): 零件a×1000 + 零件b×600
采购单-CG002 (GYS002): 零件c×300
         ↓ 各自走付款流程（不变）
```

### 8.4 注意事项

1. **仓库选择逻辑不受影响**：BOM 预填时为每个零件选择库存最多的零件仓库（第 373-404 行），这个逻辑与供应商无关，保持不变。
2. **BOM 零件过滤不受影响**：只取 `product_type='part'` 的子件，与供应商无关。
3. **`sales_order_id` 传递不受影响**：排期关联的销货订单号仍会传递到采购订单和采购单，用于日志追踪。
4. **如果商品未设置 `supplier_id`**：`supplyer_code` 留空，用户在采购订单页面手动选择。

---

## 九、关键边界情况

### 9.1 部分行已生成采购单后编辑

- 已生成采购单的行：前端 `disabled`，后端不处理
- 未生成的行：可修改（含供应商），可删除
- 删除未生成行时不影响已生成的采购单

### 9.2 供应商未知的商品

- `supplyer_code` 为空的商品行分到"未指定供应商"组
- 允许生成采购单，供应商字段留空，用户后续在采购单中补充

### 9.3 历史数据兼容

- 读取行级 `supplyer_code` 时，如果为空则 fallback 到表头 `supplyer_code`
- 历史订单（表头有值、所有行相同）行为完全不变

### 9.4 订单完成状态

- `store_number`（已入库数量）通过 `kuerp_goods_purord_info` 汇总，多个采购单各自审核入库后自动累加
- `status`（0=未入库, 1=全部入库, 2=部分入库）计算逻辑不变

### 9.5 反审核

- 采购单反审核时检查是否已生成付款单（现有逻辑），不受影响
- 采购订单反审核时检查所有关联采购单是否已全部反审核（现有逻辑通过 `kuerp_goods_purord` 检查），逻辑不变

### 9.6 购货退货单

`goods_purchase_order` 支持 `purchase_type = 2`（采购退货）。退货订单同样支持多供应商，逻辑与采购完全对称：
- 退货订单每行可绑定不同供应商
- 生成退货单时按供应商分组拆分
- 退货单审核/付款流程不变

退货改造不在本期范围内，但数据库和前端改造（行级供应商列）完成后，退货订单自动获得多供应商能力——只需在批量生成时增加 `purchase_type = 2` 的分支。

### 9.7 采购费用 (cost_data)

采购单有 `cost_data` 字段存储采购费用（运费、杂费等），审核时写入 `kuerp_cost` 表。该字段位于采购单层面，按供应商拆分后，每个采购单各自携带其 `cost_data`。如费用需要从订单级分配到供应商级，由用户在生成采购单后手动编辑各采购单的采购费用。本期不做自动分摊。
