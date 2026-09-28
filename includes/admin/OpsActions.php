<?php
/**
 * @module  OpsActions
 * @desc    Ops console action entry (admin-post): verify, dispatch to scene handler, audit, redirect.
 * @since   2.0.0
 */

defined('ABSPATH') || exit;

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
 * 六、数据导出（CSV，只读操作：不要求「运维清除」总闸）
 * ============================================================ */

add_action('admin_post_zhiji_ops_export', 'zhiji_ops_handle_export');

/**
 * 导出当前筛选结果为 CSV
 *
 * 只读能力：仍需登录 + 场景权限 + nonce，但**不受「允许运维清除」总闸限制**
 * （导出不改变任何业务状态，被关闸时同样需要能取证）。
 *
 * @return void
 */
function zhiji_ops_handle_export()
{
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die(__('您没有权限执行该操作', 'zhiji'));
    }
    $scene_id = isset($_GET['scene']) ? sanitize_key(wp_unslash($_GET['scene'])) : '';
    check_admin_referer('zhiji_ops_export_' . $scene_id);

    $scene = zhiji_ops_scene($scene_id);
    if (!$scene) {
        wp_die(__('场景不存在', 'zhiji'));
    }
    if (!current_user_can($scene['cap'])) {
        wp_die(__('权限不足', 'zhiji'));
    }

    // 复用页面同一套筛选白名单
    $filters = zhiji_ops_collect_filters($scene);
    $args    = array_merge($filters, array('scene' => $scene_id, 'page' => 1, 'per_page' => 5000));
    if (is_callable($scene['query'])) {
        $result = (array) call_user_func($scene['query'], $args);
    } elseif (function_exists('zhiji_claim_log_query')) {
        $result = zhiji_claim_log_query($args);
    } else {
        $result = array();
    }
    $rows = isset($result['rows']) ? (array) $result['rows'] : array();

    // 导出列解析优先级：
    //   ① 列声明了 export 回调 → 用它（计算列：优惠内容 / 名下券数 / 奖励文本等）
    //   ② 否则取行对象上的原始字段（render 回调产出的是 HTML，**不参与导出**）
    // 这样渲染层的「527/1970 归一、来源汉化、状态本地化」能同样作用于 CSV，
    // 而新增计算列只需在场景里补一个 export，不必改本文件。
    $cols = array();
    foreach ($scene['columns'] as $col) {
        $key = isset($col['key']) ? (string) $col['key'] : '';
        if ('' === $key) {
            continue;
        }
        if (isset($col['export']) && is_callable($col['export'])) {
            $cols[$key] = array('label' => $col['label'], 'export' => $col['export']);
            continue;
        }
        $has = false;
        foreach ($rows as $row) {
            if (is_object($row) && property_exists($row, $key)) {
                $has = true;
                break;
            }
        }
        if ($has || !$rows) {
            $cols[$key] = array('label' => $col['label']);
        }
    }

    $filename = sprintf('zhiji-ops-%s-%s.csv', $scene_id, current_time('Ymd-His'));

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');

    // UTF-8 BOM：Excel 直接双击打开不乱码
    fwrite($out, "\xEF\xBB\xBF");

    $head = array();
    foreach ($cols as $def) {
        $head[] = (string) $def['label'];
    }
    fputcsv($out, $head);

    /**
     * CSV 单元格防注入：以 = + - @ 开头会被 Excel 当公式执行，前置单引号
     *
     * @param mixed $v
     * @return string
     */
    $safe = function ($v) {
        $v = is_scalar($v) ? (string) $v : '';
        return preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
    };

    foreach ($rows as $row) {
        $line = array();
        foreach ($cols as $key => $def) {
            if (isset($def['export'])) {
                // 计算列：回调必须返回标量（由 $safe 兜底把非标量转为空串）
                $line[] = $safe(call_user_func($def['export'], $row));
            } else {
                $line[] = $safe(isset($row->{$key}) ? $row->{$key} : '');
            }
        }
        fputcsv($out, $line);
    }

    fclose($out);

    zhiji_ops_add_activity(
        'export',
        sprintf(
            /* translators: 1: 场景 2: 导出条数 */
            __('导出 %1$s 共 %2$d 条记录（CSV）', 'zhiji'),
            $scene_id,
            count($rows)
        ),
        $scene_id
    );
    exit;
}

add_action('admin_post_zhiji_ops_export_audit', 'zhiji_ops_handle_export_audit');

/**
 * 导出**审计日志**为 CSV（2026-09-29 新增，附录 Y ⭐⭐⭐）
 *
 * 与场景导出的区别：导出对象是审计日志本身 → **导出行为必须留痕**（meta-logging，
 * 标准依据："读取敏感数据也要留痕"——导出是把全部审计条目一次性拿走的动作）。
 *
 * 筛选口径与总览页审计面板完全一致（所见即所得）。
 * 安全：登录 + manage_options + nonce `zhiji_ops_export_audit`。
 *
 * ⚠️ 顺序约定：**先取行、再写审计、最后输出 CSV** ——
 *    本次导出的文件里**不含**它自己的导出记录（那条会出现在*下一次*导出里），
 *    避免同一事件在单次导出中"自我包含"。
 *
 * @return void
 */
function zhiji_ops_handle_export_audit()
{
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die(__('您没有权限执行该操作', 'zhiji'));
    }
    check_admin_referer('zhiji_ops_export_audit');

    // 与面板同一套筛选白名单（GET 参数名一致）
    $outcomes = array('', 'success', 'denied', 'error');
    $outcome  = isset($_GET['audit_outcome']) ? sanitize_key(wp_unslash($_GET['audit_outcome'])) : '';
    if (!in_array($outcome, $outcomes, true)) {
        $outcome = '';
    }
    $user   = isset($_GET['audit_user']) ? sanitize_user(wp_unslash($_GET['audit_user'])) : '';
    $scene  = isset($_GET['audit_scene']) ? sanitize_key(wp_unslash($_GET['audit_scene'])) : '';
    $search = isset($_GET['audit_search']) ? sanitize_text_field(wp_unslash($_GET['audit_search'])) : '';

    $args = array();
    if ('' !== $outcome) {
        $args['outcome'] = $outcome;
    }
    if ('' !== $user) {
        $args['user'] = $user;
    }
    if ('' !== $search) {
        $args['search'] = $search;
    }

    $rows = zhiji_ops_activities(ZHIJI_OPS_ACTIVITY_MAX, $scene, $args);

    // meta-logging：导出审计日志这个动作本身写入审计（success + 影响面）
    zhiji_ops_add_activity(
        'export_audit',
        sprintf(
            /* translators: 1: 导出条数 */
            __('导出审计日志共 %1$d 条记录（CSV）', 'zhiji'),
            count($rows)
        ),
        '',
        array(
            'outcome' => 'success',
            'target'  => 'audit_rows=' . count($rows) . ('' !== $scene ? ' scene=' . $scene : ''),
        )
    );

    $filename = sprintf('zhiji-ops-audit-%s.csv', current_time('Ymd-His'));

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');

    // UTF-8 BOM：Excel 直接双击打开不乱码
    fwrite($out, "\xEF\xBB\xBF");

    // 审计导出为**全字段**（页面是摘要，CSV 是取证口径）
    $head = array('记录时间(本地)', '记录时间(UTC)', '操作者', '来源 IP', '场景', '动作', '动作(原始)', '说明', '结果', '影响面', '变更摘要', '理由', '事件 ID');
    fputcsv($out, $head);

    $safe = function ($v) {
        $v = is_scalar($v) ? (string) $v : '';
        return preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
    };

    $outcome_label = array(
        'success' => __('成功', 'zhiji'),
        'denied'  => __('已拒绝', 'zhiji'),
        'error'   => __('失败', 'zhiji'),
    );

    foreach ($rows as $row) {
        $changes = isset($row['changes']) && is_array($row['changes']) && $row['changes']
            ? wp_json_encode($row['changes'], JSON_UNESCAPED_UNICODE)
            : '';
        $o = isset($row['outcome']) ? (string) $row['outcome'] : '';
        fputcsv($out, array(
            $safe(isset($row['time']) ? $row['time'] : ''),
            $safe(isset($row['time_utc']) ? $row['time_utc'] : ''),
            $safe(isset($row['user']) ? $row['user'] : ''),
            $safe(isset($row['ip']) ? $row['ip'] : ''),
            $safe(isset($row['scene']) ? $row['scene'] : ''),
            $safe(zhiji_ops_action_label(isset($row['action']) ? $row['action'] : '')),
            $safe(isset($row['action']) ? $row['action'] : ''),
            $safe(isset($row['detail']) ? $row['detail'] : ''),
            $safe(isset($outcome_label[$o]) ? $outcome_label[$o] : $o),
            $safe(isset($row['target']) ? $row['target'] : ''),
            $safe($changes),
            $safe(isset($row['reason']) ? $row['reason'] : ''),
            $safe(isset($row['event_id']) ? $row['event_id'] : ''),
        ));
    }

    fclose($out);
    exit;
}

