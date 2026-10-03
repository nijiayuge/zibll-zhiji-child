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
  //     加载顺序要求：Contracts（接口）→ EventBus → 其余 core → Manifest → ContractRegistry
  //     → ConfigSchema → ConfigJanitor → DependencyGuard
  //     ContractRegistry 必须在 Manifest 之后（靠 Manifest 的 provides 找实现）、在模块之前
  //     （否则解析契约时实现方的工厂函数还不存在）。ConfigSchema 同理依赖 Registry。
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
  // 配置键注册表（P4）：必须在 Registry 之后（要 Registry::modules() 才知道模块 option）
  require_once $zhiji_child_inc . 'core/ConfigSchema.php';
  // 配置回收器（P4）：依赖 ConfigSchema 的废弃键表；文件内部自带 is_admin/WP-CLI 守卫
  require_once $zhiji_child_inc . 'core/ConfigJanitor.php';
  // 后台设置折叠分组（P4）：基于 subheading，替代不可用的 accordion 字段
  require_once $zhiji_child_inc . 'core/FieldCollapse.php';
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
//
// 【惰性装载（P6，2.2.0）】—— 仅对「纯后台模块」延后加载。
//
// 设计约束（实测得出，勿随意扩大范围）：
//   · 只有「加载期只挂 admin_menu / admin_post_* / wp_ajax_* / 后台专用 API」的模块
//     才可延后；它们在前台请求中注册的东西前台根本用不到。
//   · 任何挂了前台钩子（wp_footer / the_content / wp_head …）或 **cron** 的模块
//     **绝不可延后** —— cron 在无 admin 上下文中触发，延后会导致定时任务不执行。
//   · 判定依据 = VM 实测：42 模块中只有 security_scanner / seed_pages 满足条件，
//     且这两个模块定义的函数**零外部引用**（已逐函数 grep 确认）。
//   · 收益：前台请求少解析 ~30KB；风险：0（这两个模块前台从不被调用）。
//   · 逃生开关：定义 ZHIJI_NO_LAZY_MODULES 为 true 即恢复全量加载。
$zhiji_modules = Zhiji_Registry::scan_module_files();

// 纯后台模块白名单 —— 值 = **模块文件名（不含 .php，PascalCase，与 scan_module_files 口径一致）**。
// 新增前必须：① 确认其加载期只挂 admin_* / admin_post_* / wp_ajax_* / 后台专用 API；
//             ② 确认其定义的函数无前台调用点（逐函数 grep）；③ 确认其无 cron 注册。
$zhiji_admin_only = array( 'SecurityScanner', 'SeedPages' );

// 判定「当前请求需要后台模块」。
// 实测（VM 前台 HTTP 请求）：本文件在 functions.php 顶层执行时，
// `is_admin()` / `WP_ADMIN` / `DOING_AJAX` 均已可**准确**判定 ——
// 前台首页实测得到 is_admin=F WP_ADMIN=F DOING_AJAX=F，故判定可靠、无需延迟补加载。
// 只要任一为真即**全量加载**（保守），仅「确定是纯前台」才走惰性分支。
$zhiji_is_admin_ctx = is_admin()
    || ( defined( 'WP_ADMIN' ) && WP_ADMIN )
    || ( defined( 'DOING_AJAX' ) && DOING_AJAX )
    || ( defined( 'DOING_CRON' ) && DOING_CRON )
    || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
    || ( defined( 'WP_CLI' ) && WP_CLI );

$zhiji_lazy_off = defined( 'ZHIJI_NO_LAZY_MODULES' ) && ZHIJI_NO_LAZY_MODULES;

if ( ! empty( $zhiji_modules )
    && ! $zhiji_is_admin_ctx
    && ! $zhiji_lazy_off
    && ! empty( $zhiji_admin_only ) ) {
    // ---- 惰性路径（确定是纯前台请求）----
    $zhiji_load = array();
    foreach ( $zhiji_modules as $zhiji_mod ) {
        if ( ! in_array( basename( $zhiji_mod ), $zhiji_admin_only, true ) ) {
            $zhiji_load[] = $zhiji_mod;
        }
    }
    if ( ! empty( $zhiji_load ) ) {
        zib_require( $zhiji_load, true );
    }
    // ⚠️ 这里**不做** after_setup_theme 补加载：
    //    实测发现「无条件补加载」会让惰性完全失效（前台仍是 42 模块）。
    //    而本文件的执行时机已能准确判定上下文，故无需兜底。
} elseif ( ! empty( $zhiji_modules ) ) {
    // ---- 全量路径（后台 / AJAX / cron / CLI / 逃生开关）----
    zib_require( $zhiji_modules, true );
}

// ⑤ 对外唯一就绪信号：其它扩展可挂此钩子
do_action('zhiji_loaded');
