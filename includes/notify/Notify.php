<?php
/**
 * @module  Notify
 * @desc    统一通知中心 —— 全站唯一通知入口 zhiji_notify()
 *          四条保证：① 幂等(dedupe_key) ② 不阻断(渠道异常全部捕获) ③ 频控 ④ 可观测(环形日志)
 *          渠道：msg(站内) / mail(邮件) / toast(前端提示) / badge(角标刷新) / wechat(微信模板·可选)
 * @since   2.0.0
 */

defined('ABSPATH') || exit;

if (!defined('ZHIJI_NOTIFY_LOG_KEY')) {
    define('ZHIJI_NOTIFY_LOG_KEY', 'zhiji_notify_log');
}
if (!defined('ZHIJI_NOTIFY_SEEN_META')) {
    define('ZHIJI_NOTIFY_SEEN_META', '_zhiji_notify_seen');
}

/**
 * ★ 统一通知入口（全站唯一，业务模块不得直接调用 ZibMsg / wp_mail）
 *
 * @param string $event 事件 key（须在 Notify/Events.php 注册；未注册会走兜底并记日志）
 * @param array  $args  user_id(int|int[]) | title | content | data[] | link
 *                      | channels[] | dedupe_key | silent
 * @return array        ['event','sent'=>[uid=>[channel=>bool]],'skipped'=>[],'errors'=>[]]
 */
function zhiji_notify($event, array $args = array())
{
    $event = sanitize_key($event);
    $args  = wp_parse_args($args, array(
        'user_id'    => 0,
        'title'      => '',
        'content'    => '',
        'data'       => array(),
        'link'       => '',
        'channels'   => array(),
        'dedupe_key' => '',
        'silent'     => false,
    ));

    $cfg    = zhiji_notify_get_event($event);
    $result = array('event' => $event, 'sent' => array(), 'skipped' => array(), 'errors' => array());

    // ---- 接收人归一化 ----
    $users = is_array($args['user_id']) ? $args['user_id'] : array($args['user_id']);
    $users = array_values(array_unique(array_filter(array_map('intval', $users))));
    if (!$users) {
        $result['errors'][] = 'missing_user';
        zhiji_log('通知缺少接收人', array('event' => $event));
        return $result;
    }

    // ---- 渠道归一化（可用过滤器动态增减）----
    $channels = $args['channels'] ? (array) $args['channels'] : (array) $cfg['channels'];
    $channels = array_values(array_filter(array_map('sanitize_key', $channels)));
    $channels = apply_filters('zhiji_notify_channels', $channels, $event, $args, $cfg);
    if (!$channels) {
        $result['skipped'][] = 'no_channel';
        return $result;
    }

    // ---- 文案与链接兜底 ----
    if ('' === $args['title']) {
        $args['title'] = $cfg['title'] ? $cfg['title'] : $cfg['label'];
    }
    if ('' === $args['link'] && !empty($cfg['link'])) {
        $args['link'] = zhiji_notify_parse_link($cfg['link'], $args);
    }

    $ttl = (int) $cfg['dedupe_ttl'];
    $th  = (array) $cfg['throttle'];

    foreach ($users as $uid) {
        // ① 幂等：同一 dedupe_key 在 TTL 内只发一次
        if ($args['dedupe_key'] && zhiji_notify_seen($uid, $args['dedupe_key'], $ttl)) {
            $result['skipped'][] = 'dedupe:' . $uid;
            continue;
        }

        // ② 频控：单用户单事件超过限额则跳过（并记录，便于观测）
        if (!empty($th[0]) && zhiji_notify_throttled($uid, $event, (int) $th[0], (int) zhiji_arr_get($th, 1, HOUR_IN_SECONDS))) {
            $result['skipped'][] = 'throttle:' . $uid;
            zhiji_notify_log($event, $uid, 'throttle', '超出频控限额，已跳过');
            continue;
        }

        // ③ 逐渠道发送（每个渠道独立，失败不影响其它渠道与主流程）
        $sent = array();
        foreach ($channels as $ch) {
            $ok          = zhiji_notify_dispatch($ch, $uid, $event, $args, $cfg);
            $sent[$ch]   = $ok;
            if (!$ok) {
                $result['errors'][] = $ch . ':' . $uid;
            }
        }
        $result['sent'][$uid] = $sent;

        // ④ 记账：幂等标记 + 频控计数 + 可观测日志
        if ($args['dedupe_key']) {
            zhiji_notify_mark_seen($uid, $args['dedupe_key'], $ttl);
        }
        zhiji_notify_bump_throttle($uid, $event, (int) zhiji_arr_get($th, 1, HOUR_IN_SECONDS));
        zhiji_notify_log($event, $uid, 'sent', implode(',', array_keys(array_filter($sent))));

        do_action('zhiji_notify_sent', $event, $uid, $args, $sent);
    }

    return $result;
}

/**
 * 渠道分发（含异常兜底：任何渠道抛错都只记日志，绝不断主流程）
 *
 * @param string $channel
 * @param int    $uid
 * @param string $event
 * @param array  $args
 * @param array  $cfg
 * @return bool
 */
function zhiji_notify_dispatch($channel, $uid, $event, array $args, array $cfg)
{
    $map = array(
        'msg'    => 'Zhiji_Notify_Msg',
        'mail'   => 'Zhiji_Notify_Mail',
        'toast'  => 'Zhiji_Notify_Toast',
        'badge'  => 'Zhiji_Notify_Badge',
        'wechat' => 'Zhiji_Notify_Wechat',
    );

    try {
        if (isset($map[$channel]) && class_exists($map[$channel])) {
            return (bool) call_user_func(array($map[$channel], 'send'), $uid, $event, $args, $cfg);
        }
        // 可扩展自定义渠道：add_filter('zhiji_notify_channel_sms', fn($h,$uid,$e,$a,$c) => ...)
        return (bool) apply_filters('zhiji_notify_channel_' . $channel, false, $uid, $event, $args, $cfg);
    } catch (\Throwable $e) {
        zhiji_log('通知渠道异常（已拦截，不影响主流程）', array(
            'channel' => $channel, 'user' => $uid, 'event' => $event, 'error' => $e->getMessage(),
        ));
        return false;
    }
}

/**
 * 链接模板解析（{user_center} → 父主题用户中心·消息页；缺失时回落为消息中心或首页）
 *
 * @param string $tpl
 * @param array  $args
 * @return string
 */
function zhiji_notify_parse_link($tpl, array $args)
{
    $link = (string) $tpl;
    if (false !== strpos($link, '{user_center}')) {
        $link = str_replace('{user_center}', Zhiji_Adapter::user_center_url('msg'), $link);
    }
    return $link;
}

/* ============================================================
 * 幂等
 * ============================================================ */

function zhiji_notify_seen($uid, $key, $ttl)
{
    $seen = get_user_meta($uid, ZHIJI_NOTIFY_SEEN_META, true);
    if (!is_array($seen)) {
        return false;
    }
    $k = md5((string) $key);
    return isset($seen[$k]) && (time() - (int) $seen[$k]) < max(1, (int) $ttl);
}

function zhiji_notify_mark_seen($uid, $key, $ttl)
{
    $seen = get_user_meta($uid, ZHIJI_NOTIFY_SEEN_META, true);
    if (!is_array($seen)) {
        $seen = array();
    }
    $now = time();
    foreach ($seen as $k => $t) {
        if ($now - (int) $t > max(1, (int) $ttl)) {
            unset($seen[$k]);
        }
    }
    $seen[md5((string) $key)] = $now;
    if (count($seen) > 100) {
        $seen = array_slice($seen, -100, null, true);
    }
    update_user_meta($uid, ZHIJI_NOTIFY_SEEN_META, $seen);
}

/* ============================================================
 * 频控（固定窗口，transient 自动过期）
 * ============================================================ */

function zhiji_notify_throttled($uid, $event, $limit, $window)
{
    $n = (int) get_transient('zhiji_thr_' . $uid . '_' . $event);
    return $n >= $limit;
}

function zhiji_notify_bump_throttle($uid, $event, $window)
{
    $key = 'zhiji_thr_' . $uid . '_' . $event;
    $n   = (int) get_transient($key);
    set_transient($key, $n + 1, max(60, (int) $window));
}

/* ============================================================
 * 可观测：环形日志（最近 100 条，autoload=false 不拖慢整站）
 * ============================================================ */

function zhiji_notify_log($event, $uid, $status, $detail = '')
{
    $log = get_option(ZHIJI_NOTIFY_LOG_KEY, array());
    if (!is_array($log)) {
        $log = array();
    }
    array_unshift($log, array(
        'time'   => current_time('mysql'),
        'event'  => $event,
        'user'   => (int) $uid,
        'status' => $status,
        'detail' => (string) $detail,
    ));
    update_option(ZHIJI_NOTIFY_LOG_KEY, array_slice($log, 0, 100), false);
}

/**
 * 读取通知日志（后台自检 / 排障用）
 *
 * @param int $limit
 * @return array
 */
function zhiji_notify_logs($limit = 50)
{
    $log = get_option(ZHIJI_NOTIFY_LOG_KEY, array());
    return is_array($log) ? array_slice($log, 0, max(1, (int) $limit)) : array();
}
