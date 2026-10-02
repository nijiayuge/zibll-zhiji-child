<?php
/**
 * @module  Helpers
 * @desc    通用工具：资源 URL（自动带版本）、日志、数组取值
 * @since   2.0.0
 *
 * ⚠️ 2026-09-28：`zhiji_is_enabled()` 与 `zhiji_update_option()` 已迁至 **core/Options.php**
 *    （配置相关能力集中到配置门面）。本文件不再定义它们 —— 重复定义会导致致命错误。
 */

defined('ABSPATH') || exit;

/**
 * 静态资源 URL（自动附加 filemtime 版本号，避免"改了没生效")
 *
 * @param string $rel 相对 assets/zhiji/ 的路径，如 css/zhiji-front.css
 * @return string
 */
function zhiji_asset_url($rel)
{
    $rel = ltrim((string) $rel, '/');
    $abs = ZHIJI_PATH . 'assets/zhiji/' . $rel;
    $ver = is_readable($abs) ? (string) filemtime($abs) : ZHIJI_VERSION;
    return ZHIJI_ASSETS_URL . $rel . '?v=' . $ver;
}

/**
 * hex → rgba 字符串（用于派生父主题的半透明强调色变量）
 *
 * @param string $hex   #RRGGBB 或 RRGGBB
 * @param float  $alpha 0-1
 * @return string
 */
function zhiji_hex_rgba($hex, $alpha)
{
    $hex = ltrim((string) $hex, '#');
    if (3 === strlen($hex)) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (6 !== strlen($hex)) {
        return 'rgba(0,0,0,' . (float) $alpha . ')';
    }
    return sprintf(
        'rgba(%d,%d,%d,%s)',
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
        rtrim(rtrim(number_format((float) $alpha, 3, '.', ''), '0'), '.')
    );
}

/**
 * 统一日志（仅在 WP_DEBUG 时输出，避免污染生产日志）
 *
 * @param string $message
 * @param mixed  $context
 * @return void
 */
function zhiji_log($message, $context = null)
{
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    $line = '[zhiji] ' . $message;
    if (null !== $context) {
        $line .= ' ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    error_log($line);
}

/**
 * 安全读取数组值
 *
 * @param array  $arr
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function zhiji_arr_get($arr, $key, $default = null)
{
    return (is_array($arr) && isset($arr[$key])) ? $arr[$key] : $default;
}

/* ============================================================
 * 配色令牌
 * ------------------------------------------------------------
 * 2026-10-02（P3）从 modules/ColorTokens.php 迁入 core。
 *
 * 【背景：一次真实的致命 bug】
 * AF.20（2026-10-02 移除 18 个模块）把 ColorTokens 整模块删掉了，
 * 但它定义的 `zhiji_token_color()` 仍被 **6 个模块调用 19 处**
 * （MailTemplate / CouponGive / Lottery / RewardNotify / EmailSubscribe / FriendLinkApply）。
 * 其中 18 处**没有 function_exists 守卫**（项目报告
 * _reports/v2-to-v3-port-inventory.md:458 早已标注此风险，但没被处理）。
 * → 后果：任何一次邮件发送（优惠码到账 / 抽奖中奖 / 订阅确认 / 友链通过 /
 *   奖励到账）都会撞 `Call to undefined function zhiji_token_color()` → **HTTP 500**。
 *   只在 P3 用运行时探针实际渲染一次邮件时才暴露出来（首页 curl 测不到）。
 *
 * 【为什么下沉到 core 而不是恢复模块】
 * 这两个函数是**纯工具**（一次颜色加深 + 一次数组查表），不依赖任何模块上下文。
 * 它们被 6 个模块共用，任何「可关闭」的模块都不该拥有它们的生死。
 * 且 ColorTokens 的另外两个配置项（zhiji_color_tokens_enabled / zhiji_brand_color）
 * 随模块一起被删 —— 品牌主色失去了配置入口。
 * → 处置：函数下沉 core（不可关闭），品牌色改为**可通过 filter 注入**，
 *   默认取已废弃的 option（存量站点仍存着值），取不到则用兜底色。
 *   将来若重做配色模块，挂 filter 即可，无需再动这 6 个调用点。
 * ============================================================ */

/**
 * 颜色加深
 *
 * @param string $hex 颜色值（#rgb / #rrggbb）
 * @param float  $pct 加深比例 0-1
 * @return string
 */
function zhiji_color_darken($hex, $pct = 0.3)
{
    $hex = ltrim((string) $hex, '#');
    if (3 === strlen($hex)) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (6 !== strlen($hex) || !ctype_xdigit($hex)) {
        return '#1e4e8c';
    }
    $r = max(0, (int) round(hexdec(substr($hex, 0, 2)) * (1 - $pct)));
    $g = max(0, (int) round(hexdec(substr($hex, 2, 2)) * (1 - $pct)));
    $b = max(0, (int) round(hexdec(substr($hex, 4, 2)) * (1 - $pct)));
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/**
 * 取品牌主色
 *
 * 来源优先级：zhiji_brand_color 过滤器（品牌色模块据此判开关）
 *           → 存量 option（ColorTokens 遗留值，新站同样用它）
 *           → 兜底默认色
 *
 * @return string #rrggbb
 */
function zhiji_brand_color()
{
    /**
     * 品牌主色（注入点）
     *
     * 此前品牌色是 ColorTokens 模块的 option，该模块已被移除 →
     * 存量站点 option 里还有值（继续沿用），但**新站没有配置入口**。
     * 现由 modules/BrandColor.php 提供字段并挂本过滤器判开关；
     * 将来重做配色模块时改挂此处即可，6 个邮件调用点无需改动。
     *
     * @param string $brand
     */
    $brand = (string) apply_filters('zhiji_brand_color', '');

    if ('' === $brand) {
        $brand = (string) zhiji_get_option('zhiji_brand_color', '');
    }
    if ('' === $brand || !preg_match('/^#?[0-9a-fA-F]{3,6}$/', $brand)) {
        $brand = '#2e7cf6';
    }
    return '#' . ltrim($brand, '#');
}

/**
 * PHP 端获取令牌色值（邮件模板等不支持 CSS 变量的场景用）
 *
 * 邮件客户端不支持 var()，邮件模板在 PHP 侧注入色值，后台改品牌主色后邮件同步跟随。
 *
 * @param string $name brand / brand_deep / brand_light / surface_soft / border /
 *                     danger / success / gold / gold_light / gold_deep / gold_cream
 * @return string
 */
function zhiji_token_color($name = 'brand')
{
    $brand = zhiji_brand_color();
    $map   = array(
        'brand'        => $brand,
        'brand_deep'   => zhiji_color_darken($brand, 0.38),
        'brand_light'  => '#5ea2ff',
        'surface_soft' => '#eaf2fe',
        'border'       => '#dce6f5',
        'danger'       => '#e24b4a',
        'success'      => '#22b573',
        'gold'         => '#a9803f',
        'gold_light'   => '#c9a96a',
        'gold_deep'    => '#3d3a2e',
        'gold_cream'   => '#f5edd8',
    );
    return isset($map[$name]) ? $map[$name] : $brand;
}

/**
 * 输出 CSS 变量令牌（wp_head）
 *
 * 2026-10-02（P3）随 zhiji_token_color() 一并从 ColorTokens 迁入。
 *
 * 该模块被移除时，前端这批变量也一起消失了。全站 8 处 CSS 都写了
 * `var(--zhiji-brand, #2e7cf6)` 形式的 fallback，所以**不会破版**，
 * 但「后台改品牌主色 → 全站生效」的能力一并丢了。
 * 令牌是纯输出、无业务状态，不该挂在可关闭的模块上 → 放 core，随主题常驻。
 */
add_action('wp_head', function () {
    if (!function_exists('zhiji_brand_color')) {
        return;
    }
    $brand = zhiji_brand_color();
    $deep  = zhiji_color_darken($brand, 0.38);
    $glow  = function_exists('zhiji_hex_rgba') ? zhiji_hex_rgba($deep, 0.3) : 'rgba(30,78,140,.3)';
    echo '<style id="zhiji-color-tokens">:root{'
        . '--zhiji-raw-blue:' . esc_attr($brand) . ';'
        . '--zhiji-raw-blue-deep:' . esc_attr($deep) . ';'
        . '--zhiji-brand:var(--zhiji-raw-blue);'
        . '--zhiji-brand-strong:var(--zhiji-raw-blue);'
        . '--zhiji-brand-deep:var(--zhiji-raw-blue-deep);'
        . '--zhiji-accent:var(--zhiji-raw-blue);'
        . '--zhiji-surface-soft:' . esc_attr(zhiji_token_color('surface_soft')) . ';'
        . '--zhiji-border:' . esc_attr(zhiji_token_color('border')) . ';'
        . '--zhiji-glow:' . esc_attr($glow) . ';'
        . '--zhiji-gradient:linear-gradient(135deg,var(--zhiji-raw-blue-deep),var(--zhiji-raw-blue));'
        . '}'
        // 站内消息奖励高亮：通知中的数值以品牌色突出
        . '.msg-content strong{color:var(--zhiji-brand);}'
        . '</style>' . "\n";
}, 99);

/**
 * 随机取一个「优惠码有效期」（天）
 *
 * 2026-10-02（P2）从 modules/RewardCenter.php 迁入 core。
 *
 * 【为什么迁】
 * 旧名 `zhiji_reward_coupon_rand_expire()` 挂在 RewardCenter.php 里，但它既不发奖也不查库，
 * 只是一行 `apply_filters + array_rand` 的纯工具函数。RewardCenter 与 CouponGive 都要用它，
 * 于是形成真实闭环依赖：reward_center ⇄ coupon_give
 * （证据：CouponGive.php:1826 调用它，RewardCenter.php:611 也调用它）。
 * 纯工具函数放在任一业务模块里都是错位 —— 关掉那个模块，另一个就崩。
 * 故下沉到 core/Helpers.php：两个模块都只依赖基础设施，不再互相依赖。
 *
 * 旧名保留为别名（@deprecated），供第三方扩展兼容；新代码一律用本函数。
 *
 * @return int 天数；0 = 永久有效
 */
function zhiji_coupon_expire_rand_days()
{
    $pool = apply_filters('zhiji_reward_coupon_expire_pool', array(7, 30, 0));
    $pool = (is_array($pool) && !empty($pool)) ? $pool : array(7, 30, 0);
    return (int) $pool[array_rand($pool, 1)];
}

/**
 * @deprecated 2.0.7 改用 zhiji_coupon_expire_rand_days()（已下沉至 core，不再属于奖励中心）
 * @return int
 */
function zhiji_reward_coupon_rand_expire()
{
    return zhiji_coupon_expire_rand_days();
}
