# 知集子主题 (zibll-zhiji-child)

子比主题(Zibll)的子主题，用于覆盖商城后台页面模板，并存放知集插件的全部静态资源，配合 [知集插件](https://github.com/nijiayuge/zibll-zhiji) 使用。

## ⚠️ 铁律：必须与知集插件同时启用

本子主题存放知集插件的**全部静态资源**（CSS/JS/图片/字体，共 172 个文件）。仅启用插件不启用子主题会导致所有美化效果、图片、JS 动画失效。

## 功能说明

本子主题包含两大部分：

### 1. 商城后台模板覆盖
通过覆盖父主题 `zibpay/page/` 目录下的模板文件，实现商城后台页面的定制化：

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

### 2. 知集插件静态资源（assets/）
知集插件的全部静态资源存放在本子主题的 `assets/` 目录中，插件通过 `zibll_zhiji_assets_url()` 函数自动优先加载子主题中的资源：

| 目录 | 内容 | 文件数 |
|------|------|--------|
| `assets/css/` | 插件主样式、抽奖样式 | 3 |
| `assets/js/` | 插件主 JS、抽奖 JS | 2 |
| `assets/features/` | 功能模块样式与脚本 | 2 |
| `assets/zhiji/css/` | 美化效果样式（弹幕、蒲公英、登录、新年等） | 13 |
| `assets/zhiji/js/` | 美化效果脚本（樱花、雪花、鼠标、粒子等） | 33 |
| `assets/zhiji/fonts/` | 字体文件（Noto Sans SC、站酷小薇） | 2 |
| `assets/zhiji/icon/` | 图标字体 | 3 |
| `assets/zhiji/images/` | 播放器皮肤图片 | 7 |
| `assets/zhiji/img/` | 背景图、装饰图、图标、鼠标指针、任务图、UID头像等 | 100+ |
| `assets/zhiji/other/` | 其他鼠标指针 | 2 |

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
├── assets/                # ⭐ 知集插件全部静态资源（172个文件）
│   ├── css/               # 插件主样式
│   ├── js/                # 插件主脚本
│   ├── features/          # 功能模块资源
│   └── zhiji/             # 美化效果资源（css/js/fonts/icon/img/images/other）
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

- **知集插件**：提供功能逻辑（看板娘、弹幕、抽奖、任务、优惠券、美化效果等），通过 `zibll_zhiji_assets_url()` 函数加载静态资源
- **知集子主题**：存放全部静态资源（CSS/JS/图片/字体）+ 覆盖商城后台页面模板

两者**必须同时安装同时启用**，缺一不可。插件会自动检测子主题 `assets/` 目录是否存在，存在则优先使用子主题资源，不存在则回退到插件自带资源。

## 注意事项

- 本子主题依赖父主题 Zibll，未安装父主题将无法启用
- 覆盖的模板文件基于 Zibll 主题最新版本，父主题大版本更新后需检查模板兼容性
- 如需恢复原始商城后台，只需禁用本子主题即可

## License

GPL v2 or later
