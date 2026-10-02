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
