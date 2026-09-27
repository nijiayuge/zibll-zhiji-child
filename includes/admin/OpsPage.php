<?php
/**
 * @module  OpsPage
 * @desc    运维台后台页面：菜单注册 + 场景页组装。
 *          渲染元件见 OpsRender.php｜操作处理见 OpsActions.php｜JSON 接口见 OpsApi.php。
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
            <?php
            // 导出当前筛选结果（只读能力：关闸时同样可用，便于取证）
            $export_url = add_query_arg(
                array_merge(array('action' => 'zhiji_ops_export', 'scene' => $id, '_wpnonce' => wp_create_nonce('zhiji_ops_export_' . $id)), $filters),
                admin_url('admin-post.php')
            );
            ?>
            <div class="zhiji-ops-field zhiji-ops-field-actions">
                <a class="button" href="<?php echo esc_url($export_url); ?>">
                    <span class="dashicons dashicons-download" style="vertical-align:text-top"></span>
                    <?php esc_html_e('导出 CSV', 'zhiji'); ?>
                </a>
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
