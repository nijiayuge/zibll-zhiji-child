<?php
/**
 * @module  Document
 * @desc    文档知识库：document CPT + 多级分类（目录树）+ [zhiji_doc_tree] 短代码 + TOC 开关
 * @option  document_enabled  总开关
 *          document_slug     URL 别名
 *          document_toc      详情页 TOC
 * @hook    init(5) / init(20) / template_include(99)
 * @shortcode [zhiji_doc_tree show_count="1"]
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/Document.php`
 *          （归档/详情模板文件 v1 未提供，file_exists 回落父主题模板；TOC 渲染由模板负责）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('document', array(
    'title'    => '文档知识库',
    'parent'   => 'zhiji_page',
    'priority' => 150,
    'option'   => 'document_enabled',
));

/* ============================================================
 * CPT + 分类法
 * ============================================================ */
add_action('init', function () {
    if (!zhiji_is_enabled('document_enabled')) {
        return;
    }
    $slug = sanitize_title(zhiji_get_option('document_slug', 'docs'));
    if ('' === $slug) {
        $slug = 'docs';
    }

    if (!post_type_exists('document')) {
        register_post_type('document', array(
            'labels'        => array(
                'name'          => '文档',
                'singular_name' => '文档',
                'add_new_item'  => '新建文档',
                'edit_item'     => '编辑文档',
                'new_item'      => '新文档',
                'view_item'     => '查看文档',
                'search_items'  => '搜索文档',
            ),
            'public'        => true,
            'has_archive'   => $slug,
            'menu_icon'     => 'dashicons-book-alt',
            'menu_position' => 21,
            'supports'      => array('title', 'editor', 'author', 'excerpt', 'thumbnail', 'comments'),
            'taxonomies'    => array('document_cat'),
            'rewrite'       => array('slug' => $slug, 'with_front' => false),
            'show_in_rest'  => false,
        ));
        register_taxonomy('document_cat', array('document'), array(
            'labels'            => array(
                'name'          => '文档分类',
                'singular_name' => '文档分类',
                'add_new_item'  => '新建文档分类',
                'edit_item'     => '编辑文档分类',
            ),
            'public'            => true,
            'hierarchical'      => true,
            'rewrite'           => array('slug' => 'doc-cat', 'with_front' => false),
            'show_admin_column' => true,
        ));
    }
}, 5);

/**
 * 首次 flush 重写规则
 */
add_action('init', function () {
    if (!zhiji_is_enabled('document_enabled') || get_option('zhiji_document_flushed')) {
        return;
    }
    flush_rewrite_rules();
    update_option('zhiji_document_flushed', 1);
}, 20);

/**
 * 模板接管（自定义模板存在时才接管）
 */
add_filter('template_include', function ($template) {
    if (!zhiji_is_enabled('document_enabled')) {
        return $template;
    }
    $file = '';
    if (is_post_type_archive('document') || is_tax('document_cat')) {
        $file = 'zhiji-document-archive.php';
    } elseif (is_singular('document')) {
        $file = 'zhiji-single-document.php';
    }
    if ($file) {
        $custom = ZHIJI_PATH . 'templates/' . $file;
        if (file_exists($custom)) {
            return $custom;
        }
    }
    return $template;
}, 99);

/* ============================================================
 * 目录树短代码
 * ============================================================ */

/**
 * 渲染文档分类目录树（可多级）
 *
 * @param bool $show_count 是否显示数量
 * @return string
 */
function zhiji_document_tree_html($show_count = true)
{
    $terms = get_terms(array(
        'taxonomy'   => 'document_cat',
        'hide_empty' => false,
        'parent'     => 0,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ));
    if (is_wp_error($terms) || empty($terms)) {
        return '<div class="zhiji-doc-tree-empty">暂无文档分类。</div>';
    }

    $html = '<ul class="zhiji-doc-tree">';
    foreach ($terms as $term) {
        $link  = get_term_link($term);
        if (is_wp_error($link)) {
            continue;
        }
        $count = $show_count ? ' <span class="zhiji-doc-count">(' . (int) $term->count . ')</span>' : '';
        $html .= '<li><strong><a href="' . esc_url($link) . '">' . esc_html($term->name) . '</a></strong>' . $count;

        $children = get_terms(array(
            'taxonomy'   => 'document_cat',
            'hide_empty' => false,
            'parent'     => $term->term_id,
            'orderby'    => 'name',
        ));
        if (!is_wp_error($children) && $children) {
            $html .= '<ul class="zhiji-doc-tree-sub">';
            foreach ($children as $child) {
                $clink = get_term_link($child);
                if (is_wp_error($clink)) {
                    continue;
                }
                $html .= '<li><a href="' . esc_url($clink) . '">' . esc_html($child->name) . '</a>'
                    . ($show_count ? ' <span class="zhiji-doc-count">(' . (int) $child->count . ')</span>' : '') . '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</li>';
    }
    $html .= '</ul>';

    $html .= '<style id="zhiji-doc-tree-css">'
        . '.zhiji-doc-tree{list-style:none;margin:0;padding:0}'
        . '.zhiji-doc-tree li{margin:6px 0}'
        . '.zhiji-doc-tree a{color:var(--focus-color,#3b82f6);text-decoration:none}'
        . '.zhiji-doc-tree a:hover{text-decoration:underline}'
        . '.zhiji-doc-tree-sub{list-style:none;margin:4px 0 4px 16px;padding:0}'
        . '.zhiji-doc-tree-sub a{color:var(--muted-color,#8a919f)}'
        . '.zhiji-doc-count{color:var(--muted-color,#8a919f);font-size:12px}'
        . '</style>';

    return $html;
}

add_shortcode('zhiji_doc_tree', function ($atts) {
    if (!zhiji_is_enabled('document_enabled')) {
        return '';
    }
    $atts = shortcode_atts(array('show_count' => '1'), $atts, 'zhiji_doc_tree');
    return zhiji_document_tree_html((bool) $atts['show_count']);
});

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('document', array(
        array(
            'id'      => 'document_enabled',
            'type'    => 'switcher',
            'title'   => '启用文档知识库',
            'default' => false,
            'desc'    => '启用后新增「文档」自定义文章类型和「文档分类」，支持目录树和TOC自动导航。',
        ),
        array(
            'id'         => 'document_slug',
            'type'       => 'text',
            'title'      => '文档 URL 别名（slug）',
            'default'    => 'docs',
            'desc'       => '文档归档页的 URL 前缀，修改后需重新保存固定链接。',
            'dependency' => array('document_enabled', '==', '1'),
        ),
        array(
            'id'         => 'document_toc',
            'type'       => 'switcher',
            'title'      => '详情页 TOC 自动目录',
            'default'    => true,
            'desc'       => '在文档详情页自动生成 h2/h3 目录导航（由模板渲染）。',
            'dependency' => array('document_enabled', '==', '1'),
        ),
    ));
}, 20);
