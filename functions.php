<?php
/*
 * @Author        : Qinver
 * @Url           : zibll.com
 * @Date          : 2020-09-29 13:18:36
 * @LastEditTime: 2024-10-11 12:22:00
 * @Email         : 770349780@qq.com
 * @Project       : Zibll子比主题
 * @Description   : 一款极其优雅的Wordpress主题
 * @Read me       : 感谢您使用子比主题，主题源码有详细的注释，支持二次开发。
 * @Remind        : 使用盗版主题会存在各种未知风险。支持正版，从我做起！
 */

/**
 * ── 修正父主题版本常量（必须放在父主题 require 之前）────────────────────
 *
 * 问题：zibll 父主题用 `wp_get_theme()`（**无参数 = 当前活动主题**）取版本：
 *     inc/inc.php:53  $theme_data = wp_get_theme();
 *     inc/inc.php:54  $_version   = $theme_data['Version'];   // 子主题激活时 = 子主题版本
 *     inc/inc.php:55  define('THEME_VERSION', $_version);     // 于是父主题版本被污染
 * 后果：
 *   ① 后台误报「当前主题版本：V2.0.0，可更新到最新版本：V9.1」并诱导点「在线更新」
 *      —— 那会去覆盖父主题（本项目铁律：绝不改动父主题）
 *   ② Adapter::parent_version()（本子主题升级回归判断用）返回错误值
 *
 * 修法：在加载父主题之前，按**父主题目录名**（get_template() === 'zibll'）取真实版本先钉住常量。
 * 父主题随后会再 define 一次 → PHP 8 抛 "Constant already defined" Warning，
 * 这里用**精确匹配**的错误处理器只屏蔽这一条，其余告警照常抛出。
 */
if (!defined('THEME_VERSION')) {
    $zhiji_parent_theme = wp_get_theme(get_template()); // get_template() = 'zibll'
    if ($zhiji_parent_theme->exists() && $zhiji_parent_theme->get('Version')) {
        define('THEME_VERSION', $zhiji_parent_theme->get('Version'));
    }
}

// 引入父主题核心函数（父主题会重复 define THEME_VERSION，精确屏蔽该条 Warning）
set_error_handler(function ($errno, $errstr) {
    return $errno === E_WARNING && false !== strpos($errstr, 'THEME_VERSION');
});
require_once get_theme_file_path('/inc/inc.php');
restore_error_handler();

// 在父主题之后引入子主题核心函数
require_once get_theme_file_path('/includes/index.php');

/**
 * 如果您需要添加一些自定义的PHP代码
 * 您可以在当前目录下新建一个 func.php 的文件，然后在最顶部写上 <?php ，再写入您的php代码
 * 主题会自动判断文件进行引入
 * 使用此方式在线更新主题的时候，func.php文件的内容将不会被覆盖（手动更新仍然会覆盖）
 * 当然需要注意php的代码规范，错误代码将会引起网站严重错误！
 */
if (file_exists(get_theme_file_path('/func.php'))) {
    require_once get_theme_file_path('/func.php');
}
