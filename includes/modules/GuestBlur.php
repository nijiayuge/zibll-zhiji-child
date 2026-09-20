<?php
/**
 * @module  GuestBlur
 * @desc    未登录模糊：访客浏览时图片模糊，点击引导登录（全站图片/仅文章内图片两种范围）
 * @option  guest_blur_enabled        总开关
 *          guest_blur_mode           范围 site|article
 *          guest_blur_level          模糊程度(px)
 *          guest_blur_exclude_logo   排除 Logo（site 模式）
 *          guest_blur_exclude_avatar 排除头像（site 模式）
 *          guest_blur_tip            点击提示文字
 * @hook    wp_head(100) / wp_footer(100)
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/GuestBlur.php`
 *          （v1 的旧双开关一次性迁移逻辑已移除 —— v2 为全新安装，无历史键）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('guest_blur', array(
    'title'    => '未登录模糊',
    'parent'   => 'zhiji_cap',
    'priority' => 30,
    'option'   => 'guest_blur_enabled',
));

/* ============================================================
 * 生效判断（未登录 + 前台）
 * ============================================================ */

/**
 * 模糊是否对当前请求生效
 *
 * @return bool
 */
function zhiji_guest_blur_active()
{
    if (!zhiji_is_enabled('guest_blur_enabled')) {
        return false;
    }
    if (is_user_logged_in() || is_admin()) {
        return false;
    }
    return true;
}

/**
 * 当前范围下的图片选择器
 *
 * @param string $mode
 * @return string
 */
function zhiji_guest_blur_selector($mode)
{
    return ('article' === $mode)
        ? '.entry-content img, .article-content img, .post-content img'
        : 'img:not(.no-blur)';
}

/* ============================================================
 * 前台输出
 * ============================================================ */
add_action('wp_head', function () {
    if (!zhiji_guest_blur_active()) {
        return;
    }
    $mode   = (string) zhiji_get_option('guest_blur_mode', 'site');
    $blur   = max(2, min(30, (int) zhiji_get_option('guest_blur_level', 8)));
    $blur_h = max($blur - 2, 1);
    $selector = zhiji_guest_blur_selector($mode);

    $css = "$selector { filter: blur({$blur}px); transition: filter .3s; cursor: pointer; }\n"
        . "$selector:hover { filter: blur({$blur_h}px); }\n";

    if ('site' === $mode) {
        if (zhiji_is_enabled('guest_blur_exclude_logo', true)) {
            $css .= ".logo img, .site-logo img, .header-logo img { filter: none !important; cursor: default; }\n";
        }
        if (zhiji_is_enabled('guest_blur_exclude_avatar', true)) {
            $css .= ".avatar img, .user-avatar img, .author-avatar img { filter: none !important; cursor: default; }\n";
        }
    }

    echo '<style id="zhiji-guest-blur">' . "\n" . $css . '</style>' . "\n";
}, 100);

add_action('wp_footer', function () {
    if (!zhiji_guest_blur_active()) {
        return;
    }
    $mode     = (string) zhiji_get_option('guest_blur_mode', 'site');
    $selector = zhiji_guest_blur_selector($mode);
    $login    = esc_url(wp_login_url(get_permalink()));
    $tip_text = (string) zhiji_get_option('guest_blur_tip', '登录后查看清晰图片');

    echo '<script id="zhiji-guest-blur-js">'
        . '(function(){'
        . 'var selector=' . wp_json_encode($selector) . ';'
        . 'var loginUrl=' . wp_json_encode($login) . ';'
        . 'var tipText=' . wp_json_encode($tip_text) . ';'
        . 'document.addEventListener("click",function(e){'
        . 'if(!e.target.matches||!e.target.matches(selector))return;'
        . 'e.preventDefault();window.location.href=loginUrl;});'
        . 'document.addEventListener("DOMContentLoaded",function(){'
        . 'document.querySelectorAll(selector).forEach(function(img){'
        . 'var parent=img.parentElement;'
        . 'if(parent&&!parent.querySelector(".zhiji-blur-tip")){'
        . 'parent.style.position="relative";'
        . 'var tip=document.createElement("div");'
        . 'tip.className="zhiji-blur-tip";tip.textContent=tipText;'
        . 'parent.appendChild(tip);}});});'
        . '})();</script>'
        . '<style id="zhiji-guest-blur-tip-css">'
        . '.zhiji-blur-tip{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);'
        . 'background:rgba(0,0,0,.7);color:#fff;padding:8px 16px;border-radius:20px;font-size:13px;'
        . 'pointer-events:none;z-index:10;white-space:nowrap;}'
        . '</style>' . "\n";
}, 100);

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('guest_blur', array(
        array(
            'id'      => 'guest_blur_enabled',
            'type'    => 'switcher',
            'title'   => '启用未登录模糊',
            'default' => false,
            'desc'    => '未登录用户访问时图片自动模糊，点击引导登录。',
        ),
        array(
            'id'         => 'guest_blur_mode',
            'type'       => 'select',
            'title'      => '模糊范围',
            'options'    => array('site' => '全站图片', 'article' => '仅文章内图片'),
            'default'    => 'site',
            'dependency' => array('guest_blur_enabled', '==', '1'),
        ),
        array(
            'id'         => 'guest_blur_level',
            'type'       => 'slider',
            'title'      => '模糊程度(px)',
            'min'        => 2,
            'max'        => 30,
            'step'       => 1,
            'default'    => 8,
            'dependency' => array('guest_blur_enabled', '==', '1'),
        ),
        array(
            'id'         => 'guest_blur_exclude_logo',
            'type'       => 'switcher',
            'title'      => '排除Logo图片',
            'default'    => true,
            'dependency' => array('guest_blur_enabled', '==', '1', 'guest_blur_mode', '==', 'site'),
        ),
        array(
            'id'         => 'guest_blur_exclude_avatar',
            'type'       => 'switcher',
            'title'      => '排除头像图片',
            'default'    => true,
            'dependency' => array('guest_blur_enabled', '==', '1', 'guest_blur_mode', '==', 'site'),
        ),
        array(
            'id'         => 'guest_blur_tip',
            'type'       => 'text',
            'title'      => '点击提示文字',
            'default'    => '登录后查看清晰图片',
            'dependency' => array('guest_blur_enabled', '==', '1'),
        ),
    ));
}, 20);
