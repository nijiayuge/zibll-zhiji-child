<?php
/**
 * @module  Infomation
 * @desc    资讯 CPT：新增「资讯」文章类型与「资讯分类」，归档页 /{slug}/
 * @option  infomation_enabled  总开关
 *          infomation_slug     URL 别名
 * @hook    init(5) / init(20) / template_include(99)
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/Infomation.php`
 *          （模板接管保留 file_exists 检查：templates/zhiji-infomation-archive.php
 *            不存在时自动回落父主题模板，无副作用）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('infomation', array(
    'title'    => '资讯 CPT',
    'parent'   => 'zhiji_page',
    'priority' => 140,
    'option'   => 'infomation_enabled',
));

/* ============================================================
 * CPT 注册
 * ============================================================ */
add_action('init', function () {
    if (!zhiji_is_enabled('infomation_enabled')) {
        return;
    }
    $slug = sanitize_title(zhiji_get_option('infomation_slug', 'infomation'));
    if ('' === $slug) {
        $slug = 'infomation';
    }

    register_post_type('infomation', array(
        'labels'        => array(
            'name'          => '资讯',
            'singular_name' => '资讯',
            'add_new_item'  => '发布资讯',
            'edit_item'     => '编辑资讯',
            'new_item'      => '新资讯',
            'view_item'     => '查看资讯',
            'search_items'  => '搜索资讯',
            'not_found'     => '未找到资讯',
            'menu_name'     => '资讯',
        ),
        'public'        => true,
        'has_archive'   => $slug,
        'menu_icon'     => 'dashicons-megaphone',
        'menu_position' => 5,
        'supports'      => array('title', 'editor', 'author', 'excerpt', 'thumbnail', 'comments'),
        'taxonomies'    => array('infomation_cat'),
        'rewrite'       => array('slug' => $slug, 'with_front' => false),
        'show_in_rest'  => true,
    ));

    register_taxonomy('infomation_cat', array('infomation'), array(
        'labels'       => array(
            'name'          => '资讯分类',
            'singular_name' => '资讯分类',
            'add_new_item'  => '添加资讯分类',
            'edit_item'     => '编辑资讯分类',
            'menu_name'     => '资讯分类',
        ),
        'public'       => true,
        'hierarchical' => true,
        'rewrite'      => array('slug' => $slug . '-cat', 'with_front' => false),
        'show_in_rest' => true,
    ));
}, 5);

/**
 * 重写规则刷新（仅首次；开关开启状态下的第一次 init）
 */
add_action('init', function () {
    if (!zhiji_is_enabled('infomation_enabled')) {
        return;
    }
    if (get_option('zhiji_infomation_flushed')) {
        return;
    }
    flush_rewrite_rules();
    update_option('zhiji_infomation_flushed', 1);
}, 20);

/**
 * 归档/分类页模板接管（自定义模板存在时才接管）
 */
add_filter('template_include', function ($template) {
    if (!zhiji_is_enabled('infomation_enabled')) {
        return $template;
    }
    if (is_post_type_archive('infomation') || is_tax('infomation_cat')) {
        $custom = ZHIJI_PATH . 'templates/zhiji-infomation-archive.php';
        if (file_exists($custom)) {
            return $custom;
        }
    }
    return $template;
}, 99);

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('infomation', array(
        array(
            'id'      => 'infomation_enabled',
            'type'    => 'switcher',
            'title'   => '启用资讯 CPT',
            'default' => false,
            'desc'    => '开启后新增「资讯」文章类型和「资讯分类」，归档页 /infomation/。',
        ),
        array(
            'id'         => 'infomation_slug',
            'type'       => 'text',
            'title'      => 'URL 别名（slug）',
            'default'    => 'infomation',
            'desc'       => '修改后需在 设置→固定链接 中保存一次以刷新重写规则。',
            'dependency' => array('infomation_enabled', '==', 'true'),
        ),
    ));
}, 20);
