<?php
/**
 * @module  MemberGuide
 * @desc    会员引导（迎新序列引擎）：注册触发迎新券（可选）+ 按天触达序列（每用户独立计划任务，
 *          经 zhiji_notify 五渠道送达）。行业口径：迎新必须在注册当下自动触发，
 *          Day0/7/30 多时点把新会员在热度最高时接住（见四功能模块调研 §三）。
 * @option  member_guide_enabled           总开关
 *          member_guide_welcome_coupon    注册即发迎新优惠码
 *          member_guide_coupon_scope      迎新券档位
 *          member_guide_seq               触达序列（每行：天数|标题|内容）
 * @hook    user_register                  触发迎新 + 排程序列
 *          zhiji_member_guide_tick        序列触达（每用户每行一个计划任务）
 *          deleted_user                   清理该用户全部待发计划
 * @since   2.0.0（2026-09-29 新增）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('member_guide', array(
    'title'    => '会员引导',
    'parent'   => 'zhiji_user',
    'priority' => 140,
    'option'   => 'member_guide_enabled',
));

/* ============================================================
 * 通知事件注册（notify 体系要求先登记）
 * ============================================================ */
zhiji_notify_register_event('member_guide_seq', array(
    'label'       => __('会员引导', 'zhiji'),
    'title'       => __('会员动态', 'zhiji'),
    'channels'    => array('msg', 'badge'),
    'toast'       => 'info',
    'dedupe_ttl'  => 90 * DAY_IN_SECONDS,
    'throttle'    => array(0, HOUR_IN_SECONDS), // 序列触达不限流（本身低频且幂等）
));

/* ============================================================
 * 后台配置
 * ============================================================ */
Zhiji_Registry::register_options('member_guide', array(
    array(
        'id'      => 'member_guide_enabled',
        'type'    => 'switcher',
        'title'   => __( '启用会员引导', 'zhiji' ),
        'label'   => __( '开启后按后台配置的序列向新用户推送引导；可发放注册迎新券。', 'zhiji' ),
        'default' => true,
    ),
    array(
        'type'    => 'submessage',
        'style'   => 'info',
        'content' => __('触达序列每行一条：<code>天数|标题|内容</code>。注册当天自动触发迎新（天数 0），'
            . '其余按「注册日 + 天数」经站内消息 + 角标送达；每用户每条只发一次（幂等）。', 'zhiji'),
    ),
    array(
        'type'  => 'subheading',
        'title' => __( '① 迎新优惠码', 'zhiji' ),
    ),
    array(
        'id'         => 'member_guide_welcome_coupon',
        'type'       => 'switcher',
        'title'      => '注册即发迎新优惠码',
        'default'    => false,
        'desc'       => __('注册成功立刻发放一张迎新优惠码（「立刻可核销」是新会员激活的关键）。', 'zhiji'),
    ),
    array(
        'id'         => 'member_guide_coupon_scope',
        'type'       => 'select',
        'title'      => '迎新券档位',
        'options'    => array(
            'login'  => __('登录档（默认）', 'zhiji'),
            'active' => __('活跃档', 'zhiji'),
            'vip'    => __('VIP 档', 'zhiji'),
        ),
        'default'    => 'login',
    ),
    array(
        'type'  => 'subheading',
        'title' => __( '② 触达序列', 'zhiji' ),
    ),
    array(
        'id'         => 'member_guide_seq',
        'type'       => 'textarea',
        'title'      => '触达序列',
        'rows'       => 6,
        'sanitize'   => false,
        'placeholder' => "0|欢迎加入知集|这里是权益总览与新手指南…\n7|你已加入 7 天|试试用积分在商城兑换奖品…\n30|老朋友，好久不见|回顾一下你的专属权益…",
        'desc'       => __('每行一条，竖线分隔；天数 0 = 注册当天立即发送。', 'zhiji'),
    ),
), 150);

/* ============================================================
 * 数据层
 * ============================================================ */

/**
 * 解析触达序列（容错；按天数升序）
 *
 * @return array array( array('day'=>int,'title'=>,'content'=>), … )
 */
function zhiji_mg_seq()
{
    $raw = (string) zhiji_get_option('member_guide_seq', '');
    $out = array();
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        $line = trim($line);
        if ('' === $line) {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) < 3 || !preg_match('/^\d+$/', $parts[0]) || '' === $parts[1]) {
            continue; // 天数/标题非法 → 跳过
        }
        $out[] = array(
            'day'     => (int) $parts[0],
            'title'   => $parts[1],
            'content' => $parts[2],
        );
    }
    usort($out, function ($a, $b) {
        return $a['day'] - $b['day'];
    });
    return $out;
}

/**
 * 该用户全部待发的序列计划（user_meta 存档，供注销清理）
 *
 * @param int $uid
 * @return array array( array('idx'=>int,'ts'=>int), … )
 */
function zhiji_mg_scheduled($uid)
{
    $meta = get_user_meta($uid, 'zhiji_mg_scheduled', true);
    return is_array($meta) ? $meta : array();
}

/* ============================================================
 * 触发与排程
 * ============================================================ */

/**
 * 注册触发：迎新券（可选）+ 排程触达序列
 *
 * @param int $uid
 * @return void
 */
function zhiji_mg_on_register($uid)
{
    if (!zhiji_is_enabled('member_guide_enabled', true)) {
        return;
    }
    $uid = (int) $uid;
    if (!$uid) {
        return;
    }

    // 迎新券（可选；发放失败静默 —— 不阻断注册）
    if (zhiji_get_option('member_guide_welcome_coupon', false)) {
        zhiji_reward_center_grant_one($uid, 'coupon', 'member_guide', array(
            'coupon_scope' => zhiji_get_option('member_guide_coupon_scope', 'login'),
        ));
    }

    // 排程触达序列（每用户每行一个计划任务；幂等：已排过的不重复排）
    $rows  = zhiji_mg_seq();
    $saved = zhiji_mg_scheduled($uid);
    $have  = array();
    foreach ($saved as $s) {
        $have[$s['idx']] = true;
    }
    $base = current_time('timestamp');
    foreach ($rows as $idx => $row) {
        if (isset($have[$idx])) {
            continue;
        }
        $ts = $base + $row['day'] * DAY_IN_SECONDS;
        wp_schedule_single_event($ts, 'zhiji_member_guide_tick', array($uid, $idx));
        $saved[] = array('idx' => $idx, 'ts' => $ts);
    }
    update_user_meta($uid, 'zhiji_mg_scheduled', $saved);
}
add_action('user_register', 'zhiji_mg_on_register');

/**
 * 序列触达（cron 处理器；幂等：同用户同行重复触发不重复发）
 *
 * @param int $uid
 * @param int $idx 序列行号
 * @return void
 */
function zhiji_member_guide_tick($uid, $idx)
{
    if (!zhiji_is_enabled('member_guide_enabled', true)) {
        return;
    }
    $uid = (int) $uid;
    $idx = (int) $idx;
    if (!get_userdata($uid)) {
        return; // 用户已注销
    }
    $rows = zhiji_mg_seq();
    if (!isset($rows[$idx])) {
        return;
    }
    $row = $rows[$idx];

    zhiji_notify('member_guide_seq', array(
        'user_id'    => $uid,
        'title'      => $row['title'],
        'content'    => $row['content'],
        'dedupe_key' => 'mg_' . $uid . '_' . $idx, // 幂等键：同用户同条只发一次
    ));
}
add_action('zhiji_member_guide_tick', 'zhiji_member_guide_tick', 10, 2);

/**
 * 用户注销 → 清理该用户全部待发计划（精确到 args，不影响其他用户）
 *
 * @param int $uid
 * @return void
 */
function zhiji_mg_on_delete($uid)
{
    $uid = (int) $uid;
    foreach (zhiji_mg_scheduled($uid) as $s) {
        wp_clear_scheduled_hook('zhiji_member_guide_tick', array($uid, (int) $s['idx']));
    }
    delete_user_meta($uid, 'zhiji_mg_scheduled');
}
add_action('deleted_user', 'zhiji_mg_on_delete');
