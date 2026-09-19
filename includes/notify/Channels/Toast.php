<?php
/**
 * @module  Notify_Channel_Toast
 * @desc    渠道：前端 toast 提示
 *          - 目标用户 = 当前登录用户 → 立即在本请求的 wp_footer 输出（自操作即时反馈）
 *          - 否则 → 写入用户队列，该用户下次打开页面时输出（最多保留 5 条）
 * @since   2.0.0
 */

defined('ABSPATH') || exit;

class Zhiji_Notify_Toast
{
    const META = '_zhiji_toast_queue';
    const MAX  = 5;

    /** 本请求内即时展示（同一用户自操作） */
    private static $live = array();

    /**
     * @param int    $uid
     * @param string $event
     * @param array  $args
     * @param array  $cfg
     * @return bool
     */
    public static function send($uid, $event, array $args, array $cfg)
    {
        $item = array(
            'type'    => sanitize_key(!empty($cfg['toast']) ? $cfg['toast'] : 'info'),
            'title'   => (string) $args['title'],
            'content' => wp_strip_all_tags((string) $args['content']),
            'link'    => (string) $args['link'],
            'event'   => $event,
        );

        if (get_current_user_id() === (int) $uid) {
            self::$live[] = $item;
            return true;
        }

        $queue = get_user_meta((int) $uid, self::META, true);
        if (!is_array($queue)) {
            $queue = array();
        }
        $queue[] = $item;
        update_user_meta((int) $uid, self::META, array_slice($queue, -self::MAX));
        return true;
    }

    /**
     * 输出到前端（wp_footer）
     */
    public static function flush()
    {
        $items = self::$live;

        if (is_user_logged_in()) {
            $uid   = get_current_user_id();
            $queue = get_user_meta($uid, self::META, true);
            if (is_array($queue) && $queue) {
                delete_user_meta($uid, self::META);
                $items = array_merge($items, array_slice($queue, -self::MAX));
            }
        }

        if (!$items) {
            return;
        }

        self::enqueue_script();
        printf(
            "<script id='zhiji-toast-queue'>window.zhijiToastQueue=%s;if(window.zhijiNotifyFlush){window.zhijiNotifyFlush();}</script>\n",
            wp_json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * 注册前端脚本（仅在有提示时加载）
     */
    public static function enqueue_script()
    {
        if (wp_script_is('zhiji-notify', 'enqueued')) {
            return;
        }
        wp_enqueue_script(
            'zhiji-notify',
            zhiji_asset_url('js/zhiji-notify.js'),
            array(),
            null,
            true
        );
    }
    /**
     * 前台尽早决定是否加载提示脚本（覆盖"上一次请求留下的持久队列"场景）
     * 注意：wp_print_footer_scripts 在 wp_footer:20 执行，晚于它再 enqueue 就不会输出脚本标签
     */
    public static function maybe_enqueue()
    {
        if (self::$live) {
            self::enqueue_script();
            return;
        }
        if (!is_user_logged_in()) {
            return;
        }
        $queue = get_user_meta(get_current_user_id(), self::META, true);
        if (is_array($queue) && $queue) {
            self::enqueue_script();
        }
    }
}

add_action('wp_enqueue_scripts', array('Zhiji_Notify_Toast', 'maybe_enqueue'), 99);
add_action('wp_footer', array('Zhiji_Notify_Toast', 'flush'), 99);
