<?php
/**
 * @module  ConfigJanitor
 * @desc    配置清理器 —— 安全回收「已下线模块」的配置键（备份 → 清理 → 可观测）
 * @since   2.0.9
 *
 * 2026-10-02（P4）新增。
 *
 * ─────────────────────────────────────────────────────────────
 * 【为什么需要它】
 *
 * CSF 保存是**白名单整体替换**（`update_option(ZHIJI_OPTION_KEY, $data)`）：
 * 凡是不在字段表里的键，管理员每点一次「保存设置」就被清除一批。
 * 线上实测 265 个已存键中有 **95 个是孤儿键**，且全部来自已下线模块
 * （AF.19 秒杀/砍价、AF.20 移除的 18 模块 / 2 整类）。
 *
 * 双重风险：
 *   ① 用户配置悄悄消失（用户不知道，也没法找回）
 *   ② 用户已明确要重做这些模块 → 恢复时无法还原此前的配置
 *
 * ─────────────────────────────────────────────────────────────
 * 【三条不可让步的原则】
 *
 *   1. **先备份，后删除** —— 清理前把废弃键原样写进
 *      `zhiji_options_deprecated` 选项。恢复模块时用它回填，用户零感知。
 *   2. **绝不自动删** —— 只提供显式入口（后台按钮 + WP-CLI），默认不跑。
 *      配置是用户的数据，清理由用户按下按钮的那一刻起才发生。
 *   3. **可观测** —— 清理动作写日志、留数量记录、后台能看到清了什么。
 *      任何「悄悄改了用户数据」的行为在本项目都是红线。
 *
 * 附带的第三个收益：清理后 `zhiji_options` 里只剩活键，
 * `config_audit.py` 的噪音下降，也更容易发现「真正来路不明的键」。
 * ─────────────────────────────────────────────────────────────
 */

defined('ABSPATH') || exit;

/**
 * 加载守卫：只在后台 / WP-CLI / 探针里加载
 *
 * 本文件全是「体检 + 回收」的管理动作，前台用不到 —— 但**不能**在
 * `is_admin()` 为假时整块 return 掉 WP-CLI 分支（CLI 的 is_admin() 恒为 false）。
 * 场景注册挂在 init 上，只在后台/CLI 才需要，故此处提前退出对前台零开销。
 */
if (!is_admin() && !defined('WP_CLI') && !defined('ZHIJI_CONFIG_PROBE')) {
    return;
}

/**
 * 读出全部「已登记的废弃键」当前实际存了什么
 *
 * 只看废弃键表里登记过的 —— **不碰任何未登记的键**。
 * 未登记的键可能是代码在用的（runtime 层），也可能是真孤儿，
 * 但无论如何都不该由「废弃键清理器」处理。
 *
 * @return array<string,mixed>
 */
function zhiji_config_collect_deprecated()
{
    $stored = (array) get_option(ZHIJI_OPTION_KEY, array());
    $out    = array();
    foreach (zhiji_config_deprecated_keys() as $key => $desc) {
        if (array_key_exists($key, $stored)) {
            $out[$key] = $stored[$key];
        }
    }
    return $out;
}

/**
 * 已回收的废弃键存档（键 => {value, desc, at}）
 *
 * @return array
 */
function zhiji_config_archive()
{
    $a = get_option(ZHIJI_DEPRECATED_OPTION_KEY, array());
    return is_array($a) ? $a : array();
}

/**
 * 体检（只读，不改任何数据）—— 后台展示与 CLI 预演共用
 *
 * @return array{stored:int,live:int,deprecated:int,unknown:array,collectable:int}
 */
function zhiji_config_janitor_report()
{
    $stored = (array) get_option(ZHIJI_OPTION_KEY, array());
    $audit  = zhiji_config_audit_orphans($stored);
    $persist = zhiji_config_persistent_keys();
    $known   = zhiji_config_known_keys();

    // 可回收 = 已登记废弃键 且 实际存在 且 不在持久键集合里
    $collectable = 0;
    foreach (array_keys($audit['deprecated']) as $k) {
        if (!isset($persist[$k])) {
            $collectable++;
        }
    }

    return array(
        'stored'     => count($stored),
        'live'       => $audit['live'],
        'deprecated' => count($audit['deprecated']),
        'collectable' => $collectable,
        'unknown'    => $audit['unknown'],
        'archived'   => count(zhiji_config_archive()),
    );
}

/**
 * 执行回收：备份 → 从主配置移除 → 写日志
 *
 * 幂等：已回收过的键不会重复处理（第二次调用返回 removed=0）。
 *
 * 🛡️ **删除前逐键验活**（P4 追加的最后一道防线）
 * 废弃键表是**人工维护**的，迟早会有「其实还在用」的键被误登记
 * （2026-10-02 真实踩到：monitor_404_track_logged_in 被误登记成废弃，
 *   实际 Monitor404.php 仍在读它控制「是否统计登录用户」）。
 * 故删除前**逐键扫源码**，凡仍被 zhiji_get_option / zhiji_is_enabled 等读取的键，
 * 一律**从回收清单中剔除**并记日志 —— 宁可不回收，也不能让一个在用的配置失效。
 * 这样即使废弃表登记错，线上也不会被误伤（人工复核不再是唯一防线）。
 *
 * @param bool $dry_run true = 只报告不执行
 * @return array{ok:bool,removed:int,keys:array,skipped_in_use:array,backed_up:int,message:string}
 */
function zhiji_config_janitor_run($dry_run = false)
{
    $found = zhiji_config_collect_deprecated();
    // 持久键豁免：runtime 层 / 别名涉及的旧键**永不回收**
    $persist = zhiji_config_persistent_keys();
    $keys    = array();
    foreach (array_keys($found) as $k) {
        if (!isset($persist[$k])) {
            $keys[] = $k;
        }
    }

    // 🛡️ 逐键验活：剔除仍被源码读取的键
    $in_use = array();
    foreach ($keys as $k) {
        if (zhiji_config_key_in_use($k)) {
            $in_use[] = $k;
        }
    }
    if ($in_use) {
        $keys = array_values(array_diff($keys, $in_use));
        zhiji_log('回收跳过：废弃表登记有误，键仍在被使用', array('keys' => $in_use));
    }

    if (!$keys) {
        return array('ok' => true, 'removed' => 0, 'keys' => array(),
            'skipped_in_use' => $in_use, 'backed_up' => 0,
            'message' => $in_use
                ? sprintf(
                    /* translators: %d: 数量 */
                    __('没有可回收的废弃配置键（%d 个键虽在废弃表里，但源码仍在读取，已跳过）', 'zhiji'),
                    count($in_use)
                )
                : __('没有可回收的废弃配置键', 'zhiji'));
    }

    if ($dry_run) {
        return array('ok' => true, 'removed' => 0, 'keys' => $keys,
            'skipped_in_use' => $in_use, 'backed_up' => 0,
            'message' => sprintf(
                /* translators: %d: 数量 */
                _n('预演：将回收 %d 个废弃配置键（未实际执行）', '预演：将回收 %d 个废弃配置键（未实际执行）', count($keys), 'zhiji'),
                count($keys)
            ));
    }

    // ① 先备份（存档 + 独立选项双写，前者便于遍历，后者防存档本身被误改）
    $archive = zhiji_config_archive();
    $desc    = zhiji_config_deprecated_keys();
    $at      = current_time('mysql');
    foreach ($keys as $k) {
        $archive[$k] = array(
            'value' => $found[$k],
            'desc'  => isset($desc[$k]) ? $desc[$k] : '',
            'at'    => $at,
        );
    }
    update_option(ZHIJI_DEPRECATED_OPTION_KEY, $archive, false);

    // ② 从主配置移除
    $options = (array) get_option(ZHIJI_OPTION_KEY, array());
    foreach ($keys as $k) {
        unset($options[$k]);
    }
    update_option(ZHIJI_OPTION_KEY, $options);
    zhiji_options_flush();

    // ③ 日志
    zhiji_log('回收废弃配置键', array('count' => count($keys), 'keys' => $keys));

    $msg = __('已回收并备份', 'zhiji');
    if ($in_use) {
        $msg .= sprintf(
            /* translators: %s: 键名列表 */
            __('（另有 %s 个键虽在废弃表里但源码仍在读取，已跳过）', 'zhiji'),
            implode(', ', $in_use)
        );
    }

    return array('ok' => true, 'removed' => count($keys), 'keys' => $keys,
        'skipped_in_use' => $in_use, 'backed_up' => count($archive), 'message' => $msg);
}

/**
 * 某配置键当前是否仍被源码读取
 *
 * 用途：回收器的最后一道防线 —— 废弃键表是人工维护的，难免有登记错的。
 * 凡是仍被读的键，无论登记成什么都**不能删**。
 *
 * 实现：扫 includes/ 下所有 PHP，用正则找 `zhiji_xxx_option('键名'` 形态的读取。
 * ⚠️ 只认**字面量键名**（本项目的配置读取都是字面量，不存在动态拼接）——
 *    若将来出现 zhiji_get_option($k) 这类动态读法，本函数会漏判，
 *    届时的兜底是 preflight 的「来路不明」报告（列出但需人工确认）。
 *
 * @param string $key
 * @return bool
 */
function zhiji_config_key_in_use($key)
{
    static $cache = null;
    if (null === $cache) {
        $cache = array();
        $base  = get_stylesheet_directory() . '/includes/';
        $files = array();
        foreach (array('', 'core/', 'notify/', 'notify/Channels/', 'modules/', 'functions/', 'options/', 'admin/') as $sub) {
            foreach ((array) glob($base . $sub . '*.php') as $f) {
                $files[] = $f;
            }
        }
        foreach ($files as $f) {
            $src = (string) @file_get_contents($f);
            if ('' === $src) {
                continue;
            }
            // zhiji_get_option / zhiji_is_enabled / zhiji_update_option / zhiji_option_bool|int
            if (preg_match_all(
                "/zhiji_(?:get_option|is_enabled|update_option|option_bool|option_int)\s*\(\s*'([A-Za-z0-9_]+)'/",
                $src,
                $m
            )) {
                foreach ($m[1] as $k) {
                    $cache[(string) $k] = true;
                }
            }
        }
    }
    return isset($cache[(string) $key]);
}

/**
 * 恢复某个废弃键（用户恢复模块后用）
 *
 * @param string $key
 * @return bool 是否恢复成功
 */
function zhiji_config_janitor_restore($key)
{
    $key = (string) $key;
    $archive = zhiji_config_archive();
    if (!isset($archive[$key]['value'])) {
        return false;
    }
    $options = (array) get_option(ZHIJI_OPTION_KEY, array());
    $options[$key] = $archive[$key]['value'];
    update_option(ZHIJI_OPTION_KEY, $options);
    zhiji_options_flush();
    zhiji_log('恢复废弃配置键', array('key' => $key));

    return true;
}

/* ============================================================
 * 后台：运维管理 → 注册「配置体检」场景
 * ============================================================ */

add_action('init', function () {
    if (!function_exists('zhiji_ops_register_scene')) {
        return;
    }
    zhiji_ops_register_scene('config_janitor', array(
        'title'    => '配置体检与回收',
        'desc'     => '已下线模块（砍价 / 弹幕 / 积分商城 / 秒杀等）的配置键仍留在 zhiji_options 里，'
            . '而 CSF 保存是「白名单整体替换」—— 每保存一次设置就会被清掉一批。'
            . '回收前自动备份，恢复模块时可一键回填。',
        'priority' => 20,
        'notice'   => '只处理「已登记的废弃键」；来路不明的键永不自动删除，仅列出供人工确认。',
        'stats'    => function () {
            $r = zhiji_config_janitor_report();
            return array(
                '已存键'       => (int) $r['stored'],
                '活键'         => (int) $r['live'],
                '可回收废弃键' => (int) $r['collectable'],
                '已存档'       => (int) $r['archived'],
                '来路不明'     => count($r['unknown']),
            );
        },
        // 表单型操作：不需要先选中记录（目标数据本就不在列表里）
        // ⚠️ 实测 OpsPage/OpsActions 的真实契约是 pre_actions{key,label,confirm,fields,tone}
        //    + 场景级 handle()。注册表里虽有 'actions'/'columns' 字段，但渲染层从未读取，
        //    写了也是空转 —— 别再凭印象用不存在的键。
        'pre_actions' => array(
            array(
                'key'     => 'dry_run',
                'label'   => '预演（只报告，不改任何数据）',
                'tone'    => 'button-secondary',
            ),
            array(
                'key'     => 'clean',
                'label'   => '确认回收废弃配置键',
                'tone'    => 'button-primary',
                'confirm' => '回收后这些配置将移出主配置（已自动备份到 ' . ZHIJI_DEPRECATED_OPTION_KEY
                    . '，可随时恢复）。确定继续？',
            ),
        ),
        'query'  => function () {
            $desc = zhiji_config_deprecated_keys();
            $rows = array();
            $i    = 0;
            foreach (zhiji_config_collect_deprecated() as $k => $v) {
                $i++;
                // ⚠️ 运维页表格的列是 ClaimLog 专用固定列（time/user/action/detail/…，
                //    见 admin/OpsRender.php:346-351），**没有自定义列机制**
                //    （场景表里的 'columns' 字段渲染层从未读取）。
                //    → 只能把信息塞进这四个固定列；详情抽屉用 detail 承载完整说明。
                $rows[] = array(
                    'id'      => $k,
                    'time'    => sprintf('#%d', $i),
                    'user'    => $k,
                    'action'  => 'config_deprecated',
                    'detail'  => (isset($desc[$k]) ? $desc[$k] : '')
                        . ' ｜ 当前值：' . (is_scalar($v) ? (string) $v : wp_json_encode($v)),
                    'scene'   => 'config_janitor',
                    'ip'      => '',
                    'event_id'=> md5($k),
                );
            }
            return array('rows' => $rows, 'total' => count($rows), 'pages' => 1);
        },
        'handle' => function ($op, $post, $ids, $scene) {
            if ('dry_run' !== $op && 'clean' !== $op) {
                return array('ok' => false, 'msg' => __('不支持的操作', 'zhiji'));
            }
            $res  = zhiji_config_janitor_run('dry_run' === $op);
            $keys = $res['keys'] ? ('：' . implode('、', array_slice($res['keys'], 0, 30))
                . (count($res['keys']) > 30 ? ' 等' : '')) : '';
            $skip = '';
            if (!empty($res['skipped_in_use'])) {
                $skip = sprintf(
                    '　⚠ 跳过 %d 个仍在使用的键：%s（废弃表登记有误，请核实）',
                    count($res['skipped_in_use']),
                    implode('、', $res['skipped_in_use'])
                );
            }
            return array('ok' => (bool) $res['ok'], 'msg' => $res['message'] . $keys . $skip);
        },
        /**
         * 详情抽屉：本场景的行不是 ClaimLog 形态（没有 event/time/user 那套语义），
         * 故用 replace=true 整体接管字段（见 admin/OpsPage.php 的契约注释）。
         */
        'detail' => function ($row) {
            $key    = isset($row['id']) ? (string) $row['id'] : '';
            $desc   = zhiji_config_deprecated_keys();
            $stored = (array) get_option(ZHIJI_OPTION_KEY, array());
            $v      = array_key_exists($key, $stored) ? $stored[$key] : null;
            return array(
                'replace' => true,
                'primary' => array(array('k' => $key, 'v' => $key)),
                'fields'  => array(
                    array('k' => __('原本用途', 'zhiji'), 'v' => isset($desc[$key]) ? $desc[$key] : '—'),
                    array('k' => __('当前值', 'zhiji'), 'v' => null === $v ? '—'
                        : (is_scalar($v) ? (string) $v : wp_json_encode($v))),
                    array('k' => __('状态', 'zhiji'), 'v' => __('已下线模块的残留配置，可安全回收（会自动备份）', 'zhiji')),
                ),
            );
        },
    ));
}, 30);

/**
 * 回收结果的一次性提示（走运维页的 redirect，不另开 admin_post）
 *
 * @return void
 */
function zhiji_config_janitor_flash()
{
    if (!isset($_GET['zhiji_cfg'])) {
        return;
    }
    $n = isset($_GET['n']) ? (int) $_GET['n'] : 0;
    if ('success' === sanitize_key(wp_unslash($_GET['zhiji_cfg']))) {
        printf(
            '<div class="notice notice-success is-dismissible"><p>已回收 <b>%d</b> 个废弃配置键（已备份到 <code>%s</code>，恢复模块时可用）。</p></div>',
            $n,
            esc_html(ZHIJI_DEPRECATED_OPTION_KEY)
        );
    }
}
add_action('admin_notices', 'zhiji_config_janitor_flash', 5);

/**
 * 运维面板：注册「配置体检」场景
 *
 * ⚠️ 运维页用的是**场景注册制**（core/Ops.php 的 zhiji_ops_register_scene），
 *    不是 apply_filters —— 一开始按「过滤面板 HTML」写了个不存在的钩子
 *    `zhiji_ops_panel_extra`，那行 add_filter 完全是空转（又一次凭印象写钩子…）。
 *    改用场景制：它自带权限、排序、渲染、动作处理，且与既有场景一致。
 *
 * 场景只负责「展示 + 触发」，真正的回收逻辑在 zhiji_config_janitor_run()。
 */
add_action('init', function () {
    if (!function_exists('zhiji_ops_register_scene')) {
        return;
    }
    zhiji_ops_register_scene('config_janitor', array(
        'title'    => '配置体检与回收',
        'desc'     => '已下线模块（砍价/弹幕/积分商城/秒杀等）的配置键仍在 zhiji_options 里，'
            . '每保存一次设置就会被 CSF 清除一批。回收前自动备份，可随时恢复。',
        'priority' => 20,
        'notice'   => '回收只影响「已登记的废弃键」；来路不明的键永不自动删除。',
        'stats'    => function () {
            $r = zhiji_config_janitor_report();
            return array(
                '已存键'       => (int) $r['stored'],
                '活键'         => (int) $r['live'],
                '可回收废弃键' => (int) $r['collectable'],
                '已存档'       => (int) $r['archived'],
                '来路不明'     => count($r['unknown']),
            );
        },
        'actions'  => array(
            'dry_run' => array(
                'label' => '预演（不删）',
                'cap'   => 'zhiji_ops_clear',
                'handler' => function () {
                    $res = zhiji_config_janitor_run(true);
                    return array('message' => $res['message'], 'items' => $res['keys']);
                },
            ),
            'clean'   => array(
                'label' => '确认回收',
                'cap'   => 'zhiji_ops_clear',
                'confirm' => '回收后这些配置将移出主配置（已自动备份，可恢复）。确定？',
                'handler' => function () {
                    $res = zhiji_config_janitor_run(false);
                    return array('message' => $res['message'] . ' ' . $res['removed'] . ' 个', 'items' => $res['keys']);
                },
            ),
        ),
        'columns' => array(
            'key'  => array('title' => '键名'),
            'desc' => array('title' => '原本用途'),
        ),
        'query'  => function () {
            $desc = zhiji_config_deprecated_keys();
            $rows = array();
            foreach (array_keys(zhiji_config_collect_deprecated()) as $k) {
                $rows[] = array('key' => $k, 'desc' => isset($desc[$k]) ? $desc[$k] : '');
            }
            return $rows;
        },
    ));
}, 30);

/**
 * WP-CLI：wp zhiji config audit|clean|restore
 *
 * 用法：
 *   wp zhiji config audit                 # 体检（只读）
 *   wp zhiji config clean --dry-run       # 预演
 *   wp zhiji config clean                 # 执行（先备份）
 *   wp zhiji config restore <key>         # 恢复单个键
 */
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('zhiji config', function ($args, $assoc) {
        $sub = isset($args[0]) ? $args[0] : 'audit';
        if ('audit' === $sub) {
            $r = zhiji_config_janitor_report();
            WP_CLI::log(sprintf('已存 %d ｜ 活 %d ｜ 可回收 %d ｜ 已存档 %d ｜ 来路不明 %d',
                $r['stored'], $r['live'], $r['collectable'], $r['archived'], count($r['unknown'])));
            if ($r['unknown']) {
                WP_CLI::warning('来路不明的键（不会自动处理）: ' . implode(', ', $r['unknown']));
            }
            return;
        }
        if ('clean' === $sub) {
            $res = zhiji_config_janitor_run(!empty($assoc['dry-run']));
            WP_CLI::success($res['message'] . '：' . $res['removed'] . ' 个'
                . ($res['keys'] ? ' → ' . implode(', ', array_slice($res['keys'], 0, 20)) : ''));
            return;
        }
        if ('restore' === $sub) {
            $key = isset($args[1]) ? $args[1] : '';
            if (!$key) {
                WP_CLI::error('缺少键名');
            }
            WP_CLI::success(zhiji_config_janitor_restore($key) ? ('已恢复 ' . $key) : ('存档中没有 ' . $key));
        }
    });
}
