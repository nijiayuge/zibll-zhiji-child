<?php
/**
 * @module  OpsConsole
 * @desc    运维管理页面：集中管理需要人工干预的业务状态与可变配置（场景注册制）。
 *          当前场景：邮箱领取限制（退出挽留弹窗领券记录，可按邮箱查询/重置/删除）。
 *          后台路径：后台左侧菜单「知集运维」。
 * @option  ops_console_enabled       运维页面总开关
 *          ops_console_clear_enabled 是否允许运维清除（总闸：关掉后只能查询）
 *          ops_console_log_enabled   是否记录领取日志（关掉则不做持久化校验）
 *          ops_console_blocked_stat  是否统计被拦截次数
 *          ops_console_per_page      列表每页条数
 *          ops_console_retention_days 记录保留天数（0=永久）
 *          ops_scene_claim_enabled   场景开关：邮箱领取限制
 * @hook    wp_loaded · 按保留天数自动清理过期记录（每日至多一次）
 * @since   2.0.0
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('ops_console', array(
    'title'    => '运维管理页面',
    'parent'   => 'zhiji_basic',
    'priority' => 8,
    'option'   => 'ops_console_enabled',
));

    // 2026-09-27：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('ops_console', array(
        array(
            'id'      => 'ops_console_enabled',
            'type'    => 'switcher',
            'title'   => '启用运维管理页面',
            'default' => true,
            'desc'    => '启用后后台左侧出现「知集运维」菜单，集中管理需要人工干预的业务状态与可变配置。',
        ),
        array(
            'id'         => 'ops_console_clear_enabled',
            'type'       => 'switcher',
            'title'      => '允许运维清除数据',
            'default'    => true,
            'desc'       => '总闸。关闭后运维页面只能查询，不能重置/删除任何记录；HTTP 清除接口同步拒绝。',
            'dependency' => array('ops_console_enabled', '==', '1'),
        ),
        array(
            'id'         => 'ops_console_log_enabled',
            'type'       => 'switcher',
            'title'      => '记录领取日志',
            'default'    => true,
            'desc'       => '把每一次领取成功写入领取记录表（用于「同一邮箱仅一次」校验与运维查询）。关闭后不做此项校验。',
            'dependency' => array('ops_console_enabled', '==', '1'),
        ),
        array(
            'id'         => 'ops_console_blocked_stat',
            'type'       => 'switcher',
            'title'      => '统计被拦截次数',
            'default'    => true,
            'desc'       => '记录「同一邮箱重复领取」被拦截的次数，用于评估规则命中情况。',
            'dependency' => array('ops_console_enabled', '==', '1'),
        ),
        array(
            'id'         => 'ops_console_per_page',
            'type'       => 'text',
            'title'      => '列表每页条数',
            'default'    => '20',
            'desc'       => '运维页面表格分页大小（1~200）。',
            'dependency' => array('ops_console_enabled', '==', '1'),
        ),
        array(
            'id'         => 'ops_console_retention_days',
            'type'       => 'text',
            'title'      => '记录保留天数',
            'default'    => '0',
            'desc'       => '0 = 永久保留；大于 0 时每天自动清理超过该天数的领取记录。',
            'dependency' => array('ops_console_enabled', '==', '1'),
        ),
        array(
            'id'         => 'ops_scene_claim_enabled',
            'type'       => 'switcher',
            'title'      => '场景：邮箱领取限制',
            'default'    => true,
            'desc'       => '在运维页面中启用「邮箱领取限制」场景（退出挽留弹窗领券记录的管理入口）。',
            'dependency' => array('ops_console_enabled', '==', '1'),
        ),
        array(
            'id'         => 'ops_scene_fortune_enabled',
            'type'       => 'switcher',
            'title'      => '场景：评论福袋待领取',
            'default'    => true,
            'desc'       => '在运维页面中启用「评论福袋待领取」场景（用户维度：查询发放记录、补发中奖弹窗）。',
            'dependency' => array('ops_console_enabled', '==', '1'),
        ),
    ), 15);

/* ============================================================
 * 后台层加载（仅后台上下文，前台零开销）
 *   · admin/OpsPage.php     菜单、页面渲染、admin-post 操作、HTTP 查询/清除接口
 *   · admin/scenes/*.php    各运维场景声明（新增场景只需新增文件）
 * ============================================================ */
if (is_admin() && zhiji_ops_enabled()) {
    require_once ZHIJI_INC . 'admin/OpsPage.php';

    foreach ((array) glob(ZHIJI_INC . 'admin/scenes/*.php') as $zhiji_ops_scene_file) {
        require_once $zhiji_ops_scene_file;
    }
}

/* ============================================================
 * 记录保留策略：按「记录保留天数」每日至多清理一次
 * ============================================================ */
add_action('wp_loaded', function () {
    if (!zhiji_ops_enabled()) {
        return;
    }
    $days = (int) zhiji_get_option('ops_console_retention_days', 0);
    if ($days <= 0) {
        return;
    }
    if (get_transient('zhiji_ops_cleanup_done')) {
        return;
    }
    set_transient('zhiji_ops_cleanup_done', 1, DAY_IN_SECONDS);

    global $wpdb;
    $table = zhiji_claim_log_table();
    $limit = gmdate('Y-m-d H:i:s', current_time('timestamp') - $days * DAY_IN_SECONDS);
    $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE created < %s", $limit));
});
