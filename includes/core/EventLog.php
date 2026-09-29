<?php
/**
 * @module  EventLog（核心基础设施）
 * @desc    系统事件日志 —— 排障用的"机器轨迹"（附录 Y ⭐⭐）：
 *          统一记录**系统侧失败**（邮件发送失败、WebP 转换失败等），
 *          与「操作审计」（谁做了什么）互补：审计回答"人做了什么"，事件回答"系统哪里出了问题"。
 * @option  zhiji_event_table_version（autoload=false）
 * @table   {prefix}zhiji_event_log
 * @api     zhiji_event_log($type, $message, $context)   记录事件（内置 60s 同内容节流，防失败风暴刷库）
 *          zhiji_event_query($args)                     查询（type / date_from / date_to / search + 分页）
 *          zhiji_event_types()                          日志中实际出现过的类型（筛选用）
 *          zhiji_event_cleanup()                        按保留天数清理（每日 cron）
 * @hook    wp_mail_failed                              邮件发送失败 → type=mail
 *          zhiji_event_cleanup（每日 cron）             保留期清理
 * @since   2.0.0（2026-09-29 新增）
 *
 * ⚠️ 设计纪律：
 *  1) **只记系统侧失败，不记业务操作**（业务操作归审计日志）；
 *  2) **节流**：同 type+message 在 60 秒内只记一条 —— 邮件队列坏了可能每秒失败一次，
 *     不节流会把表刷爆，把真正不同的问题淹没；
 *  3) **保留期**：默认 30 天自动清理（排障日志不是审计证据，不需要长期保留）。
 */

defined('ABSPATH') || exit;

if (!defined('ZHIJI_EVENT_TABLE_VERSION')) {
    define('ZHIJI_EVENT_TABLE_VERSION', '1.0');
}
if (!defined('ZHIJI_EVENT_RETENTION_DAYS')) {
    define('ZHIJI_EVENT_RETENTION_DAYS', 30);
}

/**
 * 事件表名
 *
 * @return string
 */
function zhiji_event_table()
{
    global $wpdb;
    return $wpdb->prefix . 'zhiji_event_log';
}

/**
 * 建表（幂等，dbDelta；与 ClaimLog 同一范式）
 *
 * @return void
 */
function zhiji_event_install()
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table   = zhiji_event_table();
    $collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        time DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
        type VARCHAR(40) NOT NULL DEFAULT '',
        message VARCHAR(255) NOT NULL DEFAULT '',
        context LONGTEXT NULL,
        PRIMARY KEY (id),
        KEY type (type),
        KEY time (time)
    ) {$collate};";

    dbDelta($sql);
    update_option('zhiji_event_table_version', ZHIJI_EVENT_TABLE_VERSION, false);
}

// 幂等建表：仅在版本不符时执行（option 走 autoload=false）
add_action('wp_loaded', function () {
    if (get_option('zhiji_event_table_version') === ZHIJI_EVENT_TABLE_VERSION) {
        return;
    }
    zhiji_event_install();
    // 保留期清理：每日一次（建表后顺手排上，幂等）
    if (!wp_next_scheduled('zhiji_event_cleanup')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'zhiji_event_cleanup');
    }
});

/**
 * 记录一条系统事件
 *
 * @param string $type    事件类型（mail / webp / …）
 * @param string $message 摘要（≤255 字符，超出截断）
 * @param array  $context 上下文（数组 → JSON 存储；注意不要放敏感凭据）
 * @return int 事件 ID（被节流跳过时返回 0）
 */
function zhiji_event_log($type, $message, array $context = array())
{
    global $wpdb;
    $table = zhiji_event_table();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return 0; // 表未就绪（如插件加载早于建表）—— 静默放弃，绝不能因日志而炸业务
    }

    $type    = sanitize_key((string) $type);
    $message = mb_substr((string) $message, 0, 255);

    // 节流：同 type+message 在 60 秒内只记一条（防失败风暴刷库）
    $recent = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE type = %s AND message = %s AND time >= %s LIMIT 1",
        $type,
        $message,
        date('Y-m-d H:i:s', current_time('timestamp') - 60)
    ));
    if ($recent) {
        return 0;
    }

    $wpdb->insert(
        $table,
        array(
            'time'    => current_time('mysql'),
            'type'    => $type,
            'message' => $message,
            'context' => $context ? wp_json_encode($context, JSON_UNESCAPED_UNICODE) : null,
        ),
        array('%s', '%s', '%s', '%s')
    );
    return (int) $wpdb->insert_id;
}

/**
 * 查询事件（与运维场景查询契约同构）
 *
 * $args：type / search（message 模糊）/ date_from / date_to / page / per_page
 *
 * @param array $args
 * @return array array('rows','total','pages','page','per_page')
 */
function zhiji_event_query(array $args = array())
{
    global $wpdb;
    $table = zhiji_event_table();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return array('rows' => array(), 'total' => 0, 'pages' => 1, 'page' => 1, 'per_page' => 20);
    }

    $where  = array('1=1');
    $params = array();
    if (!empty($args['type'])) {
        $where[]  = 'type = %s';
        $params[] = (string) $args['type'];
    }
    if (!empty($args['search'])) {
        $where[]  = 'message LIKE %s';
        $params[] = '%' . $wpdb->esc_like((string) $args['search']) . '%';
    }
    if (!empty($args['date_from'])) {
        $where[]  = 'time >= %s';
        $params[] = (string) $args['date_from'] . ' 00:00:00';
    }
    if (!empty($args['date_to'])) {
        $where[]  = 'time <= %s';
        $params[] = (string) $args['date_to'] . ' 23:59:59';
    }

    $total    = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE " . implode(' AND ', $where),
        $params
    ));
    $per_page = min(200, max(1, (int) (isset($args['per_page']) ? $args['per_page'] : 20)));
    $pages    = max(1, (int) ceil($total / $per_page));
    $page     = max(1, min($pages, (int) (isset($args['page']) ? $args['page'] : 1)));

    $params[] = ($page - 1) * $per_page;
    $params[] = $per_page;
    $rows     = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . ' ORDER BY time DESC, id DESC LIMIT %d, %d',
        $params
    ));

    return array(
        'rows'     => (array) $rows,
        'total'    => $total,
        'pages'    => $pages,
        'page'     => $page,
        'per_page' => $per_page,
    );
}

/**
 * 日志中实际出现过的类型（筛选用，避免枚举写死漏新类型）
 *
 * @return array 值 => 类型名
 */
function zhiji_event_types()
{
    global $wpdb;
    $table = zhiji_event_table();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return array();
    }
    $rows = $wpdb->get_col("SELECT DISTINCT type FROM {$table} ORDER BY type");
    $out  = array();
    foreach ((array) $rows as $t) {
        $out[$t] = zhiji_event_type_label($t);
    }
    return $out;
}

/**
 * 事件类型 → 中文
 *
 * @param string $type
 * @return string
 */
function zhiji_event_type_label($type)
{
    $map = array(
        'mail' => __('邮件发送失败', 'zhiji'),
        'webp' => __('WebP 转换失败', 'zhiji'),
    );
    $type = (string) $type;
    return isset($map[$type]) ? $map[$type] : $type;
}

/**
 * 保留期清理（每日 cron）
 *
 * @return int 删除行数
 */
function zhiji_event_cleanup()
{
    global $wpdb;
    $table = zhiji_event_table();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return 0;
    }
    $cut = date('Y-m-d H:i:s', current_time('timestamp') - ZHIJI_EVENT_RETENTION_DAYS * DAY_IN_SECONDS);
    return (int) $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE time < %s", $cut));
}
add_action('zhiji_event_cleanup', 'zhiji_event_cleanup');

/* ============================================================
 * 写入点（v1：邮件失败 + WebP 失败；后续按需追加）
 * ============================================================ */

// 邮件发送失败（券码邮件 / 奖励通知邮件都走 wp_mail —— 这是运营最需要知道的失败）
add_action('wp_mail_failed', function ($wp_error) {
    if (!is_wp_error($wp_error)) {
        return;
    }
    $ctx = $wp_error->get_error_data();
    zhiji_event_log('mail', $wp_error->get_error_message(), array(
        'to'      => isset($ctx['to']) ? (string) $ctx['to'] : '',
        'subject' => isset($ctx['subject']) ? mb_substr((string) $ctx['subject'], 0, 120) : '',
    ));
}, 10, 1);
