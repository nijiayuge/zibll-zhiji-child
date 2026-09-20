<?php
/**
 * @module  AutoDeleteAttachments
 * @desc    删除文章时自动删除其关联附件（可选仅特色图片/全部附件，是否连服务器文件一起删）
 * @option  auto_delete_attachments_enabled  总开关
 *          auto_delete_attachments_mode     删除范围 thumbnail|all
 *          auto_delete_attachments_file     是否删除服务器文件
 * @hook    before_delete_post(10,1)
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/AutoDeleteAttachments.php`
 *          （改进：in_array 严格比较；附件自身删除不再递归触发；日志走 zhiji_log）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('auto_delete_attachments', array(
    'title'    => '删除文章自动删附件',
    'parent'   => 'zhiji_basic',
    'priority' => 60,
    'option'   => 'auto_delete_attachments_enabled',
));

/* ============================================================
 * 业务逻辑
 * ============================================================ */

/**
 * 文章删除时清理关联附件
 *
 * @param int $post_id
 * @return void
 */
function zhiji_auto_delete_attachments($post_id)
{
    if (!zhiji_is_enabled('auto_delete_attachments_enabled')) {
        return;
    }
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    // 附件自身被删除时不触发（避免递归：删附件→又进 before_delete_post）
    if ('attachment' === get_post_type($post_id)) {
        return;
    }

    $mode       = (string) zhiji_get_option('auto_delete_attachments_mode', 'all');
    $delete_file = zhiji_is_enabled('auto_delete_attachments_file', true);

    $attachments = array();
    if ('thumbnail' === $mode || 'all' === $mode) {
        $thumb_id = (int) get_post_thumbnail_id($post_id);
        if ($thumb_id) {
            $attachments[] = $thumb_id;
        }
    }
    if ('all' === $mode) {
        $children = get_posts(array(
            'post_type'      => 'attachment',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'post_parent'    => $post_id,
            'fields'         => 'ids',
        ));
        foreach ($children as $att_id) {
            if (!in_array((int) $att_id, $attachments, true)) {
                $attachments[] = (int) $att_id;
            }
        }
    }

    $deleted = 0;
    foreach ($attachments as $att_id) {
        if (wp_delete_attachment($att_id, $delete_file)) {
            $deleted++;
        }
    }
    if ($deleted > 0) {
        zhiji_log('文章 {id} 已删除，同时删除 {n} 个附件', array('id' => $post_id, 'n' => $deleted));
    }
}
add_action('before_delete_post', 'zhiji_auto_delete_attachments', 10, 1);

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('auto_delete_attachments', array(
        array(
            'id'      => 'auto_delete_attachments_enabled',
            'type'    => 'switcher',
            'title'   => '启用自动删除附件',
            'desc'    => '删除文章时自动删除关联的附件文件',
            'default' => false,
        ),
        array(
            'id'         => 'auto_delete_attachments_mode',
            'type'       => 'radio',
            'title'      => '删除范围',
            'options'    => array('thumbnail' => '仅特色图片', 'all' => '全部附件'),
            'default'    => 'all',
            'inline'     => true,
            'dependency' => array('auto_delete_attachments_enabled', '==', '1'),
        ),
        array(
            'id'         => 'auto_delete_attachments_file',
            'type'       => 'switcher',
            'title'      => '同时删除服务器文件',
            'default'    => true,
            'desc'       => '关闭则仅从媒体库删除，保留服务器文件',
            'dependency' => array('auto_delete_attachments_enabled', '==', '1'),
        ),
    ));
}, 20);
