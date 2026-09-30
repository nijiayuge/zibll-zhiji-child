<?php
/**
 * @module  Bargain
 * @desc    砍价：用户发起砍价 → 分享链接 → 好友助力 → 权重曲线递减 → 归零发奖励。
 *          已对接父主题商城（2026-09-29，卡密方式）：配置商品 ID 后，归零发放「绑定该商品的
 *          免单券」，商品页结账 0 元拿货；未配置则维持通用优惠码奖励（不做微信 SDK）。
 *          权重曲线（调研口径）：首刀 = 总额×60%；此后每刀 = max(0.01, 剩余×rand(0.10~0.18))；
 *          新注册用户 ×加权（默认 3）；剩余 ≤0.05 → 任意助力直接清零。
 * @option  bargain_enabled            总开关
 *          bargain_hours              时效（小时，默认 24）
 *          bargain_assist_daily       助力者每日助力上限（默认 3）
 *          bargain_new_multiplier     新用户助力加权倍数（默认 3）
 *          bargain_first_cut_pct      首刀占比（百分比，默认 60）
 *          bargain_scope              砍到 0 元发放的优惠码档位
 * @hook    wp_ajax(_nopriv)_zhiji_bargain_create · 发起砍价
 *          wp_ajax(_nopriv)_zhiji_bargain_assist · 助力
 *          wp_ajax(_nopriv)_zhiji_bargain_fetch · 查进度
 * @short   [zhiji_bargain]
 * @since   2.0.0（2026-09-29 新增）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('bargain', array(
    'title'    => '砍价',
    'parent'   => 'zhiji_interact',
    'priority' => 135,
    'option'   => 'bargain_enabled',
));

/* ============================================================
 * 后台配置
 * ============================================================ */
Zhiji_Registry::register_options('bargain', array(
    array(
        'id'      => 'bargain_enabled',
        'type'    => 'switcher',
        'title'   => __( '启用砍价', 'zhiji' ),
        'label'   => __( '开启后前台可用短码 [zhiji_bargain] 发起砍价，归零发放奖励。', 'zhiji' ),
        'default' => true,
    ),
    array(
        'type'    => 'submessage',
        'style'   => 'info',
        'content' => __('砍到 0 元发奖励。权重曲线：首刀 = 总额×首刀占比，此后递减，'
            . '新用户助力加权。24 小时内未归零即失效。<b>已对接父主题商城</b>：填了下方商品 ID 后，'
            . '砍价成功发放<b>绑定该商品的免单券</b>（卡密方式，不碰支付流程），到商品页结账输入券码 0 元拿货；'
            . '商品 ID 填 0 则维持原「通用优惠码」奖励。', 'zhiji'),
    ),
    array('id' => 'bargain_hours', 'type' => 'number', 'title' => '时效（小时）', 'default' => 24, 'min' => 1, 'max' => 168),
    array('id' => 'bargain_assist_daily', 'type' => 'number', 'title' => '助力者每日助力上限', 'default' => 3, 'min' => 1, 'max' => 20),
    array('id' => 'bargain_new_multiplier', 'type' => 'number', 'title' => '新用户助力加权', 'default' => 3, 'min' => 1, 'max' => 10,
        'desc' => __('新注册用户（注册 ≤ 7 天）的助力金额倍数。', 'zhiji')),
    array('id' => 'bargain_first_cut_pct', 'type' => 'number', 'title' => '首刀占比（%）', 'default' => 60, 'min' => 10, 'max' => 90),
    array('id' => 'bargain_scope', 'type' => 'select', 'title' => '砍到 0 元优惠码档位', 'options' => array(
        'login'  => __('登录档', 'zhiji'), 'active' => __('活跃档', 'zhiji'), 'vip' => __('VIP 档', 'zhiji')),
        'default' => 'login'),
    array('id' => 'bargain_product_id', 'type' => 'number', 'title' => '对接父主题商城商品 ID', 'default' => 0, 'min' => 0,
        'desc' => __('填父主题商城付费商品的文章 ID（>0 生效）：砍价成功改发「绑定该商品的免单券」，用户到商品页结账 0 元拿货（卡密方式，不碰支付流程）；填 0 则发放上方的通用优惠码。', 'zhiji')),
), 135);

/* ============================================================
 * 数据层（option 环形 200）
 * ============================================================ */

function zhiji_bargain_all()
{
    $log = get_option('zhiji_bargain_log', array());
    return is_array($log) ? $log : array();
}

function zhiji_bargain_save(array $log)
{
    if (count($log) > 200) {
        $log = array_slice($log, 0, 200);
    }
    update_option('zhiji_bargain_log', $log, false);
}

function zhiji_bargain_get($bid)
{
    foreach (zhiji_bargain_all() as $b) {
        if (isset($b['bid']) && $b['bid'] === $bid) {
            return $b;
        }
    }
    return null;
}

function zhiji_bargain_update(array $updated)
{
    $log = zhiji_bargain_all();
    foreach ($log as $i => $b) {
        if (isset($b['bid']) && $b['bid'] === $updated['bid']) {
            $log[$i] = $updated;
            zhiji_bargain_save($log);
            return;
        }
    }
}

/** 助力记录（嵌套在砍价内） */
function zhiji_bargain_assists($bid)
{
    $b = zhiji_bargain_get($bid);
    return $b ? (array) ($b['assists'] ?? array()) : array();
}

/** 生成砍价 ID */
function zhiji_bargain_gen_id()
{
    return substr(md5(uniqid('bargain', true)), 0, 12);
}

/* ============================================================
 * 权重曲线
 * ============================================================ */

/**
 * 计算本次助力砍掉的金额（权重曲线核心）
 *
 * @param float $remaining   剩余金额
 * @param float $total       总额
 * @param bool  $is_new_user 是否新用户（注册 ≤ 7 天）
 * @param int   $assist_n    已有助力次数（含本次之前）
 * @return float
 */
function zhiji_bargain_cut($remaining, $total, $is_new_user, $assist_n)
{
    if ($remaining <= 0.05) {
        return $remaining; // 临门一脚直接清零
    }
    $pct = max(10, min(90, (int) zhiji_get_option('bargain_first_cut_pct', 60)));
    if (0 === $assist_n) {
        // 首刀（发起人自砍）
        return round($total * $pct / 100, 2);
    }
    // 后续刀：剩余 × rand(10%~18%)
    $base = $remaining * (mt_rand(10, 18) / 100);
    // 新用户加权
    if ($is_new_user) {
        $mult = max(1, (int) zhiji_get_option('bargain_new_multiplier', 3));
        $base *= $mult;
    }
    // 不超过剩余（留 0.01 兜底）
    $cut = min($remaining - 0.01, round($base, 2));
    return max(0.01, $cut);
}

/**
 * 判断是否新用户（注册 ≤ 7 天）
 *
 * @param int $uid
 * @return bool
 */
function zhiji_bargain_is_new_user($uid)
{
    $u = get_userdata($uid);
    if (!$u) {
        return false;
    }
    $reg = strtotime($u->user_registered);
    return (current_time('timestamp') - $reg) <= 7 * DAY_IN_SECONDS;
}

/* ============================================================
 * AJAX 入口
 * ============================================================ */

zhiji_api_register('zhiji_bargain_create', 'zhiji_bargain_ajax_create', true, '');
zhiji_api_register('zhiji_bargain_assist', 'zhiji_bargain_ajax_assist', true, '');
zhiji_api_register('zhiji_bargain_fetch', 'zhiji_bargain_ajax_fetch', true, '');
add_action('wp_ajax_zhiji_bargain_create', 'zhiji_bargain_ajax_create');
add_action('wp_ajax_nopriv_zhiji_bargain_create', 'zhiji_bargain_ajax_create');
add_action('wp_ajax_zhiji_bargain_assist', 'zhiji_bargain_ajax_assist');
add_action('wp_ajax_nopriv_zhiji_bargain_assist', 'zhiji_bargain_ajax_assist');
add_action('wp_ajax_zhiji_bargain_fetch', 'zhiji_bargain_ajax_fetch');
add_action('wp_ajax_nopriv_zhiji_bargain_fetch', 'zhiji_bargain_ajax_fetch');

/**
 * 发起砍价
 *
 * @return void
 */
function zhiji_bargain_ajax_create()
{
    if (function_exists('zhiji_ops_kill_active') && zhiji_ops_kill_active()) {
        wp_send_json_error(array('msg' => __('应急模式已开启，砍价功能暂停', 'zhiji')), 503);
    }
    $uid = get_current_user_id();
    if (!$uid) {
        wp_send_json_error(array('msg' => __('请先登录', 'zhiji')), 200);
    }
    $nonce = isset($_POST['nonce']) ? wp_unslash($_POST['nonce']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if (!wp_verify_nonce($nonce, 'zhiji_bargain')) {
        wp_send_json_error(array('msg' => __('页面已过期，请刷新后重试', 'zhiji')), 403);
    }

    // 同一用户只能有一个进行中的砍价
    $hours = max(1, (int) zhiji_get_option('bargain_hours', 24));
    foreach (zhiji_bargain_all() as $b) {
        if ((int) $b['uid'] === $uid && 'active' === $b['status']
            && (current_time('timestamp') - strtotime($b['created'])) < $hours * HOUR_IN_SECONDS) {
            wp_send_json_error(array('msg' => __('你已有一个进行中的砍价', 'zhiji')), 200);
        }
    }

    // 总额 = 当前优惠码档位面值
    $scope = zhiji_get_option('bargain_scope', 'login');
    $discount = function_exists('zhiji_coupon_give_discount_meta') ? zhiji_coupon_give_discount_meta($scope) : array('type' => 'reduce', 'val' => 10);
    $total = ('reduce' === $discount['type']) ? (float) $discount['val'] : 10.0;

    $bid  = zhiji_bargain_gen_id();
    $first = zhiji_bargain_cut($total, $total, false, 0);
    $remaining = round($total - $first, 2);

    $log = zhiji_bargain_all();
    $log = array_values(array_filter($log, function ($b) use ($hours) {
        return !('active' === $b['status'] && (current_time('timestamp') - strtotime($b['created'])) >= $hours * HOUR_IN_SECONDS);
    }));

    $log[] = array(
        'bid'       => $bid,
        'uid'       => $uid,
        'user'      => ($u = get_userdata($uid)) ? $u->display_name : ('ID#' . $uid),
        'total'     => $total,
        'remaining' => $remaining,
        'status'    => 'active',
        'created'   => current_time('mysql'),
        'expires'   => date('Y-m-d H:i:s', current_time('timestamp') + $hours * HOUR_IN_SECONDS),
        'assists'   => array(array('uid' => $uid, 'user' => ($u = get_userdata($uid)) ? $u->display_name : '', 'cut' => $first, 'time' => current_time('mysql'))),
    );
    zhiji_bargain_save($log);

    wp_send_json_success(array(
        'bid'       => $bid,
        'total'     => $total,
        'remaining' => $remaining,
        'share_url' => home_url('/?zhiji_bargain=' . $bid),
        'msg'       => sprintf(__('砍价已发起！已自砍 %.2f 元，剩余 %.2f 元', 'zhiji'), $first, $remaining),
    ));
}

/**
 * 助力
 *
 * @return void
 */
function zhiji_bargain_ajax_assist()
{
    if (function_exists('zhiji_ops_kill_active') && zhiji_ops_kill_active()) {
        wp_send_json_error(array('msg' => __('应急模式已开启，砍价功能暂停', 'zhiji')), 503);
    }
    $uid = get_current_user_id();
    if (!$uid) {
        wp_send_json_error(array('msg' => __('请先登录再助力', 'zhiji')), 200);
    }
    $nonce = isset($_POST['nonce']) ? wp_unslash($_POST['nonce']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if (!wp_verify_nonce($nonce, 'zhiji_bargain')) {
        wp_send_json_error(array('msg' => __('页面已过期，请刷新后重试', 'zhiji')), 403);
    }
    $bid = isset($_POST['bid']) ? sanitize_text_field(wp_unslash($_POST['bid'])) : '';
    $b   = zhiji_bargain_get($bid);
    if (!$b) {
        wp_send_json_error(array('msg' => __('砍价不存在', 'zhiji')), 200);
    }
    if ('active' !== $b['status']) {
        wp_send_json_error(array('msg' => __('该砍价已完成或已过期', 'zhiji')), 200);
    }
    if ((current_time('timestamp') - strtotime($b['created'])) >= max(1, (int) zhiji_get_option('bargain_hours', 24)) * HOUR_IN_SECONDS) {
        wp_send_json_error(array('msg' => __('该砍价已过期', 'zhiji')), 200);
    }

    // 同一用户同一砍价仅助力 1 次
    $assists = (array) ($b['assists'] ?? array());
    foreach ($assists as $a) {
        if ((int) $a['uid'] === $uid) {
            wp_send_json_error(array('msg' => __('你已助力过该砍价', 'zhiji')), 200);
        }
    }

    // 助力者每日上限
    $daily_cap = max(1, (int) zhiji_get_option('bargain_assist_daily', 3));
    $today     = current_time('Y-m-d');
    $today_n   = 0;
    foreach (zhiji_bargain_all() as $bb) {
        foreach ((array) ($bb['assists'] ?? array()) as $a) {
            if ((int) $a['uid'] === $uid && 0 === strpos((string) $a['time'], $today)) {
                $today_n++;
            }
        }
    }
    if ($today_n >= $daily_cap) {
        wp_send_json_error(array('msg' => sprintf(__('今日助力次数已达上限（%d 次）', 'zhiji'), $daily_cap)), 200);
    }

    // 权重曲线
    $remaining  = (float) $b['remaining'];
    $is_new     = zhiji_bargain_is_new_user($uid);
    $assist_n   = count($assists);
    $cut        = zhiji_bargain_cut($remaining, (float) $b['total'], $is_new, $assist_n);
    $new_rem    = round(max(0, $remaining - $cut), 2);

    $u = get_userdata($uid);
    $assists[] = array('uid' => $uid, 'user' => $u ? $u->display_name : '', 'cut' => $cut, 'new' => $is_new, 'time' => current_time('mysql'));
    $b['assists']   = $assists;
    $b['remaining'] = $new_rem;

    $done = ($new_rem <= 0);
    if ($done) {
        $b['status'] = 'done';
        // 归零发奖励给发起人（2026-09-29 对接父主题商城）：
        // 配置了商品 ID → 发「绑定该商品的免单券」（卡密方式，商品页结账 0 元拿货）；
        // 未配置（=0）→ 维持原通用优惠码奖励。
        $pid      = (int) zhiji_get_option('bargain_product_id', 0);
        $pid_post = ($pid > 0) ? get_post($pid) : null;
        if ($pid_post && function_exists('zhiji_coupon_give_create_one')) {
            $bargain_code = zhiji_coupon_give_create_one(array(
                'discount' => array('type' => 'multiply', 'val' => 0), // 0 折 = 免单
                'title'    => sprintf(__('砍价免单·%s', 'zhiji'), get_the_title($pid)),
                'reuse'    => 1,
                'user_id'  => (int) $b['uid'],
                'source'   => 'bargain',
            ), $pid);
            $r = $bargain_code ? array('code' => $bargain_code) : array();
        } else {
            $r = zhiji_reward_center_grant_one((int) $b['uid'], 'coupon', 'bargain', array(
                'coupon_scope' => zhiji_get_option('bargain_scope', 'login'),
            ));
        }
        $b['code'] = isset($r['code']) ? $r['code'] : '';
        // 通知发起人
        if (function_exists('zhiji_notify')) {
            zhiji_notify('bargain_success', array(
                'user_id' => (int) $b['uid'],
                'title'   => __('砍价成功！', 'zhiji'),
                'content' => $pid_post
                    ? sprintf(__('你的砍价已归零，商品免单券 %s 已发放（我的优惠码可见），到该商品页结账输入券码即可 0 元拿货。', 'zhiji'), $b['code'])
                    : sprintf(__('你的砍价已归零，优惠码 %s 已发放。', 'zhiji'), $b['code']),
            ));
        }
        // FOMO 弹幕联动 + 勋章增强事件（2026-09-29 新增）
        if (function_exists('zhiji_danmu_push')) {
            zhiji_danmu_push('bargain', (int) $b['uid'], sprintf('砍价成功！获得了 %s 的优惠码', (string) ($b['code'] ?? '')));
        }
        do_action('zhiji_bargain_success', (int) $b['uid']);
    }

    zhiji_bargain_update($b);

    wp_send_json_success(array(
        'cut'       => $cut,
        'remaining' => $new_rem,
        'done'      => $done,
        'is_new'    => $is_new,
        'msg'       => $done
            ? sprintf(__('砍价成功！%s 助力砍掉 %.2f 元，已归零！', 'zhiji'), $is_new ? __('新用户', 'zhiji') : '', $cut)
            : sprintf(__('助力成功！砍掉 %.2f 元%s，剩余 %.2f 元', 'zhiji'), $cut, $is_new ? __('（新用户加权）', 'zhiji') : '', $new_rem),
    ));
}

/**
 * 查进度
 *
 * @return void
 */
function zhiji_bargain_ajax_fetch()
{
    $bid = isset($_POST['bid']) ? sanitize_text_field(wp_unslash($_POST['bid'])) : '';
    $b   = zhiji_bargain_get($bid);
    if (!$b) {
        wp_send_json_error(array('msg' => __('砍价不存在', 'zhiji')), 200);
    }
    wp_send_json_success(array(
        'remaining' => $b['remaining'],
        'status'    => $b['status'],
        'assists'   => count((array) ($b['assists'] ?? array())),
        'total'     => $b['total'],
    ));
}

/* ============================================================
 * 短码
 * ============================================================ */

/**
 * 短码：[zhiji_bargain]
 *
 * @return string
 */
function zhiji_bargain_shortcode()
{
    if (!zhiji_is_enabled('bargain_enabled', true)) {
        return '';
    }

    zhiji_bargain_enqueue();

    // 助力落地页（?zhiji_bargain=xxx）
    $bid_param = isset($_GET['zhiji_bargain']) ? sanitize_text_field(wp_unslash($_GET['zhiji_bargain'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $b = $bid_param ? zhiji_bargain_get($bid_param) : null;

    $out = '<div class="zhiji-bargain" id="zhiji-bargain">';
    $out .= '<div class="zhiji-bargain-box" style="border:1px solid #eee;border-radius:8px;padding:16px;background:#fff">';

    if ($b) {
        // 助力落地页
        $remaining = (float) $b['remaining'];
        $total     = (float) $b['total'];
        $progress  = $total > 0 ? round((($total - $remaining) / $total) * 100, 1) : 0;
        $n_assists = count((array) ($b['assists'] ?? array()));

        $out .= '<h3>' . esc_html($b['user']) . esc_html__(' 的砍价', 'zhiji') . '</h3>';
        $out .= '<div style="margin:8px 0"><span class="zhiji-ops-tag ' . ('done' === $b['status'] ? 'cleared' : 'active') . '">'
            . esc_html('done' === $b['status'] ? __('已完成', 'zhiji') : __('进行中', 'zhiji')) . '</span></div>';
        $out .= '<div style="background:#f0f0f0;border-radius:99px;height:20px;overflow:hidden;margin:8px 0">'
            . '<div style="background:linear-gradient(90deg,#ff6b35,#ff9b35);height:100%;width:' . esc_attr($progress) . '%;border-radius:99px"></div></div>';
        $out .= '<p>' . esc_html(sprintf(__('已砍 %.2f / %.2f 元（%d 人助力）', 'zhiji'), $total - $remaining, $total, $n_assists)) . '</p>';
        if ('done' === $b['status']) {
            $out .= '<p style="color:#22c55e;font-weight:600">' . esc_html__('🎉 砍价成功！', 'zhiji') . '</p>';
        } elseif ('active' === $b['status'] && is_user_logged_in()) {
            $out .= '<button class="button button-primary" id="zhiji-bargain-assist-btn" data-bid="' . esc_attr($bid) . '">' . esc_html__('帮 TA 砍一刀', 'zhiji') . '</button>';
        } elseif (!is_user_logged_in()) {
            $out .= '<p class="description">' . esc_html__('登录后即可助力。', 'zhiji') . '</p>';
        }
    } else {
        // 发起页
        if (!is_user_logged_in()) {
            $out .= '<p class="description">' . esc_html__('登录后即可发起砍价。', 'zhiji') . '</p></div></div>';
            return $out;
        }
        $out .= '<p>' . esc_html__('发起砍价后分享给好友，24 小时内砍到 0 元即可获得优惠码。', 'zhiji') . '</p>';
        $out .= '<button class="button button-primary" id="zhiji-bargain-create-btn">' . esc_html__('发起砍价', 'zhiji') . '</button>';
        $out .= '<div id="zhiji-bargain-result" style="margin-top:12px"></div>';
    }

    $out .= '</div></div>';
    return $out;
}
add_shortcode('zhiji_bargain', 'zhiji_bargain_shortcode');

/**
 * 前台资源（JS 独立文件）
 *
 * @return void
 */
function zhiji_bargain_enqueue()
{
    if (!zhiji_is_enabled('bargain_enabled', true)) {
        return;
    }
    wp_enqueue_script('zhiji-bargain', zhiji_asset_url('js/bargain.js'), array(), ZHIJI_VERSION, false);
    wp_add_inline_script('zhiji-bargain', 'window.ZHIJI_BARGAIN_CFG=' . wp_json_encode(array(
        'nonce' => wp_create_nonce('zhiji_bargain'),
        'ajax'  => admin_url('admin-ajax.php'),
        'bid'   => isset($_GET['zhiji_bargain']) ? sanitize_text_field(wp_unslash($_GET['zhiji_bargain'])) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    )) . ';', 'before');
    zhiji_asset_add_css('bargain', '.zhiji-bargain-box{border:1px solid #eee;border-radius:8px;padding:16px;background:#fff}');
}
add_action('wp_enqueue_scripts', 'zhiji_bargain_enqueue', 20);
