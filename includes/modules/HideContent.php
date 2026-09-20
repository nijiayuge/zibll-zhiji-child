<?php
/**
 * @module  HideContent
 * @desc    隐藏内容标记：{hide} 评论后可见、{hide=login} 登录后可见
 * @option  hide_content_enabled      总开关
 *          hide_content_reply_text   评论可见提示
 *          hide_content_login_text   登录可见提示
 * @hook    the_content(6) / wp_footer(98)
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/HideContent.php`
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('hide_content', array(
    'title'    => '隐藏内容短代码',
    'parent'   => 'zhiji_cap',
    'priority' => 10,
    'option'   => 'hide_content_enabled',
));

/* ============================================================
 * 权限判断
 * ============================================================ */

/**
 * 是否可见。
 *
 * @param string $type    reply|login
 * @param int    $post_id 文章 ID
 * @return bool
 */
function zhiji_hide_can_view($type, $post_id = 0)
{
    if ('login' === $type) {
        return is_user_logged_in();
    }
    // reply：登录用户直接放行
    if (is_user_logged_in()) {
        return true;
    }
    // 游客：按评论者邮箱判断本站是否已有已审核评论
    $commenter = wp_get_current_commenter();
    $email     = isset($commenter['comment_author_email']) ? $commenter['comment_author_email'] : '';
    if (!is_email($email)) {
        return false;
    }
    return (bool) get_comments(array(
        'author_email' => $email,
        'status'       => 'approve',
        'count'        => true,
        'number'       => 1,
    ));
}

/**
 * 生成提示块。
 *
 * @param string $text 提示文字
 * @param string $type reply|login
 * @return string
 */
function zhiji_hide_tip($text, $type)
{
    return '<div class="zhiji-hide-tip" data-type="' . esc_attr($type) . '">'
        . '<span class="zhiji-hide-icon">&#128274;</span>'
        . '<span class="zhiji-hide-text">' . esc_html($text) . '</span>'
        . '</div>';
}

/**
 * 解析 {hide} / {hide=login} 标记
 *
 * @param string $content
 * @return string
 */
function zhiji_hide_parse($content)
{
    if (!zhiji_is_enabled('hide_content_enabled')) {
        return $content;
    }
    if (!is_singular()) {
        return $content;
    }
    if (false === strpos($content, '{hide')) {
        return $content;
    }

    $post_id    = get_the_ID();
    $reply_text = (string) zhiji_get_option('hide_content_reply_text', '此处内容需要评论后可见');
    $login_text = (string) zhiji_get_option('hide_content_login_text', '此处内容需要登录后可见');

    // 先处理更具体的 {hide=login}
    $content = preg_replace_callback('/\{hide=login\}(.*?)\{\/hide\}/s', function ($m) use ($login_text, $post_id) {
        return zhiji_hide_can_view('login', $post_id) ? $m[1] : zhiji_hide_tip($login_text, 'login');
    }, $content);

    // 再处理 {hide}（评论可见）
    $content = preg_replace_callback('/\{hide\}(.*?)\{\/hide\}/s', function ($m) use ($reply_text, $post_id) {
        return zhiji_hide_can_view('reply', $post_id) ? $m[1] : zhiji_hide_tip($reply_text, 'reply');
    }, $content);

    return $content;
}
add_filter('the_content', 'zhiji_hide_parse', 6);

/* ============================================================
 * 提示块样式（wp_footer 直接输出；量小仅一段）
 * ============================================================ */
add_action('wp_footer', function () {
    if (!zhiji_is_enabled('hide_content_enabled')) {
        return;
    }
    echo '<style id="zhiji-hide-css">'
        . '.zhiji-hide-tip{display:flex;align-items:center;gap:8px;padding:12px 16px;margin:14px 0;'
        . 'border:1px dashed var(--main-border-color,#d1d5db);border-radius:10px;'
        . 'background:var(--main-bg-color,#f7f8fa);color:var(--muted-color,#8a919f);font-size:14px}'
        . '.zhiji-hide-icon{font-size:15px}'
        . '</style>' . "\n";
}, 98);

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('hide_content', array(
        array(
            'id'      => 'hide_content_enabled',
            'type'    => 'switcher',
            'title'   => '启用隐藏内容',
            'desc'    => '支持 {hide} 评论可见、{hide=login} 登录可见两种标记。',
            'default' => false,
        ),
        array(
            'id'         => 'hide_content_reply_text',
            'type'       => 'text',
            'title'      => '评论可见提示文字',
            'default'    => '此处内容需要评论后可见',
            'dependency' => array('hide_content_enabled', '==', 'true'),
        ),
        array(
            'id'         => 'hide_content_login_text',
            'type'       => 'text',
            'title'      => '登录可见提示文字',
            'default'    => '此处内容需要登录后可见',
            'dependency' => array('hide_content_enabled', '==', 'true'),
        ),
    ));
}, 20);
