<?php
/**
 * @module  DependencyGuard
 * @desc    模块依赖守卫 —— 开启前校验、关闭前提示影响面
 * @since   2.0.0
 *
 * 2026-10-02（P1 架构重构）新增。
 *
 * 【解决什么问题】
 * 改造前模块的依赖关系只存在于人的记忆里，于是：
 *   · 打开抽奖时不会提示「它还需要优惠券发放和邮件模板」
 *   · 关掉邮件模板时不会提示「抽奖、邮件订阅等 5 个功能会失效」
 *   · 直到页面报错才发现依赖没满足
 * 本模块把 Manifest 的依赖数据接到两个时机：
 *   ① 保存设置后（csf_zhiji_options_saved）：校验依赖，缺依赖则自动补开并提示
 *   ② 后台渲染设置页时：在受影响模块的开关上方给出说明
 *
 * 【设计取舍：只提示不阻断】
 * 刻意**不阻断保存**。理由：
 *   · 依赖不满足时，模块实际不会工作（各模块 init 都会查自己的开关），
 *     此时强制拦截保存只会让管理员改不动配置、且错误信息不透明
 *   · 改为「自动补开依赖 + 明确提示」，保证保存后配置始终自洽
 *   · 真正的强制约束留给 P2 的契约层（缺契约实现时模块自身拒绝启用）
 *
 * 【零性能开销】
 * 本文件只在后台上下文挂载（is_admin() 为真时），前台请求不产生任何开销。
 */

defined('ABSPATH') || exit;

if (!is_admin()) {
    return;
}

/**
 * 当前「运行时实际可用」的模块集合
 *
 * ⚠️ 这里刻意**不用** Zhiji_Registry::module_enabled()。
 * 原因（本项目既有的语义分裂，不是本模块引入的）：
 *   Registry::module_enabled() 读 option，option 未设置即视为「关」；
 *   而 mail_template / reward_center 在代码里写的是
 *   zhiji_is_enabled('mail_template_enabled', true) —— 第二参数 true，**option 未设置时默认可用**。
 * 两种口径对「未设置」的处理相反，若依赖校验用前者，会把实际可用的模块判成不可用，
 * 产出假告警（例如 mail_template 未设置开关却被判为「5 个模块缺依赖」）。
 * → 此处按**运行时口径**：option 有值则用值，无值则回落到模块自己声明的默认值。
 *
 * @return array<int,string>
 */
function zhiji_depguard_enabled()
{
    $out = array();
    foreach (Zhiji_Registry::modules() as $key => $def) {
        if (zhiji_depguard_module_usable($key, $def)) {
            $out[] = $key;
        }
    }
    return $out;
}

/**
 * 扫描模块源码里 `zhiji_is_enabled('<key>_enabled', true)` 这类「默认开启」声明
 *
 * 背景：本项目里同一模块的「是否可用」有两套口径 ——
 *   · Registry::module_enabled()：option 未设置即视为「关」
 *   · 模块内部：zhiji_is_enabled('mail_template_enabled', true) 的第二参数为 true 时，
 *     未设置即「开」
 * mail_template 就属于后者（MailTemplate.php:92 明确写了默认值 true），
 * 它的 option 从未保存过，因此按 Registry 口径会判为不可用 —— 但它实际能工作。
 * 依赖校验必须跟随**运行时口径**，否则会报出「5 个模块缺 mail_template」这种假警。
 *
 * @param string $opt 主开关 option 名
 * @return bool 源码是否声明了默认开启
 */
function zhiji_depguard_source_default_on($opt)
{
    static $cache = array();
    if (isset($cache[$opt])) {
        return $cache[$opt];
    }
    $dir = get_stylesheet_directory() . '/includes/modules/';
    $hit = false;
    foreach ((array) glob($dir . '*.php') as $file) {
        $src = (string) @file_get_contents($file);
        if ('' === $src) {
            continue;
        }
        if (preg_match(
            "/zhiji_is_enabled\(\s*'" . preg_quote($opt, '/') . "'\s*,\s*true\s*\)/",
            $src
        )) {
            $hit = true;
            break;
        }
    }
    $cache[$opt] = $hit;
    return $hit;
}

/**
 * 单模块「运行时是否可用」
 *
 * @param string $key  模块 key
 * @param array  $def  register_module 的元数据
 * @return bool
 */
function zhiji_depguard_module_usable($key, $def)
{
    if (!empty($def['always_on'])) {
        return true;
    }
    $opt = isset($def['option']) ? $def['option'] : '';
    if ('' === $opt) {
        return false;
    }
    $stored = zhiji_get_option($opt, null);
    if (null !== $stored) {
        return (bool) $stored;   // 已保存：以值为准
    }
    // 未保存：先看注册元数据，再看模块源码里是否声明了默认开启
    if (!empty($def['enabled_default'])) {
        return true;
    }
    return zhiji_depguard_source_default_on($opt);
}

/**
 * 平台能力的中文名（用于提示文案）
 *
 * @param string $key 模块 key
 * @return string
 */
function zhiji_depguard_label($key)
{
    $mods = Zhiji_Registry::modules();
    if (isset($mods[$key]['title'])) {
        return (string) $mods[$key]['title'];
    }
    return $key;
}

/**
 * 检查依赖是否满足（只读，不改配置）
 *
 * ⚠️ 刻意**不做自动补开**。理由：补开意味着替管理员改开关设置，
 * 而「模块未设置开关」在本项目里可能就等于「用模块自己的默认值」——
 * 强行写 1 会改变运行时行为（例如把默认关闭的模块打开）。
 * 这里只**如实报告**缺什么，由管理员自己决定。
 *
 * @return array<string,array<int,string>> 键为模块，值为缺失的依赖
 */
function zhiji_depguard_check()
{
    $enabled = zhiji_depguard_enabled();
    return zhiji_manifest_validate($enabled);
}

/**
 * 在设置页顶部列出依赖状况
 *
 * 两块内容：
 *   ① 循环依赖警告（有环时优先提示）
 *   ② 平台能力复用情况 + 当前配置下的缺失依赖
 *
 * @return string HTML
 */
function zhiji_depguard_notice_html()
{
    $report = zhiji_manifest_report();
    if (!empty($report['cycles'])) {
        $rows = '';
        foreach ($report['cycles'] as $c) {
            $rows .= '<li><code>' . esc_html(implode(' → ', $c)) . '</code></li>';
        }
        return '<div class="notice notice-warning"><p><strong>模块依赖提示</strong></p>'
            . '<p>检测到循环依赖，关闭相关开关可能产生非预期行为：</p><ul>' . $rows . '</ul></div>';
    }

    $lines = array();
    foreach (zhiji_manifest() as $key => $def) {
        if ('platform' !== $def['layer']) {
            continue;
        }
        $deps = zhiji_manifest_dependents($key);
        if ($deps) {
            $names = array_map('zhiji_depguard_label', $deps);
            $lines[] = sprintf(
                '%s：被 %d 个功能复用（%s）',
                zhiji_depguard_label($key),
                count($deps),
                esc_html(implode('、', $names))
            );
        }
    }

    $html = '';
    // 缺失依赖：如实报告，不擅自改配置
    $check = zhiji_depguard_check();
    if (!empty($check['missing'])) {
        $items = '';
        foreach ($check['missing'] as $mod => $deps) {
            $items .= '<li><code>' . esc_html(zhiji_depguard_label($mod)) . '</code> 需要：'
                . esc_html(implode('、', $deps)) . '</li>';
        }
        $html .= '<div class="notice notice-warning"><p><strong>依赖未满足</strong></p>'
            . '<p>以下功能已启用，但其依赖的能力当前不可用：</p><ul>' . $items . '</ul></div>';
    }
    if ($lines) {
        $items = '';
        foreach ($lines as $l) {
            $items .= '<li>' . esc_html($l) . '</li>';
        }
        $html .= '<div class="notice notice-info"><p><strong>平台能力复用情况</strong></p><ul>'
            . $items . '</ul></div>';
    }
    return $html;
}

/**
 * 保存设置后：把缺失依赖记下来，在页面顶部提示
 *
 * 只记录不修改配置（见 zhiji_depguard_check 的说明）。
 */
function zhiji_depguard_on_save()
{
    $check = zhiji_depguard_check();
    if (!empty($check['missing'])) {
        set_transient('zhiji_depguard_missing', $check['missing'], 60);
    }
}
add_action('csf_zhiji_options_saved', 'zhiji_depguard_on_save', 30);

/**
 * 一次性提示（若有缺失依赖）
 */
function zhiji_depguard_flash()
{
    $missing = get_transient('zhiji_depguard_missing');
    if (!$missing) {
        return;
    }
    delete_transient('zhiji_depguard_missing');
    $items = '';
    foreach ((array) $missing as $mod => $deps) {
        $items .= '<li><code>' . esc_html(zhiji_depguard_label($mod)) . '</code> 需要：'
            . esc_html(implode('、', (array) $deps)) . '</li>';
    }
    printf(
        '<div class="notice notice-warning is-dismissible"><p><strong>依赖未满足</strong>：以下功能的依赖未启用，它们可能无法正常工作。</p><ul>%s</ul></div>',
        $items
    );
}
add_action('admin_notices', 'zhiji_depguard_flash', 5);

/**
 * 渲染设置页顶部的依赖说明
 *
 * 只在本主题设置页显示（与 PageProvisioner 的既有范式一致：比对 page 参数）。
 * 注意：不能用 zhiji_update_notices —— 该钩子全仓无人 do_action，是死钩子。
 */
function zhiji_depguard_render()
{
    if (!is_admin()) {
        return;
    }
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if ($page !== ZHIJI_OPTION_KEY) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    $html = zhiji_depguard_notice_html();
    if ($html) {
        echo $html;
    }
}
add_action('admin_notices', 'zhiji_depguard_render', 20);
