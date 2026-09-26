<?php
/**
 * @module  Constants
 * @desc    主题常量：版本、路径、URL、选项库 key
 * @since   2.0.0
 */

defined('ABSPATH') || exit;

/**
 * 版本号：单一来源 = style.css 的 Child Version（禁止在别处硬编码版本）
 */
if (!defined('ZHIJI_VERSION')) {
    $zhiji_ver = '2.0.0';
    $zhiji_style = get_stylesheet_directory() . '/style.css';
    if (is_readable($zhiji_style)) {
        $zhiji_head = (string) @file_get_contents($zhiji_style, false, null, 0, 2048);
        if (preg_match('/Child\s+Version:\s*([0-9][0-9.]*)/i', $zhiji_head, $zhiji_m)) {
            $zhiji_ver = $zhiji_m[1];
        }
    }
    define('ZHIJI_VERSION', $zhiji_ver);
}

/** 主题根目录（带尾斜杠） */
if (!defined('ZHIJI_PATH')) {
    define('ZHIJI_PATH', trailingslashit(get_stylesheet_directory()));
}

/** 主题根 URL（带尾斜杠） */
if (!defined('ZHIJI_URL')) {
    define('ZHIJI_URL', trailingslashit(get_stylesheet_directory_uri()));
}

/** 主题代码目录（core/ notify/ modules/ 所在目录，带尾斜杠）
 *  注意：本项目结构是 includes/，**不是** inc/ —— 子主题内不得有 inc/inc.php（白屏红线），
 *  写成 'inc/' 会导致模块扫描 glob 落空、所有模块静默不加载。
 */
if (!defined('ZHIJI_INC')) {
    define('ZHIJI_INC', ZHIJI_PATH . 'includes/');
}

/** 静态资源 URL（带尾斜杠）—— 所有资源必须放这里，禁止引用外部 CDN */
if (!defined('ZHIJI_ASSETS_URL')) {
    define('ZHIJI_ASSETS_URL', ZHIJI_URL . 'assets/zhiji/');
}

/** 主题配置在 wp_options 中的 key（唯一，禁止再出现别的写法） */
if (!defined('ZHIJI_OPTION_KEY')) {
    define('ZHIJI_OPTION_KEY', 'zhiji_options');
}

/** 主题备份用的 option key */
if (!defined('ZHIJI_BACKUP_KEY')) {
    define('ZHIJI_BACKUP_KEY', 'zhiji_options_backup');
}

/**
 * 模块清单缓存的 transient key（见 Zhiji_Registry::scan_module_files）
 * 存 {fp: 目录 mtime, files: [...] }；无过期时间，靠目录 mtime 指纹失效。
 */
if (!defined('ZHIJI_MODULE_LIST_TRANSIENT')) {
    define('ZHIJI_MODULE_LIST_TRANSIENT', 'zhiji_module_files');
}
