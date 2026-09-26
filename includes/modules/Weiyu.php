<?php
/**
 * @module  Weiyu
 * @desc    微语时间线：shuoshuo CPT + 页面模板（点赞支持弹幕联动、同源校验 + IP 限频）
 * @option  weiyu_enabled         总开关
 *          weiyu_posts_per_page  每页条数
 *          weiyu_show_avatar     显示头像
 *          weiyu_like_enabled    点赞
 * @hook    init / template_include(20) / wp_ajax_zhiji_weiyu_like
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/Weiyu.php`（模板 templates/zhiji-weiyu.php 随迁）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('weiyu', array(
    'title'    => '微语时间线',
    'parent'   => 'zhiji_page',
    'priority' => 20,
    'option'   => 'weiyu_enabled',
));

/* ============================================================
 * CPT
 * ============================================================ */
add_action('init', function () {
    if (!zhiji_is_enabled('weiyu_enabled') || post_type_exists('shuoshuo')) {
        return;
    }
    register_post_type('shuoshuo', array(
        'labels'              => array(
            'name'          => '微语',
            'singular_name' => '微语',
            'add_new'       => '发布微语',
            'add_new_item'  => '发布新微语',
            'edit_item'     => '编辑微语',
            'new_item'      => '新微语',
            'view_item'     => '查看微语',
            'search_items'  => '搜索微语',
        ),
        'public'              => true,
        'has_archive'         => true,
        'show_in_rest'        => true,
        'exclude_from_search' => false,
        'supports'            => array('title', 'editor', 'author', 'thumbnail', 'comments'),
        'menu_icon'           => 'dashicons-format-chat',
        'menu_position'       => 5,
    ));
});

/* ============================================================
 * 模板（templates/zhiji-weiyu.php 随主题分发）
 * ============================================================ */
add_filter('theme_page_templates', function ($templates) {
    if (!zhiji_is_enabled('weiyu_enabled')) {
        return $templates;
    }
    $templates['zhiji-weiyu.php'] = '微语时间线页面（Zhiji）';
    return $templates;
});

add_filter('template_include', function ($template) {
    if (!zhiji_is_enabled('weiyu_enabled')) {
        return $template;
    }
    if (is_page() && get_page_template_slug() === 'zhiji-weiyu.php'
        || is_singular('shuoshuo')) {
        $file = ZHIJI_PATH . 'templates/zhiji-weiyu.php';
        if (file_exists($file)) {
            return $file;
        }
    }
    return $template;
}, 20);

/* ============================================================
 * 点赞（统一 AJAX 命名 zhiji_weiyu_like；请求参数沿用 um_id/um_action
 * 协议，前端模板随迁、前后端同源可控）
 * 安全：同源 Referer 校验 + 每 IP 每分钟 20 次限频
 * ============================================================ */
// 2026-09-26：注册到网关（P2-⑥），旧端点保留为转发入口
zhiji_api_register( 'zhiji_weiyu_like', 'zhiji_weiyu_like', true, '' );
add_action( 'wp_ajax_zhiji_weiyu_like', 'zhiji_api_legacy_forward' );
add_action('wp_ajax_nopriv_zhiji_weiyu_like', 'zhiji_weiyu_like');

/**
 * 点赞处理
 *
 * @return void
 */
function zhiji_weiyu_like()
{
    if (!zhiji_is_enabled('weiyu_enabled') || !zhiji_is_enabled('weiyu_like_enabled', true)) {
        die('0');
    }
    // 同源校验（父主题点赞 JS 无法附加 nonce）
    $ref  = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
    $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
    if ($host && $ref && strtolower((string) wp_parse_url($ref, PHP_URL_HOST)) !== strtolower($host)) {
        die('0');
    }
    // IP 限频：每分钟最多 20 次
    $ip     = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $ip_key = 'zhiji_like_' . md5($ip);
    $cnt    = (int) get_transient($ip_key);
    if ($cnt >= 20) {
        die('0');
    }
    set_transient($ip_key, $cnt + 1, MINUTE_IN_SECONDS);

    $id     = isset($_POST['um_id']) ? (int) $_POST['um_id'] : 0;
    $action = isset($_POST['um_action']) ? sanitize_text_field(wp_unslash($_POST['um_action'])) : '';
    if (!$id || 'ding' !== $action || 'shuoshuo' !== get_post_type($id)) {
        die('0');
    }

    $domain = ('localhost' !== $host) ? $host : false;
    setcookie('bigfa_ding_' . $id, (string) $id, time() + 99999999, '/', $domain, false);

    $raters = get_post_meta($id, 'bigfa_ding', true);
    update_post_meta($id, 'bigfa_ding', ($raters && is_numeric($raters)) ? (int) $raters + 1 : 1);

    // 弹幕联动（Danmu 模块启用时）
    if (function_exists('zhiji_danmu_push')) {
        zhiji_danmu_push('like', get_current_user_id(), '赞了一条动态', get_permalink($id));
    }

    echo get_post_meta($id, 'bigfa_ding', true);
    die;
}

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('weiyu', array(
        array(
            'id'      => 'weiyu_enabled',
            'type'    => 'switcher',
            'title'   => '启用微语时间线',
            'default' => false,
            'desc'    => '开启后注册 shuoshuo 自定义文章类型，并提供微语时间线页面模板（页面属性中选择）。',
        ),
        array(
            'id'         => 'weiyu_posts_per_page',
            'type'       => 'number',
            'title'      => '每页显示条数',
            'desc' => __( '微语时间线每页显示的条数。', 'zhiji' ),
            'default'    => 20,
            'min'        => 5,
            'max'        => 50,
            'dependency' => array('weiyu_enabled', '==', '1'),
        ),
        array(
            'id'         => 'weiyu_show_avatar',
            'type'       => 'switcher',
            'title'      => '显示作者头像',
            'desc' => __( '是否在每条微语旁显示作者头像。', 'zhiji' ),
            'default'    => true,
            'dependency' => array('weiyu_enabled', '==', '1'),
        ),
        array(
            'id'         => 'weiyu_like_enabled',
            'type'       => 'switcher',
            'title'      => '启用说说点赞',
            'default'    => true,
            'desc'       => '开启后每条微语显示点赞按钮（同源校验 + IP 限频），支持与弹幕联动。',
            'dependency' => array('weiyu_enabled', '==', '1'),
        ),
    ));
}, 20);
