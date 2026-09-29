<?php
/**
 * @module  ClaimLog
 * @desc    一次性领取类业务的持久化日志与校验基础设施（按 scene 维度隔离）
 *          - 领取前校验：同一邮箱是否已领取 / 是否已被运维放行
 *          - 领取成功落库：邮箱、用户、IP、关联对象（券码等）、来源、时间
 *          - 运维查询与清除：reset（标记放行、保留审计）/ delete（硬删）
 *          放在 core 层，任何业务模块可直接调用，不受模块加载顺序影响。
 * @api     zhiji_claim_log_table()        表名
 *          zhiji_claim_log_install()      幂等建表（wp_loaded 自动触发）
 *          zhiji_claim_log_add($args)     写入一条领取记录 → int|false
 *          zhiji_claim_log_query($args)   条件查询（分页 + 总数）
 *          zhiji_claim_log_stats($scene)  统计（总/有效/已清除/今日）
 *          zhiji_claim_log_check($args)   领取前校验 → array(allow,reason,code,msg,record)
 *          zhiji_claim_log_clear($args)   清除 → array(mode,affected)
 * @since   2.0.0
 */

defined('ABSPATH') || exit;

if (!defined('ZHIJI_CLAIM_TABLE')) {
    define('ZHIJI_CLAIM_TABLE', 'zhiji_claim_log');
}
if (!defined('ZHIJI_CLAIM_TABLE_VERSION')) {
    define('ZHIJI_CLAIM_TABLE_VERSION', '1.0');
}

/**
 * 领取记录表名（含前缀）
 *
 * @return string
 */
function zhiji_claim_log_table()
{
    global $wpdb;
    return $wpdb->prefix . ZHIJI_CLAIM_TABLE;
}

/**
 * 邮箱归一化键（小写 + 去空格后取 md5，用于等值索引查询）
 *
 * @param string $email
 * @return string
 */
function zhiji_claim_log_email_key($email)
{
    $email = strtolower(trim((string) $email));
    return '' === $email ? '' : md5($email);
}

/**
 * 建表（幂等，dbDelta）
 *
 * @return void
 */
function zhiji_claim_log_install()
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table   = zhiji_claim_log_table();
    $collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        scene VARCHAR(40) NOT NULL DEFAULT '',
        email VARCHAR(100) NOT NULL DEFAULT '',
        email_key CHAR(32) NOT NULL DEFAULT '',
        user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        ip VARCHAR(45) NOT NULL DEFAULT '',
        object_id VARCHAR(64) NOT NULL DEFAULT '',
        source VARCHAR(40) NOT NULL DEFAULT '',
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        note VARCHAR(255) NOT NULL DEFAULT '',
        created DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
        cleared DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
        cleared_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        meta LONGTEXT NULL,
        PRIMARY KEY (id),
        KEY scene_email (scene, email_key),
        KEY scene_status (scene, status),
        KEY object_id (object_id),
        KEY created (created)
    ) {$collate};";

    dbDelta($sql);
    update_option('zhiji_claim_table_version', ZHIJI_CLAIM_TABLE_VERSION, false);
}

// 幂等建表：仅在版本不符时执行（option 走 autoload=false，避免额外查询）
add_action('wp_loaded', function () {
    if (get_option('zhiji_claim_table_version') === ZHIJI_CLAIM_TABLE_VERSION) {
        return;
    }
    zhiji_claim_log_install();
});

/**
 * 写入一条领取记录
 *
 * @param array $args scene（必填）/ email / user_id / ip / object_id / source / status / note / meta
 * @return int|false 记录 ID
 */
function zhiji_claim_log_add(array $args)
{
    global $wpdb;

    $args = wp_parse_args($args, array(
        'scene'     => '',
        'email'     => '',
        'user_id'   => 0,
        'ip'        => '',
        'object_id' => '',
        'source'    => '',
        'status'    => 'active',
        'note'      => '',
        'meta'      => array(),
    ));

    $scene = sanitize_key($args['scene']);
    if ('' === $scene) {
        return false;
    }

    $email = sanitize_email($args['email']);
    // ⚠️ 必须**显式传 format**（2026-09-27 踩坑）：
    //    WordPress 的 wpdb::$field_types 里注册了 'object_id' => '%d'（源自核心表 wp_term_relationships），
    //    不传 format 时 wpdb 会按 %d 处理，把券码（如 'iauZY6EXN0bQ' / '=1+1'）强转成整数 → 存成 0！
    //    （历史数据里"关联优惠码"大量为 0/空即由此造成，已由 zhiji_claim_log_repair_object_id() 修复）
    $data = array(
        'scene'     => $scene,
        'email'     => $email,
        'email_key' => zhiji_claim_log_email_key($email),
        'user_id'   => (int) $args['user_id'],
        'ip'        => substr((string) $args['ip'], 0, 45),
        'object_id' => substr((string) $args['object_id'], 0, 64),
        'source'    => substr(sanitize_key($args['source']), 0, 40),
        'status'    => in_array($args['status'], array('active', 'cleared'), true) ? $args['status'] : 'active',
        'note'      => substr((string) $args['note'], 0, 255),
        'created'   => current_time('mysql'),
        'meta'      => wp_json_encode($args['meta'], JSON_UNESCAPED_UNICODE),
    );
    $formats = array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s');

    $ok = $wpdb->insert(zhiji_claim_log_table(), $data, $formats);
    return $ok ? (int) $wpdb->insert_id : false;
}

/**
 * 一次性修复：把因 wpdb field_types 误判而丢失的 object_id（券码）补回来
 *
 * 修复来源（两条可靠路径）：
 *   1) 福袋场景：券码就在 meta.reward.code 里 → 直接回填
 *   2) 领券场景：按邮箱到卡密表找"发放时间最接近（≤1 小时）"的那张券 → 回填
 *
 * @return int 修复条数
 */
function zhiji_claim_log_repair_object_id()
{
    global $wpdb;

    $table = zhiji_claim_log_table();
    $fixed = 0;

    // 1) 福袋场景：meta.reward.code
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, meta FROM {$table} WHERE scene = %s AND ( object_id = '' OR object_id = '0' ) LIMIT 500",
        ZHIJI_COMMENT_FORTUNE_CLAIM_SCENE
    ));
    foreach ((array) $rows as $r) {
        $meta = zhiji_claim_log_meta($r->meta);
        $code = isset($meta['reward']['code']) ? (string) $meta['reward']['code'] : '';
        if ('' !== $code) {
            $wpdb->query($wpdb->prepare("UPDATE {$table} SET object_id = %s WHERE id = %d", $code, (int) $r->id));
            $fixed++;
        }
    }

    // 2) 领券场景：按邮箱匹配卡密表里时间最接近的券
    $rows = $wpdb->get_results(
        "SELECT id, email, created FROM {$table} WHERE scene = 'coupon_give' AND ( object_id = '' OR object_id = '0' ) LIMIT 500"
    );
    if ($rows && class_exists('ZibCardPass')) {
        $by_email = array();
        foreach ((array) ZibCardPass::get(array('type' => 'coupon'), 'id', 0, 'all') as $c) {
            $m = maybe_unserialize($c->meta);
            if (is_array($m) && !empty($m['email'])) {
                $by_email[strtolower($m['email'])][] = array(
                    'code' => (string) $c->password,
                    'time' => (string) $c->create_time,
                );
            }
        }
        foreach ((array) $rows as $r) {
            $key = strtolower((string) $r->email);
            if (empty($by_email[$key])) {
                continue;
            }
            $best      = null;
            $best_delta = null;
            foreach ($by_email[$key] as $c) {
                $delta = abs(strtotime($c['time']) - strtotime($r->created));
                if (null === $best_delta || $delta < $best_delta) {
                    $best_delta = $delta;
                    $best       = $c;
                }
            }
            // 1 小时内视为同一次发放，避免把历史券错配到新记录
            if ($best && null !== $best_delta && $best_delta <= 3600) {
                $wpdb->query($wpdb->prepare("UPDATE {$table} SET object_id = %s WHERE id = %d", $best['code'], (int) $r->id));
                $fixed++;
            }
        }
    }

    update_option('zhiji_claim_object_id_fixed', '1.0', false);
    return $fixed;
}

// 幂等执行：仅在版本不符时跑一次
add_action('wp_loaded', function () {
    if (get_option('zhiji_claim_object_id_fixed') === '1.0') {
        return;
    }
    $n = zhiji_claim_log_repair_object_id();
    if ($n > 0) {
        zhiji_log('claim log object_id repaired', array('fixed' => $n));
    }
});

/**
 * 构造查询条件（内部使用）
 *
 * @param array $args
 * @return array array($where_sql, $params)
 */
function zhiji_claim_log_build_where(array $args)
{
    $args  = wp_parse_args($args, array(
        'scene'     => '',
        'email'     => '',
        'status'    => '',
        'source'    => '',
        'object_id' => '',
        'user_id'   => 0,
        'ip'        => '',
        'search'    => '',
        'date_from' => '',
        'date_to'   => '',
        'exclude_ids' => array(),
    ));

    $where  = array('1=1');
    $params = array();

    if ('' !== (string) $args['scene']) {
        $where[]  = 'scene = %s';
        $params[] = sanitize_key($args['scene']);
    }
    if ('' !== (string) $args['email']) {
        $where[]  = 'email_key = %s';
        $params[] = zhiji_claim_log_email_key($args['email']);
    }
    if ('' !== (string) $args['status']) {
        $where[]  = 'status = %s';
        $params[] = $args['status'];
    }
    if ('' !== (string) $args['source']) {
        $where[]  = 'source = %s';
        $params[] = sanitize_key($args['source']);
    }
    if ('' !== (string) $args['object_id']) {
        $where[]  = 'object_id = %s';
        $params[] = $args['object_id'];
    }
    if ((int) $args['user_id'] > 0) {
        $where[]  = 'user_id = %d';
        $params[] = (int) $args['user_id'];
    }
    if ('' !== (string) $args['ip']) {
        $where[]  = 'ip = %s';
        $params[] = $args['ip'];
    }
    if ('' !== (string) $args['search']) {
        // 模糊搜索：邮箱 或 关联对象
        $like     = '%' . $GLOBALS['wpdb']->esc_like($args['search']) . '%';
        $where[]  = '(email LIKE %s OR object_id LIKE %s)';
        $params[] = $like;
        $params[] = $like;
    }
    if ('' !== (string) $args['date_from']) {
        $where[]  = 'created >= %s';
        $params[] = $args['date_from'] . ' 00:00:00';
    }
    if ('' !== (string) $args['date_to']) {
        $where[]  = 'created <= %s';
        $params[] = $args['date_to'] . ' 23:59:59';
    }
    if (!empty($args['exclude_ids']) && is_array($args['exclude_ids'])) {
        $ids = array_filter(array_map('intval', $args['exclude_ids']));
        if ($ids) {
            $where[] = 'id NOT IN (' . implode(',', $ids) . ')';
        }
    }

    return array(implode(' AND ', $where), $params);
}

/**
 * 条件查询领取记录（分页 + 总数）
 *
 * @param array $args 见 zhiji_claim_log_build_where()，另加 page / per_page / orderby / order
 * @return array array(rows, total, pages, page, per_page)
 */
function zhiji_claim_log_query(array $args = array())
{
    global $wpdb;

    $args     = wp_parse_args($args, array(
        'page'     => 1,
        'per_page' => 20,
        'orderby'  => 'id',
        'order'    => 'DESC',
    ));
    $orderby  = in_array($args['orderby'], array('id', 'created', 'email', 'status'), true) ? $args['orderby'] : 'id';
    $order    = ('ASC' === strtoupper((string) $args['order'])) ? 'ASC' : 'DESC';
    $page     = max(1, (int) $args['page']);
    // per_page 上限 200：分页 UI 的防拖库上限；
    // 导出场景（zhiji_ops_handle_export）需要全量 → 由调用方循环分页拉取，不走单次 5000
    $per_page = min(200, max(1, (int) $args['per_page']));

    list($where_sql, $params) = zhiji_claim_log_build_where($args);
    $table = zhiji_claim_log_table();

    // 总数
    $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
    $total     = (int) ($params ? $wpdb->get_var($wpdb->prepare($count_sql, $params)) : $wpdb->get_var($count_sql));

    // 数据
    $sql        = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
    $data_parms = array_merge($params, array($per_page, ($page - 1) * $per_page));
    $rows       = $wpdb->get_results($wpdb->prepare($sql, $data_parms));

    return array(
        'rows'     => is_array($rows) ? $rows : array(),
        'total'    => $total,
        'pages'    => (int) ceil($total / $per_page),
        'page'     => $page,
        'per_page' => $per_page,
    );
}

/**
 * 统计某场景的记录（供运维总览卡片）
 *
 * @param string $scene
 * @return array total / active / cleared / today
 */
function zhiji_claim_log_stats($scene)
{
    global $wpdb;

    $scene = sanitize_key($scene);
    $table = zhiji_claim_log_table();
    $today = current_time('Y-m-d');

    $total   = 0;
    $active  = 0;
    $cleared = 0;
    if ('' !== $scene) {
        $row     = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN status='cleared' THEN 1 ELSE 0 END) AS cleared
             FROM {$table} WHERE scene = %s",
            $scene
        ));
        $total   = $row ? (int) $row->total : 0;
        $active  = $row ? (int) $row->active : 0;
        $cleared = $row ? (int) $row->cleared : 0;
    }

    $today_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE scene = %s AND created >= %s",
        $scene,
        $today . ' 00:00:00'
    ));

    return array(
        'total'   => $total,
        'active'  => $active,
        'cleared' => $cleared,
        'today'   => $today_count,
    );
}

/**
 * 领取前校验（同一邮箱是否已被占用 / 是否已被运维放行）
 *
 * 判定顺序（scene + email 维度）：
 *   1) 存在 status=active 记录 → 拦截（reason=claimed）
 *   2) 存在 status=cleared 记录 → 放行（reason=cleared_by_ops，运维已重置）
 *   3) 无任何记录 → 放行（reason=empty）
 *
 * 说明：本函数只负责"日志维度"的判定；调用方可继续叠加自己的其它规则
 * （如「每位用户仅限一次」「每日限量」），互不影响。
 *
 * @param array $args scene（必填）/ email / user_id / ip
 * @return array allow(bool) / reason(string) / code(string) / msg(string) / record(object|null)
 */
function zhiji_claim_log_check(array $args)
{
    global $wpdb;

    $args = wp_parse_args($args, array(
        'scene'   => '',
        'email'   => '',
        'user_id' => 0,
        'ip'      => '',
    ));

    $out = array(
        'allow'  => true,
        'reason' => 'empty',
        'code'   => '',
        'msg'    => '',
        'record' => null,
    );

    $scene = sanitize_key($args['scene']);
    $email = sanitize_email($args['email']);
    if ('' === $scene || '' === $email) {
        return apply_filters('zhiji_claim_log_check', $out, $args);
    }

    $table = zhiji_claim_log_table();
    $rows  = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE scene = %s AND email_key = %s ORDER BY id DESC LIMIT 50",
        $scene,
        zhiji_claim_log_email_key($email)
    ));

    if (is_array($rows) && $rows) {
        $active  = null;
        $cleared = null;
        foreach ($rows as $row) {
            if ('active' === $row->status && null === $active) {
                $active = $row;
            }
            if ('cleared' === $row->status && null === $cleared) {
                $cleared = $row;
            }
        }
        if (null !== $active) {
            // 有激活记录：
            //   仅当存在"更晚的已放行记录"时才视为已被运维重置（可再次领取）；
            //   没有已放行记录（$cleared 为 null）→ 一律拦截。
            if (null !== $cleared && (int) $cleared->id > (int) $active->id) {
                $out['allow']  = true;
                $out['reason'] = 'cleared_by_ops';
                $out['record'] = $active;
            } else {
                $out['allow']  = false;
                $out['reason'] = 'claimed';
                $out['code']   = 'email_claimed';
                $out['msg']    = __('该邮箱已领取过，同一邮箱仅限领取一次', 'zhiji');
                $out['record'] = $active;
            }
        } elseif (null !== $cleared) {
            $out['reason'] = 'cleared_by_ops';
        }
    }

    return apply_filters('zhiji_claim_log_check', $out, $args);
}

/**
 * 清除记录（运维页面调用）
 *
 * mode=reset  标记为 cleared：恢复可领取状态，同时保留审计信息（推荐）
 * mode=delete 硬删除记录（不可恢复；若调用方仍有其它"已领取"来源，可能仍被拦截）
 *
 * 必须给出过滤条件（ids 或 scene+email / scene+status），禁止无条件清除。
 *
 * @param array $args ids(array) / scene / email / status / mode / note / by(操作人 ID)
 * @return array array(mode, affected, error)
 */
function zhiji_claim_log_clear(array $args)
{
    global $wpdb;

    $args = wp_parse_args($args, array(
        'ids'    => array(),
        'scene'  => '',
        'email'  => '',
        'status' => '',
        'mode'   => 'reset',
        'note'   => '',
        'by'     => 0,
    ));

    $mode = ('delete' === $args['mode']) ? 'delete' : 'reset';
    $ids  = array_filter(array_map('intval', (array) $args['ids']));

    $where  = array();
    $params = array();

    if ($ids) {
        $where[] = 'id IN (' . implode(',', $ids) . ')';
    } else {
        if ('' !== (string) $args['email']) {
            $where[]  = 'email_key = %s';
            $params[] = zhiji_claim_log_email_key($args['email']);
        } elseif ('' !== (string) $args['scene'] && '' !== (string) $args['status']) {
            $where[]  = 'scene = %s';
            $params[] = sanitize_key($args['scene']);
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        } else {
            return array('mode' => $mode, 'affected' => 0, 'error' => 'missing_filter');
        }
        if ('' !== (string) $args['scene'] && '' !== (string) $args['email']) {
            $where[]  = 'scene = %s';
            $params[] = sanitize_key($args['scene']);
        }
    }

    $where_sql = implode(' AND ', $where);
    $table     = zhiji_claim_log_table();

    if ('delete' === $mode) {
        $sql = "DELETE FROM {$table} WHERE {$where_sql}";
    } else {
        $sql = "UPDATE {$table} SET status = 'cleared', cleared = %s, cleared_by = %d, note = %s WHERE {$where_sql}";
        $params = array_merge(
            array(current_time('mysql'), (int) $args['by'], substr((string) $args['note'], 0, 255)),
            $params
        );
    }

    $affected = $wpdb->query($wpdb->prepare($sql, $params));

    do_action('zhiji_claim_log_cleared', array(
        'mode'     => $mode,
        'ids'      => $ids,
        'scene'    => sanitize_key($args['scene']),
        'email'    => $args['email'],
        'affected' => (int) $affected,
        'by'       => (int) $args['by'],
    ));

    return array('mode' => $mode, 'affected' => (int) $affected, 'error' => '');
}

/**
 * 恢复记录为「占用中 / 待处理」（运维"补发"用，是 clear() 的反向操作）
 *
 * 场景：某条记录被标记为已放行/已领取后，运维需要让它回到"待处理"状态
 * （例如福袋弹窗被误消费、用户没看到，需要重新置为待领取）。
 *
 * 必须给出过滤条件：ids 或 scene+user_id，禁止无条件恢复。
 *
 * @param array $args ids(array) / scene / user_id / note / by(操作人 ID)
 * @return array array(affected, error)
 */
function zhiji_claim_log_restore(array $args = array())
{
    global $wpdb;

    $args = wp_parse_args($args, array(
        'ids'     => array(),
        'scene'   => '',
        'user_id' => 0,
        'note'    => '',
        'by'      => 0,
    ));

    $ids    = array_filter(array_map('intval', (array) $args['ids']));
    $where  = array();
    $params = array();

    if ($ids) {
        $where[] = 'id IN (' . implode(',', $ids) . ')';
    } elseif ('' !== (string) $args['scene'] && (int) $args['user_id'] > 0) {
        $where[]  = 'scene = %s';
        $params[] = sanitize_key($args['scene']);
        $where[]  = 'user_id = %d';
        $params[] = (int) $args['user_id'];
    } else {
        return array('affected' => 0, 'error' => 'missing_filter');
    }

    $table = zhiji_claim_log_table();
    $sql   = "UPDATE {$table}
              SET status = 'active', cleared = '1970-01-01 00:00:00', cleared_by = 0, note = %s
              WHERE " . implode(' AND ', $where);

    $query_params = array_merge(array(substr((string) $args['note'], 0, 255)), $params);
    $affected     = $wpdb->query($wpdb->prepare($sql, $query_params));

    do_action('zhiji_claim_log_restored', array(
        'ids'      => $ids,
        'scene'    => sanitize_key($args['scene']),
        'user_id'  => (int) $args['user_id'],
        'affected' => (int) $affected,
        'by'       => (int) $args['by'],
    ));

    return array('affected' => (int) $affected, 'error' => '');
}

/**
 * 解析记录的 meta 字段（统一入口）
 *
 * ⚠️ 本表的 meta 约定用 **JSON** 存储（写入时 wp_json_encode）。
 *    早期/外部写入可能是 PHP 序列化字符串，这里做双兼容：
 *    先按 JSON 解，再回退 maybe_unserialize。
 *    消费方**不要**直接对 $row->meta 调 maybe_unserialize() ——
 *    JSON 字符串不是序列化数据，会解析失败（曾因此导致奖励列为空、补发被跳过）。
 *
 * @param object|string $row 记录行对象，或直接传 meta 字符串
 * @return array 解析后的数组（失败返回空数组）
 */
function zhiji_claim_log_meta($row)
{
    $raw = is_object($row) ? (isset($row->meta) ? $row->meta : '') : (string) $row;
    $raw = (string) $raw;
    if ('' === $raw) {
        return array();
    }
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    $un = maybe_unserialize($raw);
    return is_array($un) ? $un : array();
}

/**
 * 按 ID 取单条记录
 *
 * @param int $id
 * @return object|null
 */
function zhiji_claim_log_get($id)
{
    global $wpdb;

    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }

    return $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . zhiji_claim_log_table() . ' WHERE id = %d',
        $id
    ));
}

/**
 * 按关联对象删除记录（业务侧回滚时使用，如邮件发送失败）
 *
 * @param string $scene
 * @param string $object_id
 * @return int 影响行数
 */
function zhiji_claim_log_delete_by_object($scene, $object_id)
{
    global $wpdb;

    $scene     = sanitize_key($scene);
    $object_id = (string) $object_id;
    if ('' === $scene || '' === $object_id) {
        return 0;
    }

    return (int) $wpdb->delete(
        zhiji_claim_log_table(),
        array('scene' => $scene, 'object_id' => $object_id),
        array('%s', '%s')
    );
}

/**
 * 时间字段展示文本（占位符归一）
 *
 * ⚠️ 本表 created/cleared 的默认值是 '1970-01-01 00:00:00'（DATETIME NOT NULL 的 epoch 占位），
 *    「从未发生」的时间（未放行 / 未领取）都会是这个值 —— 展示层必须归一为占位符，
 *    不能把 1970 直接甩给用户（2026-09-28 用户反馈）。
 *
 * @param string $value DATETIME 字符串
 * @return string 有效时间原样返回；空值 / epoch 占位返回 '—'
 */
function zhiji_claim_log_time_text($value)
{
    $value = trim((string) $value);
    if ('' === $value || '0000-00-00 00:00:00' === $value) {
        return '—';
    }
    // 年份 < 2000 一律视为 epoch 占位（1970-01-01 00:00:00 及各时区变体），业务数据不可能早于 2000
    if ((int) substr($value, 0, 4) < 2000) {
        return '—';
    }
    return $value;
}

/**
 * 来源标识 → 用户可读标签（数据层用内部码，展示层一律走这里，绝不外泄英文码）
 *
 * @return array key => label
 */
function zhiji_claim_log_source_labels()
{
    $labels = array(
        'direct'               => __('邮箱领取', 'zhiji'),
        'ref_bonus'            => __('分享奖励', 'zhiji'),
        'ops_release'          => __('运维放行', 'zhiji'),
        'selftest'             => __('联调自测', 'zhiji'),
        'comment_fortune'      => __('评论福袋', 'zhiji'),
        'comment_fortune_free' => __('评论福袋免单券', 'zhiji'),
        'zhiji_lottery'        => __('大转盘抽奖', 'zhiji'),
        'lottery'              => __('大转盘抽奖', 'zhiji'),
        'manual_test'          => __('后台发放', 'zhiji'),
        'reward_center'        => __('奖励中心', 'zhiji'),
    );
    return apply_filters('zhiji_claim_log_source_labels', $labels);
}

/**
 * 单个来源标识的展示标签（未识别的内部码兜底「—」，不外泄）
 *
 * @param string $key
 * @return string
 */
function zhiji_claim_log_source_label($key)
{
    $key    = (string) $key;
    $labels = zhiji_claim_log_source_labels();
    if ('' === $key) {
        return '—';
    }
    return isset($labels[$key]) ? $labels[$key] : '—';
}
