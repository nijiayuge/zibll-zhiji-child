<?php
/**
 * @module  TransplantBeautify
 * @desc    文章信息增强：字数统计 + 阅读时间估算（可前置/后置于正文）
 * @option  transplant_enabled     总开关
 *          transplant_wcr         字数与阅读时间
 *          transplant_wcr_speed   阅读速度（字/分钟）
 *          transplant_wcr_position 显示位置 before|after
 * @hook    the_content · 正文渲染时注入信息行
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/TransplantBeautify.php`
 *          （修正沿用：preg_replace 需用 /\s/ 删空白符——/s/ 只会删字母 s）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('transplant_beautify', array(
    'title'    => '文章信息增强',
    'parent'   => 'zhiji_post',
    'priority' => 20,
    'option'   => 'transplant_enabled',
));

/* ============================================================
 * 计算
 * ============================================================ */

/**
 * 计算文章字数和阅读时间
 *
 * @return string
 */
function zhiji_count_words_read_time()
{
    global $post;
    if (empty($post)) {
        return '';
    }
    $speed = (int) zhiji_get_option('transplant_wcr_speed', 300);
    if ($speed < 1) {
        $speed = 300;
    }
    // 必须用 /\s/ 删除空白符（/s/ 只会删字母 s）
    $text      = html_entity_decode(strip_tags($post->post_content), ENT_QUOTES, 'UTF-8');
    $text_num  = mb_strlen(preg_replace('/\s/', '', $text), 'UTF-8');
    $read_time = max(1, ceil($text_num / $speed));
    return '共计' . $text_num . '字，阅读大约' . $read_time . '分钟。';
}

/**
 * 输出信息行 HTML
 *
 * @return string
 */
function zhiji_wcr_output()
{
    $info = zhiji_count_words_read_time();
    if ('' === $info) {
        return '';
    }
    return '<div class="zhiji-wcr muted-color" style="font-size:13px;margin:10px 0">' . esc_html($info) . '</div>';
}

/* ============================================================
 * 正文注入（钩子常注册，回调内判开关）
 * ============================================================ */
add_filter('the_content', function ($content) {
    if (!zhiji_is_enabled('transplant_enabled') || !zhiji_is_enabled('transplant_wcr', true)) {
        return $content;
    }
    // 仅文章正文（不判断 in_the_loop：zibll 模板渲染上下文中该值为 false）
    if (!is_singular('post') || is_feed() || is_preview()) {
        return $content;
    }
    $info = zhiji_wcr_output();
    if ('' === $info) {
        return $content;
    }
    return ('before' === zhiji_get_option('transplant_wcr_position', 'after'))
        ? $info . $content
        : $content . $info;
}, 20);

/* ============================================================
 * 后台字段
 * ============================================================ */
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('transplant_beautify', array(
        array(
            'id'      => 'transplant_enabled',
            'type'    => 'switcher',
            'title'   => '启用文章信息增强',
            'default' => false,
        ),
        array(
            'id'         => 'transplant_wcr',
            'type'       => 'switcher',
            'title'      => '文章字数和阅读时间',
            'default'    => true,
            'desc'       => '在文章内容末尾显示「共计X字，阅读大约X分钟」。',
            'dependency' => array('transplant_enabled', '==', '1'),
        ),
        array(
            'id'         => 'transplant_wcr_speed',
            'type'       => 'number',
            'title'      => '阅读速度（字/分钟）',
            'default'    => 300,
            'desc'       => '每分钟阅读的字数，用于计算阅读时间（建议 200-500）。',
            'dependency' => array('transplant_enabled', '==', '1', 'transplant_wcr', '==', '1'),
        ),
        array(
            'id'         => 'transplant_wcr_position',
            'type'       => 'select',
            'title'      => '显示位置',
            'options'    => array('before' => '文章内容前', 'after' => '文章内容后'),
            'default'    => 'after',
            'desc'       => '字数和阅读时间显示在文章内容的前面还是后面。',
            'dependency' => array('transplant_enabled', '==', '1', 'transplant_wcr', '==', '1'),
        ),
    ), 20);
