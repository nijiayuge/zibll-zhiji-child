<?php
/**
 * 运维场景：积分兑换记录（points_mall）
 *
 * @scene   points_mall
 * @desc    用户以积分兑换优惠码的记录。只读；数据源 zhiji_pmall_records（option 环形 200）。
 * @since   2.0.0（2026-09-29 新增）
 */

defined('ABSPATH') || exit;

zhiji_ops_register_scene('points_mall', array(
    'title'         => __('积分兑换', 'zhiji'),
    'desc'          => __('积分商城的兑换记录（谁、什么时候、用什么积分换了什么优惠码）。只读：兑换不可撤销（积分已扣、优惠码已发），撤销属退款操作。', 'zhiji'),
    'priority'      => 35,
    'icon'          => 'dashicons-cart',
    'clear_enabled' => false,

    'stats'         => function () {
        $log = zhiji_pmall_records();
        $today = current_time('Y-m-d');
        $today_n = 0;
        $total_cost = 0;
        foreach ($log as $e) {
            if (isset($e['time']) && 0 === strpos((string) $e['time'], $today)) { $today_n++; }
            $total_cost += (int) ($e['cost'] ?? 0);
        }
        return array(
            array('label' => __('兑换总数', 'zhiji'), 'value' => count($log)),
            array('label' => __('今日', 'zhiji'), 'value' => $today_n),
            array('label' => __('消耗积分', 'zhiji'), 'value' => number_format_i18n($total_cost)),
        );
    },

    'filters'       => array(
        array('key' => 'user', 'label' => __('用户', 'zhiji'), 'type' => 'text', 'placeholder' => __('用户名 / ID', 'zhiji')),
        array('key' => 'item', 'label' => __('奖品', 'zhiji'), 'type' => 'text', 'placeholder' => __('奖品名（模糊）', 'zhiji')),
        array('key' => 'date_from', 'label' => __('开始日期', 'zhiji'), 'type' => 'date'),
        array('key' => 'date_to', 'label' => __('结束日期', 'zhiji'), 'type' => 'date'),
    ),

    'columns'       => array(
        array('key' => 'time', 'label' => __('兑换时间', 'zhiji'), 'width' => 160),
        array('key' => 'user', 'label' => __('用户', 'zhiji')),
        array('key' => 'item', 'label' => __('奖品', 'zhiji')),
        array('key' => 'cost', 'label' => __('消耗积分', 'zhiji')),
        array('key' => 'code', 'label' => __('优惠码', 'zhiji'),
            'render' => function ($row) {
                $code = (string) ($row->code ?? '');
                return '' !== $code ? '<code style="font-size:12px">' . esc_html(substr($code, 0, 8)) . '…</code>' : '<span class="description">—</span>';
            },
            'export' => function ($row) { return (string) ($row->code ?? ''); }),
    ),

    'query'         => function ($args) {
        $log = zhiji_pmall_records();
        $rows = array();
        foreach ($log as $i => $e) {
            $e = (array) $e;
            $rows[] = (object) array(
                'id'    => $i + 1,
                'time'  => isset($e['time']) ? (string) $e['time'] : '',
                'uid'   => isset($e['uid']) ? (int) $e['uid'] : 0,
                'user'  => isset($e['user']) ? (string) $e['user'] : '',
                'item'  => isset($e['item']) ? (string) $e['item'] : '',
                'cost'  => isset($e['cost']) ? (int) $e['cost'] : 0,
                'code'  => isset($e['code']) ? (string) $e['code'] : '',
            );
        }
        $user  = isset($args['user']) ? (string) $args['user'] : '';
        $item  = isset($args['item']) ? (string) $args['item'] : '';
        $from  = isset($args['date_from']) ? (string) $args['date_from'] : '';
        $to    = isset($args['date_to']) ? (string) $args['date_to'] : '';
        $rows = array_values(array_filter($rows, function ($r) use ($user, $item, $from, $to) {
            if ('' !== $user && false === mb_stripos($r->user, $user) && (string) $r->uid !== $user) { return false; }
            if ('' !== $item && false === mb_stripos($r->item, $item)) { return false; }
            $day = substr($r->time, 0, 10);
            if ('' !== $from && $day < $from) { return false; }
            if ('' !== $to && $day > $to) { return false; }
            return true;
        }));
        usort($rows, function ($a, $b) { return strcmp($b->time, $a->time); });
        foreach ($rows as $i => $r) { $r->id = $i + 1; }
        $total    = count($rows);
        $per_page = min(200, max(1, (int) (isset($args['per_page']) ? $args['per_page'] : 20)));
        $pages    = max(1, (int) ceil($total / $per_page));
        $page     = max(1, min($pages, (int) (isset($args['page']) ? $args['page'] : 1)));
        return array('rows' => array_slice($rows, ($page - 1) * $per_page, $per_page), 'total' => $total, 'pages' => $pages, 'page' => $page, 'per_page' => $per_page);
    },

    'detail'        => function ($row) {
        $out = array('replace' => true, 'primary' => array(), 'fields' => array());
        if (!is_object($row) && !is_array($row)) { return $out; }
        $r = (object) $row;
        $out['primary'][] = array('k' => __('用户', 'zhiji'), 'v' => (string) $r->user);
        $out['primary'][] = array('k' => __('奖品', 'zhiji'), 'v' => (string) $r->item);
        $out['fields'][] = array('k' => __('消耗积分', 'zhiji'), 'v' => (string) $r->cost);
        $out['fields'][] = array('k' => __('优惠码', 'zhiji'), 'v' => (string) $r->code);
        $out['fields'][] = array('k' => __('兑换时间', 'zhiji'), 'v' => (string) $r->time);
        return $out;
    },
));
