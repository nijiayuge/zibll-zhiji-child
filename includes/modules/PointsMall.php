<?php
/**
 * @module  PointsMall
 * @desc    积分商城：用户以积分兑换奖励（v1 = 积分兑优惠码），发放走 RewardCenter 统一发奖。
 *          防刷红线（调研口径落地）：单日兑换次数上限 / 每人每品限兑 / 积分不足拒绝 / 发码失败自动退回积分。
 * @option  points_mall_enabled   总开关
 *          points_mall_items     兑换品列表（每行：名称|所需积分|库存|-1=无限|每人限兑）
 *          points_mall_scope     兑换所得优惠码档位（与奖励中心同源）
 *          points_mall_daily     单日兑换次数上限
 * @hook    wp_ajax(_nopriv)_zhiji_pmall_exchange · 兑换（含应急开关/登录/限兑守卫）
 * @api     zhiji_pmall_items()  解析兑换品列表
 *          zhiji_pmall_records() 兑换记录（环形 200，与 Lottery 同范式）
 * @short   [zhiji_points_mall]
 * @since   2.0.0（2026-09-29 新增）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('points_mall', array(
    'title'    => '积分商城',
    'parent'   => 'zhiji_user',
    'priority' => 145,
    'option'   => 'points_mall_enabled',
    'enabled_default' => true,   // 商城默认开启（与原短码/入队逻辑一致）
    // 前台入口页由统一建页基建 PageProvisioner 负责（2026-09-29 批3 迁入）
    'pages'    => array(
        array('slug' => 'points-mall', 'title' => '积分商城', 'content' => '[zhiji_points_mall]'),
    ),
));

/* ============================================================
 * 后台配置（Fields 构件）
 * ============================================================ */
Zhiji_Registry::register_options('points_mall', array(
    array(
        'type'    => 'submessage',
        'style'   => 'info',
        'content' => __('<b>前台入口</b>：启用本模块后，站点会自动创建「积分商城」页面（<code>/points-mall</code>，首次访问后台时生成）；'
            . '也可把短码 <code>[zhiji_points_mall]</code> 放到任意页面/文章。后台兑换记录见「知集运维 → 积分兑换」。', 'zhiji'),
    ),
    array(
        'type'    => 'submessage',
        'style'   => 'info',
        'content' => __('兑换品每行一条：<code>名称|所需积分|库存|每人限兑</code>（库存填 -1 表示不限）。'
            . '兑换所得为优惠码（档位见下方选择），发放失败会自动退回积分。', 'zhiji'),
    ),
    array(
        'id'         => 'points_mall_items',
        'type'       => 'textarea',
        'title'      => '兑换品列表',
        'rows'       => 6,
        'sanitize'   => false,
        'placeholder' => "10 元优惠码|100|50|2\n30 元优惠码|300|10|1",
        'desc'       => __('每行一条，竖线分隔。库存扣完即显示「已兑完」。', 'zhiji'),
    ),
    array(
        'id'         => 'points_mall_scope',
        'type'       => 'select',
        'title'      => '优惠码档位',
        'options'    => array(
            'login'  => __('登录档（默认）', 'zhiji'),
            'active' => __('活跃档', 'zhiji'),
            'vip'    => __('VIP 档', 'zhiji'),
        ),
        'default'    => 'login',
        'desc'       => __('兑换发放的优惠码面值档位，与「邮箱领券」同源（zhiji_coupon_give_discount_meta）。', 'zhiji'),
    ),
    array(
        'id'         => 'points_mall_daily',
        'type'       => 'number',
        'title'      => '单日兑换次数上限',
        'default'    => 3,
        'min'        => 1,
        'max'        => 20,
        'desc'       => __('防刷红线：单用户单日可发起的兑换次数。', 'zhiji'),
    ),
), 150);

/* ============================================================
 * 数据层
 * ============================================================ */

/**
 * 解析兑换品列表（容错：格式错误的行跳过）
 *
 * @return array array( array('id'=>行号0起,'name'=>,'cost'=>,'stock'=>,'limit'=>), … )
 */
function zhiji_pmall_items()
{
    $raw = (string) zhiji_get_option('points_mall_items', '');
    $out = array();
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $i => $line) {
        $line = trim($line);
        if ('' === $line || 0 !== strpos($line, '#')) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 2 || '' === $parts[0] || !preg_match('/^\d+$/', $parts[1])) {
                continue; // 名称或积分数非法 → 跳过该行
            }
            $out[] = array(
                'id'    => $i,
                'name'  => $parts[0],
                'cost'  => (int) $parts[1],
                'stock' => isset($parts[2]) && '' !== $parts[2] && is_numeric($parts[2]) ? (int) $parts[2] : -1,
                'limit' => isset($parts[3]) && '' !== $parts[3] && is_numeric($parts[3]) ? (int) $parts[3] : 0,
            );
        }
    }
    return $out;
}

/**
 * 兑换记录（环形 200，与 Lottery 同范式）
 *
 * @return array
 */
function zhiji_pmall_records()
{
    $log = get_option('zhiji_pmall_records', array());
    return is_array($log) ? $log : array();
}

/**
 * 用户的兑换次数（指定商品累计 / 今日次数）
 *
 * @param int $uid
 * @param int $item_id 商品行号（0 起）
 * @param string $day  Y-m-d；传 null 不查当日
 * @return array array('bought'=>累计,'today'=>当日)
 */
function zhiji_pmall_user_counts($uid, $item_id, $day = null)
{
    $meta = get_user_meta($uid, 'zhiji_pmall_bought', true);
    $meta = is_array($meta) ? $meta : array();
    $bought = isset($meta[$item_id]) ? (int) $meta[$item_id] : 0;

    $daily = get_user_meta($uid, 'zhiji_pmall_daily', true);
    $daily = is_array($daily) ? $daily : array();
    $today_n = (isset($daily['date']) && $daily['date'] === current_time('Y-m-d')) ? (int) $daily['count'] : 0;

    return array('bought' => $bought, 'today' => $today_n);
}

/**
 * 执行兑换（服务端全量校验 + 扣积分 + 发码 + 失败退回）
 *
 * @param int $uid
 * @param int $item_id
 * @return array array('ok'=>bool,'msg'=>,'code'=>优惠码|'')
 */
function zhiji_pmall_exchange($uid, $item_id)
{
    if (function_exists('zhiji_ops_kill_active') && zhiji_ops_kill_active()) {
        return array('ok' => false, 'msg' => __('应急模式已开启，兑换功能暂停', 'zhiji'));
    }
    if (!$uid) {
        return array('ok' => false, 'msg' => __('请先登录', 'zhiji'));
    }

    $items = zhiji_pmall_items();
    if (!isset($items[$item_id])) {
        return array('ok' => false, 'msg' => __('兑换品不存在或已下架', 'zhiji'));
    }
    $item = $items[$item_id];

    // 库存
    if (0 === $item['stock']) {
        return array('ok' => false, 'msg' => __('该奖品已兑完', 'zhiji'));
    }

    // 每人限兑
    $cnt = zhiji_pmall_user_counts($uid, $item_id);
    if ($item['limit'] > 0 && $cnt['bought'] >= $item['limit']) {
        return array('ok' => false, 'msg' => sprintf(__('该奖品每人限兑 %d 次，你已兑完', 'zhiji'), $item['limit']));
    }

    // 单日上限
    $daily = max(1, (int) zhiji_get_option('points_mall_daily', 3));
    if ($cnt['today'] >= $daily) {
        return array('ok' => false, 'msg' => sprintf(__('今日兑换次数已达上限（%d 次），明天再来', 'zhiji'), $daily));
    }

    // 积分余额
    $points = Zhiji_Adapter::get_user_points($uid);
    if ($points < $item['cost']) {
        return array('ok' => false, 'msg' => sprintf(__('积分不足（还差 %d 分）', 'zhiji'), $item['cost'] - $points));
    }

    // 扣积分（负值；Adapter 已实测支持）
    Zhiji_Adapter::update_user_points($uid, array(
        'value' => -$item['cost'],
        'type'  => __('积分商城', 'zhiji'),
        'desc'  => sprintf(__('兑换：%s', 'zhiji'), $item['name']),
    ));

    // 发码（RewardCenter 统一发奖；失败 → 退回积分，绝不白扣）
    $r = zhiji_reward_center_grant_one($uid, 'coupon', 'points_mall', array(
        'coupon_scope' => zhiji_get_option('points_mall_scope', 'login'),
    ));
    if (empty($r) || 'coupon' !== $r['type']) {
        Zhiji_Adapter::update_user_points($uid, array(
            'value' => $item['cost'],
            'type'  => __('积分商城', 'zhiji'),
            'desc'  => sprintf(__('兑换失败退回：%s', 'zhiji'), $item['name']),
        ));
        return array('ok' => false, 'msg' => __('优惠码发放失败，积分已退回，请稍后再试', 'zhiji'));
    }
    $code = isset($r['code']) ? (string) $r['code'] : '';

    // 记录（环形 200）
    $log = zhiji_pmall_records();
    array_unshift($log, array(
        'time'  => current_time('mysql'),
        'uid'   => (int) $uid,
        'user'  => ($u = get_userdata($uid)) ? $u->display_name : ('ID#' . $uid),
        'item'  => $item['name'],
        'cost'  => $item['cost'],
        'code'  => $code,
    ));
    if (count($log) > 200) {
        $log = array_slice($log, 0, 200);
    }
    update_option('zhiji_pmall_records', $log, false);

    // FOMO 弹幕联动（2026-09-29 新增）
    if (function_exists('zhiji_danmu_push')) {
        zhiji_danmu_push('exchange', $uid, sprintf('用 %d 积分兑换了「%s」', $item['cost'], $item['name']));
    }

    // 勋章增强事件（2026-09-29 新增）
    do_action('zhiji_pmall_exchanged', $uid);

    // 库存 -1（-1 无限不动）
    if ($item['stock'] > 0) {
        $items[$item_id]['stock'] = $item['stock'] - 1;
        $all = zhiji_get_option('points_mall_items', '');
        $lines = preg_split('/\r\n|\r|\n/', (string) $all);
        if (isset($lines[$item_id])) {
            $parts = array_map('trim', explode('|', $lines[$item_id]));
            if (count($parts) >= 3) {
                $parts[2] = (string) ($item['stock'] - 1);
                $lines[$item_id] = implode('|', $parts);
                zhiji_update_option('points_mall_items', implode("\n", $lines));
            }
        }
        unset($items);
    }

    // 用户计数
    $meta = get_user_meta($uid, 'zhiji_pmall_bought', true);
    $meta = is_array($meta) ? $meta : array();
    $meta[$item_id] = (isset($meta[$item_id]) ? (int) $meta[$item_id] : 0) + 1;
    update_user_meta($uid, 'zhiji_pmall_bought', $meta);

    $daily_m = get_user_meta($uid, 'zhiji_pmall_daily', true);
    $daily_m = is_array($daily_m) ? $daily_m : array();
    $today = current_time('Y-m-d');
    $daily_m = (isset($daily_m['date']) && $daily_m['date'] === $today)
        ? array('date' => $today, 'count' => (int) $daily_m['count'] + 1)
        : array('date' => $today, 'count' => 1);
    update_user_meta($uid, 'zhiji_pmall_daily', $daily_m);

    return array('ok' => true, 'msg' => sprintf(__('兑换成功！优惠码：%s（已同步到「我的优惠码」）', 'zhiji'), $code), 'code' => $code);
}

/* ============================================================
 * AJAX + 短码
 * ============================================================ */

zhiji_api_register('zhiji_pmall_exchange', 'zhiji_pmall_ajax_exchange', true, '');
add_action('wp_ajax_zhiji_pmall_exchange', 'zhiji_pmall_ajax_exchange');
add_action('wp_ajax_nopriv_zhiji_pmall_exchange', 'zhiji_pmall_ajax_exchange');

/**
 * AJAX 兑换端点
 *
 * @return void
 */
function zhiji_pmall_ajax_exchange()
{
    $uid   = get_current_user_id();
    $item  = isset($_POST['item']) ? absint($_POST['item']) : -1;
    $nonce = isset($_POST['nonce']) ? wp_unslash($_POST['nonce']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if (!wp_verify_nonce($nonce, 'zhiji_pmall')) {
        wp_send_json_error(array('msg' => __('页面已过期，请刷新后重试', 'zhiji')), 403);
    }
    $r = zhiji_pmall_exchange($uid, $item);
    if ($r['ok']) {
        wp_send_json_success($r);
    }
    wp_send_json_error(array('msg' => $r['msg']), 200);
}

/**
 * 短码：[zhiji_points_mall]
 *
 * @return string
 */
function zhiji_pmall_shortcode()
{
    if (!zhiji_is_enabled('points_mall_enabled', true)) {
        return '';
    }
    $uid   = get_current_user_id();
    $items = zhiji_pmall_items();

    // 前台 JS/CSS 由 zhiji_pmall_enqueue() 在 wp_enqueue_scripts 统一注册（head 内联）
    $out = '<div class="zhiji-pmall-wrap">';
    if (!$uid) {
        $out .= '<p class="description">' . esc_html__('登录后即可用积分兑换奖品。', 'zhiji') . '</p>';
    } else {
        $out .= '<p>' . esc_html(sprintf(__('当前积分：%d', 'zhiji'), Zhiji_Adapter::get_user_points($uid))) . '</p>';
    }
    if (!$items) {
        $out .= '<p class="description">' . esc_html__('暂无可兑换奖品。', 'zhiji') . '</p></div>';
        return $out;
    }

    $out .= '<div class="zhiji-pmall">';
    foreach ($items as $it) {
        $soldout = (0 === $it['stock']);
        $bought = $uid ? zhiji_pmall_user_counts($uid, $it['id']) : array('bought' => 0, 'today' => 0);
        $limit_hit = ($it['limit'] > 0 && $uid && $bought['bought'] >= $it['limit']);
        $can = ($uid && !$soldout && !$limit_hit);
        // 行业通行券版式：左侧渐变面额区 + 虚线撕票口 + 右侧信息/按钮区
        $out .= '<div class="zhiji-pmall-card' . ($soldout ? ' is-off' : '') . '">';
        $out .= '<div class="zhiji-pmall-left"><b>' . esc_html($it['cost']) . '</b><span>' . esc_html__('积分', 'zhiji') . '</span></div>';
        $out .= '<div class="zhiji-pmall-right"><div class="zhiji-pmall-info">';
        $out .= '<h4>' . esc_html($it['name']) . '</h4>';
        $out .= '<div class="zhiji-pmall-meta">';
        $out .= (-1 === $it['stock']) ? esc_html__('库存充足', 'zhiji') : esc_html(sprintf(__('剩余 %d 件', 'zhiji'), max(0, $it['stock'])));
        if ($it['limit'] > 0) {
            $out .= ' · ' . esc_html(sprintf(__('每人限兑 %d 次', 'zhiji'), $it['limit']));
        }
        $out .= '</div></div>'; // 关闭 meta + info
        if ($soldout) {
            $out .= '<button class="button" disabled>' . esc_html__('已兑完', 'zhiji') . '</button>';
        } elseif (!$uid) {
            $out .= '<button class="button" disabled>' . esc_html__('登录后兑换', 'zhiji') . '</button>';
        } elseif ($limit_hit) {
            $out .= '<button class="button" disabled>' . esc_html__('已达限兑次数', 'zhiji') . '</button>';
        } else {
            $out .= '<button class="button button-primary zhiji-pmall-buy" data-id="' . esc_attr($it['id']) . '"' . ($can ? '' : ' disabled') . '>' . esc_html__('立即兑换', 'zhiji') . '</button>';
        }
        $out .= '</div></div>'; // 关闭 right + card
    }
    $out .= '</div></div>';
    return $out;
}
add_shortcode('zhiji_points_mall', 'zhiji_pmall_shortcode');

/**
 * 前台资源（head 内联；模块启用即注册 —— 与 Danmu/CouponHighlight 同模式）
 *
 * ⚠️ 不能在短码回调里注册：zhiji_asset_print_inline 挂在 wp_head(99)，
 *    短码渲染发生在 head 之后 → 注册为时已晚（JS/CSS 不会输出）。
 *
 * @return void
 */
function zhiji_pmall_enqueue()
{
    if (!zhiji_is_enabled('points_mall_enabled', true)) {
        return;
    }
    $js = "(function(){var C=window.ZHIJI_PMALL||{};document.addEventListener('click',function(e){var b=e.target.closest?e.target.closest('.zhiji-pmall-buy'):null;if(!b)return;e.preventDefault();if(b.disabled)return;b.disabled=true;b.textContent='兑换中…';var d=new FormData();d.append('action','zhiji_api');d.append('api','zhiji_pmall_exchange');d.append('item',b.getAttribute('data-id'));d.append('nonce',C.nonce||'');fetch(C.ajax||'/wp-admin/admin-ajax.php',{method:'POST',credentials:'same-origin',body:d}).then(function(r){return r.json()}).then(function(j){var m=(j.data&&j.data.msg)||'操作失败';alert(m);if(j.success){location.reload()}}).catch(function(){alert('网络异常，请重试');b.disabled=false;b.textContent='立即兑换'})})})();";
    zhiji_asset_add_js('points_mall', $js);
    zhiji_asset_add_js('points_mall_cfg', 'window.ZHIJI_PMALL={nonce:' . wp_json_encode(wp_create_nonce('zhiji_pmall'))
        . ',ajax:' . wp_json_encode(admin_url('admin-ajax.php')) . '};');
    // 券版式（行业通行做法）：横向 ticket —— 左侧渐变面额区 + 虚线撕票口 + 右侧信息/胶囊按钮。
    // 撕票缺口用 radial-gradient mask 挖「真缺口」（透明露出页面底色，暗色/亮色模式都对），
    // 上下两层各 51% 高取并集即可，无需 mask-composite；不支持 mask 的老浏览器自动降级为无缺口圆角券。
    zhiji_asset_add_css('points_mall', '.zhiji-pmall-wrap{width:100%}.zhiji-pmall{display:flex;flex-direction:column;gap:14px}.zhiji-pmall-card{position:relative;display:flex;align-items:stretch;background:#fff;border-radius:12px;filter:drop-shadow(0 4px 10px rgba(0,0,0,.15));-webkit-mask:radial-gradient(circle at 140px 0,#0000 9px,#000 9.5px) 0 0/100% 51% no-repeat,radial-gradient(circle at 140px 0,#0000 9px,#000 9.5px) 0 100%/100% 51% no-repeat;mask:radial-gradient(circle at 140px 0,#0000 9px,#000 9.5px) 0 0/100% 51% no-repeat,radial-gradient(circle at 140px 0,#0000 9px,#000 9.5px) 0 100%/100% 51% no-repeat}.zhiji-pmall-left{flex:0 0 140px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:linear-gradient(135deg,#ff7a45,#e8533f);color:#fff;text-align:center;padding:18px 10px}.zhiji-pmall-left b{font-size:30px;line-height:1.1;font-weight:700}.zhiji-pmall-left span{font-size:13px;opacity:.92;margin-top:2px}.zhiji-pmall-right{flex:1;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 20px;border-left:1px dashed #ffd9cd;min-width:0}.zhiji-pmall-card h4{margin:0 0 6px;font-size:16px;font-weight:600;color:#222}.zhiji-pmall-meta{font-size:12px;color:#999}.zhiji-pmall .button{flex-shrink:0;margin:0;padding:9px 26px;border:none;border-radius:999px;font-size:14px;line-height:1.4;color:#fff;background:linear-gradient(135deg,#ff7a45,#e8533f);cursor:pointer}.zhiji-pmall .button:hover{opacity:.9}.zhiji-pmall .button:disabled{background:#d4d4d4;color:#8a8a8a;cursor:not-allowed}.zhiji-pmall-card.is-off .zhiji-pmall-left{background:linear-gradient(135deg,#cfcfcf,#b8b8b8)}.zhiji-pmall-card.is-off .zhiji-pmall-right{border-left-color:#ddd}@media(max-width:520px){.zhiji-pmall-left{flex-basis:104px}.zhiji-pmall-left b{font-size:24px}.zhiji-pmall-right{flex-wrap:wrap}}');
}
add_action('wp_enqueue_scripts', 'zhiji_pmall_enqueue', 20);

/* ============================================================
 * 前台入口页：由统一建页基建 PageProvisioner 负责（2026-09-29 批3 迁入）
 *
 * 模块在 register_module() 的 'pages' 键声明 /points-mall 页（见上方注册块）。
 * PageProvisioner 在 admin_init 幂等建页（回收站恢复 / slug 冲突后缀 / 禁用不删页），
 * 手动同步入口见本主题设置页顶部按钮。旧 zhiji_pmall_ensure_page() 逻辑已废弃移除。
 * ============================================================ */
