<?php
/**
 * @module  HideContent
 * @desc    v1 隐藏内容标记兼容桥：把旧内容里的 {hide}/{hide=login} 转换为父主题
 *          原生 [hidecontent] 短代码，渲染完全交给父主题（登录/评论/会员/付费可见）。
 * @option  hide_content_enabled  总开关（默认关闭 —— 新站点直接用父主题 [hidecontent] 即可，
 *          本模块只为兼容 v1 时期写入的 {hide} 旧标记）
 * @hook    the_content(6)
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/HideContent.php`
 *          ⚠️ 2026-09-20 与父主题功能对比后重构：v1 自绘提示块与父主题 [hidecontent]
 *          （支持 logged/reply/vip1/payshow/vip2）完全重复且能力更弱，改为纯转换桥。
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('hide_content', array(
    'title'    => '隐藏内容兼容桥（v1 {hide} 标记）',
    'parent'   => 'zhiji_cap',
    'priority' => 10,
    'option'   => 'hide_content_enabled',
));

/* ============================================================
 * 标记转换
 * ============================================================ */

/**
 * 把 v1 的 {hide} / {hide=login} 标记转换为父主题 [hidecontent] 短代码。
 *
 * 转换规则：
 *   {hide}内容{/hide}          → [hidecontent type="reply"]内容[/hidecontent]
 *   {hide=login}内容{/hide}    → [hidecontent type="logged"]内容[/hidecontent]
 *
 * 渲染（含权限判断、提示样式、会员/付费扩展）全部由父主题完成，
 * 本模块不再自绘提示块 —— 避免与父主题功能重复、样式不一致。
 *
 * @param string $content
 * @return string
 */
function zhiji_hide_parse($content)
{
    if (!zhiji_is_enabled('hide_content_enabled')) {
        return $content;
    }
    if (!is_singular() || false === strpos($content, '{hide')) {
        return $content;
    }

    // 先处理更具体的 {hide=login}
    $content = preg_replace_callback(
        '/\{hide=login\}(.*?)\{\/hide\}/s',
        function ($m) {
            return '[hidecontent type="logged" desc="隐藏内容：登录可见"]' . $m[1] . '[/hidecontent]';
        },
        $content
    );
    // 再处理 {hide}（评论可见）
    $content = preg_replace_callback(
        '/\{hide\}(.*?)\{\/hide\}/s',
        function ($m) {
            return '[hidecontent type="reply" desc="隐藏内容：评论可见"]' . $m[1] . '[/hidecontent]';
        },
        $content
    );

    return $content;
}
add_filter('the_content', 'zhiji_hide_parse', 6);

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('hide_content', array(
        array(
            'id'      => 'hide_content_enabled',
            'type'    => 'switcher',
            'title'   => '启用 v1 标记兼容',
            'desc'    => '把旧文章里的 {hide}（评论可见）与 {hide=login}（登录可见）标记转换为父主题原生的 [hidecontent] 短代码渲染。'
                       . '新内容请直接使用父主题编辑器的「隐藏内容」按钮（支持登录/评论/会员/付费可见），无需开启本开关。',
            'default' => false,
        ),
    ));
}, 20);
