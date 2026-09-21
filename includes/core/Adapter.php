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
     * 用户中心地址（父主题路由；缺失时回落到首页，保证链接永远不会是空串）
     *
     * @param string $type 目标页，如 msg / order / coupon
     * @param string $tab  子页签
     * @return string
     */
    public static function user_center_url($type = 'msg', $tab = '')
    {
        if (function_exists('zib_get_user_center_url')) {
            $url = zib_get_user_center_url($type, $tab);
            if ($url) {
                return $url;
            }
        }
        return home_url('/');
    }

    /**
     * 临时停用父主题对 wp_mail 内容的覆盖（发自定义邮件前调用）
     * 用法：self::mail_filter_off(); wp_mail(...); self::mail_filter_on();
     * 注意：父主题挂载点为 add_filter('wp_mail','zib_get_mail_content')（默认优先级 10，见 zib-email.php:77）
     */
    public static function mail_filter_off()
    {
        if (function_exists('zib_get_mail_content')) {
            remove_filter('wp_mail', 'zib_get_mail_content', 10);
        }
    }

    /**
     * 恢复父主题的 wp_mail 内容覆盖
     */
    public static function mail_filter_on()
    {
        if (function_exists('zib_get_mail_content')) {
            add_filter('wp_mail', 'zib_get_mail_content', 10);
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
    /**
     * SVG 图标（父主题图标库；缺失返回空串）
     *
     * @param string $name 图标名
     * @return string
     */
    public static function svg($name)
    {
        return function_exists('zib_get_svg') ? zib_get_svg($name) : '';
    }

    /**
     * 用户会员等级（0 = 无会员/父主题缺失）
     *
     * @param int $user_id
     * @return int
     */
    public static function user_vip_level($user_id)
    {
        return function_exists('zib_get_user_vip_level') ? (int) zib_get_user_vip_level($user_id) : 0;
    }

    /**
     * 优惠码折扣文案（父主题 zibpay 文案函数；缺失返回空串）
     *
     * @param array $discount discount 结构
     * @return string
     */
    public static function coupon_discount_text($discount)
    {
        return function_exists('zibpay_get_coupon_discount_text') ? (string) zibpay_get_coupon_discount_text($discount) : '';
    }
    /**
     * 用户积分变动（父主题 zibpay；缺失时静默返回 false）
     *
     * @param int   $user_id
     * @param array $args value/type/desc
     * @return bool
     */
    public static function update_user_points($user_id, array $args)
    {
        if (!function_exists('zibpay_update_user_points')) {
            return false;
        }
        zibpay_update_user_points($user_id, $args);
        return true;
    }

    /**
     * AJAX 分页容器（父主题 zibll 用户中心列表分页；缺失时返回原 HTML）
     *
     * @param string $html
     * @return string
     */
    public static function ajaxpager_one_centent($html)
    {
        if (!function_exists('zib_get_ajax_ajaxpager_one_centent')) {
            return (string) $html;
        }
        return (string) zib_get_ajax_ajaxpager_one_centent($html);
    }
    /**
     * 用户余额变动（父主题 zibpay；缺失时静默返回 false）
     *
     * @param int   $user_id
     * @param array $args value/type/desc
     * @return bool
     */
    public static function update_user_balance($user_id, array $args)
    {
        if (!function_exists('zibpay_update_user_balance')) {
            return false;
        }
        zibpay_update_user_balance($user_id, $args);
        return true;
    }

    /**
     * 发送 HTML 邮件（模块内统一入口，禁止业务模块直调 wp_mail）
     *
     * @param string $to      收件人
     * @param string $subject 主题
     * @param string $body    HTML 正文
     * @param array  $headers 附加头
     * @return bool
     */
    public static function mail_raw($to, $subject, $body, array $headers = array())
    {
        if (!is_email($to)) {
            return false;
        }
        if (empty($headers)) {
            $headers = array('Content-Type: text/html; charset=UTF-8');
        }
        return (bool) wp_mail($to, $subject, $body, $headers);
    }
    /**
     * 用户等级经验变动（父主题 zib_add_user_level_integral）
     *
     * @param int    $user_id
     * @param int    $value
     * @param string $key
     * @param bool   $no_limit_day_max
     * @return void
     */
    public static function user_level_integral_add($user_id = 0, $value = 0, $key = '', $no_limit_day_max = false)
    {
        if (function_exists('zib_add_user_level_integral')) {
            zib_add_user_level_integral($user_id, $value, $key, $no_limit_day_max);
        }
    }

    /**
     * 积分来源列表（父主题 zib_get_integral_add_lists）
     *
     * @return array
     */
    public static function integral_add_lists()
    {
        return function_exists('zib_get_integral_add_lists') ? (array) zib_get_integral_add_lists() : array();
    }

    /**
     * 用户等级（父主题 zib_get_user_level）
     *
     * @param int $user_id
     * @return int
     */
    public static function user_level($user_id = 0)
    {
        return function_exists('zib_get_user_level') ? (int) zib_get_user_level($user_id) : 0;
    }

    /**
     * 用户余额（父主题 zibpay_get_user_balance）
     *
     * @param int $user_id
     * @return float
     */
    public static function get_user_balance($user_id)
    {
        return function_exists('zibpay_get_user_balance') ? (float) zibpay_get_user_balance($user_id) : 0.0;
    }

    /**
     * 用户积分（父主题 zibpay_get_user_points，个人中心口径）
     *
     * @param int $user_id
     * @return int
     */
    public static function get_user_points($user_id = 0)
    {
        return function_exists('zibpay_get_user_points') ? (int) zibpay_get_user_points($user_id) : 0;
    }

    /**
     * 更新用户会员（父主题 zibpay_update_user_vip）
     *
     * @param int   $user_id
     * @param array $data
     * @return bool
     */
    public static function update_user_vip($user_id, array $data)
    {
        if (!function_exists('zibpay_update_user_vip')) {
            return false;
        }
        zibpay_update_user_vip($user_id, $data);
        return true;
    }
}
