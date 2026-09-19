<?php
/**
 * @module  Notify_Channel_Wechat
 * @desc    渠道：微信模板消息（复用父主题能力；失败不阻断主流程）
 *          使用前提：公众号登录配置完整 + 用户已绑定并关注（oauth_weixingzh_openid）
 *          事件需提供模板类型：注册事件时给 'wechat_type'（父主题模板类型表里的 key）
 * @since   2.0.0
 */

defined('ABSPATH') || exit;

class Zhiji_Notify_Wechat
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
        if (empty($cfg['wechat_type'])) {
            zhiji_log('微信渠道跳过：事件未声明 wechat_type', array('event' => $event));
            return false;
        }

        // 父主题要求参数按模板字段映射；这里把 data 原样传入，由父主题做字段名映射与长度处理
        $data = array_merge(
            array(
                'name' => (string) $args['title'],
                'desc' => wp_strip_all_tags((string) $args['content']),
                'time' => current_time('Y-m-d H:i:s'),
            ),
            (array) $args['data']
        );

        $r = Zhiji_Adapter::wechat_template((int) $uid, $cfg['wechat_type'], $data, (string) $args['link']);

        // 父主题发送失败会返回 array('error'=>true,'msg'=>...)，这里只记日志，不影响主流程
        if (!empty($r['error'])) {
            zhiji_log('微信模板消息失败', array('event' => $event, 'user' => $uid, 'msg' => zhiji_arr_get($r, 'msg', '')));
            return false;
        }
        return true;
    }
}
