<?php
/**
 * @module  Stream
 * @desc    动态流短代码：[zhiji_stream] 聚合多内容类型（文章/微语/资讯…）混合时间线
 * @option  stream_enable         总开关
 *          stream_default_count  默认显示数量
 *          stream_default_types  默认内容类型
 * @shortcode [zhiji_stream count="10" types="post,shuoshuo,infomation"]
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/Stream.php`
 *          （微语/资讯/问答/文档 等类型需对应模块启用后才有内容，本模块只做聚合展示）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('stream', array(
    'title'    => '动态流',
    'parent'   => 'zhiji_forum',
    'priority' => 10,
    'option'   => 'stream_enable',
));

/* ============================================================
 * 类型标签
 * ============================================================ */

/**
 * 内容类型 => 显示标签
 *
 * @return array
 */
function zhiji_stream_type_labels()
{
    return array(
        'post'       => '文章',
        'shuoshuo'   => '微语',
        'infomation' => '资讯',
        'ask'        => '问答',
        'document'   => '文档',
    );
}

/* ============================================================
 * 短代码
 * ============================================================ */
add_shortcode('zhiji_stream', function ($atts) {
    if (!zhiji_is_enabled('stream_enable')) {
        return '';
    }

    $default_count = (int) zhiji_get_option('stream_default_count', 10);
    $default_types = (string) zhiji_get_option('stream_default_types', 'post,shuoshuo,infomation');

    $atts = shortcode_atts(array(
        'count' => $default_count,
        'types' => $default_types,
    ), $atts, 'zhiji_stream');

    $types = array_filter(array_map('sanitize_key', array_map('trim', explode(',', (string) $atts['types']))));
    if (empty($types)) {
        $types = array('post');
    }

    $items = get_posts(array(
        'post_type'      => $types,
        'posts_per_page' => max(1, (int) $atts['count']),
        'orderby'        => 'date',
        'order'          => 'DESC',
        'post_status'    => 'publish',
        'no_found_rows'  => true,
    ));

    if (!$items) {
        return '<div class="zhiji-stream-empty">暂无动态。</div>';
    }

    $type_labels = zhiji_stream_type_labels();
    $html = '<div class="zhiji-stream">';
    foreach ($items as $item) {
        $type    = $item->post_type;
        $label   = isset($type_labels[$type]) ? $type_labels[$type] : '内容';
        $excerpt = wp_trim_words(wp_strip_all_tags($item->post_excerpt ? $item->post_excerpt : $item->post_content), 40, '…');
        $author  = get_the_author_meta('display_name', $item->post_author);

        $html .= '<div class="zhiji-stream-item"><div class="zhiji-stream-type">'
            . '<span class="zhiji-stream-badge">' . esc_html($label) . '</span></div>'
            . '<div class="zhiji-stream-body">'
            . '<a class="zhiji-stream-title" href="' . esc_url(get_permalink($item->ID)) . '">' . esc_html($item->post_title) . '</a>'
            . '<div class="zhiji-stream-excerpt">' . esc_html($excerpt) . '</div>'
            . '<div class="zhiji-stream-meta">' . esc_html($author) . ' · ' . esc_html(mysql2date('Y-m-d H:i', $item->post_date)) . '</div>'
            . '</div></div>';
    }
    $html .= '</div>';

    // 样式随短代码返回（不依赖父主题私有工具类）
    $html .= '<style id="zhiji-stream-css">'
        . '.zhiji-stream{max-width:100%;margin:10px 0;padding:14px;border:1px solid var(--main-border-color,#e5e7eb);border-radius:12px;background:var(--main-bg-color,#fff)}'
        . '.zhiji-stream-item{display:flex;gap:10px;padding:10px 0;border-bottom:1px dashed var(--main-border-color,#f0f1f2)}'
        . '.zhiji-stream-item:last-child{border-bottom:none}'
        . '.zhiji-stream-type{flex-shrink:0}'
        . '.zhiji-stream-badge{display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;color:#fff;'
        . 'background:linear-gradient(135deg,#3b82f6,#2563eb)}'
        . '.zhiji-stream-title{font-weight:600;color:var(--main-color,#333);text-decoration:none}'
        . '.zhiji-stream-title:hover{color:var(--focus-color,#3b82f6)}'
        . '.zhiji-stream-excerpt{font-size:13px;color:var(--muted-color,#8a919f);margin-top:4px}'
        . '.zhiji-stream-meta{font-size:12px;color:var(--muted-color,#8a919f);margin-top:4px;opacity:.85}'
        . '</style>';

    return $html;
});

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('stream', array(
        array(
            'id'      => 'stream_enable',
            'type'    => 'switcher',
            'title'   => '启用动态流短代码',
            'default' => false,
            'desc'    => '启用后可使用 [zhiji_stream] 短代码输出混合时间线。',
        ),
        array(
            'id'         => 'stream_default_count',
            'type'       => 'number',
            'title'      => '默认显示数量',
            'default'    => '10',
            'desc'       => '短代码默认显示的动态数量（可通过 count 参数覆盖）。',
            'dependency' => array('stream_enable', '==', 'true'),
        ),
        array(
            'id'         => 'stream_default_types',
            'type'       => 'text',
            'title'      => '默认内容类型',
            'default'    => 'post,shuoshuo,infomation',
            'desc'       => '短代码默认聚合的内容类型（可通过 types 参数覆盖，逗号分隔）。',
            'dependency' => array('stream_enable', '==', 'true'),
        ),
    ));
}, 20);
