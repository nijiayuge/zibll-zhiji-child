<?php
/**
 * @module  PageCache
 * @desc    页面缓存：为游客生成静态 HTML 缓存，内容变化自动清除
 * @option  page_cache_enabled  总开关
 *          page_cache_ttl      缓存时长（小时）
 * @hook    init（优先级 1，先于业务输出判断是否命中缓存）
 *          save_post / delete_post / wp_insert_comment / comment_post /
 *          updated_option / switch_theme · 清空缓存
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/PageCache.php`（v1 生产验证过；改造点：
 *          zhiji_get → zhiji_get_option、类名对齐 Zhiji_* 规范）
 *          ⚠️ 与父主题 PJAX 共存：X-Requested-With 请求不缓存（保持父主题行为）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('page_cache', array(
    'title'    => '页面缓存',
    'parent'   => 'zhiji_basic',
    'priority' => 40,
    'option'   => 'page_cache_enabled',
));

/**
 * 页面缓存引擎（静态类：init 在文件加载时直调，保证 init 优先级 1 生效）
 */
class Zhiji_PageCache
{
    /**
     * 注册钩子（尽早尝试服务缓存 + 内容变化时失效）
     *
     * @return void
     */
    public static function init()
    {
        add_action('init', array(__CLASS__, 'maybe_serve'), 1);
        add_action('save_post', array(__CLASS__, 'clear_cache'));
        add_action('delete_post', array(__CLASS__, 'clear_cache'));
        add_action('wp_insert_comment', array(__CLASS__, 'clear_cache'));
        add_action('comment_post', array(__CLASS__, 'clear_cache'));
        add_action('updated_option', array(__CLASS__, 'clear_cache'));
        add_action('switch_theme', array(__CLASS__, 'clear_cache'));
    }

    /**
     * 是否启用
     *
     * @return bool
     */
    public static function enabled()
    {
        return zhiji_is_enabled('page_cache_enabled');
    }

    /**
     * 缓存目录
     *
     * @return string
     */
    public static function cache_dir()
    {
        return trailingslashit(WP_CONTENT_DIR) . 'cache/zhiji-page';
    }

    /**
     * 缓存时长（秒）
     *
     * @return int
     */
    public static function ttl()
    {
        $h = (int) zhiji_get_option('page_cache_ttl', 10);
        if ($h < 1) {
            $h = 10;
        }
        return $h * HOUR_IN_SECONDS;
    }

    /**
     * 当前请求是否可缓存（游客 + 非 AJAX/CRON/CLI/REST + 无动态参数）
     *
     * @return bool
     */
    public static function is_cacheable()
    {
        if (!self::enabled()) {
            return false;
        }
        // 登录用户（含 cookie 判断，覆盖部分静态缓存场景）
        if (is_user_logged_in()) {
            return false;
        }
        if (isset($_COOKIE['wordpress_logged_in']) || isset($_COOKIE['comment_author'])) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            return false;
        }
        // 后台 / AJAX / CRON / CLI
        if (is_admin() || wp_doing_ajax()) {
            return false;
        }
        if ((defined('DOING_CRON') && DOING_CRON) || (defined('WP_CLI') && WP_CLI)) {
            return false;
        }
        // ⚠️ 联调发现：php -r / php script.php 不定义 WP_CLI 常量，会漏进缓存逻辑，
        // 导致 CLI 脚本输出被当作页面缓存写盘（Chrome 访问首页拿到脚本输出）。
        // 故直接按 SAPI 排除所有命令行环境。
        if ('cli' === PHP_SAPI || 'phpdbg' === PHP_SAPI) {
            return false;
        }
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if (false !== strpos($uri, 'wp-json') || false !== strpos($uri, 'rest_route') || false !== strpos($uri, 'admin-ajax')) {
            return false;
        }
        // 提交请求不缓存
        if (!empty($_POST)) {
            return false;
        }
        // 父主题 PJAX 请求不缓存（保持父主题既有行为）
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 'xmlhttprequest' === strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH'])) {
            return false;
        }
        // 动态/个性化 query 参数不缓存：搜索、翻页、预览、评论分页、定制器
        if (!empty($_GET)) {
            $block = array('s', 'paged', 'preview', 'cpage', 'replytocom', 'customize_changeset_uuid');
            foreach ($block as $k) {
                if (isset($_GET[$k])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 只判断存在性，不取值
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * 请求缓存键
     *
     * @return string
     */
    public static function request_key()
    {
        $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
        $uri  = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
        return md5($host . $uri);
    }

    /**
     * 缓存文件路径
     *
     * @param string $key
     * @return string
     */
    public static function cache_file($key)
    {
        return trailingslashit(self::cache_dir()) . $key . '.html';
    }

    /**
     * init 阶段：命中直接输出并终止；未命中开启输出缓冲
     *
     * @return void
     */
    public static function maybe_serve()
    {
        if (!self::is_cacheable()) {
            return;
        }
        $file = self::cache_file(self::request_key());
        if (file_exists($file) && (time() - filemtime($file)) < self::ttl()) {
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Zhiji-Cache: HIT');
            readfile($file);
            exit;
        }
        ob_start(array(__CLASS__, 'buffer_callback'));
    }

    /**
     * 输出缓冲回调：符合条件时落盘缓存
     *
     * @param string $html
     * @return string
     */
    public static function buffer_callback($html)
    {
        if (!self::enabled() || strlen((string) $html) < 500) {
            return $html;
        }
        // 防御：输出中包含后台资源（异常场景）则不缓存
        if (false !== strpos($html, 'wp-admin/load-scripts') || false !== strpos($html, 'wp-admin/load-styles')) {
            return $html;
        }
        $dir = self::cache_dir();
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        if (is_dir($dir) && is_writable($dir)) {
            @file_put_contents(self::cache_file(self::request_key()), $html, LOCK_EX); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }
        return $html;
    }

    /**
     * 清空全部页面缓存
     *
     * @return void
     */
    public static function clear_cache()
    {
        $dir = self::cache_dir();
        if (!is_dir($dir)) {
            return;
        }
        $files = glob(trailingslashit($dir) . '*.html');
        if (is_array($files)) {
            foreach ($files as $f) {
                @unlink($f); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            }
        }
    }
}

Zhiji_PageCache::init();

/* ============================================================
 * 后台字段
 * ============================================================ */
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('page_cache', array(
        array(
            'id'      => 'page_cache_enabled',
            'type'    => 'switcher',
            'title'   => '启用页面缓存',
            'label'   => '为游客访问生成静态 HTML 缓存，大幅降低打开速度（推荐开启）；保存任何设置会自动清除缓存',
            'default' => false,
        ),
        array(
            'id'         => 'page_cache_ttl',
            'type'       => 'number',
            'title'      => '缓存时长（小时）',
            'desc' => __( '页面静态缓存的过期时间（小时），过期后重新生成。', 'zhiji' ),
            'label'      => '缓存过期后自动重新生成；发布文章 / 新评论时立即清除全部缓存',
            'default'    => 10,
            'min'        => 1,
            'max'        => 72,
            'dependency' => array('page_cache_enabled', '==', '1'),
        ),
    ), 20);
