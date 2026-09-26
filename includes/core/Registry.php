<?php
/**
 * @module  Registry
 * @desc    模块注册表与后台分节助手
 *          - 扫描 includes/modules/*.php 得到模块清单
 *          - 模块自注册（标题/所属分类/priority/主开关），供功能树与后台分节生成
 *          - CSF 分节注册走"懒构建"：仅在本主题设置页或 CSF 自身 ajax 时才落字段
 * @since   2.0.0
 */

defined('ABSPATH') || exit;

class Zhiji_Registry
{
    /** @var array 模块元数据：key => [title, parent, priority, option, file] */
    private static $modules = array();

    /**
     * 扫描业务模块（返回给 zib_require 的相对路径数组）
     *
     * @return array
     */
    public static function scan_module_files()
    {
        $files = array();
        foreach ((array) glob(ZHIJI_INC . 'modules/*.php') as $path) {
            $files[] = 'includes/modules/' . basename($path, '.php');
        }
        sort($files);
        return $files;
    }

    /**
     * 模块自注册（每个 modules/*.php 文件顶部调用）
     *
     * @param string $key 模块唯一 key（= 文件名，snake_case）
     * @param array  $args title / parent(zhiji_xxx) / priority / option(主开关 key)
     * @return void
     */
    public static function register_module($key, array $args = array())
    {
        self::$modules[$key] = wp_parse_args($args, array(
            'title'    => $key,
            'parent'   => '',
            'priority' => 100,
            'option'   => $key . '_enabled',
        ));
    }

    /**
     * 全部已注册模块（供功能树/调试使用）
     */
    public static function modules()
    {
        return self::$modules;
    }

    /**
     * 模块是否启用（统一读主开关）
     */
    public static function module_enabled($key)
    {
        if (!isset(self::$modules[$key])) {
            return false;
        }
        return zhiji_is_enabled(self::$modules[$key]['option'], false);
    }

    /**
     * 是否处于"本主题设置页或 CSF 自身 ajax"上下文
     * —— 懒构建判据：只有这两种情况才注册字段 / 允许写默认值
     */
    public static function is_own_context()
    {
        if (!is_admin()) {
            return false;
        }
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($page === ZHIJI_OPTION_KEY) {
            return true;
        }
        $action = isset($_POST['action']) ? sanitize_text_field(wp_unslash($_POST['action'])) : '';
        return ($action && false !== strpos($action, 'csf_' . ZHIJI_OPTION_KEY));
    }

    /**
     * 注册一个 CSF 分节（自动套用懒构建）
     *
     * @param array $args parent / title / priority / fields / icon / id
     * @return void
     */
    public static function csf_section(array $args)
    {
        if (!class_exists('CSF')) {
            return;
        }
        // 顶层分类在所有后台请求都注册（保证菜单出现），分节字段只在自身上下文注册
        $has_parent = !empty($args['parent']);
        if ($has_parent && !self::is_own_context()) {
            return;
        }
        CSF::createSection(ZHIJI_OPTION_KEY, $args);
    }

    /**
     * 按模块元数据自动生成分节（模块只需声明 register_module，无需重复写 CSF）
     *
     * @param string $key 模块 key
     * @param array  $fields 字段定义
     * @return void
     */
    public static function csf_section_for($key, array $fields)
    {
        if (!isset(self::$modules[$key])) {
            return;
        }
        $m = self::$modules[$key];
        self::csf_section(array(
            'parent'   => $m['parent'],
            'title'    => $m['title'],
            'priority' => $m['priority'],
            'fields'   => $fields,
        ));
    }

    /**
     * 兼容 v1 迁移：接收 v1 风格的完整 section 数组（含 title/parent/priority/fields），
     * 只取 fields，分节元信息以 register_module 注册表为准。
     *
     * 用途：v1 模块的 `CSF::createSection( ZHIJI_CHILD_OPTION_KEY, array( ... ) )`
     * 只需替换函数名即可完成迁移，无需改动括号结构（避免大文件手术风险）。
     *
     * @param string $key     模块 key
     * @param array  $section v1 风格 section 定义
     * @return void
     */
    public static function csf_section_for_legacy($key, array $section)
    {
        $fields = isset($section['fields']) ? (array) $section['fields'] : array();
        self::csf_section_for($key, $fields);
    }

    /* ============================================================
     * 设置分节统一登记（2026-09-26，配置统一化探查报告 P3-⑨）
     *
     * 背景：44 个模块各自 `add_action('after_setup_theme', function () { ... })`
     *       注册自己的分节，共 47 处钩子，且写法重复。
     *
     * 收口后：模块只需调用 `Zhiji_Registry::register_options()` **登记**，
     *        由本类在**同一个钩子**中统一落盘 —— 47 个钩子收敛为 1 个。
     *        （仅收敛挂载点，字段定义与配置 key 完全不变。）
     * ============================================================ */

    /** @var array 待落盘的设置分节 */
    private static $pending_sections = array();

    /** @var bool 统一钩子是否已挂载 */
    private static $boot_hooked = false;

    /**
     * 登记一个模块的设置分节（替代模块各自 add_action('after_setup_theme', ...)）
     *
     * @param string $key      模块 key
     * @param array  $fields   字段定义
     * @param int    $priority 落盘优先级（等同原 add_action 的第三参数）
     * @return void
     */
    public static function register_options($key, array $fields, $priority = 20)
    {
        self::$pending_sections[] = array(
            'key'      => (string) $key,
            'fields'   => $fields,
            'priority' => (int) $priority,
        );
        // 惰性挂载：首次登记时挂一次统一钩子
        if (!self::$boot_hooked) {
            self::$boot_hooked = true;
            add_action('after_setup_theme', array(__CLASS__, 'boot_sections'), 20);
        }
    }

    /**
     * 按优先级顺序统一落盘所有已登记的分节
     *
     * @return void
     */
    public static function boot_sections()
    {
        $list = self::$pending_sections;
        usort($list, function ($a, $b) {
            return $a['priority'] - $b['priority'];
        });
        foreach ($list as $item) {
            self::csf_section_for($item['key'], $item['fields']);
        }
    }

    /**
     * 已登记的分节数量（自查 / 调试用）
     *
     * @return int
     */
    public static function pending_section_count()
    {
        return count(self::$pending_sections);
    }
}
