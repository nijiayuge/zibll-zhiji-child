<?php
/**
 * @module  Seckill
 * @desc    积分秒杀：商城商品的限时积分购买活动聚合页。
 *          实现方式（2026-09-30 修正版，方案 A·纯原生路径）：
 *          基于父主题原生「商品积分价」能力——商家在商品编辑页填写积分价格，
 *          用户在商品页的「确认订单」弹窗中用积分原生支付（pay_modo=points）。
 *          本模块只做活动层：聚合秒杀商品、活动时间窗、倒计时与入口，不碰支付、
 *          不发券码、不改订单。库存/限购由父主题商品原生校验。
 *          ⚠️ 背景：商城「确认订单」流程（shop_submit_order → zibpay::add_payment）
 *          前后端均无优惠码处理（父主题设计），此前「发免单券」方案在商城商品上
 *          用不出去，故废弃。
 * @option  seckill_enabled   总开关
 *          seckill_items     活动列表（每行：商品ID|开始时间|结束时间）
 * @short   [zhiji_seckill]（启用后自动追加到 /points-mall 页，也可放到任意页面）
 * @since   2.0.0（2026-09-29 新增，2026-09-30 重构为原生积分价路径）
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
        'id'      => 'seckill_enabled',
        'type'    => 'switcher',
        'title'   => __( '启用积分秒杀', 'zhiji' ),
        'label'   => __( '开启后秒杀专区自动追加到积分商城页；商品需在编辑页设为「积分商品」。', 'zhiji' ),
        'default' => false,
    ),
    array(
        'type'    => 'submessage',
        'style'   => 'info',
        'content' => __('<b>使用前提</b>：先在<b>商品编辑页 → 商城设置 → 价格&选项</b>中把<b>价格类型</b>改为<b>「积分商品」</b>并填写<b>起始价格</b>（即积分兑换价，父主题原生能力），'
            . '用户在商品页「确认订单」时会直接用积分下单，无需任何券码。'
            . '<b>活动玩法</b>：本模块把积分商品聚合成带倒计时的秒杀专区；'
            . '活动期由商家把商品起始价格改为秒杀价，结束后改回（或改回金钱类型即下架秒杀）。'
            . '库存与限购由父主题商品原生校验。'
            . '专区自动追加到「积分商城」页，也可把短码 <code>[zhiji_seckill]</code> 放到任意页面。', 'zhiji'),
    ),
    array(
        'id'         => 'seckill_items',
        'type'       => 'textarea',
        'title'      => '秒杀活动列表',
        'rows'       => 6,
        'sanitize'   => false,
        'placeholder' => "34|2026-10-01 10:00|2026-10-01 23:59\n35||",
        'desc'       => __('每行一条：商品ID|开始时间|结束时间（Y-m-d H:i，两端留空表示长期）。积分兑换价以商品编辑页「价格&选项」的设置为准（需选「积分商品」类型）。', 'zhiji'),
    ),
), 148);

/* ============================================================
 * 数据层
 * ============================================================ */

/**
 * 解析秒杀活动列表（容错：商品不存在/未设积分价的行标记给 admin 看，前台跳过）
 *
 * @param bool $with_unpriced 是否包含「未设置积分价」的行（admin 预览用）
 * @return array array( array('post_id'=>,'points_price'=>,'start'=>,'end'=>), … )
 */
function zhiji_seckill_items($with_unpriced = false)
{
    $raw = (string) zhiji_get_option('seckill_items', '');
    $out = array();
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        $line = trim($line);
        if ('' === $line || 0 !== strpos($line, '#')) {
        $p     = array_map('trim', explode('|', $line));
        $pid   = isset($p[0]) ? (int) $p[0] : 0;
        $post  = $pid ? get_post($pid) : null;
        if (!$post) {
            continue; // 商品不存在 → 跳过
        }
        // 积分价读父主题商品配置 meta（商品编辑页 → 价格&选项 → 价格类型=积分商品 时的起始价格）
        // 直接读 product_config meta（纯 WP API，不经父主题函数，符合 Adapter 架构红线）
        $cfg          = get_post_meta($pid, 'product_config', true);
        $is_points    = is_array($cfg) && isset($cfg['pay_modo']) && 'points' === $cfg['pay_modo'];
        $points_price = $is_points ? (int) $cfg['start_price'] : 0;
        if ($points_price < 1 && !$with_unpriced) {
            continue; // 非积分商品/未设置积分价（前台跳过；admin 预览可见以提示配置）
        }
            $out[] = array(
                'post_id'      => $pid,
                'title'        => get_the_title($pid),
                'url'          => get_permalink($pid),
                'points_price' => $points_price,
                'start'        => isset($p[1]) ? $p[1] : '',
                'end'          => isset($p[2]) ? $p[2] : '',
            );
        }
    }
    return $out;
}

/**
 * 活动时间窗判定
 *
 * @param array $item 活动行
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

/* ============================================================
 * 短码
 * ============================================================ */

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
    $items = zhiji_seckill_items(current_user_can('manage_options'));

    $out = '<div class="zhiji-seckill-wrap" id="zhiji-seckill">';
    $out .= '<div class="title-theme"><b>' . esc_html__('⚡ 积分秒杀专区', 'zhiji') . '</b></div>';
    if (!$items) {
        $out .= '<p class="description">' . esc_html__('暂无秒杀活动。', 'zhiji') . '</p></div>';
        return $out;
    }

    $out .= '<div class="zhiji-seckill">';
    foreach ($items as $it) {
        $win = zhiji_seckill_window($it);

        // 状态按钮（原生路径：跳商品页走确认订单积分支付）
        if ('waiting' === $win) {
            $btn = '<button class="button" disabled>' . esc_html__('未开始', 'zhiji') . '</button>';
        } elseif ('ended' === $win) {
            $btn = '<button class="button" disabled>' . esc_html__('已结束', 'zhiji') . '</button>';
        } elseif ($it['points_price'] < 1) {
            $btn = current_user_can('manage_options')
                ? '<a class="button" href="' . esc_url(get_edit_post_link($it['post_id'])) . '">未设积分价·去设置</a>'
                : '<button class="button" disabled>' . esc_html__('暂不可购', 'zhiji') . '</button>';
        } else {
            $btn = '<a class="button zhiji-seckill-buy" href="' . esc_url($it['url']) . '">' . esc_html__('去抢购', 'zhiji') . '</a>';
        }

        $out .= '<div class="zhiji-seckill-card' . ('ended' === $win || $it['points_price'] < 1 ? ' is-off' : '') . '">';
        $out .= '<div class="zhiji-seckill-left"><b>' . ($it['points_price'] ? esc_html($it['points_price']) : '—') . '</b><span>' . esc_html__('积分', 'zhiji') . '</span></div>';
        $out .= '<div class="zhiji-seckill-right"><div class="zhiji-seckill-info">';
        $out .= '<h4><a href="' . esc_url($it['url']) . '">' . esc_html($it['title']) . '</a></h4>';
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
    // 仅倒计时脚本（购买走父主题原生「确认订单 → 积分支付」，无需自有 AJAX）
    $js = "(function(){function pad(n){return n<10?'0'+n:''+n}function tick(){var els=document.querySelectorAll('.zhiji-seckill-cd');for(var i=0;i<els.length;i++){var el=els[i];var target=el.getAttribute('data-end')||el.getAttribute('data-start');if(!target){el.textContent='';continue}var ts=new Date(target.replace(/-/g,'/')).getTime();var diff=Math.floor((ts-Date.now())/1000);if(diff<=0){el.textContent='';continue}var d=Math.floor(diff/86400),h=Math.floor(diff%86400/3600),m=Math.floor(diff%3600/60),s=diff%60;el.textContent='（剩 '+(d>0?d+'天':'')+pad(h)+'时'+pad(m)+'分'+pad(s)+'秒）'}}function boot(){tick();setInterval(tick,1000)}if(document.readyState!=='loading'){boot()}else{document.addEventListener('DOMContentLoaded',boot)}})();";
    zhiji_asset_add_js('seckill', $js);
    // 券版式（与积分商城同视觉语言，紫色主题区分秒杀）：左面额区 + 撕票缺口 + 入口按钮
    zhiji_asset_add_css('seckill', '.zhiji-seckill-wrap{width:100%;margin-top:24px}.zhiji-seckill{display:grid;grid-template-columns:repeat(auto-fit,minmax(420px,1fr));gap:18px}.zhiji-seckill-card{position:relative;display:flex;align-items:stretch;background:#fff;border-radius:12px;filter:drop-shadow(0 4px 10px rgba(0,0,0,.15));-webkit-mask:radial-gradient(circle at 140px 0,#0000 9px,#000 9.5px) 0 0/100% 51% no-repeat,radial-gradient(circle at 140px 0,#0000 9px,#000 9.5px) 0 100%/100% 51% no-repeat;mask:radial-gradient(circle at 140px 0,#0000 9px,#000 9.5px) 0 0/100% 51% no-repeat,radial-gradient(circle at 140px 0,#0000 9px,#000 9.5px) 0 100%/100% 51% no-repeat}.zhiji-seckill-left{flex:0 0 140px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:linear-gradient(135deg,#a78bfa,#7c3aed);color:#fff;text-align:center;padding:18px 10px}.zhiji-seckill-left b{font-size:30px;line-height:1.1;font-weight:700}.zhiji-seckill-left span{font-size:13px;opacity:.92;margin-top:2px}.zhiji-seckill-right{flex:1;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 20px;border-left:1px dashed #e3d5ff;min-width:0}.zhiji-seckill-info{min-width:0}.zhiji-seckill-card h4{margin:0 0 6px;font-size:16px;font-weight:600;color:#222}.zhiji-seckill-card h4 a{color:#222;text-decoration:none}.zhiji-seckill-card h4 a:hover{color:#7c3aed}.zhiji-seckill-meta{font-size:12px;color:#999}.zhiji-seckill .button{flex-shrink:0;margin:0;padding:9px 24px;border:none;border-radius:999px;font-size:14px;line-height:1.4;color:#fff;background:linear-gradient(135deg,#a78bfa,#7c3aed);cursor:pointer;text-decoration:none;display:inline-block}.zhiji-seckill .button:hover{opacity:.9}.zhiji-seckill .button:disabled{background:#d4d4d4;color:#8a8a8a;cursor:not-allowed}.zhiji-seckill-card.is-off .zhiji-seckill-left{background:linear-gradient(135deg,#cfcfcf,#b8b8b8)}.zhiji-seckill-card.is-off .zhiji-seckill-right{border-left-color:#ddd}@media(max-width:520px){.zhiji-seckill{grid-template-columns:1fr}.zhiji-seckill-left{flex-basis:104px}.zhiji-seckill-left b{font-size:24px}.zhiji-seckill-right{flex-wrap:wrap}}');
}
add_action('wp_enqueue_scripts', 'zhiji_seckill_enqueue', 21);
