<?php
/**
 * @module  Notify_Events
 * @desc    通知事件注册表 —— 所有通知事件必须先在此登记，禁止在业务里硬编码渠道与文案
 * @since   2.0.0
 */

defined('ABSPATH') || exit;

/**
 * 全部已注册事件（唯一数据源）
 *
 * @return array
 */
function zhiji_notify_events()
{
    $events = isset($GLOBALS['zhiji_notify_events']) ? (array) $GLOBALS['zhiji_notify_events'] : array();

    /**
     * 读取时允许扩展/覆盖事件表
     *
     * @param array $events
     */
    return apply_filters('zhiji_notify_events', $events);
}

/**
 * 注册通知事件
 *
 * @param string $event 事件 key（蛇形，如 lottery_win）
 * @param array  $args  label / title / channels / toast / mail / link / dedupe_ttl / throttle
 * @return void
 */
function zhiji_notify_register_event($event, array $args = array())
{
    $event = sanitize_key($event);
    if (!$event) {
        return;
    }
    $defaults = array(
        'label'       => $event,
        'title'       => '',                                   // 缺省标题（$args['title'] 优先）
        'channels'    => array('msg'),                          // 默认渠道
        'toast'       => 'info',                                // toast 类型：success/info/warning/error
        'mail'        => 'ticket',                              // 邮件模板：ticket(票据) / plain(纯文本)
        'link'        => '',                                    // 站内链接模板，支持 {user_center} 占位
        'dedupe_ttl'  => DAY_IN_SECONDS,                        // 幂等有效期
        'throttle'    => array(20, HOUR_IN_SECONDS),             // [最多条数, 窗口秒数]；条数 0 = 不限
    );
    $GLOBALS['zhiji_notify_events'][$event] = array_merge($defaults, $args);
}

/**
 * 取单个事件配置（未注册返回兜底配置，保证通知不因未注册而丢失）
 *
 * @param string $event
 * @return array
 */
function zhiji_notify_get_event($event)
{
    $events = zhiji_notify_events();
    if (isset($events[$event])) {
        return $events[$event];
    }
    zhiji_log('通知事件未注册，使用兜底配置', array('event' => $event));
    return array(
        'label'      => $event,
        'title'      => '',
        'channels'   => array('msg'),
        'toast'      => 'info',
        'mail'       => 'plain',
        'link'       => '',
        'dedupe_ttl' => DAY_IN_SECONDS,
        'throttle'   => array(20, HOUR_IN_SECONDS),
    );
}

/* ============================================================
 * 内置通用事件（业务模块只需注册自己的事件，以下为公共事件）
 * ============================================================ */

/**
 * 在 wp_loaded 后注册，确保其它模块的 zhiji_notify_register_event() 已生效
 */
add_action('init', function () {
    // 通用系统通知：任何模块可复用
    zhiji_notify_register_event('system_notice', array(
        'label'    => '系统通知',
        'channels' => array('msg', 'badge'),
        'toast'    => 'info',
    ));

    // 测试事件（仅管理员可见触发入口，用于后台自检）
    zhiji_notify_register_event('test_notice', array(
        'label'      => '通知通道自检',
        'title'      => '通知通道自检',
        'channels'   => array('msg', 'mail', 'toast', 'badge'),
        'toast'      => 'success',
        'mail'       => 'ticket',
        'dedupe_ttl' => 60,
        'throttle'   => array(5, MINUTE_IN_SECONDS),
    ));
}, 20);
