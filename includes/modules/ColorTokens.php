<?php
/**
 * @module  ColorTokens
 * @desc    主题配色令牌：品牌主色派生 CSS 变量（--zhiji-brand 系列），全站语义色统一入口
 * @option  zhiji_brand_color  品牌主色（默认 #2e7cf6）
 * @hook    wp_head · 输出 :root 令牌覆盖（v2 无独立样式表，直接输出）
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/ColorTokens.php`
 *          ⚠️ 改造：v1 守卫 wp_style_is('zhiji-child-style') 依赖 v1 Assets 模块注册的
 *          样式表，v2 无此 handle（守卫下整段输出必死）→ 改为 wp_head 直接输出 style 标签
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('color_tokens', array(
    'title'    => '主题配色',
    'parent'   => 'zhiji_beautify',
    'priority' => 10,
    'option'   => 'zhiji_color_tokens_enabled',
));

/* ============================================================
 * 颜色工具
 * ============================================================ */

/**
 * 颜色加深
 *
 * @param string $hex 颜色值
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
 * PHP 端获取令牌色值（邮件模板等不支持 CSS 变量的场景用）。
 * 邮件客户端不支持 var()，邮件模板通过本函数在 PHP 侧注入色值，
 * 后台改品牌主色后邮件同样跟随。
 *
 * @param string $name 令牌名：brand / brand_deep / brand_light / surface_soft / border / danger / success / gold / gold_light / gold_deep / gold_cream
 * @return string 色值
 */
function zhiji_token_color($name = 'brand')
{
    $brand = (string) zhiji_get_option('zhiji_brand_color', '#2e7cf6');
    if ('' === $brand) {
        $brand = '#2e7cf6';
    }
    $map = array(
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

/* ============================================================
 * 令牌输出（钩子常注册，回调内判开关）
 * ============================================================ */
add_action('wp_head', function () {
    if (!zhiji_is_enabled('zhiji_color_tokens_enabled', true)) {
        return;
    }
    $brand = (string) zhiji_get_option('zhiji_brand_color', '#2e7cf6');
    if ('' === $brand || !preg_match('/^#?[0-9a-fA-F]{3,6}$/', $brand)) {
        $brand = '#2e7cf6';
    }
    $brand = '#' . ltrim($brand, '#');
    $deep  = zhiji_color_darken($brand, 0.38);
    $glow  = zhiji_hex_rgba($deep, 0.3);
    echo '<style id="zhiji-color-tokens">:root{'
        . '--zhiji-raw-blue:' . esc_attr($brand) . ';'
        . '--zhiji-raw-blue-deep:' . esc_attr($deep) . ';'
        . '--zhiji-brand:var(--zhiji-raw-blue);'
        . '--zhiji-brand-strong:var(--zhiji-raw-blue);'
        . '--zhiji-brand-deep:var(--zhiji-raw-blue-deep);'
        . '--zhiji-accent:var(--zhiji-raw-blue);'
        . '--zhiji-glow:' . esc_attr($glow) . ';'
        . '--zhiji-gradient:linear-gradient(135deg,var(--zhiji-raw-blue-deep),var(--zhiji-raw-blue));'
        . '}'
        // 站内消息奖励高亮：抽奖/奖励通知中 <strong> 包裹的数值以品牌色突出
        . '.msg-content strong{color:var(--zhiji-brand);}'
        . '</style>' . "\n";
}, 99);

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('color_tokens', array(
        array(
            'id'      => 'zhiji_color_tokens_enabled',
            'type'    => 'switcher',
            'title'   => '启用主题配色令牌',
            'default' => true,
        ),
        array(
            'id'         => 'zhiji_brand_color',
            'type'       => 'color',
            'title'      => '品牌主色',
            'desc'       => '默认 #2e7cf6（蓝）。控制播放器/通知等模块的品牌渐变。会员引导卡、转盘、排行榜等语义色模块不受影响。',
            'default'    => '#2e7cf6',
            'dependency' => array('zhiji_color_tokens_enabled', '==', '1'),
        ),
    ));
}, 20);
