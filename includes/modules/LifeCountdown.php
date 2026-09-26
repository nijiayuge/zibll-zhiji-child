<?php
/**
 * @module  LifeCountdown
 * @desc    人生倒计时：今日/本周/本月/今年四维进度 + 站点运行天数
 * @option  countdown_enabled  总开关
 *          countdown_birth    网站生日（留空取第一篇文章日期）
 * @shortcode [zhiji_countdown]
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/LifeCountdown.php`
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('life_countdown', array(
    'title'    => '人生倒计时',
    'parent'   => 'zhiji_element',
    'priority' => 100,
    'option'   => 'countdown_enabled',
));

/* ============================================================
 * 短代码
 * ============================================================ */

/**
 * 站点生日：默认取第一篇已发布文章日期。
 *
 * @return string Y-m-d
 */
function zhiji_countdown_default_birth()
{
    static $birth = null;
    if (null !== $birth) {
        return $birth;
    }
    $birth = date_i18n('Y-m-d', current_time('timestamp')); // 兜底：今天
    $posts = get_posts(array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'ASC',
        'fields'         => 'ids',
    ));
    if ($posts) {
        $birth = get_the_date('Y-m-d', $posts[0]);
    }
    return $birth;
}

/**
 * 输出脚本与样式（同一页多次调用只输出一次）
 *
 * @return string
 */
function zhiji_countdown_script()
{
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;

    return '<script id="zhiji-countdown-js">'
        . '(function(){
function pct(el,val){
var v=Math.min(100,Math.max(0,val));
el.querySelector(".zhiji-countdown-bar").style.width=v.toFixed(1)+"%";
el.querySelector(".zhiji-countdown-pct").textContent=v.toFixed(1)+"%";
};
function run(){
var box=document.querySelector(".zhiji-countdown[data-render=\"1\"]");
if(!box) return;
var now=new Date();
var hours=now.getHours(), mins=now.getMinutes(), secs=now.getSeconds();
var dayMs=86400000;
var startDay=new Date(now.getFullYear(),now.getMonth(),now.getDate());
var hourPct=(hours*3600+mins*60+secs)/86400*100;
var weekDay=(now.getDay()+6)%7;
var weekPct=(weekDay*24*3600+hours*3600+mins*60+secs)/(7*86400)*100;
var daysInMonth=new Date(now.getFullYear(),now.getMonth()+1,0).getDate();
var monthPct=((now.getDate()-1)*86400+hours*3600+mins*60+secs)/(daysInMonth*86400)*100;
var startYear=new Date(now.getFullYear(),0,1);
var daysInYear=Math.round((new Date(now.getFullYear()+1,0,1)-startYear)/dayMs);
var yearPct=((now-startYear)/dayMs+hours/24)/daysInYear*100;
var rows=box.querySelectorAll(".zhiji-countdown-row");
pct(rows[0],hourPct); pct(rows[1],weekPct); pct(rows[2],monthPct); pct(rows[3],yearPct);
var birth=box.getAttribute("data-birth");
if(birth){
var b=new Date(birth);
var days=Math.floor((startDay-b)/dayMs);
if(days>=0){ var d=box.querySelector(".zhiji-countdown-days b"); if(d) d.textContent=days; }
}
}
run();
setInterval(run,60000);
})();'
        . '</script>'
        . '<style id="zhiji-countdown-css">'
        . '.zhiji-countdown{max-width:420px;margin:14px auto;padding:18px;border:1px solid var(--main-border-color,#e5e7eb);border-radius:12px;background:var(--main-bg-color,#fff)}'
        . '.zhiji-countdown-title{font-size:14px;font-weight:700;margin-bottom:12px;color:var(--focus-color,#3b82f6)}'
        . '.zhiji-countdown-row{display:flex;align-items:center;gap:10px;margin-bottom:10px}'
        . '.zhiji-countdown-label{width:64px;font-size:12px;color:var(--muted-color,#8a919f);flex-shrink:0}'
        . '.zhiji-countdown-track{flex:1;height:8px;border-radius:4px;background:rgba(127,127,127,.12);overflow:hidden}'
        . '.zhiji-countdown-bar{display:block;height:100%;border-radius:4px;background:linear-gradient(90deg,#3b82f6,#60a5fa);transition:width 1s ease}'
        . '.zhiji-countdown-pct{width:48px;text-align:right;font-size:12px;font-weight:600;flex-shrink:0}'
        . '.zhiji-countdown-days{margin-top:12px;padding-top:10px;border-top:1px dashed var(--main-border-color,#e5e7eb);'
        . 'font-size:13px;color:var(--muted-color,#8a919f);text-align:center}'
        . '.zhiji-countdown-days b{font-size:16px;color:var(--focus-color,#3b82f6)}'
        . '</style>';
}

/**
 * [zhiji_countdown] 四维进度
 *
 * @return string
 */
function zhiji_countdown_shortcode()
{
    if (!zhiji_is_enabled('countdown_enabled')) {
        return '';
    }

    $birth = trim((string) zhiji_get_option('countdown_birth', ''));
    if ('' === $birth) {
        $birth = zhiji_countdown_default_birth();
    }

    $html = '<div class="zhiji-countdown" data-birth="' . esc_attr($birth) . '" data-render="1">';
    $html .= '<div class="zhiji-countdown-title">今日进度</div>';
    foreach (array(
        array('k' => 'hour',  't' => '今日已过'),
        array('k' => 'week',  't' => '本周已过'),
        array('k' => 'month', 't' => '本月已过'),
        array('k' => 'year',  't' => '今年已过'),
    ) as $row) {
        $html .= '<div class="zhiji-countdown-row" data-k="' . esc_attr($row['k']) . '">'
            . '<span class="zhiji-countdown-label">' . esc_html($row['t']) . '</span>'
            . '<div class="zhiji-countdown-track"><i class="zhiji-countdown-bar"></i></div>'
            . '<span class="zhiji-countdown-pct">0%</span>'
            . '</div>';
    }
    $html .= '<div class="zhiji-countdown-days">本站已运行 <b>0</b> 天</div>';
    $html .= '</div>';

    // 脚本/样式并入返回值（短代码必须 return，不能 echo —— v1 在此有 echo 的老毛病）
    $html .= zhiji_countdown_script();

    return $html;
}
add_shortcode('zhiji_countdown', 'zhiji_countdown_shortcode');

/* ============================================================
 * 后台字段
 * ============================================================ */
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('life_countdown', array(
        array(
            'id'      => 'countdown_enabled',
            'type'    => 'switcher',
            'title'   => '启用人生倒计时',
            'desc'    => '通过短代码 [zhiji_countdown] 在页面/侧栏展示今日四维进度与运行天数。',
            'default' => false,
        ),
        array(
            'id'         => 'countdown_birth',
            'type'       => 'text',
            'title'      => '网站生日',
            'default'    => '',
            'desc'       => '格式 2026-01-01；留空自动取第一篇已发布文章日期',
            'dependency' => array('countdown_enabled', '==', '1'),
        ),
    ), 20);
