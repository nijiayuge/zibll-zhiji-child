<?php
/**
 * @module  Maintenance
 * @desc    维护模式：前台访客看到 503 维护页，登录用户/白名单 IP 放行
 * @option  maintenance_enabled     总开关
 *          maintenance_title       维护页标题
 *          maintenance_desc        维护页描述
 *          maintenance_allow_login 登录用户可访问
 *          maintenance_whitelist   白名单 IP
 * @hook    template_redirect(1)
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/Maintenance.php`
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('maintenance', array(
    'title'    => '维护模式',
    'parent'   => 'zhiji_over',
    'priority' => 50,
    'option'   => 'maintenance_enabled',
));

/* ============================================================
 * 业务逻辑
 * ============================================================ */

/**
 * 取客户端 IP
 *
 * @return string
 */
function zhiji_maintenance_get_client_ip()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    if (empty($ip) && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])));
        $ip        = trim((string) reset($forwarded));
    }
    return $ip;
}

/**
 * 渲染 503 维护页并终止
 *
 * @return void
 */
function zhiji_maintenance_render_page()
{
    status_header(503);
    nocache_headers();
    $site_name = get_bloginfo('name');
    $title     = (string) zhiji_get_option('maintenance_title', '站点维护中');
    $desc      = (string) zhiji_get_option('maintenance_desc', '我们正在对站点进行升级维护，预计很快恢复。给您带来不便，敬请谅解！');
    $logo      = get_theme_mod('custom_logo') ? wp_get_attachment_image_url(get_theme_mod('custom_logo'), 'full') : '';

    echo '<!DOCTYPE html><html lang="' . esc_attr(get_locale()) . '"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . esc_html($title) . ' - ' . esc_html($site_name) . '</title>'
        . '<style>'
        . 'html,body{height:100%;margin:0;}'
        . 'body{display:flex;align-items:center;justify-content:center;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"PingFang SC","Microsoft YaHei",sans-serif;background:#f5f6fa;color:#333;}'
        . '@media (prefers-color-scheme:dark){body{background:#1a1b1e;color:#e8e8ea;}}'
        . '.card{max-width:480px;margin:24px;padding:48px 40px;text-align:center;background:#fff;border-radius:16px;box-shadow:0 8px 30px rgba(0,0,0,.08);}'
        . '@media (prefers-color-scheme:dark){.card{background:#26272b;box-shadow:0 8px 30px rgba(0,0,0,.4);}}'
        . '.logo{max-height:56px;margin-bottom:20px;}'
        . '.icon{width:64px;height:64px;margin:0 auto 20px;border-radius:50%;display:flex;align-items:center;justify-content:center;'
        . 'background:linear-gradient(135deg,#6e7bf7,#4d5ce8);font-size:28px;color:#fff;}'
        . 'h1{font-size:22px;margin:0 0 12px;font-weight:600;}'
        . 'p{font-size:14px;line-height:1.8;margin:0 0 24px;opacity:.75;}'
        . '.btn{display:inline-block;padding:10px 28px;border-radius:24px;background:linear-gradient(135deg,#6e7bf7,#4d5ce8);color:#fff;text-decoration:none;font-size:14px;cursor:pointer;border:none;}'
        . '.site{margin-top:24px;font-size:12px;opacity:.45;}'
        . '</style></head><body><div class="card">';
    if ($logo) {
        echo '<img class="logo" src="' . esc_url($logo) . '" alt="' . esc_attr($site_name) . '">';
    } else {
        echo '<div class="icon">&#128295;</div>';
    }
    echo '<h1>' . esc_html($title) . '</h1>'
        . '<p>' . esc_html($desc) . '</p>'
        . '<button class="btn" onclick="location.reload()">刷新重试</button>'
        . '<div class="site">' . esc_html($site_name) . ' &middot; ' . esc_html(gmdate('Y')) . '</div>'
        . '</div></body></html>';
    exit;
}

/**
 * 前台拦截
 */
add_action('template_redirect', function () {
    if (!zhiji_is_enabled('maintenance_enabled')) {
        return;
    }
    // 后台 / AJAX / REST / CLI / Cron 一律放行
    if (is_admin() || wp_doing_ajax() || wp_is_json_request()
        || (defined('WP_CLI') && WP_CLI)
        || (defined('DOING_CRON') && DOING_CRON)) {
        return;
    }
    // 登录用户放行（默认开启）
    if (zhiji_is_enabled('maintenance_allow_login', true) && is_user_logged_in()) {
        return;
    }
    // 白名单 IP 放行
    $whitelist = array_filter(array_map('trim', explode(',', (string) zhiji_get_option('maintenance_whitelist', ''))));
    if ($whitelist && in_array(zhiji_maintenance_get_client_ip(), $whitelist, true)) {
        return;
    }
    zhiji_maintenance_render_page();
}, 1);

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('maintenance', array(
        array(
            'id'      => 'maintenance_enabled',
            'type'    => 'switcher',
            'title'   => '启用维护模式',
            'default' => false,
            'desc'    => '开启后，前台访客将看到维护页（HTTP 503），登录用户和白名单IP不受影响。',
        ),
        array(
            'id'         => 'maintenance_title',
            'type'       => 'text',
            'title'      => '维护页标题',
            'desc' => __( '维护模式下展示的标题文字。', 'zhiji' ),
            'default'    => '站点维护中',
            'dependency' => array('maintenance_enabled', '==', '1'),
        ),
        array(
            'id'         => 'maintenance_desc',
            'type'       => 'textarea',
            'title'      => '维护页描述',
            'desc' => __( '维护模式下展示的说明文字（可写维护原因与恢复时间）。', 'zhiji' ),
            'default'    => '我们正在对站点进行升级维护，预计很快恢复。给您带来不便，敬请谅解！',
            'dependency' => array('maintenance_enabled', '==', '1'),
        ),
        array(
            'id'         => 'maintenance_allow_login',
            'type'       => 'switcher',
            'title'      => '登录用户可访问',
            'default'    => true,
            'desc'       => '开启后，已登录用户不受维护模式影响。',
            'dependency' => array('maintenance_enabled', '==', '1'),
        ),
        array(
            'id'         => 'maintenance_whitelist',
            'type'       => 'text',
            'title'      => '白名单 IP',
            'default'    => '',
            'desc'       => '多个IP用逗号分隔，白名单内的IP不受维护模式影响。',
            'dependency' => array('maintenance_enabled', '==', '1'),
        ),
    ));
}, 20);
