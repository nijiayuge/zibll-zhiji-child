<?php
/**
 * 知集（zhiji）子主题唯一入口
 *
 * 加载顺序（改动前务必阅读《新子主题-v2/03-工程结构与规范.md》）：
 *   ① 核心层 core/      —— 常量、配置门面、工具、父主题适配层、模块注册表
 *   ② 配置层 options/   —— CSF 设置页与保存/备份动作
 *   ③ 功能层 functions/ —— 主题级函数
 *   ④ 业务模块 modules/ —— 一功能一文件，自注册 + 独立开关
 *
 * ⚠️ 本目录（includes/）是子主题入口，**禁止**在子主题内新建 inc/inc.php：
 *    框架 functions.php 用 get_theme_file_path('/inc/inc.php') 加载父主题核心，
 *    而该函数"子主题有同名文件则优先子主题"，一旦同名将导致父主题核心不加载（白屏）。
 */

defined('ABSPATH') || exit;

// ① 核心层：常量 / 配置门面 / 工具 / 父主题适配层 / 模块注册表
// ⚠️ core/Options 必须位于 core 层**最靠前**（紧随 Constants）：它提供 zhiji_get_option()，
  //    原实现位于本文件顶层（早于所有 require），故 core 层任何文件在**加载期**调用它都成立。
  //    迁入 core/Options.php 后，只有保证它最先加载才能维持这一既有事实（详见该文件头部说明）。

  // ── P0/P1 新增：必须先于其余 core 加载 ──────────────────────────────
  //  ⚠️ 不可用 zib_require()：它走 get_theme_file_path()，父主题存在同名目录时
  //     （zibll/includes/）会解析到父主题去，导致 "Failed opening required .../zibll/includes/contracts/Contracts.php"
  //     —— 站点直接 500。故此处显式用 get_stylesheet_directory() 锁定子主题。
  //     加载顺序要求：Contracts（接口）→ EventBus → 其余 core → Manifest → ContractRegistry → DependencyGuard
  //     ContractRegistry 必须在 Manifest 之后（靠 Manifest 的 provides 找实现）、在模块之前
  //     （否则解析契约时实现方的工厂函数还不存在）。
  $zhiji_child_inc = get_stylesheet_directory() . '/includes/';
  require_once $zhiji_child_inc . 'contracts/Contracts.php';
  require_once $zhiji_child_inc . 'core/EventBus.php';

  zib_require(array(
      'core/Constants',
      'core/Options',
      'core/Helpers',
      'core/Fields',
      'core/Adapter',
      'core/Registry',
      'core/PageProvisioner',
      'core/Assets',
      'core/ApiRegistry',
      'core/ClaimLog',
      'core/EventLog',
      'core/Ops',
      'core/CopyToast',
  ), true, 'includes/');

  // 依赖清单与守卫：必须在 Registry 之后（要用 Registry::modules()/module_enabled()）
  require_once $zhiji_child_inc . 'core/Manifest.php';
  require_once $zhiji_child_inc . 'core/ContractRegistry.php';
  require_once $zhiji_child_inc . 'core/DependencyGuard.php';

// ② 通知层：统一通知中心（事件表 → 分发 → 渠道），业务模块只允许通过 zhiji_notify() 发通知
zib_require(array(
    'notify/Events',
    'notify/Notify',
    'notify/Channels/Msg',
    'notify/Channels/Mail',
    'notify/Channels/Toast',
    'notify/Channels/Badge',
    'notify/Channels/Wechat',
), true, 'includes/');

// ③ 配置层与功能层（框架既有结构，保持不动）
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
