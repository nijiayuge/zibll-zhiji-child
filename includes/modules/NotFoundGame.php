<?php
/**
 * @module  NotFoundGame
 * @desc    404 页小游戏（贪吃蛇）：子主题 404.php 提供画布容器，本模块负责配置与
 *          仅在 is_404() 时加载 assets/zhiji/js/snake404.js。键盘操作、零外部依赖。
 * @option  notfound_game_enabled  总开关
 * @hook    wp_enqueue_scripts · 仅 404 页加载游戏脚本
 * @since   2.0.0（2026-09-29 新增）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('notfound_game', array(
    'title'    => '404 小游戏',
    'parent'   => 'zhiji_beautify',
    'priority' => 175,
    'option'   => 'notfound_game_enabled',
));

/* ============================================================
 * 后台 CSF 设置：美化效果 → 404 小游戏
 * ============================================================ */
Zhiji_Registry::register_options('notfound_game', array(
    array(
        'type'    => 'submessage',
        'style'   => 'info',
        'content' => __('在 404 页面内置贪吃蛇小游戏（方向键/WASD 操作，空格暂停），提升错误页体验与停留。'
            . '页面容器由子主题 <code>404.php</code> 提供（父主题文件零改动）。', 'zhiji'),
    ),
    array(
        'id'      => 'notfound_game_enabled',
        'type'    => 'switcher',
        'title'   => '启用 404 小游戏',
        'default' => false,
    ),
), 175);

/* ============================================================
 * 前端资源：仅 404 页加载（JS 独立文件：assets/zhiji/js/snake404.js）
 * ============================================================ */

/**
 * 游戏脚本注册（仅 is_404）
 *
 * @return void
 */
function zhiji_404game_enqueue()
{
    if (is_admin() || !function_exists('zhiji_is_enabled')) {
        return;
    }
    if (!zhiji_is_enabled('notfound_game_enabled', false) || !is_404()) {
        return;
    }
    wp_enqueue_script('zhiji-404-snake', zhiji_asset_url('js/snake404.js'), array(), ZHIJI_VERSION, false);
}
add_action('wp_enqueue_scripts', 'zhiji_404game_enqueue', 20);
