<?php
/**
 * @module  TagCloud3D
 * @desc    3D 云标签：斐波那契球面算法 + 纯 CSS 3D 旋转，零外部库
 * @option  tag3d_enabled  总开关
 *          tag3d_limit    标签数量
 *          tag3d_radius   球体半径(px)
 * @shortcode [zhiji_tag_cloud_3d]
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/TagCloud3D.php`
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('tag_cloud_3d', array(
    'title'    => '3D 云标签',
    'parent'   => 'zhiji_element',
    'priority' => 90,
    'option'   => 'tag3d_enabled',
));

/* ============================================================
 * 短代码
 * ============================================================ */

/**
 * [zhiji_tag_cloud_3d] 球面旋转标签云
 *
 * @return string
 */
function zhiji_tag3d_shortcode()
{
    if (!zhiji_is_enabled('tag3d_enabled')) {
        return '';
    }

    $limit  = max(5, min(80, (int) zhiji_get_option('tag3d_limit', 30)));
    $radius = max(80, min(260, (int) zhiji_get_option('tag3d_radius', 150)));

    $terms = get_terms(array(
        'taxonomy'   => 'post_tag',
        'hide_empty' => true,
        'number'     => $limit,
        'orderby'    => 'count',
        'order'      => 'DESC',
    ));
    if (is_wp_error($terms) || empty($terms)) {
        return '';
    }
    shuffle($terms);

    $n      = count($terms);
    $golden = M_PI * (1 + sqrt(5));
    $colors = array('#3b82f6', '#2563eb', '#60a5fa', '#7c9cf5', '#38bdf8', '#4f7df3');

    $counts = array();
    foreach ($terms as $t) {
        $counts[] = (int) $t->count;
    }
    $max_count = $counts ? max($counts) : 1;

    $html = '<div class="zhiji-tag3d" style="height:' . esc_attr($radius * 2 + 40) . 'px">';
    $html .= '<div class="zhiji-tag3d-sphere">';

    $i = 0;
    foreach ($terms as $term) {
        // 斐波那契球面坐标
        $phi   = acos(1 - 2 * ($i + 0.5) / $n);
        $theta = $golden * $i;

        // 字号按热度 12~18px
        $ratio   = $max_count > 0 ? (int) $term->count / $max_count : 0;
        $font    = 12 + round(6 * $ratio);
        $color   = $colors[$i % count($colors)];
        $opacity = round(0.55 + 0.45 * $ratio, 2);

        $link = get_term_link($term);
        if (is_wp_error($link)) {
            $i++;
            continue;
        }

        $html .= '<a class="zhiji-tag3d-item" href="' . esc_url($link) . '" '
            . 'style="transform:rotateX(' . esc_attr(rad2deg($phi)) . 'deg) rotateY(' . esc_attr(rad2deg($theta)) . 'deg) translateZ(' . esc_attr($radius) . 'px);'
            . 'font-size:' . esc_attr($font) . 'px;color:' . esc_attr($color) . ';opacity:' . esc_attr($opacity) . '">'
            . esc_html($term->name)
            . '</a>';
        $i++;
    }

    $html .= '</div></div>';
    $html .= '<style id="zhiji-tag3d-css">'
        . '.zhiji-tag3d{perspective:900px;display:flex;align-items:center;justify-content:center;position:relative;margin:10px auto;max-width:100%;overflow:hidden}'
        . '.zhiji-tag3d-sphere{position:relative;width:0;height:0;transform-style:preserve-3d;animation:zhiji-tag3d-spin 30s linear infinite}'
        . '.zhiji-tag3d:hover .zhiji-tag3d-sphere{animation-play-state:paused}'
        . '.zhiji-tag3d-item{position:absolute;left:0;top:0;white-space:nowrap;padding:4px 12px;border-radius:16px;'
        . 'background:rgba(59,130,246,.07);border:1px solid rgba(59,130,246,.16);text-decoration:none;font-weight:600;'
        . 'transition:background .2s,color .2s,transform .2s;transform-origin:center;will-change:transform}'
        . '.zhiji-tag3d-item:hover{background:rgba(59,130,246,.16);color:#1d4ed8!important}'
        . '@keyframes zhiji-tag3d-spin{0%{transform:rotateX(-12deg) rotateY(0)}100%{transform:rotateX(-12deg) rotateY(360deg)}}'
        . '</style>';

    return $html;
}
add_shortcode('zhiji_tag_cloud_3d', 'zhiji_tag3d_shortcode');

/* ============================================================
 * 后台字段
 * ============================================================ */
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('tag_cloud_3d', array(
        array(
            'id'      => 'tag3d_enabled',
            'type'    => 'switcher',
            'title'   => '启用 3D 云标签',
            'desc'    => '通过短代码 [zhiji_tag_cloud_3d] 展示球面旋转标签云（纯 CSS 3D，无外部库）。',
            'default' => false,
        ),
        array(
            'id'         => 'tag3d_limit',
            'type'       => 'number',
            'title'      => '标签数量',
            'desc' => __( '3D 云标签展示的标签数量。', 'zhiji' ),
            'default'    => 30,
            'min'        => 5,
            'max'        => 80,
            'dependency' => array('tag3d_enabled', '==', '1'),
        ),
        array(
            'id'         => 'tag3d_radius',
            'type'       => 'number',
            'title'      => '球体半径(px)',
            'desc' => __( '3D 球体的半径（像素），影响标签分布的疏密。', 'zhiji' ),
            'default'    => 150,
            'min'        => 80,
            'max'        => 260,
            'dependency' => array('tag3d_enabled', '==', '1'),
        ),
    ), 20);
