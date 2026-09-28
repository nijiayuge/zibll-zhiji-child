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
