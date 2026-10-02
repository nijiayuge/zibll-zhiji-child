<?php
/**
 * @module  Notify_Channel_Msg
 * @desc    渠道：站内消息（经 Zhiji_Adapter 写入父主题 ZibMsg）
 * @since   2.0.0
 */

defined('ABSPATH') || exit;

class Zhiji_Notify_Msg
{
    /**
     * @param int    $uid
     * @param string $event
     * @param array  $args
     * @param array  $cfg
     * @return bool
     */
    public static function send($uid, $event, array $args, array $cfg)
    {
        if (!class_exists('ZibMsg')) {
            zhiji_log('站内消息渠道不可用：父主题 ZibMsg 未加载', array('event' => $event));
            return false;
        }

        $content = (string) $args['content'];
        if (!empty($args['link'])) {
            $content .= "\n" . $args['link'];
        }

        /**
         * 消息分类（type）：默认 system。若模块要用自定义分类，
         * 必须同时补 message_cats + 消息中心 Tab（mag_ctnter_main_tabs_array）+ 读取条件。
         */
        $type = !empty($cfg['msg_type']) ? sanitize_key($cfg['msg_type']) : 'system';

        /**
         * 发送者：0 = 系统（匿名）。
         * ⚠️ 官方类通知（如奖励到账）应传 'admin'，否则用户中心里显示为「系统」，
         *    少了「官方」权威性。P3 接入 RewardNotify 时补上此能力。
         *    取值：$args['send_user']（本次投递显式指定）> $cfg['msg_send_user']（事件级默认）> 0
         */
        $send_user = 0;
        if (isset($args['send_user'])) {
            $send_user = $args['send_user'];
        } elseif (!empty($cfg['msg_send_user'])) {
            $send_user = $cfg['msg_send_user'];
        }
        if (is_string($send_user) && '' !== $send_user) {
            $send_user = sanitize_text_field($send_user);
        } else {
            $send_user = (int) $send_user;
        }

        $result = Zhiji_Adapter::msg_add(array(
            'send_user'    => $send_user,
            'receive_user' => (int) $uid,
            'type'         => $type,
            'title'        => (string) $args['title'],
            'content'      => $content,
            'meta'         => array(
                'zhiji_event' => $event,
                'zhiji_link'  => (string) $args['link'],
                'zhiji_data'  => (array) $args['data'],
            ),
        ));

        return !empty($result);
    }
}
