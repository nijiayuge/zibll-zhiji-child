<?php
/**
 * 运维场景二：评论福袋「待领取」（用户维度）
 *
 * 业务背景：评论锦鲤福袋命中后，用户下次打开页面会收到中奖弹窗。
 *          弹窗标记原本只存在 **2 小时的 transient** 里 —— 用户没在这段时间内打开页面，
 *          弹窗就永久丢失（奖励已到账，但用户毫无感知），运营侧也无法查询与补发。
 *          本场景把发放/领取动作持久化到 core/ClaimLog.php（scene=comment_fortune），
 *          提供查询与人工干预能力。
 *
 * 与场景一（邮箱领取限制）的区别：**维度是用户（user_id）而不是邮箱**，
 * 说明运维台的场景注册制不绑定具体业务字段。
 *
 * 能力：
 *   · 状态展示：待领取 / 今日发放 / 已领取 / 累计
 *   · 查询：按用户 ID、状态、奖励类型、日期范围、券码/备注模糊搜索
 *   · 操作（受「运维清除」总闸与场景开关双重控制）：
 *       - 补发弹窗：把记录恢复为「待领取」并重设弹窗标记 → 用户下次打开页面重新看到中奖弹窗
 *       - 标记已领取：把「待领取」置为已领取，同时清掉挂着的弹窗标记
 *       - 删除记录：硬删（不可恢复）
 *
 * @since 2.0.0
 */

defined('ABSPATH') || exit;

/** 场景 ID（= ClaimLog 的 scene 值，与 CommentFortune 模块保持一致） */
if (!defined('ZHIJI_OPS_SCENE_FORTUNE')) {
    define('ZHIJI_OPS_SCENE_FORTUNE', ZHIJI_COMMENT_FORTUNE_CLAIM_SCENE);
}

/**
 * 奖励展示文本（从 meta 里取 reward）
 *
 * @param object $row 记录行
 * @return string
 */
function zhiji_ops_scene_fortune_reward_text($row)
{
    $meta   = zhiji_claim_log_meta($row);
    $reward = (isset($meta['reward']) && is_array($meta['reward'])) ? $meta['reward'] : array();
    if (!$reward) {
        return '—';
    }
    $name = isset($reward['name']) ? (string) $reward['name'] : '奖励';
    $val  = isset($reward['val']) ? $reward['val'] : '';
    $type = isset($reward['type']) ? (string) $reward['type'] : '';

    if (in_array($type, array('coupon', 'free'), true)) {
        return $name . '（' . (isset($reward['code']) ? $reward['code'] : $val) . '）';
    }
    if ('balance' === $type) {
        return $name . ' ¥' . $val;
    }
    return $name . ' ' . $val;
}

/**
 * 场景注册
 */
zhiji_ops_register_scene(ZHIJI_OPS_SCENE_FORTUNE, array(
    'title'         => __('评论福袋待领取', 'zhiji'),
    'desc'          => __('评论锦鲤福袋的发放与领取记录（按用户）。弹窗标记原本只存活 2 小时，用户错过就再也看不到中奖提示 —— 这里可查询、可补发。', 'zhiji'),
    'priority'      => 20,
    'cap'           => 'manage_options',
    'enabled'       => function () {
        return zhiji_is_enabled('ops_scene_fortune_enabled', true);
    },
    'clear_enabled' => null,
    'notice'        => __('「补发弹窗」= 把记录恢复为待领取并重设弹窗标记，用户下次打开页面会重新看到中奖弹窗（奖励不会重复发放）；「标记已领取」= 直接置为已领取并清掉弹窗标记；「删除记录」不可恢复。', 'zhiji'),

    /* ---------- 统计卡片 ---------- */
    'stats'         => function () {
        $stats = zhiji_claim_log_stats(ZHIJI_OPS_SCENE_FORTUNE);
        return array(
            array('label' => __('待领取', 'zhiji'), 'value' => $stats['active'], 'hint' => __('弹窗尚未被领走', 'zhiji'), 'tone' => $stats['active'] > 0 ? 'warn' : ''),
            array('label' => __('今日发放', 'zhiji'), 'value' => $stats['today']),
            array('label' => __('已领取', 'zhiji'), 'value' => $stats['cleared'], 'tone' => 'ok'),
            array('label' => __('累计记录', 'zhiji'), 'value' => $stats['total']),
        );
    },

    /* ---------- 查询筛选项 ---------- */
    'filters'       => array(
        array('key' => 'user_id', 'label' => __('用户 ID', 'zhiji'), 'type' => 'text', 'placeholder' => __('精确匹配，如 1', 'zhiji')),
        array('key' => 'search', 'label' => __('模糊搜索', 'zhiji'), 'type' => 'text', 'placeholder' => __('券码或备注', 'zhiji')),
        array('key' => 'status', 'label' => __('状态', 'zhiji'), 'type' => 'select', 'options' => array(
            ''        => __('全部', 'zhiji'),
            'active'  => __('待领取', 'zhiji'),
            'cleared' => __('已领取', 'zhiji'),
        )),
        array('key' => 'source', 'label' => __('奖励类型', 'zhiji'), 'type' => 'select', 'options' => array(
            ''        => __('全部', 'zhiji'),
            'points'  => __('积分', 'zhiji'),
            'balance' => __('余额', 'zhiji'),
            'vip'     => __('会员权益', 'zhiji'),
            'coupon'  => __('优惠码', 'zhiji'),
            'free'    => __('免单券', 'zhiji'),
        )),
        array('key' => 'date_from', 'label' => __('起始日期', 'zhiji'), 'type' => 'date'),
        array('key' => 'date_to', 'label' => __('截止日期', 'zhiji'), 'type' => 'date'),
    ),

    /* ---------- 表格列 ---------- */
    'columns'       => array(
        array('key' => 'id', 'label' => __('ID', 'zhiji'), 'width' => '56px'),
        array('key' => 'user', 'label' => __('用户', 'zhiji'), 'width' => '16%', 'render' => function ($row) {
            $uid = (int) $row->user_id;
            $u   = $uid ? get_userdata($uid) : null;
            if (!$u) {
                echo '<span class="zhiji-ops-tag muted">' . esc_html(sprintf(__('用户 #%d（已删除）', 'zhiji'), $uid)) . '</span>';
                return;
            }
            echo '<strong>' . esc_html($u->user_login) . '</strong>';
            echo '<br><small>' . esc_html($u->display_name) . ' · #' . $uid . '</small>';
        }),
        array('key' => 'note', 'label' => __('序位', 'zhiji'), 'width' => '9%'),
        array('key' => 'reward', 'label' => __('奖励', 'zhiji'), 'width' => '17%', 'render' => function ($row) {
            echo esc_html(zhiji_ops_scene_fortune_reward_text($row));
        }),
        array('key' => 'object_id', 'label' => __('券码', 'zhiji'), 'width' => '11%', 'render' => function ($row) {
            echo empty($row->object_id) ? '—' : '<span class="zhiji-ops-code">' . esc_html($row->object_id) . '</span>';
        }),
        array('key' => 'status', 'label' => __('状态', 'zhiji'), 'width' => '9%', 'render' => function ($row) {
            if ('active' === $row->status) {
                echo '<span class="zhiji-ops-tag active">' . esc_html__('待领取', 'zhiji') . '</span>';
            } else {
                echo '<span class="zhiji-ops-tag cleared">' . esc_html__('已领取', 'zhiji') . '</span>';
            }
        }),
        array('key' => 'created', 'label' => __('发放时间', 'zhiji'), 'width' => '13%'),
        array('key' => 'cleared', 'label' => __('领取时间', 'zhiji'), 'width' => '13%', 'render' => function ($row) {
            if (empty($row->cleared) || '0000-00-00 00:00:00' === (string) $row->cleared) {
                echo '—';
                return;
            }
            echo esc_html($row->cleared);
        }),
    ),

    /* ---------- 数据源（ClaimLog 标准查询；维度=scene + user_id） ---------- */
    'query'         => function ($args) {
        return zhiji_claim_log_query($args);
    },

    /* ---------- 操作 ---------- */
    'actions'       => array(
        array(
            'key'     => 'fortune_resend',
            'label'   => __('补发弹窗', 'zhiji'),
            'single'  => true,
            'bulk'    => true,
            'confirm' => __('确定补发吗？该用户下次打开页面会重新看到中奖弹窗（奖励不会重复发放）。', 'zhiji'),
        ),
        array(
            'key'     => 'fortune_consumed',
            'label'   => __('标记已领取', 'zhiji'),
            'single'  => true,
            'bulk'    => true,
            'confirm' => __('确定标记为已领取吗？同时会清掉挂着的弹窗标记。', 'zhiji'),
        ),
        array(
            'key'     => 'delete',
            'label'   => __('删除记录', 'zhiji'),
            'single'  => true,
            'bulk'    => true,
            'tone'    => 'zhiji-ops-danger',
            'confirm' => __('确定删除该记录吗？删除后不可恢复。', 'zhiji'),
        ),
    ),

    /* ---------- 操作处理 ---------- */
    'handle'        => function ($op, $params, array $ids, $scene) {
        $by = get_current_user_id();

        // 补发：恢复为待领取 + 重设弹窗标记
        if ('fortune_resend' === $op) {
            $ret     = zhiji_claim_log_restore(array(
                'ids'  => $ids,
                'note' => __('运维补发弹窗', 'zhiji'),
                'by'   => $by,
            ));
            $resend  = 0;
            $skipped = 0;
            foreach ($ids as $row_id) {
                $row = zhiji_claim_log_get($row_id);
                if (!$row || (int) $row->user_id <= 0) {
                    $skipped++;
                    continue;
                }
                $meta = zhiji_claim_log_meta($row);
                if (!$meta || empty($meta['n'])) {
                    $skipped++;
                    continue;
                }
                set_transient(
                    'zhiji_comment_fortune_' . (int) $row->user_id,
                    array(
                        'n'      => (int) $meta['n'],
                        'reward' => isset($meta['reward']) ? $meta['reward'] : array(),
                        'text'   => isset($meta['text']) ? (string) $meta['text'] : '',
                    ),
                    2 * HOUR_IN_SECONDS
                );
                $resend++;
            }

            $msg = sprintf(
                /* translators: 1: 已补发条数 2: 跳过条数 */
                __('已补发 %1$d 条弹窗（记录已恢复为待领取），用户下次打开页面即可看到；跳过 %2$d 条（缺少用户或奖励数据）。', 'zhiji'),
                $resend,
                $skipped
            );
            return array('ok' => $resend > 0, 'msg' => $msg);
        }

        // 标记已领取：置为已领取 + 清掉弹窗标记
        if ('fortune_consumed' === $op) {
            $uids = array();
            foreach ($ids as $row_id) {
                $row = zhiji_claim_log_get($row_id);
                if ($row && (int) $row->user_id > 0) {
                    $uids[(int) $row->user_id] = true;
                }
            }
            foreach (array_keys($uids) as $uid) {
                delete_transient('zhiji_comment_fortune_' . $uid);
            }
            $ret = zhiji_claim_log_clear(array(
                'ids'  => $ids,
                'mode' => 'reset',
                'note' => __('运维标记已领取', 'zhiji'),
                'by'   => $by,
            ));
            return array(
                'ok'  => true,
                /* translators: %d: 条数 */
                'msg' => sprintf(__('已标记 %d 条为已领取，并清掉对应弹窗标记。', 'zhiji'), (int) $ret['affected']),
            );
        }

        // 删除
        if ('delete' === $op) {
            $ret = zhiji_claim_log_clear(array(
                'ids'  => $ids,
                'mode' => 'delete',
                'by'   => $by,
            ));
            return array(
                'ok'  => true,
                /* translators: %d: 条数 */
                'msg' => sprintf(__('已删除 %d 条记录（不可恢复）。', 'zhiji'), (int) $ret['affected']),
            );
        }

        return array('ok' => false, 'msg' => __('未知操作', 'zhiji'));
    },
));
