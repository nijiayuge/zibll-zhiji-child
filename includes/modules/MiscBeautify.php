<?php
/**
 * @module  MiscBeautify
 * @desc    杂项：手机端访问限制 / 区块 hover 跳动动画 / 长期未登录用户自动清理
 * @option  misc_mobile_only      仅手机端访问
 *          misc_jump_selectors   跳动动画选择器（每行一个）
 *          misc_auto_clean_users 自动清理未登录用户
 *          misc_clean_days       未登录天数阈值
 * @hook    template_redirect · 手机端限制提示页
 *          wp_head · 跳动动画样式
 *          zhiji_auto_clean_users_event · 每日清理 cron（开关关闭时清除调度）
 * @event   通知事件 misc_user_cleaned · 清理前的邮件提醒（原 v1 直调 wp_mail + 父主题
 *          filter 的写法改为统一通知）
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/MiscBeautify.php`
 *          ⚠️ 改造：v1 自动清理直接 wp_delete_user 删除用户（不可逆、仅邮件提醒）。
 *          v2 保留行为但通知改走 zhiji_notify（邮件渠道），事件注册于本模块。
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('misc_beautify', array(
    'title'    => '杂项美化',
    'parent'   => 'zhiji_beautify',
    'priority' => 50,
    'option'   => 'misc_mobile_only',
));

/* ============================================================
 * 通知事件注册
 * ============================================================ */
add_action('init', function () {
    zhiji_notify_register_event('misc_user_cleaned', array(
        'label'    => '账号自动清理提醒',
        'title'    => '重要通知：您的账户已被自动注销',
        'channels' => array('mail'),
        'mail'     => 'plain',
        'throttle' => array(0, HOUR_IN_SECONDS), // 清理场景不限条数
    ));
}, 15);

/* ============================================================
 * 手机端访问限制
 * ============================================================ */
add_action('template_redirect', function () {
    if (!zhiji_is_enabled('misc_mobile_only')) {
        return;
    }
    if (wp_is_mobile()) {
        return;
    }
    // 管理员豁免（便于后台排查）
    if (is_user_logged_in() && current_user_can('manage_options')) {
        return;
    }
    ?>
    <html>
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0,user-scalable=no">
    <title>请使用手机访问本站</title>
    <style>
    html,body{background:#28254C;}
    *{box-sizing:border-box;}
    .box{width:350px;height:100%;max-height:600px;min-height:450px;background:#16273f;border-radius:20px;position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);padding:30px 50px;}
    .box__ghost{padding:15px 25px 25px;position:absolute;left:50%;top:30%;transform:translate(-50%,-30%);}
    .box__ghost-container{background:#fff;width:100px;height:100px;border-radius:100px 100px 0 0;position:relative;margin:0 auto;animation:upndown 3s ease-in-out infinite;}
    .box__ghost-eyes{position:absolute;left:50%;top:45%;height:12px;width:70px;}
    .box__eye-left{width:12px;height:12px;background:#16273f;border-radius:50%;margin:0 10px;position:absolute;left:0;}
    .box__eye-right{width:12px;height:12px;background:#16273f;border-radius:50%;margin:0 10px;position:absolute;right:0;}
    .box__ghost-bottom{display:flex;position:absolute;top:100%;left:0;right:0;}
    .box__ghost-bottom div{flex-grow:1;position:relative;top:-10px;height:20px;border-radius:100%;background-color:#fff;}
    .box__ghost-bottom div:nth-child(2n){top:-12px;border-top:15px solid #332F63;background:transparent;}
    .box__ghost-shadow{height:20px;box-shadow:0 50px 15px 5px #3B3769;border-radius:50%;margin:0 auto;animation:smallnbig 3s ease-in-out infinite;}
    .box__description{position:absolute;bottom:30px;left:50%;transform:translateX(-50%);}
    .box__description-container{color:#fff;text-align:center;width:200px;font-size:16px;margin:0 auto;}
    .box__description-title{font-size:24px;letter-spacing:.5px;}
    .box__description-text{color:#8C8AA7;line-height:20px;margin-top:20px;}
    .box__button{display:block;position:relative;background:#FF5E65;border:1px solid transparent;border-radius:50px;height:50px;text-align:center;text-decoration:none;color:#fff;line-height:50px;font-size:18px;padding:0 70px;white-space:nowrap;margin-top:25px;transition:background .5s ease;}
    .box__button:hover{background:transparent;border-color:#fff;color:#fff;}
    @keyframes upndown{0%{transform:translateY(5px);}50%{transform:translateY(15px);}100%{transform:translateY(5px);}}
    @keyframes smallnbig{0%{width:90px;}50%{width:100px;}100%{width:90px;}}
    </style>
    </head>
    <body>
    <div class="box">
        <div class="box__ghost">
            <div class="box__ghost-container">
                <div class="box__ghost-eyes">
                    <div class="box__eye-left"></div>
                    <div class="box__eye-right"></div>
                </div>
                <div class="box__ghost-bottom">
                    <div></div><div></div><div></div><div></div><div></div>
                </div>
                <div class="box__ghost-shadow"></div>
            </div>
            <div class="box__description">
                <div class="box__description-container">
                    <div class="box__description-title">抱歉本站仅支持手机用户访问</div>
                    <div class="box__description-text">请使用移动设备访问本站</div>
                </div>
                <a href="/" class="box__button">返回主页</a>
            </div>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}, 1);

/* ============================================================
 * 区块 hover 跳动动画
 * ============================================================ */
add_action('wp_head', function () {
    $selectors = array_filter(array_map('trim', explode("\n", (string) zhiji_get_option('misc_jump_selectors', ''))));
    if (empty($selectors)) {
        return;
    }
    $selector_str = implode(', ', $selectors);
    ?>
    <style id="zhiji-misc-jump-style">
    <?php echo esc_html($selector_str); ?>:hover{
        -webkit-animation:zhiji-jump 1.2s ease 1;
        animation:zhiji-jump 1.2s ease 1;
    }
    @-webkit-keyframes zhiji-jump{
        0%{transform:translate(0)}
        10%{transform:translateY(5px) scaleX(1.2) scaleY(.8)}
        30%{transform:translateY(-13px) scaleX(1) scaleY(1) rotate(5deg)}
        50%{transform:translateY(0) scale(1) rotate(0)}
        55%{transform:translateY(0) scaleX(1.1) scaleY(.9) rotate(0)}
        70%{transform:translateY(-4px) scaleX(1) scaleY(1) rotate(-2deg)}
        80%{transform:translateY(0) scaleX(1) scaleY(1) rotate(0)}
        85%{transform:translateY(0) scaleX(1.05) scaleY(.95) rotate(0)}
        to{transform:translateY(0) scaleX(1) scaleY(1)}
    }
    @keyframes zhiji-jump{
        0%{transform:translate(0)}
        10%{transform:translateY(5px) scaleX(1.2) scaleY(.8)}
        30%{transform:translateY(-13px) scaleX(1) scaleY(1) rotate(5deg)}
        50%{transform:translateY(0) scale(1) rotate(0)}
        55%{transform:translateY(0) scaleX(1.1) scaleY(.9) rotate(0)}
        70%{transform:translateY(-4px) scaleX(1) scaleY(1) rotate(-2deg)}
        80%{transform:translateY(0) scaleX(1) scaleY(1) rotate(0)}
        85%{transform:translateY(0) scaleX(1.05) scaleY(.95) rotate(0)}
        to{transform:translateY(0) scaleX(1) scaleY(1)}
    }
    </style>
    <?php
}, 99);

/* ============================================================
 * 自动清理长期未登录用户（每日 cron）
 * ============================================================ */
add_action('init', function () {
    if (!zhiji_is_enabled('misc_auto_clean_users')) {
        $ts = wp_next_scheduled('zhiji_auto_clean_users_event');
        if ($ts) {
            wp_unschedule_event($ts, 'zhiji_auto_clean_users_event');
        }
        return;
    }
    if (!wp_next_scheduled('zhiji_auto_clean_users_event')) {
        wp_schedule_event(time(), 'daily', 'zhiji_auto_clean_users_event');
    }
}, 20);

add_action('zhiji_auto_clean_users_event', function () {
    if (!zhiji_is_enabled('misc_auto_clean_users')) {
        return;
    }
    $clean_days = max(1, (int) zhiji_get_option('misc_clean_days', 90));
    $threshold  = strtotime('-' . $clean_days . ' days');
    $users      = get_users(array('number' => -1));

    foreach ($users as $user) {
        // 管理员不清理
        if (user_can($user->ID, 'manage_options')) {
            continue;
        }
        $last_login = get_user_meta($user->ID, 'last_login', true);
        if (empty($last_login)) {
            $last_login = $user->user_registered; // 无登录记录用注册时间
        }
        if (is_array($last_login)) {
            $last_login = $last_login[0];
        }
        if (strtotime((string) $last_login) >= $threshold) {
            continue;
        }

        // 先发提醒（统一通知邮件渠道），再删除（文章移至管理员）
        zhiji_notify('misc_user_cleaned', array(
            'user_id' => $user->ID,
            'content' => '尊敬的' . $user->display_name . "，\n\n"
                . '由于您已经超过' . $clean_days . '天未登录，您的' . get_bloginfo('name') . "账户已被自动注销。\n\n"
                . "如果您希望重新启用您的账户，请联系管理员。\n\n感谢您的理解和配合。",
        ));
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user->ID, 1);
    }
});

/* ============================================================
 * 后台字段
 * ============================================================ */
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('misc_beautify', array(
        array(
            'id'      => 'misc_mobile_only',
            'type'    => 'switcher',
            'title'   => '网站只允许手机端访问',
            'default' => false,
            'desc'    => '启用后 PC 端访问将显示「请使用手机访问本站」提示页（管理员豁免）。',
        ),
        array(
            'id'      => 'misc_jump_selectors',
            'type'    => 'textarea',
            'title'   => '区块跳动 CSS 选择器',
            'default' => '',
            'desc'    => '输入 CSS 选择器（每行一个），hover 时触发跳动动画。例如：.widget, .card',
        ),
        array(
            'id'      => 'misc_auto_clean_users',
            'type'    => 'switcher',
            'title'   => '自动清理长期未登录用户',
            'default' => false,
            'desc'    => '⚠️ 高危：启用后超过阈值的未登录用户将被删除（文章移至管理员），删除前发提醒邮件。',
        ),
        array(
            'id'         => 'misc_clean_days',
            'type'       => 'number',
            'title'      => '未登录天数阈值',
            'default'    => 90,
            'desc'       => '超过多少天未登录的用户将被删除（默认 90 天）。',
            'dependency' => array('misc_auto_clean_users', '==', '1'),
        ),
    ), 20);
