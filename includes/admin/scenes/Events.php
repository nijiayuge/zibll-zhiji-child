<?php
/**
 * 运维场景：系统事件（events）
 *
 * @scene   events
 * @desc    系统侧失败的"机器轨迹"（邮件发送失败 / WebP 转换失败等）。只读；
 *          保留期 30 天由每日 cron 自动清理（排障日志不是审计证据，无需长期保留）。
 * @note    行形态与 ClaimLog 不同 → detail 回调使用 **replace** 语义整体接管。
 * @since   2.0.0（2026-09-29 新增）
 */

defined('ABSPATH') || exit;

zhiji_ops_register_scene('events', array(
    'title'         => __('系统事件', 'zhiji'),
    'desc'          => __('系统侧失败记录（邮件发送失败 / WebP 转换失败等）。与操作审计互补：审计回答"人做了什么"，这里回答"系统哪里出了问题"。保留 30 天自动清理。', 'zhiji'),
    'priority'      => 40,
    'icon'          => 'dashicons-warning',
    // cap 省略 → 继承契约默认 zhiji_ops_view
    // 只读：保留期由每日 cron 自动清理，无需人工清除入口
    'clear_enabled' => false,

    /* ---------- 统计卡 ---------- */
    'stats'         => function () {
        $today = zhiji_event_query(array('date_from' => current_time('Y-m-d'), 'per_page' => 1));
        $all   = zhiji_event_query(array('per_page' => 1));
        $mail  = zhiji_event_query(array('type' => 'mail', 'per_page' => 1));
        $webp  = zhiji_event_query(array('type' => 'webp', 'per_page' => 1));
        return array(
            array('label' => __('事件总数', 'zhiji'), 'value' => (int) $all['total']),
            array('label' => __('今日新增', 'zhiji'), 'value' => (int) $today['total']),
            array('label' => __('邮件失败', 'zhiji'), 'value' => (int) $mail['total']),
            array('label' => __('WebP 失败', 'zhiji'), 'value' => (int) $webp['total']),
        );
    },

    /* ---------- 筛选 ---------- */
    'filters'       => array(
        array('key' => 'type', 'label' => __('事件类型', 'zhiji'), 'type' => 'select', 'options' => zhiji_event_types()),
        array('key' => 'search', 'label' => __('关键词', 'zhiji'), 'type' => 'text', 'placeholder' => __('摘要模糊匹配', 'zhiji')),
        array('key' => 'date_from', 'label' => __('开始日期', 'zhiji'), 'type' => 'date'),
        array('key' => 'date_to', 'label' => __('结束日期', 'zhiji'), 'type' => 'date'),
    ),

    /* ---------- 列 ---------- */
    'columns'       => array(
        array('key' => 'time', 'label' => __('发生时间', 'zhiji'), 'width' => 160),
        array('key' => 'type', 'label' => __('类型', 'zhiji'),
            'render' => function ($row) {
                $label = zhiji_event_type_label($row->type);
                // 非信息类失败统一红点表达（都是失败事件）
                return '<span class="zhiji-ops-tag active">' . esc_html($label) . '</span>';
            },
            'export' => function ($row) {
                return zhiji_event_type_label($row->type);
            }),
        array('key' => 'message', 'label' => __('摘要', 'zhiji')),
        array('key' => 'context', 'label' => __('上下文', 'zhiji'),
            'render' => function ($row) {
                $ctx = (string) $row->context;
                if ('' === $ctx) {
                    return '<span class="description">—</span>';
                }
                $arr = json_decode($ctx, true);
                $parts = array();
                foreach ((array) $arr as $k => $v) {
                    if (is_scalar($v) && '' !== (string) $v) {
                        $parts[] = esc_html($k . '=' . $v);
                    }
                }
                return '<span class="description" style="font-size:12px">' . implode(' · ', array_slice($parts, 0, 3)) . '</span>';
            },
            'export' => function ($row) {
                return (string) $row->context;
            }),
    ),

    /* ---------- 数据源 ---------- */
    'query'         => function ($args) {
        return zhiji_event_query($args);
    },

    /* ---------- 详情抽屉（replace：行形态非 ClaimLog） ---------- */
    'detail'        => function ($row) {
        $out = array('replace' => true, 'primary' => array(), 'fields' => array());
        if (!is_object($row) && !is_array($row)) {
            return $out;
        }
        $r = (object) $row;

        $out['primary'][] = array('k' => __('类型', 'zhiji'), 'v' => zhiji_event_type_label($r->type));
        $out['primary'][] = array('k' => __('发生时间', 'zhiji'), 'v' => (string) $r->time);

        $out['fields'][] = array('k' => __('摘要', 'zhiji'), 'v' => (string) $r->message);
        $ctx = (string) $r->context;
        if ('' !== $ctx) {
            $pretty = json_decode($ctx, true);
            $out['fields'][] = array(
                'k'   => __('上下文', 'zhiji'),
                'v'   => is_array($pretty)
                    ? wp_json_encode($pretty, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                    : $ctx,
                'pre' => true,
            );
        }
        $out['fields'][] = array('k' => __('事件 ID', 'zhiji'), 'v' => (string) $r->id);
        return $out;
    },
));
