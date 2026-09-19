<?php
/**
 * @module  Helpers
 * @desc    通用工具：资源 URL（自动带版本）、开关判定、日志
 * @since   2.0.0
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
 * 写入单个配置项（只改指定键，不动其它键；禁止在模块里直接 update_option）
 *
 * @param string $key
 * @param mixed  $value
 * @return bool
 */
function zhiji_update_option($key, $value)
{
    $options = get_option(ZHIJI_OPTION_KEY, array());
    if (!is_array($options)) {
        $options = array();
    }
    $options[$key] = $value;
    return update_option(ZHIJI_OPTION_KEY, $options);
}

/**
 * 开关判定：CSF 的 switcher 存的是字符串 '0'/'1'，必须统一用布尔解析
 *
 * @param string $key
 * @param bool   $default
 * @return bool
 */
function zhiji_is_enabled($key, $default = false)
{
    return (bool) filter_var(zhiji_get_option($key, $default), FILTER_VALIDATE_BOOLEAN);
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
