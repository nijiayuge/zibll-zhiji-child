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

        $result = Zhiji_Adapter::msg_add(array(
            'send_user'    => 0,
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
