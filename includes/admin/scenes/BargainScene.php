<?php
/**
 * 运维场景：砍价记录（bargain）
 *
 * @scene   bargain
 * @desc    砍价的发起与助力记录（谁、什么时候、砍到多少）。只读 + 一个行操作（强制过期）；
 *          不做删除（涉及奖品账目——归零已发码）。
 * @since   2.0.0（2026-09-29 新增）
 */

defined('ABSPATH') || exit;

zhiji_ops_register_scene('bargain', array(
    'title'         => __('砍价记录', 'zhiji'),
    'desc'          => __('砍价活动的发起与助力记录。可查看进度与助力明细；支持强制过期进行中的砍价。', 'zhiji'),
    'priority'      => 38,
    'icon'          => 'dashicons-share',
    // cap 省略 → zhiji_ops_view
    'clear_enabled' => true, // 允许"强制过期"操作（走 handle 回调）

    'stats'         => function () {
        $log = zhiji_bargain_all();
        $active = 0; $done = 0; $expired = 0;
        $hours = max(1, (int) zhiji_get_option('bargain_hours', 24));
        $now = current_time('timestamp');
        foreach ($log as $b) {
            $st = $b['status'] ?? '';
            if ('done' === $st) { $done++; }
            elseif ('active' === $st) {
                if ((current_time('timestamp') - strtotime($b['created'])) >= $hours * HOUR_IN_SECONDS) { $expired++; }
                else { $active++; }
            }
        }
        return array(
            array('label' => __('进行中', 'zhiji'), 'value' => $active),
            array('label' => __('已完成', 'zhiji'), 'value' => $done),
            array('label' => __('已过期', 'zhiji'), 'value' => $expired),
            array('label' => __('总数', 'zhiji'), 'value' => count($log)),
        );
    },

    'filters'       => array(
        array('key' => 'user', 'label' => __('发起人', 'zhiji'), 'type' => 'text', 'placeholder' => __('用户名 / ID', 'zhiji')),
        array('key' => 'status', 'label' => __('状态', 'zhiji'), 'type' => 'select', 'options' => array(
            'active' => __('进行中', 'zhiji'), 'done' => __('已完成', 'zhiji'), 'expired' => __('已过期', 'zhiji'),
        )),
    ),

    'columns'       => array(
        array('key' => 'user', 'label' => __('发起人', 'zhiji')),
        array('key' => 'total', 'label' => __('总额(元)', 'zhiji')),
        array('key' => 'cut_amount', 'label' => __('已砍(元)', 'zhiji'),
            'render' => function ($row) {
                return esc_html(number_format((float) ($row->total ?? 0) - (float) ($row->remaining ?? 0), 2));
            },
            'export' => function ($row) { return (string) round((float) ($row->total ?? 0) - (float) ($row->remaining ?? 0), 2); }),
        array('key' => 'remaining', 'label' => __('剩余(元)', 'zhiji')),
        array('key' => 'n_assists', 'label' => __('助力人数', 'zhiji'),
            'render' => function ($row) { return esc_html(count((array) ($row->assists ?? array()))); },
            'export' => function ($row) { return (string) count((array) ($row->assists ?? array())); }),
        array('key' => 'status_label', 'label' => __('状态', 'zhiji'),
            'render' => function ($row) {
                $st = $row->status ?? '';
                $hours = max(1, (int) zhiji_get_option('bargain_hours', 24));
                if ('done' === $st) { return '<span class="zhiji-ops-tag cleared">' . esc_html__('已完成', 'zhiji') . '</span>'; }
                if ('active' === $st && (current_time('timestamp') - strtotime($row->created)) >= $hours * HOUR_IN_SECONDS) {
                    return '<span class="zhiji-ops-tag muted">' . esc_html__('已过期', 'zhiji') . '</span>';
                }
                return '<span class="zhiji-ops-tag active">' . esc_html__('进行中', 'zhiji') . '</span>';
            },
            'export' => function ($row) {
                $st = $row->status ?? '';
                if ('done' === $st) { return '已完成'; }
                $hours = max(1, (int) zhiji_get_option('bargain_hours', 24));
                if ('active' === $st && (current_time('timestamp') - strtotime($row->created)) >= $hours * HOUR_IN_SECONDS) { return '已过期'; }
                return '进行中';
            }),
        array('key' => 'created', 'label' => __('发起时间', 'zhiji'), 'width' => 160),
    ),

    'query'         => function ($args) {
        $log = zhiji_bargain_all();
        $user_f  = isset($args['user']) ? (string) $args['user'] : '';
        $status_f = isset($args['status']) ? (string) $args['status'] : '';
        $hours = max(1, (int) zhiji_get_option('bargain_hours', 24));

        $rows = array();
        foreach ($log as $i => $b) {
            $b = (array) $b;
            $st = $b['status'] ?? '';
            $is_expired = ('active' === $st && (current_time('timestamp') - strtotime($b['created'])) >= $hours * HOUR_IN_SECONDS);
            $eff_status = 'done' === $st ? 'done' : ($is_expired ? 'expired' : 'active');

            if ('' !== $user_f && false === mb_stripos((string) ($b['user'] ?? ''), $user_f) && (string) ($b['uid'] ?? '') !== $user_f) { continue; }
            if ('' !== $status_f && $eff_status !== $status_f) { continue; }

            $rows[] = (object) array(
                'id'        => $i + 1,
                'bid'       => $b['bid'] ?? '',
                'uid'       => (int) ($b['uid'] ?? 0),
                'user'      => $b['user'] ?? '',
                'total'     => (float) ($b['total'] ?? 0),
                'remaining' => (float) ($b['remaining'] ?? 0),
                'status'    => $st,
                'created'   => $b['created'] ?? '',
                'assists'   => $b['assists'] ?? array(),
                'code'      => $b['code'] ?? '',
            );
        }
        usort($rows, function ($a, $b) { return strcmp($b->created, $a->created); });
        foreach ($rows as $i => $r) { $r->id = $i + 1; }
        $total    = count($rows);
        $per_page = min(200, max(1, (int) (isset($args['per_page']) ? $args['per_page'] : 20)));
        $pages    = max(1, (int) ceil($total / $per_page));
        $page     = max(1, min($pages, (int) (isset($args['page']) ? $args['page'] : 1)));
        return array('rows' => array_slice($rows, ($page - 1) * $per_page, $per_page), 'total' => $total, 'pages' => $pages, 'page' => $page, 'per_page' => $per_page);
    },

    'actions'       => array(
        array('key' => 'force_expire', 'label' => __('强制过期', 'zhiji'), 'mode' => 'single', 'confirm' => true, 'tone' => 'danger'),
    ),

    'handle'        => function ($action, array $params, array $ids, array $scene) {
        if ('force_expire' !== $action) {
            return array('ok' => false, 'msg' => __('不支持的操作', 'zhiji'));
        }
        $log = zhiji_bargain_all();
        $affected = 0;
        foreach ($log as $i => $b) {
            if (in_array((int) ($b['uid'] ?? 0), $ids, true) || (isset($b['bid']) && in_array($b['bid'], array_map('strval', $ids), true))) {
                if ('active' === ($b['status'] ?? '')) {
                    $log[$i]['status'] = 'expired';
                    $affected++;
                }
            }
        }
        if ($affected > 0) {
            zhiji_bargain_save($log);
        }
        return array('ok' => true, 'msg' => sprintf(__('已强制过期 %d 个砍价', 'zhiji'), $affected), 'affected' => $affected);
    },

    'detail'        => function ($row) {
        $out = array('replace' => true, 'primary' => array(), 'fields' => array());
        if (!is_object($row) && !is_array($row)) { return $out; }
        $r = (object) $row;
        $out['primary'][] = array('k' => __('发起人', 'zhiji'), 'v' => (string) $r->user);
        $out['primary'][] = array('k' => __('总额', 'zhiji'), 'v' => sprintf('%.2f 元', (float) $r->total));
        $out['primary'][] = array('k' => __('剩余', 'zhiji'), 'v' => sprintf('%.2f 元', (float) $r->remaining));
        $out['fields'][] = array('k' => __('状态', 'zhiji'), 'v' => (string) $r->status);
        $out['fields'][] = array('k' => __('发起时间', 'zhiji'), 'v' => (string) $r->created);
        $out['fields'][] = array('k' => __('砍价 ID', 'zhiji'), 'v' => (string) $r->bid);
        foreach ((array) ($r->assists ?? array()) as $a) {
            $out['fields'][] = array('k' => sprintf(__('助力: %s', 'zhiji'), $a['user'] ?? ''), 'v' => sprintf('%.2f 元%s', (float) ($a['cut'] ?? 0), !empty($a['new']) ? __('（新用户）', 'zhiji') : ''));
        }
        if (!empty($r->code)) {
            $out['fields'][] = array('k' => __('优惠码', 'zhiji'), 'v' => (string) $r->code);
        }
        return $out;
    },
));
