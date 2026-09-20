<?php
/**
 * @module  VipAutoAuth
 * @desc    开通会员自动认证：购买会员后自动调整用户组并加认证标识，会员到期自动取消
 * @option  vip_auto_auth_enabled  总开关
 *          vip_auto_auth_role     认证用户组
 *          vip_auto_auth_name     认证名称
 * @hook    zibpay_pay_success · zibpay_user_vip_expired · zib_get_user_name · wp_head
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/VipAutoAuth.php`
 *          （依赖父主题 zibll 的 zibpay 钩子；钩子监听不经 Adapter，父主题未触发时静默无效果）
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

/* ============================================================
 * 业务逻辑
 * ============================================================ */

/**
 * 开通会员（订单类型 4）后自动认证
 *
 * @param array $order   订单数据
 * @param array $product 商品数据
 * @return void
 */
function zhiji_vip_auto_auth_after_pay($order, $product = array())
{
    if (!zhiji_is_enabled('vip_auto_auth_enabled')) {
        return;
    }
    $user_id = isset($order['user_id']) ? (int) $order['user_id'] : 0;
    if (!$user_id) {
        return;
    }
    // 4 = 购买会员
    if (!isset($order['order_type']) || (int) $order['order_type'] !== 4) {
        return;
    }

    $role = (string) zhiji_get_option('vip_auto_auth_role', 'contributor');
    $name = (string) zhiji_get_option('vip_auto_auth_name', 'VIP认证用户');

    // 仅允许提升到白名单内的角色，避免配置被改成 administrator
    $allowed = array('contributor', 'author', 'editor');
    if (!in_array($role, $allowed, true)) {
        $role = 'contributor';
    }

    $user = new WP_User($user_id);
    if (!$user->exists()) {
        return;
    }
    $user->set_role($role);
    update_user_meta($user_id, 'zhiji_vip_auth', 1);
    update_user_meta($user_id, 'zhiji_vip_auth_name', $name);
    update_user_meta($user_id, 'zhiji_vip_auth_time', current_time('mysql'));

    zhiji_notify('vip_auto_auth_granted', array('user_id' => $user_id, 'role' => $role));
}
add_action('zibpay_pay_success', 'zhiji_vip_auto_auth_after_pay', 10, 2);

/**
 * 会员到期后取消认证（恢复订阅者）
 *
 * @param int $user_id
 * @return void
 */
function zhiji_vip_auto_auth_expired($user_id)
{
    if (!zhiji_is_enabled('vip_auto_auth_enabled')) {
        return;
    }
    $user_id = (int) $user_id;
    if (!get_user_meta($user_id, 'zhiji_vip_auth', true)) {
        return;
    }

    $user = new WP_User($user_id);
    if (!$user->exists()) {
        return;
    }
    $user->set_role('subscriber');
    delete_user_meta($user_id, 'zhiji_vip_auth');
    delete_user_meta($user_id, 'zhiji_vip_auth_name');
    delete_user_meta($user_id, 'zhiji_vip_auth_time');
}
add_action('zibpay_user_vip_expired', 'zhiji_vip_auto_auth_expired', 10, 1);

/**
 * 用户名后追加认证标识
 *
 * @param string $name    用户名 HTML
 * @param int    $user_id 用户 ID
 * @return string
 */
function zhiji_vip_auto_auth_badge($name, $user_id)
{
    if (!zhiji_is_enabled('vip_auto_auth_enabled')) {
        return $name;
    }
    if (!get_user_meta((int) $user_id, 'zhiji_vip_auth', true)) {
        return $name;
    }
    $auth_name = get_user_meta((int) $user_id, 'zhiji_vip_auth_name', true);
    if (!$auth_name) {
        $auth_name = 'VIP认证用户';
    }

    $badge = '<span class="zhiji-vip-auth-badge" title="' . esc_attr($auth_name) . '">'
        . '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true">'
        . '<path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>'
        . '</svg></span>';

    return $name . $badge;
}
add_filter('zib_get_user_name', 'zhiji_vip_auto_auth_badge', 10, 2);

/**
 * 认证标识样式（wp_head 内联，量小仅一段）
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
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('vip_auto_auth', array(
        array(
            'id'      => 'vip_auto_auth_enabled',
            'type'    => 'switcher',
            'title'   => '启用自动认证',
            'desc'    => '用户开通会员后自动认证用户组（依赖父主题会员系统钩子）',
            'default' => false,
        ),
        array(
            'id'         => 'vip_auto_auth_role',
            'type'       => 'select',
            'title'      => '认证用户组',
            'options'    => array('contributor' => '投稿者', 'author' => '作者', 'editor' => '编辑'),
            'default'    => 'contributor',
            'dependency' => array('vip_auto_auth_enabled', '==', 'true'),
        ),
        array(
            'id'         => 'vip_auto_auth_name',
            'type'       => 'text',
            'title'      => '认证名称',
            'default'    => 'VIP认证用户',
            'dependency' => array('vip_auto_auth_enabled', '==', 'true'),
        ),
    ));
}, 20);
