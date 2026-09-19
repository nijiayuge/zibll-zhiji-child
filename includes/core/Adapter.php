<?php
/**
 * @module  Adapter
 * @desc    父主题（Zibll）适配层 —— **全项目唯一允许调用 zib_* / Zib* 的地方**
 *          父主题升级只需改这一个文件；其它模块一律通过本类访问父主题能力。
 * @since   2.0.0
 */

defined('ABSPATH') || exit;

class Zhiji_Adapter
{
    /**
     * 父主题核心是否已加载（模块自检用）
     */
    public static function parent_loaded()
    {
        return function_exists('zib_require') && function_exists('zib_get_option');
    }

    /**
     * 写一条站内消息（走父主题 ZibMsg）
     *
     * @param array $args send_user|receive_user|type|title|content|meta|other
     * @return bool|int
     */
    public static function msg_add(array $args)
    {
        if (!class_exists('ZibMsg')) {
            zhiji_log('ZibMsg 不存在，消息未发送', $args);
            return false;
        }
        $defaults = array(
            'send_user'    => 0,
            'receive_user' => 0,
            'type'         => 'system',
            'title'        => '',
            'content'      => '',
        );
        return ZibMsg::update(array_merge($defaults, $args));
    }

    /**
     * 文章 meta 读取（父主题封装，兼容其序列化与默认值逻辑）
     */
    public static function post_meta_get($post_id, $key, $default = '')
    {
        if (function_exists('zib_get_post_meta')) {
            return zib_get_post_meta($post_id, $key, true);
        }
        $v = get_post_meta($post_id, $key, true);
        return ('' === $v) ? $default : $v;
    }

    /**
     * 文章 meta 写入
     */
    public static function post_meta_update($post_id, $key, $value)
    {
        if (function_exists('zib_update_post_meta')) {
            return zib_update_post_meta($post_id, $key, $value);
        }
        return update_post_meta($post_id, $key, $value);
    }

    /**
     * 分类 meta 读取
     */
    public static function term_meta_get($term_id, $key, $default = '')
    {
        if (function_exists('zib_get_term_meta')) {
            return zib_get_term_meta($term_id, $key, true);
        }
        $v = get_term_meta($term_id, $key, true);
        return ('' === $v) ? $default : $v;
    }

    /**
     * 分类 meta 写入
     */
    public static function term_meta_update($term_id, $key, $value)
    {
        if (function_exists('zib_update_term_meta')) {
            return zib_update_term_meta($term_id, $key, $value);
        }
        return update_term_meta($term_id, $key, $value);
    }

    /**
     * 用户头像 URL（优先父主题逻辑，缺失时回落 WP 原生）
     */
    public static function avatar_url($user_id, $size = 96)
    {
        if (function_exists('zib_get_avatar_url')) {
            $url = zib_get_avatar_url($user_id, $size);
            if ($url) {
                return $url;
            }
        }
        return get_avatar_url($user_id, array('size' => $size));
    }

    /**
     * 临时停用父主题对 wp_mail 内容的覆盖（发自定义邮件前调用）
     * 用法：self::mail_filter_off(); wp_mail(...); self::mail_filter_on();
     */
    public static function mail_filter_off()
    {
        if (function_exists('zib_get_mail_content')) {
            remove_filter('wp_mail', 'zib_get_mail_content', 999);
        }
    }

    /**
     * 恢复父主题的 wp_mail 内容覆盖
     */
    public static function mail_filter_on()
    {
        if (function_exists('zib_get_mail_content')) {
            add_filter('wp_mail', 'zib_get_mail_content', 999);
        }
    }

    /**
     * 微信模板消息（父主题能力；失败返回错误数组，不抛异常）
     */
    public static function wechat_template($user_id, $type, $data, $url = '')
    {
        if (!function_exists('zib_wechat_template_send')) {
            return array('error' => true, 'msg' => '父主题未提供微信模板消息能力');
        }
        return zib_wechat_template_send($user_id, $type, $data, $url);
    }

    /**
     * 父主题主题版本（用于升级回归判断）
     */
    public static function parent_version()
    {
        return defined('THEME_VERSION') ? THEME_VERSION : '';
    }
}
