<?php
/**
 * 知集（zhiji）子主题唯一入口
 *
 * 加载顺序（改动前务必阅读《新子主题-v2/03-工程结构与规范.md》）：
 *   ① 核心层 core/      —— 常量、工具、父主题适配层、模块注册表
 *   ② 配置层 options/   —— CSF 设置页与保存/备份动作
 *   ③ 功能层 functions/ —— 主题级函数
 *   ④ 业务模块 modules/ —— 一功能一文件，自注册 + 独立开关
 *
 * ⚠️ 本目录（includes/）是子主题入口，**禁止**在子主题内新建 inc/inc.php：
 *    框架 functions.php 用 get_theme_file_path('/inc/inc.php') 加载父主题核心，
 *    而该函数"子主题有同名文件则优先子主题"，一旦同名将导致父主题核心不加载（白屏）。
 */

defined('ABSPATH') || exit;

/**
 * 读取主题配置项（唯一实现，禁止在别处重复定义）
 *
 * @param string $name    配置键
 * @param mixed  $default 键不存在时的默认值
 * @param string $subname 嵌套子键（可选）
 * @return mixed
 */
function zhiji_get_option($name, $default = false, $subname = '')
{
    static $options = null;
    if ($options === null) {
        $options = get_option('zhiji_options');
    }
    if (!is_array($options)) {
        return $default;
    }
    if (!isset($options[$name])) {
        return $default;
    }
    if ($subname) {
        return isset($options[$name][$subname]) ? $options[$name][$subname] : $default;
    }
    return $options[$name];
}

// ① 核心层：常量 / 工具 / 父主题适配层 / 模块注册表
zib_require(array(
    'core/Constants',
    'core/Helpers',
    'core/Adapter',
    'core/Registry',
), true, 'includes/');

// ②③ 配置层与功能层（框架既有结构，保持不动）
zib_require(array(
    'includes/options/options',
    'includes/functions/functions',
), true);

// ④ 业务模块层：目录扫描 + 自注册（每个模块自带独立开关，关闭即零开销）
$zhiji_modules = Zhiji_Registry::scan_module_files();
if (!empty($zhiji_modules)) {
    zib_require($zhiji_modules, true);
}

// ⑤ 对外唯一就绪信号：其它扩展可挂此钩子
do_action('zhiji_loaded');
