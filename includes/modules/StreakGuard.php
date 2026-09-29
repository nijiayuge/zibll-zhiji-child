<?php
/**
 * @module  StreakGuard
 * @desc    断签保护（streak freeze）：连续签到每满 7 天自动获得 1 次「冻结」，
 *          漏签 1 天时自动消耗冻结补连，连击不断（参考 WordPress.com 2026 streak freeze）。
 *
 *          保护规则：
 *          · 每「连续签到满 7 天」自动 +1 次冻结，库存上限默认 2（可配）；
 *          · 漏签 1 天后补签：自动消耗 1 次冻结，连击继续累加；
 *          · 连漏 ≥2 天：冻结不救（只保护 1 天断档），连击重置为 1；
 *          · 保护范围 = 知集侧连击统计（本模块 short_code / 勋章「坚持之王」）；
 *            父主题「连续签到奖励」链路按父主题原逻辑执行（子主题不改父主题）。
 * @option  streak_guard_enabled  总开关
 *          streak_freeze_max     冻结库存上限
 * @hook    user_checkined（父主题签到成功钩子）· 统计更新 + 冻结发放/消耗
 * @short   [zhiji_streak] · 我的连击与冻结
 * @since   2.0.0（2026-09-29 新增）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('streak_guard', array(
    'title'    => '断签保护',
    'parent'   => 'zhiji_user',
    'priority' => 150,
    'option'   => 'streak_guard_enabled',
));

/* ============================================================
 * 后台 CSF 设置：用户中心 → 断签保护
 * ============================================================ */
Zhiji_Registry::register_options('streak_guard', array(
    array(
        'type'    => 'submessage',
        'style'   => 'info',
        'content' => __('<b>规则</b>：连续签到每满 7 天自动获得 1 次冻结；漏签 1 天补签时自动消耗冻结、连击不断；'
            . '连漏 2 天及以上不救。<b>范围</b>：保护知集侧连击统计（勋章「坚持之王」/短码 [zhiji_streak]），'
            . '父主题「连续签到奖励」按父主题原逻辑执行。', 'zhiji'),
    ),
    array(
        'id'      => 'streak_guard_enabled',
        'type'    => 'switcher',
        'title'   => '启用断签保护',
        'default' => false,
    ),
    array(
        'id'         => 'streak_freeze_max',
        'type'       => 'number',
        'title'      => '冻结库存上限',
        'default'    => 2,
        'min'        => 1,
        'max'        => 5,
        'dependency' => array('streak_guard_enabled', '==', '1'),
        'desc'       => __('同时最多持有几次冻结（WordPress.com 规则：上限 1 次）。', 'zhiji'),
    ),
), 150);

/* ============================================================
 * 核心逻辑
 * ============================================================ */

/**
 * 读取用户连击数据
 *
 * @param int $uid
 * @return array array('last'=>Y-m-d,'count'=>int,'best'=>int,'freezes'=>int)
 */
function zhiji_streak_get($uid)
{
    $m = get_user_meta($uid, 'zhiji_streak', true);
    $m = is_array($m) ? $m : array();
    return array(
        'last'    => isset($m['last']) ? (string) $m['last'] : '',
        'count'   => isset($m['count']) ? (int) $m['count'] : 0,
        'best'    => isset($m['best']) ? (int) $m['best'] : 0,
        'freezes' => isset($m['freezes']) ? (int) $m['freezes'] : 0,
    );
}

/**
 * 签到成功后更新连击统计（父主题 user_checkined 钩子）
 *
 * @param int   $user_id
 * @param array $the_data 签到详情（父主题传入）
 * @return void
 */
function zhiji_streak_on_checkin($user_id, $the_data = array())
{
    if (!function_exists('zhiji_is_enabled') || !zhiji_is_enabled('streak_guard_enabled', false)) {
        return;
    }
    $uid = (int) $user_id;
    if (!$uid) {
        return;
    }

    $s        = zhiji_streak_get($uid);
    $today    = current_time('Y-m-d');
    $ytd      = date('Y-m-d', strtotime('-1 day', strtotime($today)));
    $before   = date('Y-m-d', strtotime('-2 day', strtotime($today)));

    if ($s['last'] === $today) {
        return; // 今日已计（幂等：user_checkined 每签到仅触发一次，防外部重复调用）
    }

    $freeze_used = false;
    if ($s['last'] === $ytd) {
        $s['count']++;
    } elseif ($s['last'] === $before && $s['freezes'] > 0) {
        $s['freezes']--;
        $s['count']++;
        $freeze_used = true;
    } else {
        $s['count'] = 1; // 连漏 ≥2 天或无冻结 → 重置
    }
    $s['last'] = $today;
    $s['best'] = max($s['best'], $s['count']);

    // 每满 7 天连击 → 发放 1 次冻结
    $freeze_gain = false;
    if (0 === $s['count'] % 7) {
        $max = max(1, min(5, (int) zhiji_get_option('streak_freeze_max', 2)));
        if ($s['freezes'] < $max) {
            $s['freezes']++;
            $freeze_gain = true;
        }
    }

    update_user_meta($uid, 'zhiji_streak', $s);

    if (function_exists('zhiji_notify')) {
        if ($freeze_used) {
            zhiji_notify('streak_freeze_used', array(
                'user_id'    => $uid,
                'title'      => '🧊 断签保护生效',
                'content'    => '昨天漏签已自动消耗 1 次冻结，连击保住了！当前连击 ' . $s['count'] . ' 天，剩余冻结 ' . $s['freezes'] . ' 次。',
                'dedupe_key' => 'streak_freeze_used_' . $today,
            ));
        }
        if ($freeze_gain) {
            zhiji_notify('streak_freeze_gain', array(
                'user_id'    => $uid,
                'title'      => '🧊 获得断签保护',
                'content'    => '连续签到满 7 天，自动获得 1 次冻结（漏签 1 天可自动补救）。当前持有 ' . $s['freezes'] . ' 次。',
                'dedupe_key' => 'streak_freeze_gain_' . $today,
            ));
        }
    }

    // 隐藏成就「坚持之王」：连击满 30 天
    if (30 === $s['count'] && function_exists('zhiji_medal_award_once')) {
        zhiji_medal_award_once($uid, '坚持之王', 'zhiji_medal_king', '连续坚持整整一个月', 'medal_king');
    }
}
add_action('user_checkined', 'zhiji_streak_on_checkin', 30, 2);

/* ============================================================
 * 短码 [zhiji_streak]
 * ============================================================ */

/**
 * 短码：我的连击与冻结
 *
 * @return string
 */
function zhiji_streak_shortcode()
{
    if (!function_exists('zhiji_is_enabled') || !zhiji_is_enabled('streak_guard_enabled', false)) {
        return '';
    }
    $uid = get_current_user_id();
    if (!$uid) {
        return '<p class="description">登录后可查看签到连击与断签保护。</p>';
    }
    $s = zhiji_streak_get($uid);

    $out = '<div class="zhiji-streak zib-widget" style="padding:14px;margin:12px 0;display:flex;gap:18px;flex-wrap:wrap">';
    $out .= '<div><div style="font-size:12px;color:#999">当前连击</div><div style="font-size:22px;font-weight:700;color:#e8533f">' . (int) $s['count'] . ' 天</div></div>';
    $out .= '<div><div style="font-size:12px;color:#999">历史最佳</div><div style="font-size:22px;font-weight:700">' . (int) $s['best'] . ' 天</div></div>';
    $out .= '<div><div style="font-size:12px;color:#999">断签保护</div><div style="font-size:22px;font-weight:700;color:#3b82f6">🧊 ' . (int) $s['freezes'] . ' 次</div></div>';
    $out .= '</div>';
    return $out;
}
add_shortcode('zhiji_streak', 'zhiji_streak_shortcode');
