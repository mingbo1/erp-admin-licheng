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
