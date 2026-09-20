<?php
/**
 * @module  SiteFont
 * @desc    网站字体：自定义正文/标题字体与基础字号
 * @option  site_font_enabled  总开关
 *          site_font_body     正文字体名
 *          site_font_title    标题字体名
 *          site_font_size     基础字号(px)
 *          site_font_custom_url 自托管字体样式地址
 * @hook    wp_head(100) 输出字体样式
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/SiteFont.php`（迁移时去除 Google Fonts 外链）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('site_font', array(
    'title'    => '网站字体',
    'parent'   => 'zhiji_beautify',
    'priority' => 30,
    'option'   => 'site_font_enabled',
));

/* ============================================================
 * 前台输出
 * ============================================================ */

/**
 * 过滤字体名：只允许中英文、数字、空格、逗号、连字符、下划线。
 * 字体名会拼进 CSS，必须收紧字符集，避免用户输入造成样式注入。
 *
 * @param string $v
 * @return string
 */
function zhiji_site_font_safe_name($v)
{
    $v = (string) $v;
    $v = preg_replace('/[^a-zA-Z0-9\x{4e00}-\x{9fa5}\s,\-_]/u', '', $v);
    return trim($v);
}

/**
 * wp_head 输出字体样式
 */
add_action('wp_head', function () {
    if (!zhiji_is_enabled('site_font_enabled')) {
        return;
    }

    $body  = zhiji_site_font_safe_name(zhiji_get_option('site_font_body', ''));
    $title = zhiji_site_font_safe_name(zhiji_get_option('site_font_title', ''));
    $size  = zhiji_get_option('site_font_size', '');
    $url   = esc_url_raw((string) zhiji_get_option('site_font_custom_url', ''));

    // 自托管字体样式（可填本地路径或自建 CDN）。
    // ⚠️ 不内置任何外部域名：Google Fonts 在国内不可达，v2 按「资源本地化」铁律移除，
    //    需要外部字体请自托管后在此填写地址。
    if ($url) {
        printf('<link rel="stylesheet" href="%s" media="all">' . "\n", esc_url($url));
    }

    $css = '';
    if ($body) {
        $css .= "body,button,input,select,textarea{font-family:'" . $body . "',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}\n";
    }
    if ($title) {
        $css .= "h1,h2,h3,h4,h5,h6,.entry-title,.post-title{font-family:'" . $title . "',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}\n";
    }
    if (is_numeric($size) && (float) $size > 0) {
        $css .= 'html{font-size:' . (float) $size . "px}\n";
    }

    if ($css) {
        echo '<style id="zhiji-site-font">' . "\n" . $css . '</style>' . "\n";
    }
}, 100);

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('site_font', array(
        array(
            'id'      => 'site_font_enabled',
            'type'    => 'switcher',
            'title'   => '启用自定义字体',
            'default' => false,
        ),
        array(
            'id'         => 'site_font_body',
            'type'       => 'text',
            'title'      => '正文字体',
            'desc'       => '字体名称，如：Microsoft YaHei, PingFang SC',
            'default'    => '',
            'dependency' => array('site_font_enabled', '==', 'true'),
        ),
        array(
            'id'         => 'site_font_title',
            'type'       => 'text',
            'title'      => '标题字体',
            'desc'       => '字体名称，如：Noto Serif SC',
            'default'    => '',
            'dependency' => array('site_font_enabled', '==', 'true'),
        ),
        array(
            'id'         => 'site_font_size',
            'type'       => 'number',
            'title'      => '基础字号(px)',
            'desc'       => '留空使用主题默认',
            'default'    => '',
            'min'        => 12,
            'max'        => 24,
            'dependency' => array('site_font_enabled', '==', 'true'),
        ),
        array(
            'id'         => 'site_font_custom_url',
            'type'       => 'text',
            'title'      => '自托管字体样式地址',
            'desc'       => '如需外部字体，请先自托管（本地或自建 CDN）再填写 CSS 地址。主题不内置任何外部字体源。',
            'default'    => '',
            'dependency' => array('site_font_enabled', '==', 'true'),
        ),
    ));
}, 20);
