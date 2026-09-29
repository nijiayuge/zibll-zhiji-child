<?php
/**
 * @module  Seckill
 * @desc    积分秒杀：扩展积分商城商品类型 —— 除优惠码外，支持用积分秒杀父主题商城
 *          的具体商品。对接方式（卡密方式，绝不动收款逻辑）：兑换成功发放一张
 *          「免单优惠码」并绑定商品 post_id（父主题原生校验仅限该商品可用），
 *          用户到商品页结账输入该码即可 0 元拿货。
 * @option  seckill_enabled      总开关
 *          seckill_items        秒杀品列表（每行：商品ID|所需积分|库存|-1=无限|开始时间|结束时间）
 * @hook    wp_ajax(_nopriv)_zhiji_seckill_buy · 秒杀抢购（含时间窗/库存/限购/积分守卫）
 * @short   [zhiji_seckill]（启用后自动追加到 /points-mall 页，也可放到任意页面）
 * @since   2.0.0（2026-09-29 新增）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('seckill', array(
    'title'    => '积分秒杀',
    'parent'   => 'zhiji_user',
    'priority' => 148,
    'option'   => 'seckill_enabled',
    'enabled_default' => false,
));

/* ============================================================
 * 后台配置
 * ============================================================ */
Zhiji_Registry::register_options('seckill', array(
    array(
        'type'    => 'submessage',
        'style'   => 'info',
        'content' => __('<b>对接说明</b>：秒杀成功发放<b>绑定该商品的免单优惠码</b>（卡密方式，不碰支付流程），'
            . '用户在父主题商城该商品页结账时输入券码即 0 元拿货；券会出现在用户「我的优惠码」里。'
            . '每人每品限抢 1 次。开启后秒杀专区自动追加到「积分商城」页，也可把短码 <code>[zhiji_seckill]</code> 放到任意页面。', 'zhiji'),
    ),
    array(
        'id'         => 'seckill_items',
        'type'       => 'textarea',
        'title'      => '秒杀品列表',
        'rows'       => 6,
        'sanitize'   => false,
        'placeholder' => "1024|500|10|2026-10-01 10:00|2026-10-01 23:59\n2048|1200|3||",
        'desc'       => __('每行一条：商品ID|所需积分|库存(-1=无限)|开始时间|结束时间（Y-m-d H:i，两端留空表示不限时）。商品ID 为父主题商城付费商品的文章 ID。库存扣完即显示「已抢完」。', 'zhiji'),
    ),
), 148);

/* ============================================================
 * 数据层
 * ============================================================ */

/**
 * 解析秒杀品列表（容错：商品不存在/格式非法的行跳过）
 *
 * @return array array( array('post_id'=>,'cost'=>,'stock'=>,'start'=>,'end'=>,'line'=>行号0起), … )
 */
function zhiji_seckill_items()
{
    $raw = (string) zhiji_get_option('seckill_items', '');
    $out = array();
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $i => $line) {
        $line = trim($line);
        if ('' !== $line && 0 !== strpos($line, '#')) {
            $p = array_map('trim', explode('|', $line));
            $pid = isset($p[0]) ? (int) $p[0] : 0;
            if ($pid < 1 || !isset($p[1]) || !preg_match('/^\d+$/', $p[1]) || !get_post($pid)) {
                continue; // 商品 ID 非法或商品不存在 → 跳过
            }
            $out[] = array(
                'post_id' => $pid,
                'cost'    => (int) $p[1],
                'stock'   => (isset($p[2]) && '' !== $p[2] && is_numeric($p[2])) ? (int) $p[2] : -1,
                'start'   => isset($p[3]) ? $p[3] : '',
                'end'     => isset($p[4]) ? $p[4] : '',
                'line'    => $i,
            );
        }
    }
    return $out;
}

/**
 * 秒杀记录（环形 200，与 PointsMall/Lottery 同范式）
 *
 * @return array
 */
function zhiji_seckill_records()
{
    $log = get_option('zhiji_seckill_records', array());
    return is_array($log) ? $log : array();
}

/**
 * 时间窗判定
 *
 * @param array $item 秒杀品行
 * @return string ''=进行中 | waiting=未开始 | ended=已结束
 */
function zhiji_seckill_window($item)
{
    $now = current_time('timestamp');
    if (!empty($item['start']) && $now < strtotime($item['start'])) {
        return 'waiting';
    }
    if (!empty($item['end']) && $now > strtotime($item['end'])) {
        return 'ended';
    }
    return '';
}

/**
 * 执行秒杀（服务端全量校验 + 扣积分 + 发免单券 + 失败退回）
 *
 * @param int $uid
 * @param int $line 秒杀品行号（0 起按启用行计）
 * @return array array('ok'=>bool,'msg'=>,'code'=>券码|'')
 */
function zhiji_seckill_buy($uid, $line)
{
    if (function_exists('zhiji_ops_kill_active') && zhiji_ops_kill_active()) {
        return array('ok' => false, 'msg' => __('应急模式已开启，秒杀暂停', 'zhiji'));
    }
    if (!$uid) {
        return array('ok' => false, 'msg' => __('请先登录', 'zhiji'));
    }
    $items = zhiji_seckill_items();
    if (!isset($items[$line])) {
        return array('ok' => false, 'msg' => __('该秒杀品不存在或已下架', 'zhiji'));
    }
    $item = $items[$line];

    // 时间窗
    $win = zhiji_seckill_window($item);
    if ('waiting' === $win) {
        return array('ok' => false, 'msg' => __('秒杀尚未开始', 'zhiji'));
    }
    if ('ended' === $win) {
        return array('ok' => false, 'msg' => __('秒杀已结束', 'zhiji'));
    }
    // 库存
    if (0 === $item['stock']) {
        return array('ok' => false, 'msg' => __('已被抢完', 'zhiji'));
    }
    // 每人每品限 1 次（秒杀惯例，硬性）
    $bought = get_user_meta($uid, 'zhiji_seckill_bought', true);
    $bought = is_array($bought) ? $bought : array();
    if (!empty($bought[$line])) {
        return array('ok' => false, 'msg' => __('该商品每人限抢 1 次，你已抢过', 'zhiji'));
    }
    // 积分
    $points = Zhiji_Adapter::get_user_points($uid);
    if ($points < $item['cost']) {
        return array('ok' => false, 'msg' => sprintf(__('积分不足（还差 %d 分）', 'zhiji'), $item['cost'] - $points));
    }

    // 扣积分（失败即止，避免白扣）
    Zhiji_Adapter::update_user_points($uid, array(
        'value' => -$item['cost'],
        'type'  => __('积分秒杀', 'zhiji'),
        'desc'  => sprintf(__('秒杀商品 #%d', 'zhiji'), $item['post_id']),
    ));

    // 发放绑定商品的免单券（卡密方式对接父主题商城；失败 → 退积分）
    $code = '';
    if (function_exists('zhiji_coupon_give_create_one')) {
        $code = zhiji_coupon_give_create_one(array(
            'discount' => array('type' => 'multiply', 'val' => 0), // 0 折 = 免单
            'title'    => sprintf(__('秒杀专享·%s', 'zhiji'), get_the_title($item['post_id'])),
            'reuse'    => 1,
            'user_id'  => $uid,
            'source'   => 'seckill',
        ), $item['post_id']);
    }
    if (!$code) {
        Zhiji_Adapter::update_user_points($uid, array(
            'value' => $item['cost'],
            'type'  => __('积分秒杀', 'zhiji'),
            'desc'  => sprintf(__('秒杀失败退回 #%d', 'zhiji'), $item['post_id']),
        ));
        return array('ok' => false, 'msg' => __('免单券发放失败，积分已退回，请稍后再试', 'zhiji'));
    }

    // 记录（环形 200）
    $log = zhiji_seckill_records();
    array_unshift($log, array(
        'time'  => current_time('mysql'),
        'uid'   => (int) $uid,
        'user'  => ($u = get_userdata($uid)) ? $u->display_name : ('ID#' . $uid),
        'pid'   => $item['post_id'],
        'cost'  => $item['cost'],
        'code'  => $code,
    ));
    if (count($log) > 200) {
        $log = array_slice($log, 0, 200);
    }
    update_option('zhiji_seckill_records', $log, false);

    // 库存扣减（写回配置行；-1 无限不动）
    if ($item['stock'] > 0) {
        $all = preg_split('/\r\n|\r|\n/', (string) zhiji_get_option('seckill_items', ''));
        $parts = array_map('trim', explode('|', isset($all[$item['line']]) ? $all[$item['line']] : ''));
        if (count($parts) >= 3) {
            $parts[2] = (string) ($item['stock'] - 1);
            $all[$item['line']] = implode('|', $parts);
            zhiji_update_option('seckill_items', implode("\n", $all));
        }
        unset($all);
    }

    // 用户限购计数
    $bought[$line] = (isset($bought[$line]) ? (int) $bought[$line] : 0) + 1;
    update_user_meta($uid, 'zhiji_seckill_bought', $bought);

    // FOMO 弹幕联动
    if (function_exists('zhiji_danmu_push')) {
        zhiji_danmu_push('exchange', $uid, sprintf('用 %d 积分秒杀到了「%s」', $item['cost'], get_the_title($item['post_id'])));
    }

    return array(
        'ok'   => true,
        'msg'  => sprintf(__('秒杀成功！免单券 %s 已发放（我的优惠码可见），到该商品页结账输入券码即可 0 元拿货。', 'zhiji'), $code),
        'code' => $code,
    );
}

/* ============================================================
 * AJAX + 短码
 * ============================================================ */

zhiji_api_register('zhiji_seckill_buy', 'zhiji_seckill_ajax_buy', true, '');
add_action('wp_ajax_zhiji_seckill_buy', 'zhiji_seckill_ajax_buy');
add_action('wp_ajax_nopriv_zhiji_seckill_buy', 'zhiji_seckill_ajax_buy');

/**
 * AJAX 秒杀端点
 *
 * @return void
 */
function zhiji_seckill_ajax_buy()
{
    $uid   = get_current_user_id();
    $line  = isset($_POST['line']) ? absint($_POST['line']) : -1;
    $nonce = isset($_POST['nonce']) ? wp_unslash($_POST['nonce']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if (!wp_verify_nonce($nonce, 'zhiji_seckill')) {
        wp_send_json_error(array('msg' => __('页面已过期，请刷新后重试', 'zhiji')), 403);
    }
    $r = zhiji_seckill_buy($uid, $line);
    if ($r['ok']) {
        wp_send_json_success($r);
    }
    wp_send_json_error(array('msg' => $r['msg']), 200);
}

/**
 * 短码：[zhiji_seckill]
 *
 * @return string
 */
function zhiji_seckill_shortcode()
{
    if (!zhiji_is_enabled('seckill_enabled', false)) {
        return '';
    }
    $uid   = get_current_user_id();
    $items = zhiji_seckill_items();

    // 前台 JS/CSS 由 zhiji_seckill_enqueue() 在 wp_enqueue_scripts 统一注册（head 内联）
    // ⚠️ nonce 同时写在 body 的 data-nonce 上（PJAX 下 head 内联 cfg 会陈旧，同 PointsMall 修复口径）
    $out = '<div class="zhiji-seckill-wrap" id="zhiji-seckill" data-nonce="' . esc_attr(wp_create_nonce('zhiji_seckill')) . '">';
    $out .= '<div class="title-theme"><b>' . esc_html__('⚡ 积分秒杀专区', 'zhiji') . '</b></div>';
    if (!$uid) {
        $out .= '<p class="description">' . esc_html__('登录后即可用积分参与秒杀。', 'zhiji') . '</p>';
    }
    if (!$items) {
        $out .= '<p class="description">' . esc_html__('暂无秒杀活动。', 'zhiji') . '</p></div>';
        return $out;
    }

    $out .= '<div class="zhiji-seckill">';
    foreach ($items as $line => $it) {
        $win      = zhiji_seckill_window($it);
        $soldout  = (0 === $it['stock']);
        $bought_n = $uid ? 1 : 0;
        if ($uid) {
            $m = get_user_meta($uid, 'zhiji_seckill_bought', true);
            $m = is_array($m) ? $m : array();
            $bought_n = !empty($m[$line]) ? 1 : 0;
        }
        $title = get_the_title($it['post_id']);
        $url   = get_permalink($it['post_id']);

        // 状态按钮
        if ('waiting' === $win) {
            $btn = '<button class="button" disabled>' . esc_html__('未开始', 'zhiji') . '</button>';
        } elseif ('ended' === $win) {
            $btn = '<button class="button" disabled>' . esc_html__('已结束', 'zhiji') . '</button>';
        } elseif ($soldout) {
            $btn = '<button class="button" disabled>' . esc_html__('已抢完', 'zhiji') . '</button>';
        } elseif ($bought_n) {
            $btn = '<button class="button" disabled>' . esc_html__('已抢过', 'zhiji') . '</button>';
        } elseif (!$uid) {
            $btn = '<button class="button" disabled>' . esc_html__('登录后抢购', 'zhiji') . '</button>';
        } else {
            $btn = '<button class="button button-primary zhiji-seckill-buy" data-line="' . esc_attr($line) . '">' . esc_html__('立即抢购', 'zhiji') . '</button>';
        }

        $out .= '<div class="zhiji-seckill-card' . ('ended' === $win || $soldout ? ' is-off' : '') . '">';
        $out .= '<div class="zhiji-seckill-left"><b>' . esc_html($it['cost']) . '</b><span>' . esc_html__('积分', 'zhiji') . '</span></div>';
        $out .= '<div class="zhiji-seckill-right"><div class="zhiji-seckill-info">';
        $out .= '<h4><a href="' . esc_url($url) . '">' . esc_html($title) . '</a></h4>';
        $out .= '<div class="zhiji-seckill-meta">';
        if ('waiting' === $win) {
            $out .= esc_html(sprintf(__('开始于 %s', 'zhiji'), $it['start']))
                . ' <span class="zhiji-seckill-cd" data-start="' . esc_attr($it['start']) . '"></span>';
        } elseif ('ended' === $win) {
            $out .= esc_html__('本场已结束', 'zhiji');
        } else {
            $out .= (!empty($it['end']) ? esc_html(sprintf(__('截止 %s', 'zhiji'), $it['end'])) . ' ' : '')
                . '<span class="zhiji-seckill-cd" data-end="' . esc_attr($it['end']) . '"></span>';
        }
        $out .= ' · ' . ((-1 === $it['stock']) ? esc_html__('库存充足', 'zhiji') : esc_html(sprintf(__('仅剩 %d 件', 'zhiji'), $it['stock'])));
        $out .= ' · ' . esc_html__('每人限抢 1 次', 'zhiji');
        $out .= '</div></div>' . $btn . '</div></div>';
    }
    $out .= '</div></div>';
    return $out;
}
add_shortcode('zhiji_seckill', 'zhiji_seckill_shortcode');

/**
 * 前台资源（head 内联；模块启用即注册 —— 与 PointsMall 同模式）
 *
 * @return void
 */
function zhiji_seckill_enqueue()
{
    if (!zhiji_is_enabled('seckill_enabled', false)) {
        return;
    }
    // 抢购 JS：nonce 优先取 body 实时 data-nonce（PJAX 下 head cfg 会陈旧，同 PointsMall 修复口径）
    $js = "(function(){var C=window.ZHIJI_SECKILL||{};document.addEventListener('click',function(e){var b=e.target.closest?e.target.closest('.zhiji-seckill-buy'):null;if(!b)return;e.preventDefault();if(b.disabled)return;b.disabled=true;var t=b.textContent;b.textContent='抢购中…';var w=document.querySelector('.zhiji-seckill-wrap');var d=new FormData();d.append('action','zhiji_api');d.append('api','zhiji_seckill_buy');d.append('line',b.getAttribute('data-line'));d.append('nonce',(w&&w.getAttribute('data-nonce'))||C.nonce||'');fetch(C.ajax||'/wp-admin/admin-ajax.php',{method:'POST',credentials:'same-origin',body:d}).then(function(r){return r.json().then(function(j){return{code:r.status,j:j}})}).then(function(o){var j=o.j;if(j.success){alert((j.data&&j.data.msg)||'秒杀成功');location.reload();return}alert((j.data&&j.data.msg)||'操作失败');b.disabled=false;b.textContent=t;if(403===o.code){setTimeout(function(){location.reload()},800)}}).catch(function(){alert('网络异常，请重试');b.disabled=false;b.textContent=t})})})();
// 倒计时
(function(){function pad(n){return n<10?'0'+n:''+n}function tick(){var els=document.querySelectorAll('.zhiji-seckill-cd');for(var i=0;i<els.length;i++){var el=els[i];var target=el.getAttribute('data-end')||el.getAttribute('data-start');if(!target){el.textContent='';continue}var ts=new Date(target.replace(/-/g,'/')).getTime();var diff=Math.floor((ts-Date.now())/1000);if(diff<=0){el.textContent='';continue}var d=Math.floor(diff/86400),h=Math.floor(diff%86400/3600),m=Math.floor(diff%3600/60),s=diff%60;el.textContent='（剩 '+(d>0?d+'天':'')+pad(h)+'时'+pad(m)+'分'+pad(s)+'秒）'}}function boot(){tick();setInterval(tick,1000)}if(document.readyState!=='loading'){boot()}else{document.addEventListener('DOMContentLoaded',boot)}})();";
    zhiji_asset_add_js('seckill', $js);
    zhiji_asset_add_js('seckill_cfg', 'window.ZHIJI_SECKILL={nonce:' . wp_json_encode(wp_create_nonce('zhiji_seckill'))
        . ',ajax:' . wp_json_encode(admin_url('admin-ajax.php')) . '};');
    // 复用券版式（与积分商城同视觉语言）：左面额区 + 撕票缺口 + 胶囊按钮
    zhiji_asset_add_css('seckill', '.zhiji-seckill-wrap{width:100%;margin-top:24px}.zhiji-seckill{display:grid;grid-template-columns:repeat(auto-fit,minmax(420px,1fr));gap:18px}.zhiji-seckill-card{position:relative;display:flex;align-items:stretch;background:#fff;border-radius:12px;filter:drop-shadow(0 4px 10px rgba(0,0,0,.15));-webkit-mask:radial-gradient(circle at 140px 0,#0000 9px,#000 9.5px) 0 0/100% 51% no-repeat,radial-gradient(circle at 140px 0,#0000 9px,#000 9.5px) 0 100%/100% 51% no-repeat;mask:radial-gradient(circle at 140px 0,#0000 9px,#000 9.5px) 0 0/100% 51% no-repeat,radial-gradient(circle at 140px 0,#0000 9px,#000 9.5px) 0 100%/100% 51% no-repeat}.zhiji-seckill-left{flex:0 0 140px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:linear-gradient(135deg,#a78bfa,#7c3aed);color:#fff;text-align:center;padding:18px 10px}.zhiji-seckill-left b{font-size:30px;line-height:1.1;font-weight:700}.zhiji-seckill-left span{font-size:13px;opacity:.92;margin-top:2px}.zhiji-seckill-right{flex:1;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 20px;border-left:1px dashed #e3d5ff;min-width:0}.zhiji-seckill-info{min-width:0}.zhiji-seckill-card h4{margin:0 0 6px;font-size:16px;font-weight:600;color:#222}.zhiji-seckill-card h4 a{color:#222;text-decoration:none}.zhiji-seckill-card h4 a:hover{color:#7c3aed}.zhiji-seckill-meta{font-size:12px;color:#999}.zhiji-seckill .button{flex-shrink:0;margin:0;padding:9px 24px;border:none;border-radius:999px;font-size:14px;line-height:1.4;color:#fff;background:linear-gradient(135deg,#a78bfa,#7c3aed);cursor:pointer}.zhiji-seckill .button:hover{opacity:.9}.zhiji-seckill .button:disabled{background:#d4d4d4;color:#8a8a8a;cursor:not-allowed}.zhiji-seckill-card.is-off .zhiji-seckill-left{background:linear-gradient(135deg,#cfcfcf,#b8b8b8)}.zhiji-seckill-card.is-off .zhiji-seckill-right{border-left-color:#ddd}@media(max-width:520px){.zhiji-seckill{grid-template-columns:1fr}.zhiji-seckill-left{flex-basis:104px}.zhiji-seckill-left b{font-size:24px}.zhiji-seckill-right{flex-wrap:wrap}}');
}
add_action('wp_enqueue_scripts', 'zhiji_seckill_enqueue', 21);
