<?php
/**
 * @module  CommentBeautify
 * @desc    评论区美化（圆角卡片+hover）与 B 站风格评论 UID 标签（两个独立开关）
 * @option  comment_beautify_enabled  评论区美化
 *          comment_uid_enabled       UID 标签
 *          comment_uid_pad           ID 补零位数
 * @hook   wp_footer / comment_footer_info(10,3)（父主题钩子）
 * @since  2.0.0
 * @migrate 自 v1 `inc/Functions/CommentBeautify.php`
 *         （UID 标签背景图 assets/zhiji/img/uid/dtkp-*.png 共 25 张随主题分发）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('comment_beautify', array(
    'title'    => '评论区美化',
    'parent'   => 'zhiji_comment',
    'priority' => 130,
    'option'   => 'comment_beautify_enabled',
));

/* ============================================================
 * 评论区美化（圆角卡片 + hover 上浮）
 * ============================================================ */
zhiji_footer_add( 'comment-beautify-1', function () {
    if (!zhiji_is_enabled('comment_beautify_enabled')) {
        return;
    }
    echo '<style id="zhiji-comment-beautify-css">'
        . '#postcomments .commentlist .comment{border-radius:12px;margin:0 10px 12px;border:1px solid rgba(0,0,0,.06);'
        . 'background-color:rgba(255,255,255,.6);transition:all .3s ease}'
        . 'body.dark-theme #postcomments .commentlist .comment{border-color:rgba(255,255,255,.08);background-color:rgba(255,255,255,.03)}'
        . '#postcomments .commentlist .comment:hover{border-color:var(--zhiji-brand,#2e7cf6);box-shadow:0 4px 16px rgba(0,0,0,.08);transform:translateY(-1px)}'
        . '#postcomments .commentlist .comment:nth-child(odd){background:linear-gradient(135deg,rgba(59,130,246,.04) 0%,rgba(255,255,255,0) 100%)}'
        . 'body.dark-theme #postcomments .commentlist .comment:nth-child(odd){background:linear-gradient(135deg,rgba(59,130,246,.08) 0%,rgba(255,255,255,0) 100%)}'
        . '#postcomments .children{background:transparent;margin-bottom:6px;border-radius:12px}'
        . '#postcomments .children .comment{margin:0 0 8px 0;background-color:rgba(0,0,0,.02)}'
        . 'body.dark-theme #postcomments .children .comment{background-color:rgba(255,255,255,.02)}'
        . '</style>' . "\n";
}, 98 );

/* ============================================================
 * B 站风格 UID 标签
 * ============================================================ */
zhiji_footer_add( 'comment-beautify-2', function () {
    if (!zhiji_is_enabled('comment_uid_enabled')) {
        return;
    }
    echo '<style id="zhiji-comment-uid-css">'
        . '.bili-dyn-item__ornament{position:relative!important;right:auto!important;top:auto!important;'
        . 'margin:0 0 0 8px!important;float:none!important;vertical-align:middle;z-index:10}'
        . '.bili-dyn-ornament__type--3{height:44px;width:146px;position:relative}'
        . '.bili-dyn-ornament img{height:100%;width:100%;user-select:none;pointer-events:none;border-radius:4px}'
        . '.bili-dyn-ornament__type--3 span{font-family:monospace!important;font-size:12px;position:absolute;'
        . 'right:16px;top:14px;transform:scale(.88);transform-origin:right;font-weight:600;letter-spacing:1px}'
        . '@media (max-width:640px){.bili-dyn-item__ornament{right:10px;top:10px}'
        . '.bili-dyn-ornament__type--3{height:36px;width:120px}'
        . '.bili-dyn-ornament__type--3 span{font-size:10px;right:12px;top:11px}}'
        . '</style>' . "\n";
}, 98 );

add_filter('comment_footer_info', function ($info, $comment, $depth) {
    if (!zhiji_is_enabled('comment_uid_enabled')) {
        return $info;
    }
    // 随机背景色（B 站风格）
    $color_list = array(
        'rgb(138, 154, 247)', 'rgb(187, 103, 138)', 'rgb(166, 236, 149)',
        'rgb(172, 170, 94)', 'rgb(240, 88, 88)', 'rgb(182, 117, 243)',
        'rgb(219, 96, 157)', 'rgb(245, 107, 72)', 'rgb(196, 167, 104)',
        'rgb(221, 42, 42)', 'rgb(240, 158, 226)', 'rgb(243, 200, 98)',
        'rgb(248, 155, 200)', 'rgb(114, 153, 238)', 'rgb(214, 207, 107)',
        'rgb(192, 127, 235)', 'rgb(197, 184, 30)', 'rgb(245, 155, 210)',
        'rgb(231, 197, 152)', 'rgb(98, 98, 119)', 'rgb(221, 200, 173)',
        'rgb(110, 175, 187)', 'rgb(137, 141, 190)', 'rgb(166, 152, 238)',
        'rgb(104, 192, 207)', 'rgb(216, 124, 152)',
    );
    $color = $color_list[array_rand($color_list)];

    // 仅显示用户 ID（IP/位置由父主题「显示评论地理位置」提供，避免重复）
    $pad    = (int) zhiji_get_option('comment_uid_pad', 6);
    $uid    = $pad > 0 ? str_pad((string) $comment->user_id, $pad, '0', STR_PAD_LEFT) : (string) $comment->user_id;

    // Q 版背景图（25 张随机，跳过缺失的 015）
    $valid  = array_merge(range(0, 14), range(16, 25));
    $index  = $valid[array_rand($valid)];
    $img_url = ZHIJI_ASSETS_URL . 'img/uid/dtkp-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT) . '.png';

    $tag = '<div class="bili-dyn-item__ornament zhiji-comment-uid-tag"'
        . ' data-clipboard-tag="ID" data-clipboard-text="' . esc_attr($uid) . '"'
        . ' style="display:inline-flex;align-items:center;position:relative;background-image:url(' . esc_url($img_url) . ');'
        . 'background-size:100% 100%;background-repeat:no-repeat;min-width:150px;height:40px;padding:0 55px 0 12px;margin:4px 0;'
        . 'border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);">'
        . '<span style="color:#333;font-size:12px;font-weight:600;letter-spacing:.3px;'
        . 'text-shadow:0 1px 2px rgba(255,255,255,.8);white-space:nowrap;">' . esc_html('ID') . '</span>'
        . '</div>';

    return $info . $tag;
}, 10, 3);

/* ============================================================
 * 后台字段
 * ============================================================ */
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('comment_beautify', array(
        array(
            'id'      => 'comment_beautify_enabled',
            'type'    => 'switcher',
            'title'   => '启用评论区美化',
            'default' => false,
            'desc'    => '评论区圆角卡片、hover 上浮与品牌色描边（自动适配暗色模式）。',
        ),
        array(
            'id'      => 'comment_uid_enabled',
            'type'    => 'switcher',
            'title'   => '启用 B 站风格 UID 标签',
            'default' => false,
            'desc'    => '评论底部显示随机背景的用户 ID 标签（背景图随主题分发）。',
        ),
        array(
            'id'         => 'comment_uid_pad',
            'type'       => 'number',
            'title'      => 'ID 补零位数',
            'default'    => 6,
            'min'        => 0,
            'max'        => 10,
            'desc'       => '0 为不补零；例如 6 位显示 000123',
            'dependency' => array('comment_uid_enabled', '==', '1'),
        ),
    ), 20);
