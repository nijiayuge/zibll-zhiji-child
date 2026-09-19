<?php
/**
 * @module  Notify_Channel_Badge
 * @desc    渠道：消息角标刷新
 *          父主题角标只在「加载页面 / 打开消息中心」时更新（见开发文档坑 25），
 *          因此这里标记脏位，在该用户页面加载时主动触发一次角标刷新。
 * @since   2.0.0
 */

defined('ABSPATH') || exit;

class Zhiji_Notify_Badge
{
    const META = '_zhiji_badge_dirty';

    private static $live = false;

    /**
     * @param int    $uid
     * @param string $event
     * @param array  $args
     * @param array  $cfg
     * @return bool
     */
    public static function send($uid, $event, array $args, array $cfg)
    {
        if (get_current_user_id() === (int) $uid) {
            self::$live = true;
            return true;
        }
        update_user_meta((int) $uid, self::META, time());
        return true;
    }

    /**
     * 输出刷新指令（wp_footer）
     */
    public static function flush()
    {
        $dirty = self::$live;

        if (!$dirty && is_user_logged_in()) {
            $uid = get_current_user_id();
            if (get_user_meta($uid, self::META, true)) {
                delete_user_meta($uid, self::META);
                $dirty = true;
            }
        }

        if (!$dirty) {
            return;
        }

        echo "<script id='zhiji-badge-refresh'>(function(){"
            . "try{"
            . "if(typeof window.zhijiRefreshMsgBadge==='function'){window.zhijiRefreshMsgBadge();}"
            . "else{var n=document.querySelectorAll('.msg-badge,.new-msg-badge,.zib-msg-badge');"
            . "for(var i=0;i<n.length;i++){n[i].classList.add('zhiji-badge-dirty');}}"
            . "}catch(e){}"
            . "})();</script>\n";
    }
}

add_action('wp_footer', array('Zhiji_Notify_Badge', 'flush'), 98);
