<?php
/**
 * @module  BaiduSEO
 * @desc    百度收录推送：文章首次发布时通过百度站长普通收录接口主动提交 URL
 * @option  baidu_seo_enabled  总开关
 *          baidu_seo_site     站点地址 site
 *          baidu_seo_token    推送令牌 token
 * @hook    publish_post · WP 核心钩子，首次发布时推送
 * @option-record zhiji_baidu_last_push  最近一次推送结果（非 autoload）
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/BaiduSEO.php`（钩子为 WP 核心，真实可用）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('baidu_seo', array(
    'title'    => '百度收录推送',
    'parent'   => 'zhiji_over',
    'priority' => 30,
    'option'   => 'baidu_seo_enabled',
));

/* ============================================================
 * 推送逻辑
 * ============================================================ */

/**
 * 推送单篇文章到百度
 *
 * @param int $post_id 文章 ID
 * @return bool 是否成功
 */
function zhiji_baidu_push($post_id)
{
    $site  = trim((string) zhiji_get_option('baidu_seo_site', ''));
    $token = trim((string) zhiji_get_option('baidu_seo_token', ''));
    if ('' === $site || '' === $token) {
        return false;
    }
    $url = get_permalink($post_id);
    if (!$url) {
        return false;
    }

    $resp = wp_remote_post(
        add_query_arg(array('site' => $site, 'token' => $token), 'https://data.zz.baidu.com/urls'),
        array(
            'timeout' => 8,
            'headers' => array('Content-Type' => 'text/plain'),
            'body'    => $url,
        )
    );
    if (is_wp_error($resp)) {
        return false;
    }

    $data = json_decode((string) wp_remote_retrieve_body($resp), true);
    // success 字段为成功推送条数
    if (is_array($data) && isset($data['success']) && (int) $data['success'] > 0) {
        update_option('zhiji_baidu_last_push', array(
            'time' => current_time('mysql'),
            'url'  => $url,
            'msg'  => isset($data['message']) ? (string) $data['message'] : '',
        ), false);
        return true;
    }
    return false;
}

add_action('publish_post', function ($post_id, $post) {
    if (!zhiji_is_enabled('baidu_seo_enabled')) {
        return;
    }
    // 修订与自动保存不推送
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    // 每篇只推一次（防重复更新触发）
    if (get_post_meta($post_id, '_zhiji_baidu_pushed', true)) {
        return;
    }
    if (zhiji_baidu_push($post_id)) {
        update_post_meta($post_id, '_zhiji_baidu_pushed', 1);
    }
}, 10, 2);

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('baidu_seo', array(
        array(
            'id'      => 'baidu_seo_enabled',
            'type'    => 'switcher',
            'title'   => '启用百度收录推送',
            'desc'    => '文章首次发布时通过百度站长主动推送接口提交 URL，加速收录。',
            'default' => false,
        ),
        array(
            'id'         => 'baidu_seo_site',
            'type'       => 'text',
            'title'      => '站点地址 site',
            'default'    => '',
            'desc'       => '百度搜索资源平台 → 普通收录 → 推送接口中显示的 site 参数',
            'dependency' => array('baidu_seo_enabled', '==', '1'),
        ),
        array(
            'id'         => 'baidu_seo_token',
            'type'       => 'text',
            'title'      => '推送令牌 token',
            'default'    => '',
            'desc'       => '百度搜索资源平台生成的推送 token',
            'dependency' => array('baidu_seo_enabled', '==', '1'),
        ),
    ));
}, 20);
