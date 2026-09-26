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
 * 内置本地字体清单（key => 标签，下拉用）
 */
function zhiji_site_font_local_fonts()
{
    return array(
        'none'      => '不使用本地字体',
        'wenkai'    => '霞鹜文楷（本地内置）',
        'sourcehan' => '思源宋体（本地内置）',
    );
}

/**
 * 本地字体定义（key 与下拉一致）。
 * files: font-weight => 文件相对路径。含 700 字重时页面粗体用真字体而非合成加粗。
 * 霞鹜文楷无 Bold 字重，用 Medium 映射到 700。
 *
 * @return array key => [family 名, files, font-display]
 */
function zhiji_site_font_local_faces()
{
    return array(
        'wenkai' => array(
            'LXGW WenKai Lite',
            array('normal' => 'fonts/lxgw-wenkai-lite.woff2', '700' => 'fonts/lxgw-wenkai-medium.woff2'),
            'swap',
        ),
        'sourcehan' => array(
            'Source Han Serif SC',
            array('normal' => 'fonts/source-han-serif.woff2', '700' => 'fonts/source-han-serif-bold.woff2'),
            'swap',
        ),
    );
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
    $local = (string) zhiji_get_option('site_font_local', 'none');

    // 自托管字体样式（可填本地路径或自建 CDN）。
    // ⚠️ 不内置任何外部域名：Google Fonts 在国内不可达，v2 按「资源本地化」铁律移除，
    //    需要外部字体请自托管后在此填写地址。
    if ($url) {
        printf('<link rel="stylesheet" href="%s" media="all">' . "\n", esc_url($url));
    }

    // 内置本地字体：输出 @font-face，并把 family 前置到正文/标题字体链。
    // 选了它之后「正文字体/标题字体」留空也会生效 —— 无需访客本机安装。
    $faces      = zhiji_site_font_local_faces();
    $prefix     = '';
    $family_str = '';
    if (isset($faces[$local])) {
        list($family, $files, $display) = $faces[$local];
        $family_str = $family;

        // 输出各字重的 @font-face（多 weight 同 family，浏览器按粗细自动选文件）
        $face_css = '';
        foreach ($files as $weight => $file) {
            $face_css .= sprintf(
                '@font-face{font-family:"%1$s";src:url("%2$s") format("woff2");font-weight:%3$s;font-style:normal;font-display:%4$s;}',
                esc_attr($family),
                esc_url(zhiji_asset_url($file)),
                esc_attr($weight),
                esc_attr($display)
            );
        }
        printf('<style id="zhiji-font-face">%s</style>' . "\n", $face_css);

        $prefix = $family . ',';
        if ('' === $body) {
            $body = $family;
        }
        if ('' === $title) {
            $title = $family;
        }
    }

    $css = '';
    // 手填字体名与本地 family 相同时不重复拼接
    $body_prefix  = ($body && $body !== $family_str) ? $prefix : '';
    $title_prefix = ($title && $title !== $family_str) ? $prefix : '';
    if ($body) {
        $css .= "body,button,input,select,textarea{font-family:'" . $body . "'," . $body_prefix . "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}\n";
    }
    if ($title) {
        $css .= "h1,h2,h3,h4,h5,h6,.entry-title,.post-title{font-family:'" . $title . "'," . $title_prefix . "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}\n";
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
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('site_font', array(
        array(
            'id'      => 'site_font_enabled',
            'type'    => 'switcher',
            'title'   => '启用自定义字体',
            'default' => false,
        ),
        array(
            'id'         => 'site_font_local',
            'type'       => 'select',
            'title'      => '内置本地字体',
            'desc'       => '字体文件随主题分发，所有访客无需安装即可显示（与下方手填字体名叠加使用：手填字体优先，本地字体兜底）。',
            'options'    => zhiji_site_font_local_fonts(),
            'default'    => 'none',
            'dependency' => array('site_font_enabled', '==', '1'),
        ),
        array(
            'id'         => 'site_font_body',
            'type'       => 'text',
            'title'      => '正文字体',
            'desc'       => '可选。填访客电脑已安装的字体名（如 楷体）；留空时使用上方内置本地字体。',
            'default'    => '',
            'dependency' => array('site_font_enabled', '==', '1'),
        ),
        array(
            'id'         => 'site_font_title',
            'type'       => 'text',
            'title'      => '标题字体',
            'desc'       => '字体名称，如：Noto Serif SC',
            'default'    => '',
            'dependency' => array('site_font_enabled', '==', '1'),
        ),
        array(
            'id'         => 'site_font_size',
            'type'       => 'number',
            'title'      => '基础字号(px)',
            'desc'       => '留空使用主题默认',
            'default'    => '',
            'min'        => 12,
            'max'        => 24,
            'dependency' => array('site_font_enabled', '==', '1'),
        ),
        array(
            'id'         => 'site_font_custom_url',
            'type'       => 'text',
            'title'      => '自托管字体样式地址',
            'desc'       => '如需外部字体，请先自托管（本地或自建 CDN）再填写 CSS 地址。主题不内置任何外部字体源。',
            'default'    => '',
            'dependency' => array('site_font_enabled', '==', '1'),
        ),
    ), 20);
