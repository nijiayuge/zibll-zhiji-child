<?php
/**
 * @module  HistoryToday
 * @desc    那年今日：按「月-日」匹配往年同日发布的文章，以短代码展示
 * @option  history_today_enabled  总开关
 *          history_today_title    区块标题
 *          history_today_limit    最多展示条数
 * @shortcode [zhiji_history_today]
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/HistoryToday.php`
 *          （修正：v1 在短代码回调里 echo 样式，会输出到内容之前；v2 改为随 HTML 一起返回）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('history_today', array(
    'title'    => '那年今日',
    'parent'   => 'zhiji_page',
    'priority' => 110,
    'option'   => 'history_today_enabled',
));

/* ============================================================
 * 短代码
 * ============================================================ */

/**
 * [zhiji_history_today] 往年今日文章列表
 *
 * @return string
 */
function zhiji_history_today_shortcode()
{
    if (!zhiji_is_enabled('history_today_enabled')) {
        return '';
    }

    $limit = (int) zhiji_get_option('history_today_limit', 10);
    $limit = max(1, min(50, $limit));
    $title = (string) zhiji_get_option('history_today_title', '往年今日');

    global $wpdb;
    $now      = current_time('timestamp');
    $month    = (int) gmdate('n', $now);
    $day      = (int) gmdate('j', $now);
    $cur_year = (int) gmdate('Y', $now);

    // 月/日均经 (int) 强转后由 $wpdb->prepare 占位，无注入面
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, post_title, post_date FROM {$wpdb->posts}
         WHERE post_status = 'publish'
           AND post_type = 'post'
           AND MONTH(post_date) = %d
           AND DAY(post_date) = %d
           AND YEAR(post_date) != %d
         ORDER BY post_date DESC
         LIMIT %d",
        $month, $day, $cur_year, $limit
    ));

    if (empty($rows)) {
        return '';
    }

    // 样式随 HTML 一起返回（短代码必须 return，不能 echo）
    $html = '<style id="zhiji-history-css">'
        . '.zhiji-history-today{max-width:420px;margin:14px auto;padding:18px;'
        . 'border:1px solid var(--main-border-color,#e5e7eb);border-radius:12px;background:var(--main-bg-color,#fff)}'
        . '.zhiji-history-title{font-size:14px;font-weight:700;margin-bottom:10px;color:var(--focus-color,#3b82f6)}'
        . '.zhiji-history-list{list-style:none;margin:0;padding:0}'
        . '.zhiji-history-list li{display:flex;gap:8px;align-items:baseline;padding:7px 0;'
        . 'border-bottom:1px dashed var(--main-border-color,#f0f1f2);font-size:14px;line-height:1.5}'
        . '.zhiji-history-list li:last-child{border-bottom:none}'
        . '.zhiji-history-list a{color:var(--main-color,#333);text-decoration:none;'
        . 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
        . '.zhiji-history-list a:hover{color:var(--focus-color,#3b82f6)}'
        . '.zhiji-history-year{flex-shrink:0;font-size:12px;color:#fff;background:var(--focus-color,#3b82f6);'
        . 'border-radius:4px;padding:0 6px;line-height:20px}'
        . '</style>';

    $html .= '<div class="zhiji-history-today">';
    $html .= '<div class="zhiji-history-title">&#128197; ' . esc_html($title) . '</div>';
    $html .= '<ul class="zhiji-history-list">';
    foreach ($rows as $row) {
        $year  = (int) gmdate('Y', strtotime($row->post_date));
        $html .= '<li><span class="zhiji-history-year">' . esc_html($year) . '</span>'
            . '<a href="' . esc_url(get_permalink($row->ID)) . '" title="' . esc_attr($row->post_title) . '">'
            . esc_html($row->post_title) . '</a></li>';
    }
    $html .= '</ul></div>';

    return $html;
}
add_shortcode('zhiji_history_today', 'zhiji_history_today_shortcode');

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('history_today', array(
        array(
            'id'      => 'history_today_enabled',
            'type'    => 'switcher',
            'title'   => '启用那年今日',
            'desc'    => '通过短代码 [zhiji_history_today] 展示往年今日发布的文章。',
            'default' => false,
        ),
        array(
            'id'         => 'history_today_title',
            'type'       => 'text',
            'title'      => '区块标题',
            'default'    => '往年今日',
            'dependency' => array('history_today_enabled', '==', '1'),
        ),
        array(
            'id'         => 'history_today_limit',
            'type'       => 'number',
            'title'      => '最多展示条数',
            'default'    => 10,
            'min'        => 1,
            'max'        => 50,
            'dependency' => array('history_today_enabled', '==', '1'),
        ),
    ));
}, 20);
