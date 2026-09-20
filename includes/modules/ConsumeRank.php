<?php
/**
 * @module  ConsumeRank
 * @desc    消费排行榜：聚合父主题成功订单按用户消费总额排名，脱敏昵称展示
 * @option  consume_rank_enabled  总开关
 *          consume_rank_top      展示条数
 *          consume_rank_points   是否包含积分兑换单
 * @shortcode [zhiji_consume_rank]
 * @hook    wp_ajax_zhiji_consume_rank / wp_ajax_nopriv_zhiji_consume_rank
 *          theme_page_templates / template_include（页面模板，文件缺失自动回落）
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/ConsumeRank.php`
 *          （依赖父主题数据表 zibpay_order，表不存在时静默返回空）
 */

defined('ABSPATH') || exit;

define('ZHIJI_CONSUME_RANK_TEMPLATE', 'zhiji-consume-rank.php');

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('consume_rank', array(
    'title'    => '消费排行榜',
    'parent'   => 'zhiji_user',
    'priority' => 70,
    'option'   => 'consume_rank_enabled',
));

/* ============================================================
 * 数据聚合
 * ============================================================ */

/**
 * 聚合消费总额（缓存 5 分钟）
 *
 * @param int  $top            条数
 * @param bool $include_points 是否包含积分兑换单
 * @return array
 */
function zhiji_consume_rank_data($top = 20, $include_points = false)
{
    global $wpdb;
    $top   = max(1, min(100, (int) $top));
    $c_key = 'zhiji_consume_rank_' . ($include_points ? 'p' : 'm') . '_' . $top;
    $cached = get_transient($c_key);
    if (is_array($cached)) {
        return $cached;
    }

    $table = !empty($wpdb->zibpay_order) ? $wpdb->zibpay_order : $wpdb->prefix . 'zibpay_order';
    // 表不存在则降级返回空（不抛错）
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return array();
    }

    // 仅统计成功订单（status=1）；默认不含积分兑换单（pay_type='points'）
    $sql = "SELECT user_id, SUM(order_price) AS total FROM {$table} WHERE status = 1";
    if (!$include_points) {
        $sql .= " AND pay_type != 'points'";
    }
    $sql .= ' GROUP BY user_id ORDER BY total DESC LIMIT %d';
    $rows = $wpdb->get_results($wpdb->prepare($sql, $top), ARRAY_A);
    if (empty($rows)) {
        return array();
    }

    $out = array();
    foreach ($rows as $r) {
        $uid  = (int) $r['user_id'];
        $u    = get_userdata($uid);
        $name = $u ? $u->display_name : '用户' . $uid;
        $out[] = array(
            'uid'    => $uid,
            'name'   => zhiji_consume_rank_mask_name($name),
            'total'  => (float) $r['total'],
            'avatar' => get_avatar_url($uid, array('size' => 64)),
        );
    }
    set_transient($c_key, $out, 5 * MINUTE_IN_SECONDS);
    return $out;
}

/**
 * 昵称脱敏：首字 + 星号 + 尾字
 *
 * @param string $name
 * @return string
 */
function zhiji_consume_rank_mask_name($name)
{
    $name = trim((string) $name);
    $len  = mb_strlen($name, 'UTF-8');
    if ($len <= 1) {
        return $name;
    }
    if (2 === $len) {
        return mb_substr($name, 0, 1, 'UTF-8') . '*';
    }
    return mb_substr($name, 0, 1, 'UTF-8')
        . str_repeat('*', $len - 2)
        . mb_substr($name, -1, 1, 'UTF-8');
}

/* ============================================================
 * AJAX
 * ============================================================ */
add_action('wp_ajax_nopriv_zhiji_consume_rank', 'zhiji_consume_rank_fetch');
add_action('wp_ajax_zhiji_consume_rank', 'zhiji_consume_rank_fetch');

/**
 * 前台拉取榜单数据
 *
 * @return void
 */
function zhiji_consume_rank_fetch()
{
    check_ajax_referer('zhiji_consume_rank', 'nonce');
    if (!zhiji_is_enabled('consume_rank_enabled')) {
        wp_send_json_success(array());
    }
    $top            = (int) zhiji_get_option('consume_rank_top', 20);
    $include_points = zhiji_is_enabled('consume_rank_points');
    try {
        $data = zhiji_consume_rank_data($top, $include_points);
    } catch (\Throwable $e) {
        $data = array();
    }
    wp_send_json_success($data);
}

/* ============================================================
 * 页面模板 + 短代码
 * ============================================================ */
add_filter('theme_page_templates', function ($templates) {
    $templates[ZHIJI_CONSUME_RANK_TEMPLATE] = '消费排行榜（知集）';
    return $templates;
});

add_filter('template_include', function ($template) {
    if (!zhiji_is_enabled('consume_rank_enabled')) {
        return $template;
    }
    if (is_page() && get_page_template_slug() === ZHIJI_CONSUME_RANK_TEMPLATE) {
        $file = ZHIJI_PATH . 'templates/' . ZHIJI_CONSUME_RANK_TEMPLATE;
        if (file_exists($file)) {
            return $file;
        }
    }
    return $template;
});

add_shortcode('zhiji_consume_rank', function () {
    if (!zhiji_is_enabled('consume_rank_enabled')) {
        return '';
    }
    ob_start();
    ?>
    <div class="zhiji-consume-rank" id="zhijiConsumeRank">
        <div class="zhiji-cr-header">
            <span class="zhiji-cr-title">&#127942; 消费排行榜</span>
            <span class="zhiji-cr-sub">按成功订单聚合消费总额，脱敏展示</span>
        </div>
        <div class="zhiji-cr-list" id="zhijiCrList">
            <div class="zhiji-cr-loading">加载中…</div>
        </div>
    </div>
    <style>
    .zhiji-consume-rank{max-width:640px;margin:20px auto}
    .zhiji-cr-header{display:flex;align-items:baseline;gap:10px;margin-bottom:16px}
    .zhiji-cr-title{font-size:18px;font-weight:700;color:var(--main-color,#333)}
    .zhiji-cr-sub{font-size:12px;color:var(--muted-color,#999)}
    .zhiji-cr-list{border:1px solid var(--main-border-color,#eef0f5);border-radius:14px;overflow:hidden;background:var(--main-bg-color,#fff)}
    .zhiji-cr-loading,.zhiji-cr-empty{padding:40px;text-align:center;color:var(--muted-color,#999);font-size:13px}
    .zhiji-cr-row{display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid rgba(127,127,127,.08)}
    .zhiji-cr-row:last-child{border-bottom:none}
    .zhiji-cr-rank{width:34px;text-align:center;font-weight:700;font-size:15px;color:#b9bdc7;flex:0 0 auto}
    .zhiji-cr-rank.top1{color:#ffb800;font-size:20px}
    .zhiji-cr-rank.top2{color:#9aa4b2;font-size:18px}
    .zhiji-cr-rank.top3{color:#c98a4b;font-size:17px}
    .zhiji-cr-avatar{width:36px;height:36px;border-radius:50%;flex:0 0 auto;object-fit:cover}
    .zhiji-cr-name{flex:1;font-size:14px;color:var(--main-color,#333);font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .zhiji-cr-total{font-size:14px;color:#e24b4a;font-weight:600;flex:0 0 auto}
    </style>
    <script>
    (function(){
    var boot=function(){
    jQuery(function($){
    var box=$('#zhijiCrList'); if(!box.length) return;
    $.ajax({
    url: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
    method:'POST',
    data:{action:'zhiji_consume_rank', nonce:<?php echo wp_json_encode(wp_create_nonce('zhiji_consume_rank')); ?>},
    dataType:'json',
    success:function(res){
    var data=(res && res.success && res.data) ? res.data : [];
    if(!data.length){ box.html('<div class="zhiji-cr-empty">暂无消费数据</div>'); return; }
    var html='';
    $.each(data,function(i,it){
    var rank=i+1, rk='zhiji-cr-rank'+(rank<=3?' top'+rank:'');
    html+='<div class="zhiji-cr-row"><span class="'+rk+'">'+(rank<=3?'\uD83C\uDFC6\uD83E\uDD48\uD83E\uDD49'.charAt(rank-1):rank)+'</span><img class="zhiji-cr-avatar" src="'+(it.avatar||'')+'" alt=""><span class="zhiji-cr-name">'+it.name+'</span><span class="zhiji-cr-total">\u00a5'+Number(it.total).toFixed(2)+'</span></div>';
    });
    box.html(html);
    },
    error:function(){ box.html('<div class="zhiji-cr-empty">加载失败，请稍后重试</div>'); }
    });
    });};
    if(typeof window.jQuery==='undefined'){setTimeout(boot,80);}else{boot();}
    })();
    </script>
    <?php
    return ob_get_clean();
});

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('consume_rank', array(
        array(
            'id'      => 'consume_rank_enabled',
            'type'    => 'switcher',
            'title'   => '启用消费排行榜',
            'default' => false,
            'desc'    => '聚合成功订单按用户消费总额排名，独立页面展示脱敏昵称+金额。',
        ),
        array(
            'id'         => 'consume_rank_top',
            'type'       => 'text',
            'title'      => '展示条数',
            'default'    => '20',
            'desc'       => '排行榜最多展示的用户数量。',
            'dependency' => array('consume_rank_enabled', '==', '1'),
        ),
        array(
            'id'         => 'consume_rank_points',
            'type'       => 'switcher',
            'title'      => '包含积分兑换单',
            'default'    => false,
            'desc'       => '默认排除积分兑换订单，开启后计入消费总额。',
            'dependency' => array('consume_rank_enabled', '==', '1'),
        ),
    ));
}, 20);
