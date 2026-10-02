<?php
/**
 * @module  EasterEgg
 * @desc    站点彩蛋：全站任意页面按「上上下下左右左右BA」秘技（Konami Code）触发，
 * @option  easter_egg_enabled  总开关
 * @hook    zhiji_api zhiji_easter_egg · 彩蛋上报（幂等：每人仅首次授予）
 * @since   2.0.0（2026-09-29 新增）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('easter_egg', array(
    'title'    => '站点彩蛋',
    'parent'   => 'zhiji_interact',
    'priority' => 170,
    'option'   => 'easter_egg_enabled',
));

/* ============================================================
 * 后台 CSF 设置：互动玩法 → 站点彩蛋
 * ============================================================ */
Zhiji_Registry::register_options('easter_egg', array(
    array(
        'type'    => 'submessage',
        'style'   => 'info',
        'content' => __('全站任意页面键盘输入 <code>↑ ↑ ↓ ↓ ← → ← → B A</code>（经典秘技）触发隐藏成就「彩蛋猎人」。'
            . '触发后自动授予勋章。仅桌面端（键盘事件）才触发。'),
    ),
    array(
        'id'      => 'easter_egg_enabled',
        'type'    => 'switcher',
        'title'   => '启用站点彩蛋',
        'default' => false,
    ),
), 170);

/* ============================================================
 * 彩蛋上报 API（幂等）
 * ============================================================ */

/**
 * 彩蛋上报处理
 *
 * @return void
 */
function zhiji_egg_ajax_report()
{
    $uid = get_current_user_id();
    if (!$uid) {
        wp_send_json_error(array('msg' => '先登录再来找彩蛋哦～'), 200);
    }
    if (function_exists('zhiji_ops_kill_active') && zhiji_ops_kill_active()) {
        wp_send_json_error(array('msg' => '彩蛋暂时休眠，稍后再试'), 200);
    }

    // 幂等：zhiji_medal_award_once 内部有 meta 旗标防重
    $ok = false;
    if (function_exists('zhiji_medal_award_once')) {
        $ok = zhiji_medal_award_once($uid, '彩蛋猎人', 'zhiji_medal_egg', '发现了站点里的小秘密', 'medal_egg');
    }


    if ($ok) {
        wp_send_json_success(array('msg' => '🥚 恭喜解锁隐藏成就「彩蛋猎人」！'));
    }
    wp_send_json_success(array('msg' => '你已经找到过这个彩蛋啦～'));
}
zhiji_api_register('zhiji_easter_egg', 'zhiji_egg_ajax_report', false, '');

/* ============================================================
 * 前端：Konami Code 监听（短脚本，head 内联）
 * ============================================================ */

/**
 * 前端监听脚本（启用即全站注册）
 *
 * @return void
 */
function zhiji_egg_enqueue()
{
    if (is_admin() || !function_exists('zhiji_is_enabled')) {
        return;
    }
    if (!zhiji_is_enabled('easter_egg_enabled', false)) {
        return;
    }
    zhiji_asset_add_js('egg_cfg', 'window.ZHIJI_EGG_AJAX=' . wp_json_encode(admin_url('admin-ajax.php')) . ';');
    $js = "(function(){var S=['ArrowUp','ArrowUp','ArrowDown','ArrowDown','ArrowLeft','ArrowRight','ArrowLeft','ArrowRight','b','a'];var i=0;document.addEventListener('keydown',function(e){var k=e.key||e.keyCode;i=(e.key===S[i]||(e.key&&e.key.toLowerCase()===S[i]))?i+1:(e.key===S[0]?1:0);if(i===S.length){i=0;var d=new FormData();d.append('action','zhiji_api');d.append('api','zhiji_easter_egg');fetch(window.ZHIJI_EGG_AJAX||'/wp-admin/admin-ajax.php',{method:'POST',credentials:'same-origin',body:d}).then(function(r){return r.json()}).then(function(j){var m=(j.data&&j.data.msg)||'';if(m){alert(m)}}).catch(function(){})}})})();";
    zhiji_asset_add_js('easter_egg', $js);
}
add_action('wp_enqueue_scripts', 'zhiji_egg_enqueue', 20);
