# 知集子主题 (zibll-zhiji-child)

子比主题(Zibll)的子主题，用于覆盖商城后台页面模板，配合 [知集插件](https://github.com/nijiayuge/zibll-zhiji) 使用。

## 功能说明

本子主题通过覆盖父主题 `zibpay/page/` 目录下的模板文件，实现商城后台页面的定制化：

| 模板文件 | 功能 |
|---------|------|
| `coupon.php` | 优惠码管理页面 |
| `order.php` | 订单管理页面 |
| `product.php` | 商品管理页面 |
| `income.php` | 收入统计页面 |
| `withdraw.php` | 提现管理页面 |
| `charge-card.php` | 充值卡管理页面 |
| `shop.php` | 商城设置页面 |
| `rebate.php` | 分销/返利页面 |
| `index.php` | 商城仪表盘 |
| `template/` | 订单、售后、物流等子模板 |

## 安装方法

1. 下载本仓库 ZIP 或 `git clone`
2. 将 `zibll-zhiji-child` 文件夹上传到 WordPress 的 `wp-content/themes/` 目录
3. 在 WordPress 后台 → 外观 → 主题，启用「知集子主题」
4. 确保已安装并启用父主题 [子比主题(Zibll)](https://zibll.com)
5. 建议同时安装 [知集插件](https://github.com/nijiayuge/zibll-zhiji) 以获得完整功能

## 目录结构

```
zibll-zhiji-child/
├── style.css              # 子主题样式（必需，声明 Template: zibll）
├── functions.php          # 子主题功能入口
├── README.md              # 说明文档
├── img/
│   └── kanban/
│       └── zhiji-kanban.png  # 看板娘图片
└── zibpay/
    └── page/              # 商城后台页面模板覆盖
        ├── coupon.php
        ├── order.php
        ├── product.php
        ├── income.php
        ├── withdraw.php
        ├── charge-card.php
        ├── shop.php
        ├── rebate.php
        ├── index.php
        └── template/      # 子模板
            ├── dashboard.php
            ├── header.php
            ├── footer.php
            ├── content.php
            ├── order.php
            ├── order-dialog.php
            ├── after-sale.php
            ├── after-sale-dialog.php
            ├── shipping.php
            └── shipping-dialog.php
```

## 与知集插件的关系

- **知集插件**：提供前台功能（看板娘、弹幕、抽奖、任务、优惠券、美化效果等）
- **知集子主题**：覆盖商城后台页面模板，优化后台管理体验

两者独立安装、配合使用。

## 注意事项

- 本子主题依赖父主题 Zibll，未安装父主题将无法启用
- 覆盖的模板文件基于 Zibll 主题最新版本，父主题大版本更新后需检查模板兼容性
- 如需恢复原始商城后台，只需禁用本子主题即可

## License

GPL v2 or later
