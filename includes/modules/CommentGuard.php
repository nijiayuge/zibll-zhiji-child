<?php
/**
 * @module  CommentGuard
 * @desc    评论反垃圾：敏感词 / 必须含中文 / 最大字数三条规则，命中自动转待审核
 * @option  comment_guard_enabled    总开关
 *          comment_guard_words      敏感词（|| 分隔）
 *          comment_guard_require_cn 必须包含中文
 *          comment_guard_max_len    最大字数（0 不限）
 *          comment_guard_hint       前台提示文字
 * @hook    pre_comment_approved · WP 核心过滤器，命中规则返回待审核
 *          wp_footer · 评论框下方输出规则提示
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/CommentGuard.php`（钩子为 WP 核心，真实可用）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('comment_guard', array(
    'title'    => '评论反垃圾',
    'parent'   => 'zhiji_comment',
    'priority' => 130,
    'option'   => 'comment_guard_enabled',
));

/* ============================================================
 * 规则判定
 * ============================================================ */

/**
 * 逐条规则判定
 *
 * @param array $commentdata 评论数据
 * @return string 命中返回规则名，未命中返回空串
 */
function zhiji_comment_guard_hit($commentdata)
{
    if (!zhiji_is_enabled('comment_guard_enabled')) {
        return '';
    }
    $content = isset($commentdata['comment_content']) ? (string) $commentdata['comment_content'] : '';

    // 1. 敏感词（|| 分隔）
    $words = trim((string) zhiji_get_option('comment_guard_words', ''));
    if ('' !== $words) {
        $list = array_filter(array_map('trim', explode('||', $words)));
        foreach ($list as $w) {
            if ('' !== $w && false !== mb_strpos($content, $w, 0, 'UTF-8')) {
                return 'sensitive';
            }
        }
    }

    // 2. 必须包含至少一个中文
    if (zhiji_get_option('comment_guard_require_cn', false)) {
        if (!preg_match('/[\x{4e00}-\x{9fff}]/u', $content)) {
            return 'no_cn';
        }
    }

    // 3. 最大字符数（0 不限）
    $max = (int) zhiji_get_option('comment_guard_max_len', 0);
    if ($max > 0 && mb_strlen($content, 'UTF-8') > $max) {
        return 'too_long';
    }

    return '';
}

add_filter('pre_comment_approved', function ($approved, $commentdata) {
    if (zhiji_comment_guard_hit($commentdata)) {
        return '0'; // 待审核（不直接拒绝）
    }
    return $approved;
}, 98, 2);

/* ============================================================
 * 前台提示（评论框下方）
 * ============================================================ */
add_action('wp_footer', function () {
    if (!zhiji_is_enabled('comment_guard_enabled')) {
        return;
    }
    if (!is_singular() || !comments_open()) {
        return;
    }
    $hint = trim((string) zhiji_get_option('comment_guard_hint', ''));
    if ('' === $hint) {
        return;
    }
    echo '<script id="zhiji-guard-hint">'
        . '(function(){var t=document.querySelector("#comment");if(!t)return;var p=document.createElement("div");'
        . 'p.className="zhiji-guard-hint";p.style.cssText="margin:6px 0;font-size:12px;color:var(--muted-color,#8a919f);";'
        . 'p.textContent=' . wp_json_encode($hint) . ';t.parentNode.insertBefore(p,t);})();'
        . '</script>';
}, 96);

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('comment_guard', array(
        array(
            'id'      => 'comment_guard_enabled',
            'type'    => 'switcher',
            'title'   => '启用评论反垃圾',
            'desc'    => '命中规则的评论自动置为待审核，不直接拒绝。',
            'default' => false,
        ),
        array(
            'id'         => 'comment_guard_words',
            'type'       => 'textarea',
            'title'      => '敏感词列表',
            'desc'       => '使用 || 分隔，如：广告||加微信||代刷',
            'dependency' => array('comment_guard_enabled', '==', '1'),
        ),
        array(
            'id'         => 'comment_guard_require_cn',
            'type'       => 'switcher',
            'title'      => '评论必须包含中文',
            'default'    => false,
            'desc'       => '评论内容不含任何中文字符时置为待审核',
            'dependency' => array('comment_guard_enabled', '==', '1'),
        ),
        array(
            'id'         => 'comment_guard_max_len',
            'type'       => 'number',
            'title'      => '评论最大字数',
            'default'    => 0,
            'desc'       => '超过该字数的评论置为待审核，0 表示不限制',
            'dependency' => array('comment_guard_enabled', '==', '1'),
        ),
        array(
            'id'         => 'comment_guard_hint',
            'type'       => 'text',
            'title'      => '前台提示文字',
            'default'    => '',
            'desc'       => '评论框下方显示规则提示，留空不显示',
            'dependency' => array('comment_guard_enabled', '==', '1'),
        ),
    ));
}, 20);
