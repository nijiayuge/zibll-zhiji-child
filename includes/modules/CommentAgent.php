<?php
/**
 * @module  CommentAgent
 * @desc    评论设备显示：新评论记录操作系统与浏览器，评论底部展示
 * @option  comment_agent_enabled  总开关
 * @hook    wp_insert_comment(10,2) / comment_footer_info(20,3)（父主题钩子）/ wp_footer(98)
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/CommentAgent.php`
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('comment_agent', array(
    'title'    => '评论设备显示',
    'parent'   => 'zhiji_user',
    'priority' => 140,
    'option'   => 'comment_agent_enabled',
));

/* ============================================================
 * UA 解析
 * ============================================================ */

/**
 * 解析 User-Agent 为 设备 + 浏览器
 *
 * @param string $ua
 * @return array{os:string,browser:string}
 */
function zhiji_agent_parse($ua)
{
    $ua      = (string) $ua;
    $os      = '未知设备';
    $browser = '未知浏览器';

    if (preg_match('/Windows NT ([\d.]+)/', $ua, $m)) {
        $ver = array(
            '10.0' => 'Windows 10/11',
            '6.3'  => 'Windows 8.1',
            '6.2'  => 'Windows 8',
            '6.1'  => 'Windows 7',
        );
        $os = isset($ver[$m[1]]) ? $ver[$m[1]] : 'Windows';
    } elseif (strpos($ua, 'iPhone') !== false) {
        $os = 'iPhone';
    } elseif (strpos($ua, 'iPad') !== false) {
        $os = 'iPad';
    } elseif (strpos($ua, 'Android') !== false) {
        $os = 'Android';
    } elseif (strpos($ua, 'Mac OS X') !== false || strpos($ua, 'Macintosh') !== false) {
        $os = 'macOS';
    } elseif (strpos($ua, 'Linux') !== false) {
        $os = 'Linux';
    }

    // 浏览器按特征优先级：Edge → Opera → Firefox → Chrome → Samsung → Safari → 微信
    if (strpos($ua, 'Edg/') !== false) {
        $browser = 'Edge';
    } elseif (strpos($ua, 'OPR/') !== false || strpos($ua, 'Opera') !== false) {
        $browser = 'Opera';
    } elseif (strpos($ua, 'Firefox/') !== false) {
        $browser = 'Firefox';
    } elseif (strpos($ua, 'MicroMessenger') !== false) {
        $browser = '微信内置浏览器';
    } elseif (strpos($ua, 'Chrome/') !== false && strpos($ua, 'SamsungBrowser') === false) {
        $browser = 'Chrome';
    } elseif (strpos($ua, 'SamsungBrowser') !== false) {
        $browser = 'Samsung Internet';
    } elseif (strpos($ua, 'Safari/') !== false) {
        $browser = 'Safari';
    }

    return array('os' => $os, 'browser' => $browser);
}

/* ============================================================
 * 保存与展示
 * ============================================================ */

/**
 * 新评论时记录 UA（仅对新评论生效）
 */
add_action('wp_insert_comment', function ($comment_id, $comment) {
    if (!zhiji_is_enabled('comment_agent_enabled')) {
        return;
    }
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
    if ('' === $ua) {
        return;
    }
    $parsed = zhiji_agent_parse($ua);
    if ('未知设备' === $parsed['os'] && '未知浏览器' === $parsed['browser']) {
        return;
    }
    update_comment_meta($comment_id, '_zhiji_agent', $parsed['os'] . ' · ' . $parsed['browser']);
}, 10, 2);

/**
 * 评论底部追加设备信息（父主题 comment_footer_info 钩子）
 */
add_filter('comment_footer_info', function ($info, $comment, $depth) {
    if (!zhiji_is_enabled('comment_agent_enabled')) {
        return $info;
    }
    $agent = get_comment_meta($comment->comment_ID, '_zhiji_agent', true);
    if (!$agent) {
        return $info;
    }
    return $info . '<span class="zhiji-comment-agent">' . esc_html($agent) . '</span>';
}, 20, 3);

/**
 * 徽标样式
 */
add_action('wp_footer', function () {
    if (!zhiji_is_enabled('comment_agent_enabled')) {
        return;
    }
    echo '<style id="zhiji-agent-css">'
        . '.zhiji-comment-agent{display:inline-block;margin-left:8px;padding:0 8px;font-size:11px;line-height:18px;'
        . 'color:var(--muted-color,#8a919f);background:rgba(127,127,127,.1);border-radius:9px;vertical-align:1px}'
        . '</style>' . "\n";
}, 98);

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('comment_agent', array(
        array(
            'id'      => 'comment_agent_enabled',
            'type'    => 'switcher',
            'title'   => '启用评论设备显示',
            'desc'    => '评论底部显示评论者的操作系统与浏览器（仅对新评论生效）。',
            'default' => false,
        ),
    ));
}, 20);
