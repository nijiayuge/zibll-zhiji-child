<?php
/**
 * @module  TimeMachine
 * @desc    时光机 · 历史上的今天：聚合站点往年同月同日发布的文章，老内容零成本复活引流。
 *          仅使用站内数据（WP_Query），不依赖任何外部"历史上的今天"API。
 * @option  timemachine_enabled  总开关
 *          timemachine_years    回溯年数（1~20，默认 5）
 *          timemachine_limit    最多展示条数（默认 8）
 *          timemachine_auto     文章页末尾自动追加（否则仅短码）
 * @short   [zhiji_timemachine]
 * @since   2.0.0（2026-09-29 新增）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('time_machine', array(
    'title'    => '时光机·历史上的今天',
    'parent'   => 'zhiji_post',
    'priority' => 165,
    'option'   => 'timemachine_enabled',
));

/* ============================================================
 * 后台 CSF 设置：文章内容 → 时光机
 * ============================================================ */
Zhiji_Registry::register_options('time_machine', array(
    array(
        'type'    => 'submessage',
        'style'   => 'info',
        'content' => __('聚合站点<b>往年今天的文章</b>（仅站内数据，无外部 API）。前台两种用法：'
            . '① 短码 <code>[zhiji_timemachine]</code> 放任意位置；② 开启「文章页自动追加」。', 'zhiji'),
    ),
    array(
        'id'      => 'timemachine_enabled',
        'type'    => 'switcher',
        'title'   => '启用时光机',
        'default' => false,
    ),
    array(
        'type'  => 'subheading',
        'title' => __( '① 展示规则', 'zhiji' ),
    ),
    array(
        'id'         => 'timemachine_years',
        'type'       => 'number',
        'title'      => '回溯年数',
        'default'    => 5,
        'min'        => 1,
        'max'        => 20,
        'dependency' => array('timemachine_enabled', '==', '1'),
        'desc'       => __('向前追溯多少年内的「今天」。', 'zhiji'),
    ),
    array(
        'id'         => 'timemachine_limit',
        'type'       => 'number',
        'title'      => '最多展示条数',
        'default'    => 8,
        'min'        => 1,
        'max'        => 30,
        'dependency' => array('timemachine_enabled', '==', '1'),
    ),
    array(
        'type'  => 'subheading',
        'title' => __( '② 文章页自动追加', 'zhiji' ),
    ),
    array(
        'id'         => 'timemachine_auto',
        'type'       => 'switcher',
        'title'      => '文章页末尾自动追加',
        'default'    => false,
        'dependency' => array('timemachine_enabled', '==', '1'),
        'desc'       => __('开启后文章正文末尾自动展示「历史上的今天」，无需手插短码。', 'zhiji'),
    ),
), 165);

/* ============================================================
 * 数据层
 * ============================================================ */

/**
 * 查询「历史上的今天」文章（往年同月同日，排除当前文章）
 *
 * @param int $limit 条数
 * @return WP_Post[]
 */
function zhiji_tm_get_posts($limit = 8)
{
    $years = max(1, min(20, (int) zhiji_get_option('timemachine_years', 5)));
    $limit = max(1, min(30, (int) $limit));

    $q = new WP_Query(array(
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => $limit,
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
        'date_query'          => array(
            array(
                // 2026-09-30 修复：'Y-m-d' 会被 WP 解析为当天 00:00 且不含当天，
                // 导致「恰好去年今天」发布的文章被排除（与模块意图相悖）。补足到当天 23:59:59。
                'before' => date('Y-m-d 23:59:59', strtotime('-1 year')), // 不含今年今天（刚发布无意义）
                'month'  => (int) current_time('n'),
                'day'    => (int) current_time('j'),
            ),
        ),
        // date_query 不支持"最近 N 年"上界，用 after 兜底回溯范围
        'orderby'             => 'date',
        'order'               => 'DESC',
    ));

    // after 上界：today - N 年（date_query 的 before/after 混用会覆盖，手动过滤）
    $out      = array();
    $cutoff   = strtotime('-' . $years . ' years', current_time('timestamp'));
    $excluded = array(get_queried_object_id(), get_the_ID());
    foreach ($q->posts as $p) {
        if (in_array((int) $p->ID, array_map('intval', array_filter($excluded)), true)) {
            continue;
        }
        if (strtotime($p->post_date) < $cutoff) {
            continue;
        }
        $out[] = $p;
    }
    return $out;
}

/* ============================================================
 * 短码 + 自动追加
 * ============================================================ */

/**
 * 渲染「历史上的今天」卡片列表
 *
 * @param int $limit 条数（0 = 用后台配置）
 * @return string
 */
function zhiji_tm_render($limit = 0)
{
    if (!function_exists('zhiji_is_enabled') || !zhiji_is_enabled('timemachine_enabled', false)) {
        return '';
    }
    if (!$limit) {
        $limit = (int) zhiji_get_option('timemachine_limit', 8);
    }
    $posts = zhiji_tm_get_posts($limit);
    if (empty($posts)) {
        return '';
    }

    $out = '<div class="zhiji-tm zib-widget" style="padding:16px;margin:20px 0">';
    $out .= '<div style="font-weight:600;margin-bottom:10px">⏳ 历史上的今天</div>';
    $out .= '<ul style="list-style:none;margin:0;padding:0">';
    foreach ($posts as $p) {
        $year = mysql2date('Y', $p->post_date);
        $md   = mysql2date('m-d', $p->post_date);
        $out .= '<li style="display:flex;align-items:baseline;gap:8px;padding:6px 0;border-bottom:1px dashed #eee">'
            . '<span style="flex-shrink:0;font-weight:600;color:#e8533f">' . esc_html($year) . ' 年</span>'
            . '<a href="' . esc_url(get_permalink($p)) . '" style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . esc_html(get_the_title($p)) . '</a>'
            . '<span style="flex-shrink:0;margin-left:auto;font-size:12px;color:#999">' . esc_html($md) . '</span>'
            . '</li>';
    }
    $out .= '</ul></div>';
    return $out;
}

/**
 * 短码 [zhiji_timemachine]
 *
 * @return string
 */
function zhiji_tm_shortcode()
{
    return zhiji_tm_render();
}
add_shortcode('zhiji_timemachine', 'zhiji_tm_shortcode');

/**
 * 文章正文末尾自动追加（the_content 过滤，仅文章页 singular）
 *
 * @param string $content
 * @return string
 */
function zhiji_tm_auto_append($content)
{
    if (is_admin() || !is_singular('post') || !in_the_loop() || !is_main_query()) {
        return $content;
    }
    if (!function_exists('zhiji_is_enabled') || !zhiji_is_enabled('timemachine_auto', false)) {
        return $content;
    }
    // 正文含短码时不重复追加
    if (false !== strpos($content, '[zhiji_timemachine]')) {
        return $content;
    }
    return $content . zhiji_tm_render();
}
add_filter('the_content', 'zhiji_tm_auto_append', 30);
