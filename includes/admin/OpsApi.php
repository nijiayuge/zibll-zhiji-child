<?php
/**
 * @module  OpsApi
 * @desc    Ops console JSON endpoints registered into the unified AJAX gateway (query / clear).
 * @since   2.0.0
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 七、HTTP 接口（管理端 JSON：查询 / 清除）
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
    // 2026-09-28：403 类**安全拒绝**要写进审计日志（outcome=denied）。
    // 依据行业审计规范：权限拒绝/越权尝试是安全事件的第一指标，不留痕等于没有控制。
    // ⚠️ 只记 403（权限/开关），**不记 400 参数校验失败** ——
    //    后者任何人都可批量触发，会把审计日志刷满、淹没真正有价值的信号。
    if (!current_user_can('manage_options')) {
        zhiji_ops_add_activity('ops_query', __('权限不足，已拒绝', 'zhiji'), '',
            array('outcome' => 'denied', 'target' => '403'));
        wp_send_json_error(array('msg' => __('权限不足', 'zhiji')), 403);
    }
    if (!zhiji_ops_enabled()) {
        zhiji_ops_add_activity('ops_query', __('运维页面未启用，已拒绝', 'zhiji'), '',
            array('outcome' => 'denied', 'target' => '403'));
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
        // 排序（2026-09-28）：与查询层白名单一致，非法值由查询层回退默认
        'orderby'   => zhiji_api_enum($request, 'orderby', array('id', 'created', 'email', 'status'), 'id'),
        'order'     => ('asc' === strtolower((string) ($request['order'] ?? ''))) ? 'ASC' : 'DESC',
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
        zhiji_ops_add_activity('ops_clear', __('权限不足，已拒绝', 'zhiji'), '',
            array('outcome' => 'denied', 'target' => '403'));
        wp_send_json_error(array('msg' => __('权限不足', 'zhiji')), 403);
    }

    $scene_id = zhiji_api_enum($request, 'scene', array_keys(zhiji_ops_scenes()), '');
    if ('' === $scene_id) {
        wp_send_json_error(array('msg' => __('缺少或无效的 scene 参数', 'zhiji')), 400);
    }
    if (!zhiji_ops_can_clear($scene_id)) {
        zhiji_ops_add_activity('ops_clear', __('「运维清除」已被关闭，已拒绝', 'zhiji'), $scene_id,
            array('outcome' => 'denied', 'target' => '403'));
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
        // 这是"尝试了但没做成"的**业务失败**，值得留痕（区别于 400 参数校验噪声）
        zhiji_ops_add_activity(
            'reset' === $mode ? 'reset' : 'delete',
            $ret['error'],
            $scene_id,
            array('outcome' => 'error', 'target' => $email ? ('email=' . $email) : ('ids=' . count($ids)))
        );
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
        $scene_id,
        array(
            'outcome' => 'success',
            // 影响面 + before→after 摘要：审计规范要求写操作可还原"改了什么"
            'target'  => $email ? ('email=' . $email) : ('ids=' . implode(',', $ids)),
            'changes' => array(
                'mode'     => $mode,
                'affected' => (int) $ret['affected'],
                'status'   => 'reset' === $mode ? 'cleared(恢复可领取)' : 'deleted',
            ),
        )
    );

    wp_send_json_success(array(
        'scene'    => $scene_id,
        'mode'     => $ret['mode'],
        'affected' => $ret['affected'],
        'stats'    => zhiji_claim_log_stats($scene_id),
    ));
}
