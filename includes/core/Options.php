<?php
/**
 * @module  Options
 * @desc    主题配置门面 —— **全项目唯一的配置读取/写入入口**
 *          - 读取：zhiji_get_option()（唯一实现；从 includes/index.php 迁入）
 *          - 开关判定：zhiji_is_enabled()（唯一实现；从 core/Helpers.php 迁入）
 *          - 类型归一：zhiji_option_bool() / zhiji_option_int()
 *          - 写入：zhiji_update_option()（模块保存配置的唯一入口，禁止模块直接 update_option）
 *          - 缓存：zhiji_options_flush()（清请求内静态缓存）
 * @since   2.0.0（2026-09-28 由「后台设置模块化审查」P1-A 收口）
 *
 * ─────────────────────────────────────────────────────────────
 * ⚠️ 加载顺序约束（改动前必读）
 *
 * 本文件必须**排在 core 加载列表的第一位**（includes/index.php 的 zib_require 数组）。
 * 原因：原实现把 zhiji_get_option() 写在 includes/index.php 的**顶层**（早于所有 require），
 * 因此任何 core/ 文件在**加载期**调用它都成立。迁入本文件后，只有保证本文件最先加载，
 * 才能维持「core 层任何文件在加载期都可调用」这一既有事实。
 * （ZHIJI_OPTION_KEY 定义在 Constants.php，但只在运行期求值，故不构成顺序依赖。）
 * ─────────────────────────────────────────────────────────────
 */

defined('ABSPATH') || exit;

/**
 * 配置静态缓存的持有者（**仅供同文件内的读取/清空使用**）
 *
 * 之所以单独抽一个返回引用的函数：PHP 没有"重置函数内 static"的语法。
 * 让 zhiji_get_option() 通过引用读取同一个 static，即可做到真正的清空
 * —— 而不是各持一份 static（那样 flush 会失效，是个隐蔽陷阱）。
 *
 * @return mixed 引用
 */
function &zhiji_options_cache()
{
    static $options = null;

    return $options;
}

/**
 * 清空请求内的配置静态缓存
 *
 * 用途：`zhiji_get_option()` 用请求内静态缓存（每请求只读一次 options 表）。
 * 代价是**同进程内写后立即读会拿到旧值** —— `新子主题-v2/09-M2-迁移记录.md:74` 记录过该陷阱
 * （CLI 脚本据此误判成"关闭开关后仍有输出"）。
 * 写入路径（zhiji_update_option）会自动调用本函数；CLI 脚本直接改库后也可手动调用。
 *
 * @return void
 */
function zhiji_options_flush()
{
    $cache = &zhiji_options_cache();
    $cache = null;
}

/**
 * 读取主题配置项（唯一实现，禁止在别处重复定义）
 *
 * 【P4 新增：旧键兼容】
 * 若该键在 `zhiji_config_key_aliases()` 里登记了旧名，且新键未设、旧键有值，
 * 则回落使用旧键的值 —— 这样「改名」不需要用户手动重填配置。
 * ⚠️ 现有 174 个字段**均未登记别名**，故此分支对当前所有键都是死路径，
 *    行为与改造前完全一致（这是刻意的：P4 不引入任何行为变更）。
 *
 * @param string $name    配置键
 * @param mixed  $default 键不存在时的默认值
 * @param string $subname 嵌套子键（可选）
 * @return mixed
 */
function zhiji_get_option($name, $default = false, $subname = '')
{
    // ⚠️ 注意：这里是**引用**，赋值即写入静态缓存；不要改成普通赋值（会让 flush 失效）
    $options = &zhiji_options_cache();

    if ($options === null) {
        $options = get_option(ZHIJI_OPTION_KEY);
    }
    if (!is_array($options)) {
        return $default;
    }
    if (!isset($options[$name])) {
        // 旧键兼容：新键无值时，尝试用登记的旧键（P4；当前无键登记此分支，等价于原行为）
        $legacy = zhiji_config_legacy_value($name, $options);
        if (null !== $legacy) {
            return $subname
                ? (isset($legacy[$subname]) ? $legacy[$subname] : $default)
                : $legacy;
        }
        return $default;
    }
    if ($subname) {
        return isset($options[$name][$subname]) ? $options[$name][$subname] : $default;
    }

    return $options[$name];
}

/**
 * 取某个键的旧键值（无别名登记时返回 null）
 *
 * 单独成函数而非把逻辑塞进 zhiji_get_option，是为了让 Options.php 不必
 * 在加载期就依赖 ConfigSchema.php（后者排在更后面加载）。
 *
 * @param string $name    新键
 * @param array  $options 已加载的 options 数组
 * @return mixed null = 无别名可用
 */
function zhiji_config_legacy_value($name, array $options)
{
    if (!function_exists('zhiji_config_key_aliases')) {
        return null;
    }
    $aliases = zhiji_config_key_aliases();
    if (empty($aliases[$name])) {
        return null;
    }
    foreach ((array) $aliases[$name] as $old) {
        if (isset($options[$old])) {
            return $options[$old];
        }
    }
    return null;
}

/**
 * 开关判定：CSF 的 switcher 存的是字符串 '0'/'1'，必须统一用布尔解析
 *
 * @param string $key
 * @param bool   $default
 * @return bool
 */
function zhiji_is_enabled($key, $default = false)
{
    return (bool) filter_var(zhiji_get_option($key, $default), FILTER_VALIDATE_BOOLEAN);
}

/**
 * 读取布尔开关（zhiji_is_enabled 的语义化别名；新代码建议用这个）
 *
 * @param string $key
 * @param bool   $default
 * @return bool
 */
function zhiji_option_bool($key, $default = false)
{
    return zhiji_is_enabled($key, $default);
}

/**
 * 读取整数配置并夹取到区间内（新代码建议用这个，避免各处手写 max/min）
 *
 * @param string $key
 * @param int    $default
 * @param int    $min
 * @param int    $max
 * @return int
 */
function zhiji_option_int($key, $default = 0, $min = null, $max = null)
{
    $v = (int) zhiji_get_option($key, $default);
    if (null !== $min && $v < $min) {
        $v = $min;
    }
    if (null !== $max && $v > $max) {
        $v = $max;
    }

    return $v;
}

/**
 * 写入单个配置项（只改指定键，不动其它键；禁止在模块里直接 update_option）
 *
 * 写后自动清静态缓存，保证**同进程内写后读一致**。
 *
 * @param string $key
 * @param mixed  $value
 * @return bool
 */
function zhiji_update_option($key, $value)
{
    $options = get_option(ZHIJI_OPTION_KEY, array());
    if (!is_array($options)) {
        $options = array();
    }
    $options[$key] = $value;
    $ok = update_option(ZHIJI_OPTION_KEY, $options);
    zhiji_options_flush();

    return $ok;
}
