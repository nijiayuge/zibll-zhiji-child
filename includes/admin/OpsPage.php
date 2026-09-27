<?php
/**
 * @module  OpsPage
 * @desc    运维管理页面（后台）：把"需要人工干预的业务状态与可变配置"集中到一处。
 *
 *          页面结构：
 *            · 顶级菜单「知集运维」→ 总览：各场景统计卡片 + 快速入口 + 最近运维操作
 *            · 每个场景一个子页：统计卡片 → 提示 → 查询筛选 → 批量操作 + 数据表
 *              （行内操作 / 批量操作 / 分页 / 操作审计 / 接入说明）
 *
 *          场景声明见 core/Ops.php；本文件只负责通用渲染与操作分发。
 *
 * @hook    admin_menu            · 注册菜单与场景子页
 * @hook    admin_post_zhiji_ops_action · 页面操作入口（nonce + 权限 + 清除开关校验）
 * @api     wp_ajax zhiji_ops_query     · 查询接口（管理端，JSON）
 *          wp_ajax zhiji_ops_clear     · 清除接口（管理端，JSON）
 * @option  ops_console_enabled        运维页面总开关
 *          ops_console_clear_enabled  是否允许运维清除（总闸）
 *          ops_console_per_page       每页条数
 *          ops_console_clear_confirm  危险操作是否二次确认
 * @since   2.0.0
 */

defined('ABSPATH') || exit;

if (!defined('ZHIJI_OPS_MENU_SLUG')) {
    define('ZHIJI_OPS_MENU_SLUG', 'zhiji-ops');
}

/* ============================================================
 * 一、菜单注册
 * ============================================================ */

add_action('admin_menu', 'zhiji_ops_register_menu', 20);

/**
 * 注册「知集运维」菜单与各场景子页
 *
 * @return void
 */
function zhiji_ops_register_menu()
{
    if (!zhiji_ops_enabled()) {
        return;
    }

    add_menu_page(
        __('知集运维', 'zhiji'),
        __('知集运维', 'zhiji'),
        'manage_options',
        ZHIJI_OPS_MENU_SLUG,
        'zhiji_ops_render_overview',
        'dashicons-shield-alt',
        58
    );

    add_submenu_page(
        ZHIJI_OPS_MENU_SLUG,
        __('运维总览', 'zhiji'),
        __('运维总览', 'zhiji'),
        'manage_options',
        ZHIJI_OPS_MENU_SLUG,
        'zhiji_ops_render_overview'
    );

    foreach (zhiji_ops_scenes() as $id => $scene) {
        add_submenu_page(
            ZHIJI_OPS_MENU_SLUG,
            $scene['title'],
            $scene['title'],
            $scene['cap'],
            zhiji_ops_scene_slug($id),
            function () use ($id) {
                zhiji_ops_render_scene($id);
            }
        );
    }
}

/**
 * 场景页面 slug
 *
 * @param string $id
 * @return string
 */
function zhiji_ops_scene_slug($id)
{
    return ZHIJI_OPS_MENU_SLUG . '-' . sanitize_key($id);
}

/* ============================================================
 * 二、页面头部与公共片段
 * ============================================================ */

/**
 * 运维页面通用样式（仅本页面加载）
 *
 * @return void
 */
function zhiji_ops_print_styles()
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ?>
    <style>
    /* ============================================================
     * 知集运维台 · 设计规范（2026-09-27 重做）
     * ------------------------------------------------------------
     * 令牌：主色 #2271b1（WP 后台主色）｜文本 #1d2327/#50575e/#646970/#8c8f94
     *       边框 #dcdcde/#e2e2e4｜底 #fff/#f6f7f7｜圆角 10/8/999｜栅格 4·8·12·16·20·24
     * 约定：所有规则以 .zhiji-ops 作用域前缀隔离，不污染其它后台页面；
     *       组件类名与既有测试断言保持一致（zhiji-ops-card / -table / -filters …）。
     * ============================================================ */
    .zhiji-ops{--zhiji-ink:#1d2327;--zhiji-body:#50575e;--zhiji-muted:#646970;--zhiji-faint:#8c8f94;
      --zhiji-line:#dcdcde;--zhiji-line-soft:#e2e2e4;--zhiji-bg-soft:#f6f7f7;
      --zhiji-primary:#2271b1;--zhiji-primary-soft:#eef4fa;--zhiji-danger:#b32d2e;--zhiji-ok:#1a7f37;
      --zhiji-radius:10px;--zhiji-shadow:0 1px 2px rgba(16,24,40,.06);--zhiji-shadow-hover:0 6px 18px rgba(16,24,40,.10)}

    /* ---------- 页头 ---------- */
    .zhiji-ops .zhiji-ops-head{margin:10px 0 18px}
    .zhiji-ops .zhiji-ops-head h1{margin:0 0 6px;font-size:20px;line-height:1.35;font-weight:600;color:var(--zhiji-ink)}
    .zhiji-ops .zhiji-ops-desc{color:var(--zhiji-muted);margin:0;max-width:960px;font-size:13px;line-height:1.7}
    .zhiji-ops .zhiji-ops-head .page-title-action{margin-left:8px;vertical-align:middle}

    /* ---------- 分区标题 ---------- */
    .zhiji-ops .zhiji-ops-section{display:flex;align-items:center;gap:10px;margin:22px 0 10px}
    .zhiji-ops .zhiji-ops-section h2{margin:0;font-size:14px;font-weight:600;color:var(--zhiji-ink)}
    .zhiji-ops .zhiji-ops-section .zhiji-ops-section-line{flex:1;height:1px;background:var(--zhiji-line-soft)}
    .zhiji-ops .zhiji-ops-section .zhiji-ops-count{font-size:12px;color:var(--zhiji-faint);background:var(--zhiji-bg-soft);
      border:1px solid var(--zhiji-line-soft);border-radius:999px;padding:1px 9px}

    /* ---------- 统计卡片 ---------- */
    .zhiji-ops-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px;margin:0 0 6px}
    .zhiji-ops-card{position:relative;background:#fff;border:1px solid var(--zhiji-line);border-radius:var(--zhiji-radius);
      padding:14px 16px 14px 18px;box-shadow:var(--zhiji-shadow);transition:box-shadow .18s ease,transform .18s ease}
    .zhiji-ops-card:hover{box-shadow:var(--zhiji-shadow-hover);transform:translateY(-1px)}
    .zhiji-ops-card::before{content:"";position:absolute;left:0;top:12px;bottom:12px;width:3px;border-radius:0 3px 3px 0;background:var(--zhiji-primary);opacity:.85}
    .zhiji-ops-card.tone-warn::before{background:var(--zhiji-danger)}
    .zhiji-ops-card.tone-ok::before{background:var(--zhiji-ok)}
    .zhiji-ops-card .zhiji-ops-card-label{font-size:12px;color:var(--zhiji-muted);margin-bottom:6px;display:flex;align-items:center;gap:6px}
    .zhiji-ops-card .zhiji-ops-card-label .dashicons{font-size:15px;width:15px;height:15px;color:var(--zhiji-primary);opacity:.9}
    .zhiji-ops-card.tone-warn .zhiji-ops-card-label .dashicons{color:var(--zhiji-danger)}
    .zhiji-ops-card.tone-ok .zhiji-ops-card-label .dashicons{color:var(--zhiji-ok)}
    .zhiji-ops-card .zhiji-ops-card-value{font-size:26px;font-weight:600;line-height:1.2;color:var(--zhiji-ink);font-variant-numeric:tabular-nums}
    .zhiji-ops-card .zhiji-ops-card-hint{font-size:12px;color:var(--zhiji-faint);margin-top:6px}
    .zhiji-ops-card.tone-warn .zhiji-ops-card-value{color:var(--zhiji-danger)}
    .zhiji-ops-card.tone-ok .zhiji-ops-card-value{color:var(--zhiji-ok)}

    /* ---------- 总览：场景卡片 ---------- */
    .zhiji-ops-scene-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:14px;margin:0 0 6px}
    .zhiji-ops-scene-card{position:relative;background:#fff;border:1px solid var(--zhiji-line);border-radius:var(--zhiji-radius);
      padding:16px 18px;box-shadow:var(--zhiji-shadow);transition:box-shadow .18s ease,transform .18s ease}
    .zhiji-ops-scene-card:hover{box-shadow:var(--zhiji-shadow-hover);transform:translateY(-1px)}
    .zhiji-ops-scene-card h3{margin:0 0 6px;font-size:15px;font-weight:600;color:var(--zhiji-ink);display:flex;align-items:center;gap:8px}
    .zhiji-ops-scene-card h3 .dashicons{color:var(--zhiji-primary)}
    .zhiji-ops-scene-card p{color:var(--zhiji-muted);font-size:13px;line-height:1.7;margin:0 0 12px}
    .zhiji-ops-scene-card .zhiji-ops-chipset{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 12px}
    .zhiji-ops-scene-card .zhiji-ops-chip{font-size:12px;color:var(--zhiji-body);background:var(--zhiji-bg-soft);
      border:1px solid var(--zhiji-line-soft);border-radius:999px;padding:2px 10px;font-variant-numeric:tabular-nums}
    .zhiji-ops-scene-card .zhiji-ops-scene-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}

    /* ---------- 工具条：筛选 / 表单操作 ---------- */
    .zhiji-ops-filters{background:#fff;border:1px solid var(--zhiji-line);border-radius:var(--zhiji-radius);
      padding:14px 16px;margin:0 0 12px;display:flex;flex-wrap:wrap;gap:12px 14px;align-items:flex-end;box-shadow:var(--zhiji-shadow)}
    .zhiji-ops-filters .zhiji-ops-field{display:flex;flex-direction:column;gap:5px}
    .zhiji-ops-filters label{font-size:12px;color:var(--zhiji-muted)}
    .zhiji-ops-filters input[type="text"],.zhiji-ops-filters input[type="date"],.zhiji-ops-filters select{min-width:150px;border-radius:6px}
    .zhiji-ops-filters input[type="text"].zhiji-ops-w-lg{min-width:250px}
    .zhiji-ops-filters .zhiji-ops-field-actions{gap:8px;flex-direction:row;align-items:center}
    .zhiji-ops-preactions{display:flex;flex-direction:column;gap:10px;margin:0 0 12px}
    .zhiji-ops-preactions form{display:flex;flex-wrap:wrap;gap:10px;align-items:center;background:#fff;border:1px solid var(--zhiji-line);
      border-left:3px solid var(--zhiji-primary);border-radius:var(--zhiji-radius);padding:12px 16px;box-shadow:var(--zhiji-shadow)}
    .zhiji-ops-preactions label{font-size:12px;color:var(--zhiji-muted)}
    .zhiji-ops-preactions strong{font-size:13px;color:var(--zhiji-ink);white-space:nowrap}
    .zhiji-ops-preactions input[type="text"],.zhiji-ops-preactions input[type="email"]{min-width:280px;border-radius:6px}
    .zhiji-ops-preactions .description{flex-basis:100%;margin:0;color:var(--zhiji-faint)}

    /* ---------- 数据表 ---------- */
    .zhiji-ops-bulkbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;background:#fff;border:1px solid var(--zhiji-line);
      border-bottom:none;border-radius:var(--zhiji-radius) var(--zhiji-radius) 0 0;padding:10px 14px}
    .zhiji-ops-bulkbar strong{font-size:13px;color:var(--zhiji-ink)}
    .zhiji-ops .zhiji-ops-table-wrap{background:#fff;border:1px solid var(--zhiji-line);border-radius:var(--zhiji-radius);
      overflow:auto;box-shadow:var(--zhiji-shadow)}
    .zhiji-ops .zhiji-ops-table-wrap.has-bulkbar{border-radius:0 0 var(--zhiji-radius) var(--zhiji-radius)}
    .zhiji-ops table.zhiji-ops-table{margin:0;border:none;box-shadow:none;border-radius:0}
    /* 表头：不做 position:sticky —— 列表容器高度自适应时 sticky 会与首行叠压（实测出现遮挡），
       改为静态表头 + 底色区分，视觉同样清晰且无副作用 */
    .zhiji-ops table.zhiji-ops-table thead th,.zhiji-ops table.zhiji-ops-table thead td{
      background:var(--zhiji-bg-soft);border-bottom:1px solid var(--zhiji-line);font-size:11px;font-weight:600;
      letter-spacing:.04em;color:var(--zhiji-muted);text-transform:uppercase;padding:10px 12px}
    .zhiji-ops table.zhiji-ops-table tbody td{font-size:13px;color:var(--zhiji-body);padding:11px 12px;vertical-align:middle}
    .zhiji-ops table.zhiji-ops-table tbody tr{transition:background .12s ease}
    .zhiji-ops table.zhiji-ops-table tbody tr:hover{background:var(--zhiji-bg-soft)}
    .zhiji-ops table.zhiji-ops-table tbody td strong{color:var(--zhiji-ink)}
    .zhiji-ops table.zhiji-ops-table tbody small{color:var(--zhiji-faint)}
    .zhiji-ops table.zhiji-ops-table .check-column{padding-left:14px}
    @media screen and (max-width:782px){.zhiji-ops table.zhiji-ops-table thead th,.zhiji-ops table.zhiji-ops-table thead td{top:46px}}

    /* ---------- 元件 ---------- */
    .zhiji-ops .zhiji-ops-empty{padding:38px 20px;text-align:center;color:var(--zhiji-faint)}
    .zhiji-ops .zhiji-ops-empty .dashicons{display:block;font-size:28px;width:28px;height:28px;margin:0 auto 8px;opacity:.55}
    .zhiji-ops .zhiji-ops-empty strong{display:block;color:var(--zhiji-body);font-size:14px;margin-bottom:4px}
    .zhiji-ops .zhiji-ops-tag{display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;line-height:1.6;font-weight:500;white-space:nowrap}
    .zhiji-ops .zhiji-ops-tag.active{background:#fdecea;color:var(--zhiji-danger);border:1px solid #f5c2c0}
    .zhiji-ops .zhiji-ops-tag.cleared{background:#edfaef;color:var(--zhiji-ok);border:1px solid #bfe6c8}
    .zhiji-ops .zhiji-ops-tag.muted{background:var(--zhiji-bg-soft);color:var(--zhiji-muted);border:1px solid var(--zhiji-line-soft)}
    .zhiji-ops .zhiji-ops-code{font-family:Menlo,Consolas,Monaco,monospace;font-size:12px;background:var(--zhiji-bg-soft);
      border:1px solid var(--zhiji-line-soft);border-radius:6px;padding:2px 7px;color:var(--zhiji-body);white-space:nowrap}
    .zhiji-ops .zhiji-ops-rowactions{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
    .zhiji-ops .zhiji-ops-rowactions form{display:inline}
    .zhiji-ops .zhiji-ops-danger{color:var(--zhiji-danger);border-color:#f0c6c4 !important}
    .zhiji-ops .zhiji-ops-danger:hover{background:#fdecea !important;border-color:var(--zhiji-danger) !important;color:var(--zhiji-danger) !important}

    /* ---------- 分页 ---------- */
    .zhiji-ops .zhiji-ops-pagination{margin:12px 0 4px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;
      background:#fff;border:1px solid var(--zhiji-line);border-radius:var(--zhiji-radius);padding:10px 14px;box-shadow:var(--zhiji-shadow)}
    .zhiji-ops .zhiji-ops-pagination .description{margin:0 auto 0 0}

    /* ---------- 审计时间线 ---------- */
    .zhiji-ops .zhiji-ops-activity{margin-top:8px;background:#fff;border:1px solid var(--zhiji-line);border-radius:var(--zhiji-radius);
      padding:14px 18px;box-shadow:var(--zhiji-shadow)}
    .zhiji-ops .zhiji-ops-activity ul{margin:0;padding:0;list-style:none}
    .zhiji-ops .zhiji-ops-activity li{position:relative;padding:8px 0 8px 18px;color:var(--zhiji-body);font-size:13px;line-height:1.7}
    .zhiji-ops .zhiji-ops-activity li::before{content:"";position:absolute;left:2px;top:15px;width:7px;height:7px;border-radius:50%;
      background:#fff;border:2px solid var(--zhiji-primary)}
    .zhiji-ops .zhiji-ops-activity li+li{border-top:1px dashed var(--zhiji-line-soft)}
    .zhiji-ops .zhiji-ops-activity .zhiji-ops-time{color:var(--zhiji-faint);margin-right:8px;font-variant-numeric:tabular-nums}
    .zhiji-ops .zhiji-ops-activity strong{color:var(--zhiji-ink)}

    /* ---------- 接入指引 ---------- */
    .zhiji-ops .zhiji-ops-doc{margin-top:8px;background:#fff;border:1px solid var(--zhiji-line);border-radius:var(--zhiji-radius);
      padding:16px 20px;box-shadow:var(--zhiji-shadow)}
    .zhiji-ops .zhiji-ops-doc h2{margin:0 0 6px;font-size:14px}
    .zhiji-ops .zhiji-ops-doc ol{margin:8px 0 0 18px;color:var(--zhiji-body);font-size:13px;line-height:1.9}
    .zhiji-ops .zhiji-ops-doc code{background:var(--zhiji-bg-soft);border:1px solid var(--zhiji-line-soft);
      border-radius:5px;padding:1px 6px;font-size:12px}
    .zhiji-ops .zhiji-ops-doc .description{margin:6px 0 0}

    /* ---------- 无障碍：键盘焦点可见 ---------- */
    .zhiji-ops a:focus-visible,.zhiji-ops button:focus-visible,.zhiji-ops input:focus-visible,
    .zhiji-ops select:focus-visible{outline:2px solid var(--zhiji-primary);outline-offset:1px;box-shadow:none}
    </style>
    <?php
}

/**
 * 操作结果提示（由 admin-post 重定向带回）
 *
 * @return void
 */
function zhiji_ops_print_notice()
{
    if (!isset($_GET['ops_msg'])) {
        return;
    }
    $ok  = isset($_GET['ops_ok']) && '1' === (string) $_GET['ops_ok'];
    $msg = sanitize_text_field(rawurldecode(wp_unslash($_GET['ops_msg'])));
    if ('' === $msg) {
        return;
    }
    printf(
        '<div class="notice %s is-dismissible"><p>%s</p></div>',
        $ok ? 'notice-success' : 'notice-error',
        esc_html($msg)
    );
}

/**
 * 输出统计卡片
 *
 * @param array $stats
 * @return void
 */
function zhiji_ops_render_cards(array $stats)
{
    if (!$stats) {
        return;
    }
    echo '<div class="zhiji-ops-cards">';
    foreach ($stats as $card) {
        $tone = !empty($card['tone']) && in_array($card['tone'], array('warn', 'ok'), true) ? ' tone-' . $card['tone'] : '';
        echo '<div class="zhiji-ops-card' . esc_attr($tone) . '">';
        echo '<div class="zhiji-ops-card-label">';
        if (!empty($card['icon'])) {
            echo '<span class="dashicons ' . esc_attr($card['icon']) . '"></span>';
        }
        echo esc_html($card['label']) . '</div>';
        echo '<div class="zhiji-ops-card-value">' . esc_html($card['value']) . '</div>';
        if (!empty($card['hint'])) {
            echo '<div class="zhiji-ops-card-hint">' . esc_html($card['hint']) . '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
}

/**
 * 分区标题（含右侧分隔线与可选计数徽标）
 *
 * @param string $title
 * @param string $count 计数徽标文案（可选）
 * @return void
 */
function zhiji_ops_section_title($title, $count = '')
{
    echo '<div class="zhiji-ops-section"><h2>' . esc_html($title) . '</h2>';
    if ('' !== (string) $count) {
        echo '<span class="zhiji-ops-count">' . esc_html($count) . '</span>';
    }
    echo '<span class="zhiji-ops-section-line"></span></div>';
}

/* ============================================================
 * 三、总览页
 * ============================================================ */

/**
 * 渲染运维总览
 *
 * @return void
 */
function zhiji_ops_render_overview()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('您没有权限访问该页面', 'zhiji'));
    }
    zhiji_ops_print_styles();
    $scenes = zhiji_ops_scenes();
    ?>
    <div class="wrap zhiji-ops">
        <div class="zhiji-ops-head">
            <h1><?php esc_html_e('知集运维', 'zhiji'); ?></h1>
            <p class="zhiji-ops-desc">
                <?php esc_html_e('集中管理需要人工干预的业务状态与可变配置：查询业务记录、放行被规则拦住的用户、清理异常数据。所有操作都会记入下方审计列表。', 'zhiji'); ?>
            </p>
        </div>
        <?php zhiji_ops_print_notice(); ?>

        <?php if (!$scenes) : ?>
            <div class="notice notice-warning"><p><?php esc_html_e('当前没有可用的运维场景，请检查模块开关。', 'zhiji'); ?></p></div>
        <?php else : ?>
            <?php zhiji_ops_section_title(__('运维场景', 'zhiji'), sprintf(__('共 %d 个', 'zhiji'), count($scenes))); ?>
            <div class="zhiji-ops-scene-cards">
                <?php foreach ($scenes as $id => $scene) : ?>
                    <?php
                    $stats = is_callable($scene['stats']) ? (array) call_user_func($scene['stats']) : array();
                    ?>
                    <div class="zhiji-ops-scene-card">
                        <h3>
                            <span class="dashicons <?php echo esc_attr(isset($scene['icon']) && $scene['icon'] ? $scene['icon'] : 'dashicons-screenoptions'); ?>"></span>
                            <?php echo esc_html($scene['title']); ?>
                        </h3>
                        <p><?php echo esc_html($scene['desc'] ? $scene['desc'] : '—'); ?></p>
                        <?php if ($stats) : ?>
                            <div class="zhiji-ops-chipset">
                                <?php foreach ($stats as $card) : ?>
                                    <span class="zhiji-ops-chip"><?php echo esc_html($card['label'] . ' ' . $card['value']); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="zhiji-ops-scene-actions">
                            <a class="button button-primary" href="<?php echo esc_url(zhiji_ops_page_url($id)); ?>">
                                <?php esc_html_e('进入管理', 'zhiji'); ?>
                            </a>
                            <?php if (zhiji_ops_can_clear($id)) : ?>
                                <span class="description"><?php esc_html_e('允许清除操作', 'zhiji'); ?></span>
                            <?php else : ?>
                                <span class="description zhiji-ops-danger"><?php esc_html_e('已禁止清除操作', 'zhiji'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php zhiji_ops_section_title(__('最近运维操作', 'zhiji')); ?>
        <?php zhiji_ops_render_activity('', 10); ?>
    </div>
    <?php
}

/**
 * 渲染运维操作审计列表
 *
 * @param string $scene
 * @param int    $limit
 * @return void
 */
function zhiji_ops_render_activity($scene = '', $limit = 10)
{
    $rows = zhiji_ops_activities($limit, $scene);
    echo '<div class="zhiji-ops-activity">';
    if (!$rows) {
        echo '<p class="description">' . esc_html__('暂无操作记录。', 'zhiji') . '</p>';
        echo '</div>';
        return;
    }
    echo '<ul style="margin:0;padding:0;list-style:none">';
    foreach ($rows as $row) {
        printf(
            '<li><span class="zhiji-ops-time">%s</span><strong>%s</strong> · %s · %s%s</li>',
            esc_html($row['time']),
            esc_html($row['user']),
            esc_html(zhiji_ops_action_label($row['action'])),
            esc_html($row['detail']),
            $row['scene'] ? ' <span class="zhiji-ops-code">' . esc_html($row['scene']) . '</span>' : ''
        );
    }
    echo '</ul></div>';
}

/* ============================================================
 * 四、场景页
 * ============================================================ */

/**
 * 渲染单个场景页面
 *
 * @param string $id 场景 ID
 * @return void
 */
function zhiji_ops_render_scene($id)
{
    $scene = zhiji_ops_scene($id);
    if (!$scene) {
        wp_die(__('场景不存在', 'zhiji'));
    }
    if (!current_user_can($scene['cap'])) {
        wp_die(__('您没有权限访问该页面', 'zhiji'));
    }

    zhiji_ops_print_styles();

    $filters    = zhiji_ops_collect_filters($scene);
    $per_page   = (int) zhiji_get_option('ops_console_per_page', 20);
    $per_page   = ($per_page > 0 && $per_page <= 200) ? $per_page : 20;
    $page       = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
    $query_args = array_merge($filters, array(
        'page'     => $page,
        'per_page' => $per_page,
        'scene'    => $id,
    ));
    // 场景可自带 query 回调；未提供时回退到 ClaimLog 的标准查询（记录类场景的通用默认值）
    if (is_callable($scene['query'])) {
        $result = (array) call_user_func($scene['query'], $query_args);
    } elseif (function_exists('zhiji_claim_log_query')) {
        $result = zhiji_claim_log_query($query_args);
    } else {
        $result = array();
    }
    $rows       = isset($result['rows']) ? (array) $result['rows'] : array();
    $total      = isset($result['total']) ? (int) $result['total'] : 0;
    $pages      = isset($result['pages']) ? (int) $result['pages'] : 1;
    $can_clear  = zhiji_ops_can_clear($id);
    $bulk_form  = 'zhiji-ops-bulk-form';
    ?>
    <div class="wrap zhiji-ops">
        <div class="zhiji-ops-head">
            <h1>
                <?php echo esc_html($scene['title']); ?>
                <a href="<?php echo esc_url(zhiji_ops_page_url()); ?>" class="page-title-action"><?php esc_html_e('返回总览', 'zhiji'); ?></a>
            </h1>
            <?php if ($scene['desc']) : ?>
                <p class="zhiji-ops-desc"><?php echo esc_html($scene['desc']); ?></p>
            <?php endif; ?>
            <?php if ($scene['notice']) : ?>
                <p class="zhiji-ops-desc"><strong><?php echo esc_html($scene['notice']); ?></strong></p>
            <?php endif; ?>
        </div>
        <?php zhiji_ops_print_notice(); ?>
        <?php
        // HTTP 接口（JSON）入口数据：供页面后续 AJAX 刷新或外部脚本调用
        //   zhiji_api → zhiji_ops_query / zhiji_ops_clear
        ?>
        <div id="zhiji-ops-api"
             data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
             data-nonce="<?php echo esc_attr(wp_create_nonce('zhiji_ops')); ?>"
             data-scene="<?php echo esc_attr($id); ?>"
             data-per-page="<?php echo esc_attr($per_page); ?>"
             hidden></div>
        <?php if (!$can_clear) : ?>
            <div class="notice notice-warning inline"><p><?php esc_html_e('当前已关闭「运维清除」权限：可以查询记录，但不能重置或删除（可在主题设置 → 运维管理 中开启）。', 'zhiji'); ?></p></div>
        <?php endif; ?>

        <?php zhiji_ops_section_title(__('状态概览', 'zhiji')); ?>
        <?php
        if (is_callable($scene['stats'])) {
            zhiji_ops_render_cards((array) call_user_func($scene['stats']));
        }
        ?>

        <?php zhiji_ops_section_title(__('筛选与查询', 'zhiji')); ?>

        <!-- 查询筛选 -->
        <form class="zhiji-ops-filters" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
            <input type="hidden" name="page" value="<?php echo esc_attr(zhiji_ops_scene_slug($id)); ?>">
            <?php foreach ($scene['filters'] as $filter) : ?>
                <?php
                $key   = sanitize_key($filter['key']);
                $type  = isset($filter['type']) ? $filter['type'] : 'text';
                $value = isset($filters[$key]) ? $filters[$key] : '';
                ?>
                <div class="zhiji-ops-field">
                    <label for="zhiji-ops-f-<?php echo esc_attr($key); ?>"><?php echo esc_html($filter['label']); ?></label>
                    <?php if ('select' === $type) : ?>
                        <select id="zhiji-ops-f-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>">
                            <?php foreach ((array) $filter['options'] as $opt_val => $opt_label) : ?>
                                <option value="<?php echo esc_attr($opt_val); ?>" <?php selected((string) $value, (string) $opt_val); ?>><?php echo esc_html($opt_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ('date' === $type) : ?>
                        <input type="date" id="zhiji-ops-f-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                    <?php else : ?>
                        <input type="text" id="zhiji-ops-f-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>"
                               value="<?php echo esc_attr($value); ?>"
                               placeholder="<?php echo esc_attr(isset($filter['placeholder']) ? $filter['placeholder'] : ''); ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="zhiji-ops-field">
                <?php submit_button(__('查询', 'zhiji'), 'primary', '', false); ?>
            </div>
            <div class="zhiji-ops-field">
                <a class="button" href="<?php echo esc_url(zhiji_ops_page_url($id)); ?>"><?php esc_html_e('重置筛选', 'zhiji'); ?></a>
            </div>
        </form>

        <!-- 表单型操作（不需要先选中记录：目标数据可能根本不在当前列表里） -->
        <?php if ($can_clear && $scene['pre_actions']) : ?>
            <div class="zhiji-ops-preactions">
                <?php foreach ($scene['pre_actions'] as $pa) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                        <?php echo !empty($pa['confirm']) ? 'onsubmit="return confirm(\'' . esc_js($pa['confirm']) . '\');"' : ''; ?>>
                        <input type="hidden" name="action" value="zhiji_ops_action">
                        <input type="hidden" name="scene" value="<?php echo esc_attr($id); ?>">
                        <input type="hidden" name="op" value="<?php echo esc_attr($pa['key']); ?>">
                        <input type="hidden" name="redirect" value="<?php echo esc_attr(zhiji_ops_current_url($id, $filters, $page)); ?>">
                        <?php wp_nonce_field('zhiji_ops_action'); ?>
                        <strong<?php echo !empty($pa['tone']) ? ' class="' . esc_attr($pa['tone']) . '"' : ''; ?>><?php echo esc_html($pa['label']); ?></strong>
                        <?php foreach ((array) $pa['fields'] as $field) : ?>
                            <label for="zhiji-ops-pa-<?php echo esc_attr($pa['key'] . '-' . $field['name']); ?>"><?php echo esc_html($field['label']); ?></label>
                            <input type="<?php echo esc_attr(isset($field['type']) ? $field['type'] : 'text'); ?>"
                                   id="zhiji-ops-pa-<?php echo esc_attr($pa['key'] . '-' . $field['name']); ?>"
                                   name="<?php echo esc_attr($field['name']); ?>"
                                   placeholder="<?php echo esc_attr(isset($field['placeholder']) ? $field['placeholder'] : ''); ?>"
                                   <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                        <?php endforeach; ?>
                        <button type="submit" class="button <?php echo !empty($pa['tone']) ? esc_attr($pa['tone']) : 'button-secondary'; ?>">
                            <?php echo esc_html__('执行', 'zhiji'); ?>
                        </button>
                        <?php if (!empty($pa['desc'])) : ?>
                            <span class="description"><?php echo esc_html($pa['desc']); ?></span>
                        <?php endif; ?>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- 批量操作表单（表格内 checkbox 通过 form 属性归属此表单） -->
        <?php zhiji_ops_section_title(__('数据记录', 'zhiji'), sprintf(__('共 %d 条', 'zhiji'), $total)); ?>
        <?php if ($can_clear && $scene['actions']) : ?>
        <form id="<?php echo esc_attr($bulk_form); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="zhiji_ops_action">
            <input type="hidden" name="scene" value="<?php echo esc_attr($id); ?>">
            <input type="hidden" name="redirect" value="<?php echo esc_attr(zhiji_ops_current_url($id, $filters, $page)); ?>">
            <?php wp_nonce_field('zhiji_ops_action'); ?>
            <div class="zhiji-ops-bulkbar">
                <strong><?php esc_html_e('批量操作', 'zhiji'); ?></strong>
                <select name="op">
                    <option value=""><?php esc_html_e('— 选择操作 —', 'zhiji'); ?></option>
                    <?php foreach ($scene['actions'] as $act) : ?>
                        <?php if (empty($act['bulk'])) { continue; } ?>
                        <option value="<?php echo esc_attr($act['key']); ?>"><?php echo esc_html($act['label']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button(__('应用到勾选记录', 'zhiji'), 'secondary', '', false); ?>
                <span class="description"><?php esc_html_e('危险操作会弹出二次确认。', 'zhiji'); ?></span>
            </div>
        </form>
        <?php endif; ?>

        <!-- 数据表 -->
        <div class="zhiji-ops-table-wrap<?php echo ($can_clear && $scene['actions']) ? ' has-bulkbar' : ''; ?>">
            <table class="wp-list-table widefat fixed striped zhiji-ops-table">
                <thead>
                    <tr>
                        <?php if ($can_clear && $scene['actions']) : ?>
                            <td class="manage-column column-cb check-column" style="width:32px">&nbsp;</td>
                        <?php endif; ?>
                        <?php foreach ($scene['columns'] as $col) : ?>
                            <th scope="col" <?php echo !empty($col['width']) ? 'style="width:' . esc_attr($col['width']) . '"' : ''; ?>>
                                <?php echo esc_html($col['label']); ?>
                            </th>
                        <?php endforeach; ?>
                        <?php if ($can_clear && $scene['actions']) : ?>
                            <th scope="col" style="width:220px"><?php esc_html_e('操作', 'zhiji'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows) : ?>
                    <tr>
                        <td colspan="<?php echo (int) (count($scene['columns']) + 2); ?>">
                            <div class="zhiji-ops-empty">
                                <span class="dashicons dashicons-search"></span>
                                <strong><?php esc_html_e('没有匹配的记录', 'zhiji'); ?></strong>
                                <?php esc_html_e('调整上方筛选条件后重试；若目标对象查不到，用上方的表单入口直接处理。', 'zhiji'); ?>
                            </div>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($rows as $row) : ?>
                        <?php $row_id = isset($row->id) ? (int) $row->id : 0; ?>
                        <tr>
                            <?php if ($can_clear && $scene['actions']) : ?>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" form="<?php echo esc_attr($bulk_form); ?>" name="ids[]" value="<?php echo esc_attr($row_id); ?>">
                                </th>
                            <?php endif; ?>
                            <?php foreach ($scene['columns'] as $col) : ?>
                                <td>
                                    <?php
                                    if (is_callable($col['render'])) {
                                        call_user_func($col['render'], $row, $id);
                                    } elseif (isset($row->{$col['key']})) {
                                        echo esc_html((string) $row->{$col['key']});
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                            <?php if ($can_clear && $scene['actions']) : ?>
                                <td>
                                    <div class="zhiji-ops-rowactions">
                                        <?php foreach ($scene['actions'] as $act) : ?>
                                            <?php if (empty($act['single'])) { continue; } ?>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                                <?php echo !empty($act['confirm']) ? 'onsubmit="return confirm(\'' . esc_js($act['confirm']) . '\');"' : ''; ?>>
                                                <input type="hidden" name="action" value="zhiji_ops_action">
                                                <input type="hidden" name="scene" value="<?php echo esc_attr($id); ?>">
                                                <input type="hidden" name="op" value="<?php echo esc_attr($act['key']); ?>">
                                                <input type="hidden" name="id" value="<?php echo esc_attr($row_id); ?>">
                                                <input type="hidden" name="redirect" value="<?php echo esc_attr(zhiji_ops_current_url($id, $filters, $page)); ?>">
                                                <?php wp_nonce_field('zhiji_ops_action'); ?>
                                                <button type="submit" class="button button-small <?php echo !empty($act['tone']) ? esc_attr($act['tone']) : ''; ?>">
                                                    <?php echo esc_html($act['label']); ?>
                                                </button>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 分页 -->
        <div class="zhiji-ops-pagination">
            <span class="description">
                <?php
                printf(
                    /* translators: 1: 当前条数 2: 总数 */
                    esc_html__('共 %1$d 条记录，第 %2$d / %3$d 页', 'zhiji'),
                    (int) $total,
                    (int) max(1, $page),
                    (int) max(1, $pages)
                );
                ?>
            </span>
            <?php if ($page > 1) : ?>
                <a class="button button-small" href="<?php echo esc_url(zhiji_ops_current_url($id, $filters, $page - 1)); ?>"><?php esc_html_e('上一页', 'zhiji'); ?></a>
            <?php endif; ?>
            <?php if ($page < $pages) : ?>
                <a class="button button-small" href="<?php echo esc_url(zhiji_ops_current_url($id, $filters, $page + 1)); ?>"><?php esc_html_e('下一页', 'zhiji'); ?></a>
            <?php endif; ?>
        </div>

        <?php zhiji_ops_section_title(__('最近运维操作', 'zhiji')); ?>
        <?php zhiji_ops_render_activity($id, 8); ?>

        <!-- 接入说明：如何新增场景 -->
        <div class="zhiji-ops-doc">
            <h2><?php esc_html_e('如何接入新的运维场景', 'zhiji'); ?></h2>            <p class="description"><?php esc_html_e('本页面为"场景注册制"：新增一个业务场景无需改动页面代码，只要新增一个场景声明文件即可。', 'zhiji'); ?></p>
            <ol>
                <li><?php esc_html_e('在 includes/admin/scenes/ 下新建场景文件，调用 zhiji_ops_register_scene($id, $args) 声明。', 'zhiji'); ?></li>
                <li><code>stats</code> <?php esc_html_e('返回统计卡片；', 'zhiji'); ?><code>filters</code> <?php esc_html_e('声明筛选项；', 'zhiji'); ?><code>columns</code> <?php esc_html_e('声明表格列（支持 render 回调）；', 'zhiji'); ?></li>
                <li><code>query</code> <?php esc_html_e('返回 rows/total/pages；', 'zhiji'); ?><code>actions</code> + <code>handle</code> <?php esc_html_e('声明并处理操作。', 'zhiji'); ?></li>
                <li><?php esc_html_e('记录类数据建议统一走 core/ClaimLog.php（scene 维度隔离，自带查询/清除/审计）。', 'zhiji'); ?></li>
            </ol>
            <p class="description">
                <?php esc_html_e('查询接口：', 'zhiji'); ?>
                <code>zhiji_claim_log_query()</code> ·
                <?php esc_html_e('清除接口：', 'zhiji'); ?>
                <code>zhiji_claim_log_clear()</code> ·
                <?php esc_html_e('HTTP 接口：', 'zhiji'); ?>
                <code>admin-post.php?action=zhiji_ops_action</code> ·
                <code>zhiji_api → zhiji_ops_query / zhiji_ops_clear</code>
            </p>
        </div>
    </div>
    <?php
}

/**
 * 采集筛选参数（仅取场景声明过的键，白名单）
 *
 * @param array $scene
 * @return array
 */
function zhiji_ops_collect_filters(array $scene)
{
    $out = array();
    foreach ($scene['filters'] as $filter) {
        $key = sanitize_key($filter['key']);
        if (!isset($_GET[$key])) {
            continue;
        }
        $raw = wp_unslash($_GET[$key]);
        if (is_array($raw)) {
            continue;
        }
        $val = sanitize_text_field($raw);
        if ('' === $val) {
            continue;
        }
        $type          = isset($filter['type']) ? $filter['type'] : 'text';
        $out[$key]     = ('date' === $type) ? substr($val, 0, 10) : $val;
    }
    return $out;
}

/**
 * 当前页面 URL（保留筛选与分页）
 *
 * @param string $id
 * @param array  $filters
 * @param int    $page
 * @return string
 */
function zhiji_ops_current_url($id, array $filters = array(), $page = 1)
{
    $args = array('page' => zhiji_ops_scene_slug($id));
    if ($filters) {
        $args = array_merge($args, $filters);
    }
    if ($page > 1) {
        $args['paged'] = (int) $page;
    }
    return add_query_arg($args, admin_url('admin.php'));
}

/* ============================================================
 * 五、操作处理（admin-post）
 * ============================================================ */

add_action('admin_post_zhiji_ops_action', 'zhiji_ops_handle_action');

/**
 * 处理运维页面提交的操作
 *
 * 统一校验：登录 + 权限 + nonce + 场景存在 + 清除开关。
 * 之后交给场景自己的 handle 回调；场景未提供时回退到 ClaimLog 的通用重置/删除。
 *
 * @return void
 */
function zhiji_ops_handle_action()
{
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die(__('您没有权限执行该操作', 'zhiji'));
    }
    check_admin_referer('zhiji_ops_action');

    $scene_id = isset($_POST['scene']) ? sanitize_key(wp_unslash($_POST['scene'])) : '';
    $op       = isset($_POST['op']) ? sanitize_key(wp_unslash($_POST['op'])) : '';
    $ids      = isset($_POST['ids']) ? array_filter(array_map('intval', (array) wp_unslash($_POST['ids']))) : array();
    $single   = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($single) {
        $ids[] = $single;
    }
    $ids      = array_values(array_unique($ids));
    $redirect = isset($_POST['redirect']) ? esc_url_raw(wp_unslash($_POST['redirect'])) : '';

    $scene = zhiji_ops_scene($scene_id);
    $fail  = function ($msg) use ($redirect, $scene_id) {
        zhiji_ops_redirect_back($redirect, $scene_id, false, $msg);
    };

    if (!$scene) {
        $fail(__('场景不存在', 'zhiji'));
    }
    if (!current_user_can($scene['cap'])) {
        $fail(__('权限不足', 'zhiji'));
    }
    if ('' === $op) {
        $fail(__('未选择操作', 'zhiji'));
    }
    if (!zhiji_ops_can_clear($scene_id)) {
        $fail(__('「运维清除」已被关闭，操作被拒绝', 'zhiji'));
    }

    // 校验操作是否在该场景声明内（行内/批量操作 vs 表单型操作）
    $declared = wp_list_pluck($scene['actions'], 'key');
    $pre_ops  = wp_list_pluck($scene['pre_actions'], 'key');
    $is_pre   = in_array($op, $pre_ops, true);
    if (!$is_pre && !in_array($op, $declared, true)) {
        $fail(__('该场景不支持此操作', 'zhiji'));
    }
    // 表单型操作（pre_actions）自带参数，不要求选中记录；行内/批量操作必须选中
    if (!$is_pre && !$ids) {
        $fail(__('未选择任何记录', 'zhiji'));
    }

    $result = array('ok' => false, 'msg' => __('操作未执行', 'zhiji'));
    if (is_callable($scene['handle'])) {
        $result = (array) call_user_func($scene['handle'], $op, $_POST, $ids, $scene);
    } else {
        // 通用回退：reset / delete 直接作用于 ClaimLog
        $mode   = ('delete' === $op) ? 'delete' : 'reset';
        $ret    = zhiji_claim_log_clear(array(
            'ids'  => $ids,
            'mode' => $mode,
            'by'   => get_current_user_id(),
        ));
        $result = array(
            'ok'  => true,
            /* translators: 1: 操作名 2: 影响行数 */
            'msg' => sprintf(__('%1$s：影响 %2$d 条记录', 'zhiji'), zhiji_ops_action_label($op), (int) $ret['affected']),
        );
    }

    $ok  = !empty($result['ok']);
    $msg = isset($result['msg']) ? (string) $result['msg'] : ($ok ? __('操作完成', 'zhiji') : __('操作失败', 'zhiji'));

    zhiji_ops_add_activity(
        $op,
        sprintf(
            /* translators: 1: 记录数 2: 结果 */
            __('%1$d 条记录：%2$s', 'zhiji'),
            count($ids),
            $msg
        ),
        $scene_id
    );

    zhiji_ops_redirect_back($redirect, $scene_id, $ok, $msg);
}

/**
 * 操作完成后跳回原页面并带回提示
 *
 * @param string $redirect
 * @param string $scene_id
 * @param bool   $ok
 * @param string $msg
 * @return void
 */
function zhiji_ops_redirect_back($redirect, $scene_id, $ok, $msg)
{
    if ('' === $redirect) {
        $redirect = zhiji_ops_page_url($scene_id);
    }
    $redirect = add_query_arg(array(
        'ops_ok'  => $ok ? '1' : '0',
        'ops_msg' => rawurlencode(mb_substr((string) $msg, 0, 180)),
    ), $redirect);

    wp_safe_redirect($redirect);
    exit;
}

/* ============================================================
 * 六、HTTP 接口（管理端 JSON：查询 / 清除）
 *
 * 注册进统一网关（nonce 'zhiji_ops'，仅登录用户），供运维页面 AJAX
 * 或外部工具/脚本复用；权限统一要求 manage_options。
 * ============================================================ */

zhiji_api_register('zhiji_ops_query', 'zhiji_ops_api_query', false, 'zhiji_ops');
zhiji_api_register('zhiji_ops_clear', 'zhiji_ops_api_clear', false, 'zhiji_ops');

/**
 * 查询接口：按场景与条件查询记录
 *
 * 入参：scene / email / status / source / search / date_from / date_to / page / per_page
 *
 * @param array $request
 * @return void
 */
function zhiji_ops_api_query($request = array())
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('msg' => __('权限不足', 'zhiji')), 403);
    }
    if (!zhiji_ops_enabled()) {
        wp_send_json_error(array('msg' => __('运维页面未启用', 'zhiji')), 403);
    }

    $scene_id = zhiji_api_enum($request, 'scene', array_keys(zhiji_ops_scenes()), '');
    if ('' === $scene_id) {
        wp_send_json_error(array('msg' => __('缺少或无效的 scene 参数', 'zhiji')), 400);
    }

    $result = zhiji_claim_log_query(array(
        'scene'     => $scene_id,
        'email'     => zhiji_api_str($request, 'email', 100),
        'status'    => zhiji_api_enum($request, 'status', array('', 'active', 'cleared'), ''),
        'source'    => zhiji_api_str($request, 'source', 40),
        'search'    => zhiji_api_str($request, 'search', 100),
        'date_from' => zhiji_api_str($request, 'date_from', 10),
        'date_to'   => zhiji_api_str($request, 'date_to', 10),
        'page'      => max(1, zhiji_api_digits($request, 'page', 1)),
        'per_page'  => min(200, max(1, zhiji_api_digits($request, 'per_page', 20))),
    ));

    wp_send_json_success(array(
        'scene' => $scene_id,
        'stats' => zhiji_claim_log_stats($scene_id),
        'rows'  => $result['rows'],
        'total' => $result['total'],
        'pages' => $result['pages'],
        'page'  => $result['page'],
    ));
}

/**
 * 清除接口：重置（恢复可领取）或删除记录
 *
 * 入参：scene / mode(reset|delete) / ids(逗号分隔) 或 email
 *
 * @param array $request
 * @return void
 */
function zhiji_ops_api_clear($request = array())
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('msg' => __('权限不足', 'zhiji')), 403);
    }

    $scene_id = zhiji_api_enum($request, 'scene', array_keys(zhiji_ops_scenes()), '');
    if ('' === $scene_id) {
        wp_send_json_error(array('msg' => __('缺少或无效的 scene 参数', 'zhiji')), 400);
    }
    if (!zhiji_ops_can_clear($scene_id)) {
        wp_send_json_error(array('msg' => __('「运维清除」已被关闭', 'zhiji')), 403);
    }

    $mode = zhiji_api_enum($request, 'mode', array('reset', 'delete'), 'reset');
    $ids  = array();
    if (!empty($request['ids'])) {
        foreach (explode(',', (string) $request['ids']) as $piece) {
            if (preg_match('/^\d+$/', trim($piece))) {
                $ids[] = (int) trim($piece);
            }
        }
    }
    $email = zhiji_api_str($request, 'email', 100);
    if (!$ids && '' === $email) {
        wp_send_json_error(array('msg' => __('必须提供 ids 或 email，禁止无条件清除', 'zhiji')), 400);
    }

    $ret = zhiji_claim_log_clear(array(
        'ids'   => $ids,
        'scene' => $scene_id,
        'email' => $email,
        'mode'  => $mode,
        'note'  => __('通过 HTTP 接口清除', 'zhiji'),
        'by'    => get_current_user_id(),
    ));

    if (!empty($ret['error'])) {
        wp_send_json_error(array('msg' => $ret['error']), 400);
    }

    zhiji_ops_add_activity(
        'reset' === $mode ? 'reset' : 'delete',
        sprintf(
            /* translators: 1: 记录数 2: 场景 */
            __('HTTP 接口清除 %1$d 条记录（%2$s）', 'zhiji'),
            (int) $ret['affected'],
            $email ? $email : implode(',', $ids)
        ),
        $scene_id
    );

    wp_send_json_success(array(
        'scene'    => $scene_id,
        'mode'     => $ret['mode'],
        'affected' => $ret['affected'],
        'stats'    => zhiji_claim_log_stats($scene_id),
    ));
}
