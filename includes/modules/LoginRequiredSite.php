<?php
/**
 * @module  LoginRequiredSite
 * @desc    登录才能查看网站：未登录访客跳转到登录页（支持排除首页/指定页面/白名单 IP）
 * @option  login_required_enabled          总开关
 *          login_required_exclude_home     排除首页
 *          login_required_exclude_pages    排除页面 ID（逗号分隔）
 *          login_required_whitelist_ips    白名单 IP（每行一个）
 * @hook    template_redirect(1)
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/LoginRequiredSite.php`
 *          （修复：v1 调用了不存在的 is_login()，开关一开即 fatal；
 *            v2 改用 $pagenow 判断 wp-login.php / wp-register.php）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('login_required_site', array(
    'title'    => '登录才能查看网站',
    'parent'   => 'zhiji_cap',
    'priority' => 20,
    'option'   => 'login_required_enabled',
));

/* ============================================================
 * 前台拦截
 * ============================================================ */

/**
 * 未登录访客跳转登录页
 *
 * @return void
 */
function zhiji_login_required_check()
{
    if (!zhiji_is_enabled('login_required_enabled')) {
        return;
    }
    if (is_user_logged_in() || is_admin()) {
        return;
    }
    // WordPress 无 is_login() 函数（v1 在此处会 fatal），用 $pagenow 判断登录/注册页
    global $pagenow;
    if (in_array($pagenow, array('wp-login.php', 'wp-register.php'), true)) {
        return;
    }
    // REST / cron / AJAX 请求不拦截（否则后台与站点功能会被误伤）
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }
    if (defined('DOING_CRON') && DOING_CRON) {
        return;
    }
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }

    // 白名单 IP（每行一个）
    $ips = (string) zhiji_get_option('login_required_whitelist_ips', '');
    if ($ips !== '') {
        $current = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        foreach (preg_split('/\r\n|\r|\n/', $ips) as $ip) {
            $ip = trim($ip);
            if ($ip !== '' && $ip === $current) {
                return;
            }
        }
    }

    // 排除首页
    if (zhiji_is_enabled('login_required_exclude_home') && is_front_page()) {
        return;
    }

    // 排除指定页面（逗号分隔的页面 ID）
    $pages = (string) zhiji_get_option('login_required_exclude_pages', '');
    if ($pages !== '') {
        $ids = array_filter(array_map('intval', explode(',', $pages)));
        if ($ids && is_page($ids)) {
            return;
        }
    }

    wp_safe_redirect(wp_login_url(get_permalink()));
    exit;
}
add_action('template_redirect', 'zhiji_login_required_check', 1);

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('login_required_site', array(
        array(
            'id'      => 'login_required_enabled',
            'type'    => 'switcher',
            'title'   => '启用登录限制',
            'desc'    => '未登录用户访问网站时跳转到登录页',
            'default' => false,
        ),
        array(
            'id'         => 'login_required_exclude_home',
            'type'       => 'switcher',
            'title'      => '排除首页',
            'default'    => false,
            'dependency' => array('login_required_enabled', '==', 'true'),
        ),
        array(
            'id'         => 'login_required_exclude_pages',
            'type'       => 'text',
            'title'      => '排除页面ID',
            'desc'       => '多个页面ID用逗号分隔',
            'default'    => '',
            'dependency' => array('login_required_enabled', '==', 'true'),
        ),
        array(
            'id'         => 'login_required_whitelist_ips',
            'type'       => 'textarea',
            'title'      => '白名单IP',
            'desc'       => '每行一个IP，这些IP访问不受限制',
            'default'    => '',
            'dependency' => array('login_required_enabled', '==', 'true'),
        ),
    ));
}, 20);
