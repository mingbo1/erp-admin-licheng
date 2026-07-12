# 采购订单多供应商 + 部分付款 改造方案

> 分析日期: 2026-07-12
> 涉及文件: GoodsPurchaseOrder.php, GoodsPurchase.php, Payment.php, 前端 JS/View, 数据库

---

## 一、现状分析

### 1.1 当前数据流

```
采购订单 (1个供应商)
  │ fa_kuerp_goods_purchase_order.supplyer_code = "GYS001"
  │ fa_kuerp_goods_purchase_order_info.supplyer_code = "GYS001" (每条行)
  │
  └── 生成购货单 (手动点击) → 1个购货单
       │ fa_kuerp_goods_purchase.supplyer_code = "GYS001"
       │
       ├── 审核(_examine) →
       │   ├── payment_log (1条, 按suppliers_code)
       │   ├── invoicing (1条, 按bill_code=购货单号)
       │   ├── writeoff_source (1条, 核销源单)
       │   └── accounts_copewith (1条, 应付账款)
       │
       └── 生成付款单 →
           └── kuerp_payment_sale (1条, purchase_number=购货单号)
```

**核心问题**: `supplyer_code` 是单据级别的，不能表达"一个订单里不同商品来自不同供应商"。

### 1.2 现有"部分付款"机制

当前已经有了部分付款的字段基础，但它是在**单据内**而非**供应商维度**：
- `pay_status`: 0=未付, 1=已付清, 2=部分付款
- `pay_money`: 本次付款金额
- `arrears`: 欠款金额

---

## 二、改造目标

1. **采购订单**支持每行商品绑定不同供应商
2. 生成购货单时，**按供应商拆分**为多个购货单
3. 每个购货单独立走付款流程（付款单 → 核销）
4. 采购订单层面可查看**按供应商维度的付款汇总**

---

## 三、数据库改造

### 3.1 fa_kuerp_goods_purchase_order (采购订单主表)

| 变更 | 字段 | 说明 |
|------|------|------|
| 保留 | `supplyer_code` | 保留但不作为唯一供应商，改为"主要供应商"或留空 |
| 新增 | `supplier_count` | 涉及的供应商数量（冗余，便于列表展示） |

### 3.2 fa_kuerp_goods_purchase_order_info (采购订单明细)

| 变更 | 字段 | 说明 |
|------|------|------|
| **改造** | `supplyer_code` | 已有字段，确保每行独立存储供应商 |

> 该表已有 `supplyer_code` 字段，目前存的是表头供应商。改造后每行存各自供应商。

### 3.3 fa_kuerp_goods_purchase (购货单主表)

无需改表结构。一个购货单对应一个供应商（现状不变）。

### 3.4 无需改动的表

- `fa_kuerp_payment_sale` — 付款单关联购货单，购货单已按供应商拆分即可
- `fa_kuerp_invoicing` — 开票按购货单号，同上
- `fa_kuerp_writeoff_source` — 核销源单，同上
- `fa_kuerp_accounts_copewith` — 应付账款，同上

---

## 四、控制器改造

### 4.1 GoodsPurchaseOrder.php（采购订单）

#### 4.1.1 `add()` / `edit()` 写操作

**改造点**：商品行中每行已有 `supplyer_code` 字段（来自 `fa_kuerp_goods.supplier_id`），保存时：
- 遍历所有行，收集涉及的 `supplyer_code` 列表
- 表头 `supplyer_code` 取第一个有值的供应商，或设为汇总值
- 新增 `supplier_count` = 去重后的供应商数

**伪代码**：
```php
// 遍历 data 收集供应商
$supplierCodes = [];
foreach ($data as $d) {
    $code = Db::name('kuerp_goods')->where(['goods_code' => $d['goods_code']])->value('supplier_id');
    // 通过 supplier_id → supplyer_code
    $sc = Db::name('kuerp_suppliers')->where(['id' => $code])->value('supplyer_code');
    if (!empty($sc)) $supplierCodes[] = $sc;
}
$uniqueSuppliers = array_unique($supplierCodes);
$row['supplyer_code'] = reset($uniqueSuppliers) ?: '';
$row['supplier_count'] = count($uniqueSuppliers);
```

#### 4.1.2 `add()` GET — 从销货排期 BOM 预填时

**改造点**：BOM 商品预填时，从 `fa_kuerp_goods.supplier_id` 自动读取供应商，填充到每行的 `supplyer_code`。

```php
// scheduleBomGoods 中增加 supplier_code
$supplierId = Db::name('kuerp_goods')->where(['goods_code' => $p['goods_code']])->value('supplier_id');
$supplierCode = '';
if ($supplierId) {
    $supplierCode = Db::name('kuerp_suppliers')->where(['id' => $supplierId])->value('supplyer_code');
}
$scheduleBomGoods[] = [
    ...
    'supplyer_code' => $supplierCode,
];
```

#### 4.1.3 `index()` 列表展示

**改造点**：显示涉及的供应商数量和付款汇总状态。

- 已有的 `purchase_id` 字段展示了关联的购货单号列表，可以增加按供应商维度展示

#### 4.1.4 `examine()` 审核

采购订单审核**不涉及**付款，不需要改。但如果要汇总付款状态，可以在列表查询时计算。

### 4.2 GoodsPurchase.php（购货单）

#### 4.2.1 `add($ids)` — 从采购订单生成购货单

**这是最核心的改造**。

当前逻辑：从订单拉取所有商品行 → 预填一个购货单表单。

改造后逻辑：

```
1. 从采购订单拉取所有商品行
2. 按 supplyer_code 分组
3. 前端展示：
   - 显示"该订单涉及多个供应商，将分别生成购货单"
   - 默认选中第一个供应商的商品
   - 用户可选择"按供应商生成"或"全部生成"
4. 保存时：
   - 如果只有一个供应商 → 照旧生成1个购货单
   - 如果有多个供应商 → 生成多个购货单 (每个供应商一个)
     - 每个购货单有独立的 purchase_code
     - kuerp_goods_purord 插入多条记录 (同 order_id → 多个 purchase_id)
```

**伪代码**：
```php
// 在 add() POST 中
$supplierGroups = [];
foreach ($data as $d) {
    $sc = $d['supplyer_code'];
    if (!isset($supplierGroups[$sc])) $supplierGroups[$sc] = [];
    $supplierGroups[$sc][] = $d;
}

// 为每个供应商生成一个购货单
foreach ($supplierGroups as $supplierCode => $items) {
    $purchaseCode = Goods::getCodes('', 8); // 生成新单号
    // ... 计算该供应商的总金额、总数量
    // ... 插入 kuerp_goods_purchase + kuerp_goods_purchase_info
    // ... 插入 kuerp_goods_purord (order_id → purchase_id)
}
```

#### 4.2.2 `_examine()` 审核

**基本不需要改**。每个购货单依然只有一个供应商，审核时：
- `payment_log` → 按购货单的 `supplyer_code`
- `invoicing` → 按购货单号
- `writeoff_source` → 按购货单号
- `accounts_copewith` → 按购货单号

#### 4.2.3 `edit()` 编辑

需检查：反审核时不能反审核"已按供应商拆分的购货单"的校验可能受影响。已有的校验：
```php
$purchase_id = Db::name('kuerp_payment_sale')->where(['purchase_number'=>$list_v['purchase_code']])->find();
```
这个校验不变，因为每个购货单仍是独立单据。

### 4.3 Payment.php（付款单）

**不需要改**。付款单基于购货单号（`purchase_number`），购货单已按供应商拆分，自然就支持了多供应商分别付款。

---

## 五、前端改造

### 5.1 goods_purchase_order.js + add.html

#### 5.1.1 表头供应商

**改为非必填**（因为一个订单可能涉及多个供应商）。如果所有商品行的供应商相同，自动填充表头。

#### 5.1.2 商品行增加供应商列

每行商品显示供应商下拉（selectpage），来源 `kuerp/suppliers/index`。

- 新添加的行：供应商从 `fa_kuerp_goods.supplier_id` 自动读取
- BOM 预填的行：同上
- 已有的行：显示已绑定的供应商

#### 5.1.3 JS 函数

- `changeGoods()`: Ajax 返回时增加返回 `supplier_id`，用于自动填充供应商下拉
- `initScheduleBom()`: 预填时包含 `supplyer_code`

### 5.2 goods_purchase.js

#### 5.2.1 从采购订单生成购货单时

- 如果采购订单有多个供应商：弹窗提示/自动拆分
- 显示每个供应商的金额汇总

### 5.3 goods_purchase_order/index.html

#### 5.3.1 列表增加列

| 原有 | 新增 |
|------|------|
| 购货单号 | 关联购货单（已存在） |
| 供应商 | 涉及供应商数 |
| — | 按供应商付款状态 |

---

## 六、GoodsPurchase::getUnit() 改造

`getUnit()` 是 Ajax 方法，用于商品行切换时获取单位、库存、价格等信息。需增加返回供应商信息：

```php
// 在 getUnit() 返回数据中增加
$row['supplier_id'] = Db::name('kuerp_goods')->where(['goods_code' => $goods_code])->value('supplier_id');
if ($row['supplier_id']) {
    $supplier = Db::name('kuerp_suppliers')->where(['id' => $row['supplier_id']])->field('id, supplyer_code, supplyer_name')->find();
    $row['supplier'] = $supplier ?: null;
}
```

前端 `changeGoods()` 收到后自动填充供应商下拉。

---

## 七、GoodsPurchaseOrder 的 pay_status 汇总

采购订单列表需要展示"按供应商的付款汇总"。

可在 `index()` 中查询：
```php
// 获取该订单关联的所有购货单
$purchaseIds = Db::name('kuerp_goods_purord')->where(['order_id' => $list_v['id']])->column('purchase_id');
$purchaseList = Db::name('kuerp_goods_purchase')
    ->where(['id' => ['in', $purchaseIds]])
    ->field('supplyer_code, pay_status, real_money, pay_money, arrears')
    ->select();
// 按供应商汇总
```

---

## 八、实施优先级

| 优先级 | 任务 | 难度 | 影响范围 |
|--------|------|------|---------|
| P0 | 采购订单商品行增加供应商列 (DB + 前端 + getUnit) | 中 | 订单编辑页 |
| P0 | add()/edit() 保存行级供应商 | 低 | 采购订单控制器 |
| P1 | 从订单生成购货单时按供应商拆分 | 高 | 购货单控制器 |
| P1 | BOM预填时自动读取商品供应商 | 低 | 采购订单 add() |
| P2 | 采购订单列表展示供应商付款汇总 | 中 | 采购订单 index() |
| P2 | 反审核校验适配 | 低 | 购货单 + 付款单 |

---

## 九、关键风险点

1. **采购订单号唯一性**: 生成多个购货单时，购货单号需要独立生成，不会冲突（已有机制）
2. **反审核连锁**: 反审核购货单时需检查是否已生成付款单 — 现有校验不变
3. **订单完成状态**: `store_number` vs `number` 判断订单是否全部入库 — 需确认按供应商拆分后统计逻辑是否正确
4. **优费/折扣分摊**: 当按供应商拆分购货单时，表头的优惠金额需要按比例分摊到每个供应商的购货单
5. **向后兼容**: 历史数据中 `supplyer_code` 在表头，行级 `supplyer_code` 需兼容读取
