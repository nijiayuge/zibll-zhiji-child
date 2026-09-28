<?php
/**
 * 运维场景：抽奖记录（lottery）
 *
 * @scene   lottery
 * @desc    大转盘抽奖的发放记录（谁、什么时候、抽中什么）。数据源为 Lottery 模块的
 *          `zhiji_lottery_log`（option，环形保留最近 200 条）—— **只读场景**：
 *          不提供清除/重置/补发等变更操作（奖品已入账积分/余额/会员，撤销属业务决策，
 *          不该藏在运维台里一键执行）。
 * @note    行形态与 ClaimLog 不同（数组、无 email/status 等通用键），因此
 *          `detail` 回调使用 **replace** 语义整体接管详情字段（2026-09-29 契约扩展）。
 * @since   2.0.0（2026-09-29 新增）
 */

defined('ABSPATH') || exit;

/**
 * 奖品类型 → 中文（场景内私有助手）
 *
 * @param string $type
 * @return string
 */
function zhiji_ops_scene_lottery_type_label($type)
{
    $map = array(
        'none'    => __('谢谢参与', 'zhiji'),
        'points'  => __('积分', 'zhiji'),
        'level'   => __('会员', 'zhiji'),
        'balance' => __('余额', 'zhiji'),
        'coupon'  => __('优惠码', 'zhiji'),
    );
    $type = (string) $type;
    return isset($map[$type]) ? $map[$type] : $type;
}

/**
 * 日志中实际出现过的奖品类型（用于筛选下拉，避免枚举写死后漏新类型）
 *
 * @return array 值 => 中文
 */
function zhiji_ops_scene_lottery_types()
{
    $log = get_option(ZHIJI_LOTTERY_LOG_OPTION, array());
    $types = array();
    foreach ((array) $log as $e) {
        $t = isset($e['type']) ? (string) $e['type'] : '';
        if ('' !== $t) {
            $types[$t] = zhiji_ops_scene_lottery_type_label($t);
        }
    }
    // 保持稳定顺序：已知类型在前
    $known = array('none', 'points', 'level', 'balance', 'coupon');
    $out = array();
    foreach ($known as $k) {
        if (isset($types[$k])) {
            $out[$k] = $types[$k];
            unset($types[$k]);
        }
    }
    foreach ($types as $k => $v) {
        $out[$k] = $v;
    }
    return $out;
}

zhiji_ops_register_scene('lottery', array(
    'title'         => __('抽奖记录', 'zhiji'),
    'desc'          => __('大转盘抽奖的发放记录（谁、什么时候、抽中什么）。只读：奖品发放已即时入账（积分/余额/会员），撤销属业务决策不在此提供。', 'zhiji'),
    'priority'      => 30,
    'icon'          => 'dashicons-awards',
    // cap 省略 → 继承契约默认 zhiji_ops_view
    // ⚠️ 只读场景：显式禁用清除类操作（HTTP 清除接口与页面按钮同被拦截）
    'clear_enabled' => false,

    /* ---------- 统计卡 ---------- */
    'stats'         => function () {
        $log = get_option(ZHIJI_LOTTERY_LOG_OPTION, array());
        $log = is_array($log) ? $log : array();
        $today = current_time('Y-m-d');
        $today_n = 0;
        $win_n = 0;
        foreach ($log as $e) {
            if (isset($e['time']) && 0 === strpos((string) $e['time'], $today)) {
                $today_n++;
            }
            if (isset($e['type']) && 'none' !== $e['type']) {
                $win_n++;
            }
        }
        $total = count($log);
        $rate = $total > 0 ? round($win_n * 100 / $total, 1) . '%' : '—';
        return array(
            array('label' => __('累计抽奖', 'zhiji'), 'value' => $total . __(' 次', 'zhiji')),
            array('label' => __('今日', 'zhiji'), 'value' => $today_n . __(' 次', 'zhiji')),
            array('label' => __('中奖', 'zhiji'), 'value' => $win_n . __(' 次', 'zhiji')),
            array('label' => __('中奖率', 'zhiji'), 'value' => $rate),
        );
    },

    /* ---------- 筛选 ---------- */
    'filters'       => array(
        array('key' => 'user', 'label' => __('用户', 'zhiji'), 'type' => 'text', 'placeholder' => __('用户名 / ID', 'zhiji')),
        array('key' => 'prize', 'label' => __('奖品', 'zhiji'), 'type' => 'text', 'placeholder' => __('奖品名（模糊）', 'zhiji')),
        array('key' => 'type', 'label' => __('奖品类型', 'zhiji'), 'type' => 'select', 'options' => zhiji_ops_scene_lottery_types()),
        array('key' => 'date_from', 'label' => __('开始日期', 'zhiji'), 'type' => 'date'),
        array('key' => 'date_to', 'label' => __('结束日期', 'zhiji'), 'type' => 'date'),
    ),

    /* ---------- 列 ---------- */
    'columns'       => array(
        array('key' => 'time', 'label' => __('抽奖时间', 'zhiji'), 'width' => 160),
        array('key' => 'user', 'label' => __('用户', 'zhiji')),
        array('key' => 'name', 'label' => __('奖品', 'zhiji')),
        array('key' => 'type', 'label' => __('类型', 'zhiji'),
            'render' => function ($row) {
                return esc_html(zhiji_ops_scene_lottery_type_label($row->type));
            },
            'export' => function ($row) {
                return zhiji_ops_scene_lottery_type_label($row->type);
            }),
        array('key' => 'value', 'label' => __('数值', 'zhiji'),
            'render' => function ($row) {
                return 'none' === $row->type ? '—' : esc_html((string) $row->value);
            },
            'export' => function ($row) {
                return 'none' === $row->type ? '' : (string) $row->value;
            }),
        array('key' => 'extra', 'label' => __('额外次数', 'zhiji'),
            'render' => function ($row) {
                return !empty($row->extra)
                    ? '<span class="zhiji-ops-tag muted">' . esc_html__('是', 'zhiji') . '</span>'
                    : '<span class="description">—</span>';
            },
            'export' => function ($row) {
                return !empty($row->extra) ? '是' : '否';
            }),
    ),

    /* ---------- 数据源 ---------- */
    // 抽奖日志存于 option（环形保留最近 200 条），内存内筛选 + 排序 + 分页即可
    'query'         => function ($args) {
        $log = get_option(ZHIJI_LOTTERY_LOG_OPTION, array());
        $log = is_array($log) ? $log : array();

        // 统一为对象行 + 合成序号（时间倒序后 1..N；抽屉标题/详情用它，仅展示用途）
        $rows = array();
        foreach ($log as $i => $e) {
            $e = (array) $e;
            $rows[] = (object) array(
                'id'    => $i + 1,
                'time'  => isset($e['time']) ? (string) $e['time'] : '',
                'uid'   => isset($e['uid']) ? (int) $e['uid'] : 0,
                'user'  => isset($e['user']) ? (string) $e['user'] : '',
                'name'  => isset($e['name']) ? (string) $e['name'] : '',
                'type'  => isset($e['type']) ? (string) $e['type'] : '',
                'value' => isset($e['value']) ? $e['value'] : '',
                'extra' => !empty($e['extra']) ? 1 : 0,
            );
        }

        // 筛选（与页面筛选同键）
        $user  = isset($args['user']) ? (string) $args['user'] : '';
        $prize = isset($args['prize']) ? (string) $args['prize'] : '';
        $type  = isset($args['type']) ? (string) $args['type'] : '';
        $from  = isset($args['date_from']) ? (string) $args['date_from'] : '';
        $to    = isset($args['date_to']) ? (string) $args['date_to'] : '';

        $rows = array_values(array_filter($rows, function ($r) use ($user, $prize, $type, $from, $to) {
            if ('' !== $user
                && false === mb_stripos((string) $r->user, $user)
                && (string) $r->uid !== $user) {
                return false;
            }
            if ('' !== $prize && false === mb_stripos((string) $r->name, $prize)) {
                return false;
            }
            if ('' !== $type && (string) $r->type !== $type) {
                return false;
            }
            $day = substr((string) $r->time, 0, 10);
            if ('' !== $from && $day < $from) {
                return false;
            }
            if ('' !== $to && $day > $to) {
                return false;
            }
            return true;
        }));

        // 时间倒序（日志本身是追加序，倒序 = 最新在前）
        usort($rows, function ($a, $b) {
            return strcmp($b->time, $a->time);
        });

        // 重新编序号（倒序后 1..N，保证"序号 1 = 最新一条"）
        foreach ($rows as $i => $r) {
            $r->id = $i + 1;
        }

        $total    = count($rows);
        $per_page = min(200, max(1, (int) (isset($args['per_page']) ? $args['per_page'] : 20)));
        $pages    = max(1, (int) ceil($total / $per_page));
        $page     = max(1, min($pages, (int) (isset($args['page']) ? $args['page'] : 1)));

        return array(
            'rows'     => array_slice($rows, ($page - 1) * $per_page, $per_page),
            'total'    => $total,
            'pages'    => $pages,
            'page'     => $page,
            'per_page' => $per_page,
        );
    },

    /* ---------- 详情抽屉（replace：行形态非 ClaimLog，整体接管字段） ---------- */
    'detail'        => function ($row) {
        $out = array('replace' => true, 'primary' => array(), 'fields' => array());
        if (!is_object($row) && !is_array($row)) {
            return $out;
        }
        $r = (object) $row;

        $out['primary'][] = array('k' => __('用户', 'zhiji'), 'v' => (string) $r->user);
        $out['primary'][] = array('k' => __('奖品', 'zhiji'), 'v' => (string) $r->name);
        $out['primary'][] = array('k' => __('类型', 'zhiji'), 'v' => zhiji_ops_scene_lottery_type_label($r->type));

        $out['fields'][] = array('k' => __('用户 ID', 'zhiji'), 'v' => (string) $r->uid);
        $out['fields'][] = array('k' => __('数值', 'zhiji'), 'v' => 'none' === $r->type ? '—' : (string) $r->value);
        $out['fields'][] = array('k' => __('额外次数', 'zhiji'), 'v' => !empty($r->extra) ? __('是', 'zhiji') : __('否', 'zhiji'));
        $out['fields'][] = array('k' => __('抽奖时间', 'zhiji'), 'v' => (string) $r->time);
        return $out;
    },
));
