<?php
/**
 * @module  HotTags
 * @desc    热门标签云：按文章数量降序展示标签卡片网格
 * @option  hot_tags_enabled     总开关
 *          hot_tags_count       显示标签数量
 *          hot_tags_columns     每行列数
 *          hot_tags_show_count  显示文章数量
 * @shortcode [zhiji_hot_tags]
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/HotTags.php`
 *          ⚠️ 改造：v1 注册页面模板 templates/zhiji-hot-tags.php，但该模板文件
 *          在 v1 中从未存在（templates/ 仅 weiyu 一个文件），「页面模板」路径
 *          从未生效。v2 改造为短代码方案，可放子比模块化首页的 HTML 模块。
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('hot_tags', array(
    'title'    => '热门标签云',
    'parent'   => 'zhiji_page',
    'priority' => 20,
    'option'   => 'hot_tags_enabled',
));

/* ============================================================
 * 短代码
 * ============================================================ */

/**
 * [zhiji_hot_tags] 热门标签卡片网格
 *
 * @return string
 */
function zhiji_hot_tags_shortcode()
{
    if (!zhiji_is_enabled('hot_tags_enabled')) {
        return '';
    }

    $count     = max(10, min(200, (int) zhiji_get_option('hot_tags_count', 60)));
    $columns   = in_array((string) zhiji_get_option('hot_tags_columns', '6'), array('3', '4', '5', '6'), true)
        ? (string) zhiji_get_option('hot_tags_columns', '6')
        : '6';
    $show_count = (bool) zhiji_get_option('hot_tags_show_count', true);

    $terms = get_terms(array(
        'taxonomy'   => 'post_tag',
        'hide_empty' => true,
        'number'     => $count,
        'orderby'    => 'count',
        'order'      => 'DESC',
    ));
    if (is_wp_error($terms) || !$terms) {
        return '';
    }

    $style = 'grid-template-columns:repeat(' . (int) $columns . ',1fr);';
    $html  = '<div class="zhiji-hot-tags" style="' . esc_attr($style) . '">';
    foreach ($terms as $term) {
        $html .= '<a class="zhiji-hot-tag" href="' . esc_url(get_term_link($term)) . '">';
        $html .= '<span class="zhiji-hot-tag-name">' . esc_html($term->name) . '</span>';
        if ($show_count) {
            $html .= '<span class="zhiji-hot-tag-count">' . (int) $term->count . ' 篇</span>';
        }
        $html .= '</a>';
    }
    $html .= '</div>';

    $html .= '<style id="zhiji-hot-tags-css">'
        . '.zhiji-hot-tags{display:grid;gap:10px;margin:15px 0}'
        . '.zhiji-hot-tag{display:flex;flex-direction:column;align-items:center;gap:2px;padding:12px 8px;'
        . 'background:var(--main-bg-color,#fff);border-radius:var(--main-radius,8px);'
        . 'box-shadow:var(--main-shadow,0 1px 4px rgba(0,0,0,.06));text-decoration:none;transition:transform .15s}'
        . '.zhiji-hot-tag:hover{transform:translateY(-2px)}'
        . '.zhiji-hot-tag-name{color:var(--main-color,#333);font-size:14px;font-weight:500}'
        . '.zhiji-hot-tag-count{color:var(--muted-color,#999);font-size:12px}'
        . '@media (max-width:768px){.zhiji-hot-tags{grid-template-columns:repeat(3,1fr)!important}}'
        . '</style>';

    return $html;
}
add_shortcode('zhiji_hot_tags', 'zhiji_hot_tags_shortcode');

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('hot_tags', array(
        array(
            'id'      => 'hot_tags_enabled',
            'type'    => 'switcher',
            'title'   => '启用热门标签云',
            'desc'    => '通过短代码 [zhiji_hot_tags] 展示标签卡片网格（可放子比模块化首页的 HTML 模块）。',
            'default' => false,
        ),
        array(
            'id'         => 'hot_tags_count',
            'type'       => 'number',
            'title'      => '显示标签数量',
            'default'    => 60,
            'min'        => 10,
            'max'        => 200,
            'desc'       => '热门标签页面显示的标签数量（按文章数量降序）。',
            'dependency' => array('hot_tags_enabled', '==', '1'),
        ),
        array(
            'id'         => 'hot_tags_columns',
            'type'       => 'select',
            'title'      => '每行显示列数',
            'default'    => '6',
            'options'    => array('3' => '3列', '4' => '4列', '5' => '5列', '6' => '6列'),
            'desc'       => '桌面端每行显示的标签卡片数量（移动端固定 3 列）。',
            'dependency' => array('hot_tags_enabled', '==', '1'),
        ),
        array(
            'id'         => 'hot_tags_show_count',
            'type'       => 'switcher',
            'title'      => '显示文章数量',
            'default'    => true,
            'desc'       => '在标签卡片下方显示该标签下的文章数量。',
            'dependency' => array('hot_tags_enabled', '==', '1'),
        ),
    ));
}, 20);
