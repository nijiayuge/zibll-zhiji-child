<?php
/**
 * @module  VipAutoAuth
 * @desc    开通会员自动认证：购买会员后自动调整用户组并加认证徽章；会员到期（每日巡检）自动取消
 * @option  vip_auto_auth_enabled  总开关
 *          vip_auto_auth_role     认证用户组
 *          vip_auto_auth_name     认证名称
 * @hook    payment_order_success（父主题真实钩子，zibpay/class/order-class.php，传订单对象）
 *          user_name_badge（父主题用户名徽章 filter）
 *          zhiji_daily_vip_check（每日 cron，自调度）
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/VipAutoAuth.php`
 *          ⚠️ 2026-09-20 联调修正：v1 挂的 zibpay_pay_success / zibpay_user_vip_expired /
 *          zib_get_user_name 三个钩子在父主题中**均不存在**（从未生效过）。
 *          真实挂点：payment_order_success（订单对象）、user_name_badge（filter）、
 *          到期取消改为自调度每日 cron 扫描 vip_exp_date。
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('vip_auto_auth', array(
    'title'    => '开通会员自动认证',
    'parent'   => 'zhiji_user',
    'priority' => 170,
    'option'   => 'vip_auto_auth_enabled',
));

/** 允许认证的角色白名单（防止配置被改成 administrator 等高危角色） */
function zhiji_vip_auth_allowed_roles()
{
    return array('contributor', 'author', 'editor');
}

/* ============================================================
 * 通知事件注册（票据邮件模板；必须在业务调用前登记）
 * ============================================================ */
add_action('init', function () {
    zhiji_notify_register_event('vip_auto_auth_granted', array(
        'label'    => '会员认证开通',
        'title'    => '恭喜，你的会员认证已开通',
        'channels' => array('msg', 'mail'),
        'toast'    => 'success',
        'mail'     => 'ticket',
    ));
    zhiji_notify_register_event('vip_auto_auth_expired', array(
        'label'    => '会员认证到期',
        'title'    => '会员认证已到期取消',
        'channels' => array('msg', 'mail'),
        'toast'    => 'info',
        'mail'     => 'ticket',
    ));
}, 15);

/**
 * 支付方式的可读名称
 *
 * @param string $pay_type 支付类型标识
 * @return string
 */
function zhiji_vip_auth_pay_type_name($pay_type)
{
    $map = array(
        'wxpay'  => '微信支付',
        'alipay' => '支付宝',
        'qqpay'  => 'QQ钱包',
        'balance' => '余额支付',
        'points' => '积分兑换',
        'card'   => '卡密兑换',
    );
    return isset($map[$pay_type]) ? $map[$pay_type] : (string) $pay_type;
}

/* ============================================================
 * 支付成功 → 自动认证
 * ============================================================ */

/**
 * 购买会员（order_type=4）后自动认证
 *
 * @param object $order 订单对象（zibpay_order 表行）
 * @return void
 */
function zhiji_vip_auto_auth_after_pay($order)
{
    if (!zhiji_is_enabled('vip_auto_auth_enabled')) {
        return;
    }
    if (!is_object($order)) {
        return;
    }
    $user_id = isset($order->user_id) ? (int) $order->user_id : 0;
    if (!$user_id) {
        return;
    }
    // 4 = 购买会员（父主题 order_type 为 varchar，宽松比较）
    if (!isset($order->order_type) || 4 != $order->order_type) {
        return;
    }

    $role = (string) zhiji_get_option('vip_auto_auth_role', 'contributor');
    if (!in_array($role, zhiji_vip_auth_allowed_roles(), true)) {
        $role = 'contributor';
    }
    $name = (string) zhiji_get_option('vip_auto_auth_name', 'VIP认证用户');

    $user = new WP_User($user_id);
    if (!$user->exists()) {
        return;
    }
    $user->set_role($role);
    update_user_meta($user_id, 'zhiji_vip_auth', 1);
    update_user_meta($user_id, 'zhiji_vip_auth_name', $name);
    update_user_meta($user_id, 'zhiji_vip_auth_time', current_time('mysql'));

    // 票据数据：金额取实付，无实付回落订单价（均为 double(10,2)，可能为 null）
    $price = isset($order->pay_price) && null !== $order->pay_price && '' !== $order->pay_price
        ? (float) $order->pay_price
        : (isset($order->order_price) ? (float) $order->order_price : 0.0);
    zhiji_notify('vip_auto_auth_granted', array(
        'user_id' => $user_id,
        'title'   => '恭喜，你的会员认证已开通',
        'content' => sprintf('你购买的会员已支付成功，系统已自动为你开通「%s」认证身份。', $name),
        'data'    => array(
            '订单号'   => isset($order->order_num) ? (string) $order->order_num : '',
            '支付金额' => '¥' . number_format($price, 2),
            '支付方式' => zhiji_vip_auth_pay_type_name(isset($order->pay_type) ? (string) $order->pay_type : ''),
            '认证身份' => $name,
        ),
        'dedupe_key' => 'vip_auth_' . $user_id . '_' . (isset($order->order_num) ? $order->order_num : ''),
    ));
}
add_action('payment_order_success', 'zhiji_vip_auto_auth_after_pay', 20);

/* ============================================================
 * 徽章（父主题 user_name_badge filter）
 * ============================================================ */
add_filter('user_name_badge', function ($icon, $user_id) {
    if (!zhiji_is_enabled('vip_auto_auth_enabled')) {
        return $icon;
    }
    if (!get_user_meta((int) $user_id, 'zhiji_vip_auth', true)) {
        return $icon;
    }
    $name = get_user_meta((int) $user_id, 'zhiji_vip_auth_name', true);
    if (!$name) {
        $name = 'VIP认证用户';
    }
    return $icon . '<span class="zhiji-vip-auth-badge" title="' . esc_attr($name) . '">'
        . '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true">'
        . '<path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>'
        . '</svg></span>';
}, 10, 2);

/**
 * 徽章样式
 */
add_action('wp_head', function () {
    if (!zhiji_is_enabled('vip_auto_auth_enabled')) {
        return;
    }
    echo '<style id="zhiji-vip-auth-css">'
        . '.zhiji-vip-auth-badge{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;'
        . 'background:linear-gradient(135deg,#ffd700,#ff8c00);border-radius:50%;color:#fff;margin-left:4px;'
        . 'vertical-align:middle;cursor:help}'
        . '</style>' . "\n";
}, 100);

/* ============================================================
 * 到期取消（每日 cron 扫描）
 * ============================================================ */

/**
 * 调度每日巡检（模块启用时）
 */
add_action('init', function () {
    if (!zhiji_is_enabled('vip_auto_auth_enabled')) {
        wp_clear_scheduled_hook('zhiji_daily_vip_check');
        return;
    }
    if (!wp_next_scheduled('zhiji_daily_vip_check')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'zhiji_daily_vip_check');
    }
});

add_action('zhiji_daily_vip_check', function () {
    if (!zhiji_is_enabled('vip_auto_auth_enabled')) {
        return;
    }
    $users = get_users(array(
        'meta_key'   => 'zhiji_vip_auth',
        'meta_value' => 1,
        'fields'     => 'ID',
        'number'     => 500,
    ));
    $now = current_time('timestamp');
    foreach ($users as $user_id) {
        $exp = get_user_meta($user_id, 'vip_exp_date', true);
        // Permanent = 永久会员；空值视为未知（不动作）
        if ('' === $exp || 'Permanent' === $exp) {
            continue;
        }
        $ts = strtotime((string) $exp);
        if (!$ts || $ts > $now) {
            continue; // 未到期
        }
        $user = new WP_User($user_id);
        if (!$user->exists()) {
            continue;
        }
        $auth_name = get_user_meta($user_id, 'zhiji_vip_auth_name', true);
        if (!$auth_name) {
            $auth_name = 'VIP认证用户';
        }
        $user->set_role('subscriber');
        delete_user_meta($user_id, 'zhiji_vip_auth');
        delete_user_meta($user_id, 'zhiji_vip_auth_name');
        delete_user_meta($user_id, 'zhiji_vip_auth_time');
        zhiji_notify('vip_auto_auth_expired', array(
            'user_id' => $user_id,
            'title'   => '会员认证已到期取消',
            'content' => sprintf('你的「%s」认证身份已于 %s 到期，用户组已恢复为普通用户。续费会员后可重新自动认证。', $auth_name, mysql2date('Y-m-d H:i', (string) $exp)),
            'data'    => array(
                '认证身份' => $auth_name,
                '到期时间' => mysql2date('Y-m-d H:i', (string) $exp),
                '当前用户组' => '订阅者（subscriber）',
            ),
        ));
    }
});

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('vip_auto_auth', array(
        array(
            'id'      => 'vip_auto_auth_enabled',
            'type'    => 'switcher',
            'title'   => '启用自动认证',
            'desc'    => '用户购买会员（父主题 payment_order_success 事件）后自动认证用户组；每日巡检会员到期（vip_exp_date）自动取消。',
            'default' => false,
        ),
        array(
            'id'         => 'vip_auto_auth_role',
            'type'       => 'select',
            'title'      => '认证用户组',
            'options'    => array('contributor' => '投稿者', 'author' => '作者', 'editor' => '编辑'),
            'default'    => 'contributor',
            'dependency' => array('vip_auto_auth_enabled', '==', '1'),
        ),
        array(
            'id'         => 'vip_auto_auth_name',
            'type'       => 'text',
            'title'      => '认证名称',
            'default'    => 'VIP认证用户',
            'dependency' => array('vip_auto_auth_enabled', '==', '1'),
        ),
    ));
}, 20);
