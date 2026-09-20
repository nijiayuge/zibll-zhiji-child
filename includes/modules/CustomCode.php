<?php
/**
 * @module  CustomCode
 * @desc    自定义代码：站点级 CSS / head / footer 代码注入与页脚版权文案
 * @option  custom_code_enabled  总开关
 *          custom_css           自定义 CSS
 *          custom_head          自定义 head 代码
 *          custom_footer        自定义 footer 代码
 *          footer_credit        页脚版权文案
 * @hook    wp_head · 输出 CSS 与 head 代码
 *          wp_footer · 输出 footer 代码与版权
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/CustomCode.php`
 *          （v2 新增总开关，与其它模块行为一致；管理员配置的代码原样输出以保留
 *          script 标签——该选项仅 manage_options 可改，权限受控）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('custom_code', array(
    'title'    => '自定义代码',
    'parent'   => 'zhiji_basic',
    'priority' => 20,
    'option'   => 'custom_code_enabled',
));

/* ============================================================
 * 输出（钩子常注册，回调内判开关）
 * ============================================================ */

add_action('wp_head', function () {
    if (!zhiji_is_enabled('custom_code_enabled')) {
        return;
    }
    $css = trim((string) zhiji_get_option('custom_css', ''));
    if ('' !== $css) {
        echo '<style id="zhiji-custom-css">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 管理员配置的站点级 CSS
    }
    $code = trim((string) zhiji_get_option('custom_head', ''));
    if ('' !== $code) {
        echo "\n<!-- zhiji custom head -->\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 管理员配置的统计/验证代码
    }
}, 98);

add_action('wp_footer', function () {
    if (!zhiji_is_enabled('custom_code_enabled')) {
        return;
    }
    $credit = trim((string) zhiji_get_option('footer_credit', ''));
    if ('' !== $credit) {
        echo '<div class="zhiji-footer-credit text-center muted-color mt6">' . esc_html($credit) . '</div>';
    }
    $code = trim((string) zhiji_get_option('custom_footer', ''));
    if ('' !== $code) {
        echo "\n<!-- zhiji custom footer -->\n" . $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 管理员配置的站点级代码
    }
}, 998);

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('custom_code', array(
        array(
            'id'      => 'custom_code_enabled',
            'type'    => 'switcher',
            'title'   => '启用自定义代码',
            'default' => false,
        ),
        array(
            'id'         => 'custom_css',
            'type'       => 'textarea',
            'title'      => '自定义 CSS',
            'desc'       => '输出到 head 的 style 标签内',
            'dependency' => array('custom_code_enabled', '==', '1'),
        ),
        array(
            'id'         => 'custom_head',
            'type'       => 'textarea',
            'title'      => '自定义 head 代码',
            'desc'       => '统计代码/验证代码等，原样输出（仅管理员可改）',
            'dependency' => array('custom_code_enabled', '==', '1'),
        ),
        array(
            'id'         => 'custom_footer',
            'type'       => 'textarea',
            'title'      => '自定义 footer 代码',
            'desc'       => '输出到 body 结束前，原样输出（仅管理员可改）',
            'dependency' => array('custom_code_enabled', '==', '1'),
        ),
        array(
            'id'         => 'footer_credit',
            'type'       => 'text',
            'title'      => '自定义页脚版权',
            'desc'       => '纯文本，显示在页脚底部',
            'dependency' => array('custom_code_enabled', '==', '1'),
        ),
    ));
}, 20);
