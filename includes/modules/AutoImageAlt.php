<?php
/**
 * @module  AutoImageAlt
 * @desc    图片自动 alt/title：文章保存时补齐正文图片的 alt 与 title，上传附件时补 alt
 * @option  auto_image_alt_enabled  总开关
 *          auto_image_alt_prefix   alt 前缀
 *          auto_image_alt_suffix   alt 后缀
 * @hook    save_post(20,1) / wp_generate_attachment_metadata(20,2)
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/AutoImageAlt.php`
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('auto_image_alt', array(
    'title'    => '图片自动alt/title',
    'parent'   => 'zhiji_post',
    'priority' => 50,
    'option'   => 'auto_image_alt_enabled',
));

/* ============================================================
 * 文章正文处理
 * ============================================================ */

/**
 * 文章保存时处理正文图片
 *
 * @param int $post_id
 * @return void
 */
function zhiji_auto_image_alt_process_post($post_id)
{
    if (!zhiji_is_enabled('auto_image_alt_enabled')) {
        return;
    }
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    $post = get_post($post_id);
    if (!$post || 'post' !== $post->post_type || 'publish' !== $post->post_status) {
        return;
    }

    // 移除钩子避免 wp_update_post 触发无限循环
    remove_action('save_post', 'zhiji_auto_image_alt_process_post', 20);

    $title  = wp_strip_all_tags($post->post_title);
    $prefix = (string) zhiji_get_option('auto_image_alt_prefix', '');
    $suffix = (string) zhiji_get_option('auto_image_alt_suffix', '');
    $full   = $prefix . $title . $suffix;

    $content = preg_replace_callback(
        '/<img([^>]+)>/i',
        function ($matches) use ($full) {
            $img_attr = $matches[1];

            // alt：已有非空则不动；空或缺失则补
            if (preg_match('/\salt\s*=\s*["\']([^"\']*)["\']/i', $img_attr, $alt_match)) {
                if (trim($alt_match[1]) === '') {
                    $img_attr = preg_replace('/\salt\s*=\s*["\'][^"\']*["\']/i', ' alt="' . esc_attr($full) . '"', $img_attr);
                }
            } else {
                $img_attr .= ' alt="' . esc_attr($full) . '"';
            }

            // title：缺失则用图片文件名（文件名太短则退回文章标题）
            if (!preg_match('/\stitle\s*=\s*["\']/i', $img_attr)) {
                $img_title = $full;
                if (preg_match('/\ssrc\s*=\s*["\']([^"\']+)["\']/i', $img_attr, $src_match)) {
                    $filename = preg_replace('/\.[^.]+$/', '', basename($src_match[1]));
                    if ($filename && mb_strlen($filename) > 2) {
                        $img_title = $filename;
                    }
                }
                $img_attr .= ' title="' . esc_attr($img_title) . '"';
            }

            return '<img' . $img_attr . '>';
        },
        $post->post_content
    );

    if ($content !== $post->post_content) {
        wp_update_post(array(
            'ID'           => $post_id,
            'post_content' => $content,
        ));
    }

    add_action('save_post', 'zhiji_auto_image_alt_process_post', 20, 1);
}
add_action('save_post', 'zhiji_auto_image_alt_process_post', 20, 1);

/**
 * 图片上传时补 alt（仅当为空时）
 *
 * @param array $metadata      附件元数据
 * @param int   $attachment_id 附件 ID
 * @return array
 */
function zhiji_auto_image_alt_process_attachment($metadata, $attachment_id)
{
    if (!zhiji_is_enabled('auto_image_alt_enabled')) {
        return $metadata;
    }
    $attachment = get_post($attachment_id);
    if (!$attachment || !wp_attachment_is_image($attachment_id)) {
        return $metadata;
    }
    $existing_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
    if (empty($existing_alt)) {
        $prefix = (string) zhiji_get_option('auto_image_alt_prefix', '');
        $suffix = (string) zhiji_get_option('auto_image_alt_suffix', '');
        $new_alt = $prefix . wp_strip_all_tags($attachment->post_title) . $suffix;
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $new_alt);
    }
    return $metadata;
}
add_filter('wp_generate_attachment_metadata', 'zhiji_auto_image_alt_process_attachment', 20, 2);

/* ============================================================
 * 后台字段
 * ============================================================ */
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('auto_image_alt', array(
        array(
            'id'      => 'auto_image_alt_enabled',
            'type'    => 'switcher',
            'title'   => '启用图片自动alt/title',
            'label'   => '文章发布/图片上传时自动添加alt和title属性（SEO优化）',
            'default' => false,
        ),
        array(
            'id'         => 'auto_image_alt_prefix',
            'type'       => 'text',
            'title'      => 'alt属性前缀',
            'desc'       => '自动生成的alt属性前缀（可选）',
            'default'    => '',
            'dependency' => array('auto_image_alt_enabled', '==', '1'),
        ),
        array(
            'id'         => 'auto_image_alt_suffix',
            'type'       => 'text',
            'title'      => 'alt属性后缀',
            'desc'       => '自动生成的alt属性后缀（可选）',
            'default'    => '',
            'dependency' => array('auto_image_alt_enabled', '==', '1'),
        ),
    ), 20);
