<?php
/**
 * 运维场景：退出挽留弹窗「邮箱领取限制」
 *
 * 业务背景：访客在退出挽留弹窗填邮箱领取一次性优惠码。
 *          规则要求「同一邮箱领取成功后不允许重复领取」——领取记录持久化于
 *          core/ClaimLog.php 的 wp_zhiji_claim_log 表（scene=coupon_give）。
 *
 * 本场景提供的能力：
 *   · 状态展示：占用中 / 今日新增 / 已放行 / 累计，以及被拦截次数
 *   · 查询：按邮箱精确查询、状态/来源筛选、日期范围、券码模糊搜索、分页
 *   · 清除（受「运维清除」总闸与场景开关双重控制）：
 *       - 重置并放行：把记录标记为已放行 → 该邮箱恢复可领取（保留审计）
 *       - 删除记录：硬删记录（不可恢复）
 *       - 作废关联优惠码：连带清掉该邮箱名下的历史优惠码，彻底恢复可领取
 *
 * @since 2.0.0
 */

defined('ABSPATH') || exit;

/** 场景 ID（ClaimLog 的 scene 值，与 CouponGive 保持一致） */
if (!defined('ZHIJI_OPS_SCENE_CLAIM')) {
    define('ZHIJI_OPS_SCENE_CLAIM', 'coupon_give');
}

/**
 * 某邮箱当前名下的历史优惠码数量（带请求内缓存，避免逐行全表扫描）
 *
 * @param string $email
 * @return int
 */
function zhiji_ops_scene_claim_coupon_count($email)
{
    static $cache = array();
    $email = (string) $email;
    if ('' === $email) {
        return 0;
    }
    if (isset($cache[$email])) {
        return $cache[$email];
    }
    $count = function_exists('zhiji_coupon_give_count_by_email')
        ? (int) zhiji_coupon_give_count_by_email($email)
        : 0;
    $cache[$email] = $count;
    return $count;
}

/**
 * 采集某邮箱名下的优惠码（含具体券码，供作废使用）
 *
 * @param string $email
 * @return array 券码字符串数组
 */
function zhiji_ops_scene_claim_coupons_by_email($email)
{
    $codes = array();
    if ('' === $email || !class_exists('ZibCardPass')) {
        return $codes;
    }
    $rows = ZibCardPass::get(array('type' => 'coupon'), 'id', 0, 'all');
    foreach ((array) $rows as $row) {
        $meta = maybe_unserialize($row->meta);
        if (!is_array($meta) || empty($meta['email'])) {
            continue;
        }
        if (strtolower((string) $meta['email']) === strtolower($email)) {
            $codes[] = (string) $row->password;
        }
    }
    return $codes;
}

/**
 * 读取某张券码的「优惠内容」文本（如「免单」「8.8折」「立减5元」）
 *
 * 2026-09-28 新增：运维列表此前只显示券码本身，管理员无法直观看出每张券的力度。
 * 口径统一走 zhiji_coupon_give_discount_text()（含免单特判：multiply/val<=0 → 免单），
 * 与用户端邮件/通知显示完全一致。
 *
 * @param string $code 券码（claim_log.object_id）
 * @return string 优惠内容文本，无法解析时返回 ''
 */
function zhiji_ops_scene_claim_discount_text($code)
{
    static $cache = array();
    $code = (string) $code;
    if ('' === $code || !class_exists('ZibCardPass') || !function_exists('zhiji_coupon_give_discount_text')) {
        return '';
    }
    if (array_key_exists($code, $cache)) {
        return $cache[$code];
    }
    $text = '';
    // password 有索引，按券码精确查一条（type=coupon 限定优惠码，防止撞卡密）
    $rows = ZibCardPass::get(array('password' => $code, 'type' => 'coupon'), 'id', 0, 1);
    foreach ((array) $rows as $row) {
        $meta = maybe_unserialize($row->meta);
        if (!is_array($meta)) {
            break;
        }
        // 兼容两种 meta 结构：标准 discount 子键 / 直接 type+val（与 CouponGive 1539 行同口径）
        $discount = !empty($meta['discount'])
            ? $meta['discount']
            : ((!empty($meta['type']) && isset($meta['val'])) ? array('type' => $meta['type'], 'val' => $meta['val']) : null);
        if (is_array($discount)) {
            $text = (string) zhiji_coupon_give_discount_text($discount);
        }
        break;
    }
    $cache[$code] = $text;
    return $text;
}

/**
 * 场景注册
 */
zhiji_ops_register_scene(ZHIJI_OPS_SCENE_CLAIM, array(
    'title'         => __('邮箱领取限制', 'zhiji'),
    'icon'          => 'dashicons-email-alt',
    'desc'          => __('退出挽留弹窗「输入邮箱领优惠码」的领取记录。规则：同一邮箱领取成功后不可重复领取；此处可查询被拦记录，并按需放行或清除。', 'zhiji'),
    'priority'      => 10,
    // cap 省略 → 继承契约默认 zhiji_ops_view（2026-09-29 RBAC：操作门槛由 zhiji_ops_manage 单独把守）
    'enabled'       => function () {
        return zhiji_is_enabled('ops_scene_claim_enabled', true);
    },
    'clear_enabled' => null, // 跟随全局「运维清除」开关
    'notice'        => __('「重置并放行」= 该用户可立即再次领取，且对**全部**限领规则一并生效（含「每位用户仅限一次（按账号/IP）」），只需点这一次，无需再手动作废券；放行只生效一次 —— 用户再领一次后会重新受限（保留审计记录）。「删除记录」不可恢复；「作废关联券」用于清理该邮箱名下的历史优惠码。若某邮箱「查不到记录却仍被拦」（历史券造成），用上方「按邮箱放行」。', 'zhiji'),

    /* ---------- 统计卡片 ---------- */
    'stats'         => function () {
        $stats   = zhiji_claim_log_stats(ZHIJI_OPS_SCENE_CLAIM);
        $blocked = (int) get_option('zhiji_claim_blocked_count', 0);
        return array(
            array('label' => __('占用中（已领取）', 'zhiji'), 'value' => $stats['active'], 'hint' => __('这些邮箱当前不可再领', 'zhiji'), 'tone' => $stats['active'] > 0 ? 'warn' : '', 'icon' => 'dashicons-lock'),
            array('label' => __('今日新增', 'zhiji'), 'value' => $stats['today'], 'icon' => 'dashicons-chart-line'),
            array('label' => __('已放行', 'zhiji'), 'value' => $stats['cleared'], 'hint' => __('运维重置后可再领', 'zhiji'), 'tone' => 'ok', 'icon' => 'dashicons-unlock'),
            array('label' => __('累计记录', 'zhiji'), 'value' => $stats['total'], 'hint' => sprintf(__('历史拦截 %d 次', 'zhiji'), $blocked), 'icon' => 'dashicons-database'),
        );
    },

    /* ---------- 查询筛选项 ---------- */
    'filters'       => array(
        array('key' => 'email', 'label' => __('邮箱（精确）', 'zhiji'), 'type' => 'text', 'placeholder' => 'user@example.com'),
        array('key' => 'search', 'label' => __('模糊搜索', 'zhiji'), 'type' => 'text', 'placeholder' => __('邮箱或券码片段', 'zhiji')),
        array('key' => 'status', 'label' => __('状态', 'zhiji'), 'type' => 'select', 'options' => array(
            ''        => __('全部', 'zhiji'),
            'active'  => __('占用中', 'zhiji'),
            'cleared' => __('已放行', 'zhiji'),
        )),
        array('key' => 'source', 'label' => __('来源', 'zhiji'), 'type' => 'select', 'options' => array(
            ''                    => __('全部', 'zhiji'),
            'direct'              => __('邮箱直接领取', 'zhiji'),
            'ref_bonus'           => __('分享裂变奖励', 'zhiji'),
            'ops_release'         => __('运维放行', 'zhiji'),
            'comment_fortune'     => __('评论福袋', 'zhiji'),
            'comment_fortune_free' => __('评论福袋免单券', 'zhiji'),
            'lottery'             => __('大转盘抽奖', 'zhiji'),
            'reward_center'       => __('奖励中心', 'zhiji'),
        )),
        array('key' => 'date_from', 'label' => __('起始日期', 'zhiji'), 'type' => 'date'),
        array('key' => 'date_to', 'label' => __('截止日期', 'zhiji'), 'type' => 'date'),
    ),

    /* ---------- 表格列 ---------- */
    'columns'       => array(
        array('key' => 'id', 'label' => __('ID', 'zhiji'), 'width' => '60px'),
        array('key' => 'email', 'label' => __('邮箱', 'zhiji'), 'width' => '22%', 'render' => function ($row) {
            echo '<strong>' . esc_html($row->email ? $row->email : '—') . '</strong>';
        }),
        array('key' => 'object_id', 'label' => __('关联优惠码', 'zhiji'), 'width' => '12%', 'render' => function ($row) {
            if (empty($row->object_id)) {
                echo '—';
                return;
            }
            echo '<span class="zhiji-ops-code">' . esc_html($row->object_id) . '</span>';
        }),
        array('key' => 'discount', 'label' => __('优惠内容', 'zhiji'), 'width' => '11%',

            // 导出：同一口径直接给文本（免单 / 8.8折 / 立减N元），空则 '—'
            'export' => function ($row) {
                $text = zhiji_ops_scene_claim_discount_text($row->object_id);
                return '' === $text ? '—' : $text;
            },

            'render' => function ($row) {
                // 2026-09-28 新增：按券码解析优惠力度（免单/折扣/立减），与用户端口径一致
                $text = zhiji_ops_scene_claim_discount_text($row->object_id);
                if ('' === $text) {
                    echo '<span class="zhiji-ops-muted">—</span>';
                    return;
                }
                $is_free = __('免单', 'zhiji') === $text;
                $cls     = $is_free ? 'zhiji-ops-tag cleared' : 'zhiji-ops-tag muted';
                echo '<span class="' . esc_attr($cls) . '" title="' . esc_attr(sprintf(__('该券优惠内容：%s', 'zhiji'), $text)) . '">' . esc_html($text) . '</span>';
            },
        ),
        array('key' => 'status', 'label' => __('状态', 'zhiji'), 'width' => '9%',

            // 导出：本地化状态，不外泄 active/cleared 内部值
            'export' => function ($row) {
                return 'active' === $row->status ? __('占用中', 'zhiji') : __('已放行', 'zhiji');
            },

            'render' => function ($row) {
                if ('active' === $row->status) {
                    echo '<span class="zhiji-ops-tag active">' . esc_html__('占用中', 'zhiji') . '</span>';
                } else {
                    echo '<span class="zhiji-ops-tag cleared">' . esc_html__('已放行', 'zhiji') . '</span>';
                }
            },
        ),
        array('key' => 'source', 'label' => __('来源', 'zhiji'), 'width' => '10%',

            // 导出：走共享汉化映射，未识别码不外泄英文（与列表同口径）
            'export' => function ($row) {
                return zhiji_claim_log_source_label($row->source);
            },

            'render' => function ($row) {
                // 2026-09-28：改用 ClaimLog 共享映射（9 种来源全量收录），未识别码不外泄英文
                echo '<span class="zhiji-ops-tag muted">' . esc_html(zhiji_claim_log_source_label($row->source)) . '</span>';
            },
        ),
        array('key' => 'created', 'label' => __('领取时间', 'zhiji'), 'width' => '12%'),
        array('key' => 'cleared', 'label' => __('放行时间', 'zhiji'), 'width' => '12%',

            // 导出：时间归一（epoch 占位 / 空值 / 年份<2000 → '—'），避免 CSV 里出现 1970-01-01
            'export' => function ($row) {
                return zhiji_claim_log_time_text($row->cleared);
            },

            'render' => function ($row) {
            // 2026-09-28 修复 1970-01-01 显示：表 schema 默认值为 epoch 占位，统一走时间归一
            // （'1970-01-01 00:00:00' / 空值 / 年份<2000 → '—'，从未放行的记录不再显示 epoch）
            $t = zhiji_claim_log_time_text($row->cleared);
            if ('—' === $t) {
                echo '<span class="zhiji-ops-muted">' . esc_html__('—（未放行）', 'zhiji') . '</span>';
                return;
            }
            echo esc_html($t);
            if ((int) $row->cleared_by > 0) {
                $u = get_userdata((int) $row->cleared_by);
                if ($u) {
                    echo '<br><small>' . esc_html($u->user_login) . '</small>';
                }
            }
        }),
        array('key' => 'coupons', 'label' => __('名下券数', 'zhiji'), 'width' => '8%',

            // 导出：计算列本会被导出跳过（行对象上没有该属性），显式给出
            'export' => function ($row) {
                return (string) zhiji_ops_scene_claim_coupon_count($row->email);
            },

            'render' => function ($row) {
            $n = zhiji_ops_scene_claim_coupon_count($row->email);
            $cls = $n > 0 ? 'zhiji-ops-tag muted' : 'zhiji-ops-tag cleared';
            echo '<span class="' . esc_attr($cls) . '">' . (int) $n . '</span>';
        }),
        array('key' => 'ip', 'label' => __('IP', 'zhiji'), 'width' => '12%'),
    ),

    /* ---------- 操作 ---------- */
    'actions'       => array(
        array(
            'key'     => 'reset',
            'label'   => __('重置并放行', 'zhiji'),
            'single'  => true,
            'bulk'    => true,
            'confirm' => __('确定重置该记录吗？重置后该邮箱可以再次领取（保留审计记录）。', 'zhiji'),
        ),
        array(
            'key'     => 'delete',
            'label'   => __('删除记录', 'zhiji'),
            'single'  => true,
            'bulk'    => true,
            'tone'    => 'zhiji-ops-danger',
            'confirm' => __('确定删除该记录吗？删除后不可恢复。', 'zhiji'),
        ),
        array(
            'key'     => 'purge_coupon',
            'label'   => __('作废关联券', 'zhiji'),
            'single'  => true,
            'bulk'    => false,
            'tone'    => 'zhiji-ops-danger',
            'confirm' => __('确定作废该邮箱名下所有历史优惠码吗？此操作不可恢复（仅影响本模块发放的券）。', 'zhiji'),
        ),
    ),

    /* ---------- 数据源 ---------- */
    // 领取记录统一存于 ClaimLog（scene=coupon_give），此处直接走其标准查询：
    // 支持 email 精确 / search 模糊 / status / source / 日期范围 / 分页。
    'query'         => function ($args) {
        return zhiji_claim_log_query($args);
    },

    /* ---------- 详情抽屉扩展（2026-09-28 新增，方案 P2-C） ---------- */
    // 补**计算字段**：按券码查出优惠内容（列表「优惠内容」列已有，
    // 但抽屉原本只渲染原始行字段，看不到它 —— 这里补上，与列表同口径）。
    'detail'        => function ($row) {
        $out = array('primary' => array());
        $code = is_object($row) && isset($row->object_id) ? (string) $row->object_id : '';
        if ('' === $code) {
            return $out;
        }
        $text = function_exists('zhiji_ops_scene_claim_discount_text')
            ? (string) zhiji_ops_scene_claim_discount_text($code)
            : '';
        if ('' === $text) {
            return $out;
        }
        $out['primary'][] = array('k' => __('优惠内容', 'zhiji'), 'v' => $text);
        return $out;
    },

    /* ---------- 表单型操作（目标数据可能不在当前列表里） ---------- */
    // 典型场景：某邮箱**在记录表里 0 条**，但名下还有历史优惠码 → 仍被「每邮箱限领」拦住，
    // 列表里没有行 = 行内「作废关联券」点不到 → 这里提供"不需要先有记录"的入口。
    'pre_actions'   => array(
        array(
            'key'     => 'release_email',
            'label'   => __('按邮箱放行', 'zhiji'),
            'desc'    => __('邮箱在下面查不到记录时用它：作废其名下历史优惠码，并写入放行记录。', 'zhiji'),
            'confirm' => __('确定对该邮箱放行吗？会作废其名下本模块发放的全部历史优惠码（不可恢复）。', 'zhiji'),
            'fields'  => array(
                array('name' => 'email', 'label' => __('邮箱', 'zhiji'), 'type' => 'email', 'placeholder' => 'user@example.com', 'required' => true),
            ),
        ),
        array(
            'key'     => 'purge_email',
            'label'   => __('按邮箱清理记录', 'zhiji'),
            'tone'    => 'zhiji-ops-danger',
            'desc'    => __('删除该邮箱在本场景的全部领取记录（不可恢复），用于清理测试/异常数据。', 'zhiji'),
            'confirm' => __('确定删除该邮箱的全部领取记录吗？此操作不可恢复。', 'zhiji'),
            'fields'  => array(
                array('name' => 'email', 'label' => __('邮箱', 'zhiji'), 'type' => 'email', 'placeholder' => 'user@example.com', 'required' => true),
            ),
        ),
    ),

    /* ---------- 操作处理 ---------- */
    'handle'        => function ($op, $params, array $ids, $scene) {
        $by = get_current_user_id();

        /* ---------- 表单型：按邮箱放行（作废历史券 + 写放行记录） ---------- */
        if ('release_email' === $op) {
            $email = isset($params['email']) ? sanitize_email(wp_unslash($params['email'])) : '';
            if (!$email || !is_email($email)) {
                return array('ok' => false, 'msg' => __('请填写有效的邮箱地址', 'zhiji'));
            }

            // 1) 作废该邮箱名下本模块发放的全部历史优惠码（旧规则「每邮箱限领」的拦截源）
            $codes   = zhiji_ops_scene_claim_coupons_by_email($email);
            $deleted = 0;
            if (class_exists('ZibCardPass')) {
                foreach ($codes as $code) {
                    if (ZibCardPass::delete(array('password' => $code))) {
                        $deleted++;
                    }
                }
            }

            // 2) 该邮箱原有的「占用中」记录一并放行（保留审计）
            zhiji_claim_log_clear(array(
                'scene' => ZHIJI_OPS_SCENE_CLAIM,
                'email' => $email,
                'mode'  => 'reset',
                'note'  => __('运维按邮箱放行', 'zhiji'),
                'by'    => $by,
            ));

            // 3) 写一条放行记录：既留审计，也让领取校验明确放行该邮箱（cleared 晚于 active）
            $row_id = zhiji_claim_log_add(array(
                'scene'  => ZHIJI_OPS_SCENE_CLAIM,
                'email'  => $email,
                'source' => 'ops_release',
                'note'   => __('运维按邮箱放行', 'zhiji'),
                'meta'   => array('by' => $by, 'purged_coupons' => $deleted),
            ));
            if ($row_id) {
                zhiji_claim_log_clear(array(
                    'ids'  => array($row_id),
                    'mode' => 'reset',
                    'note' => __('运维按邮箱放行', 'zhiji'),
                    'by'   => $by,
                ));
            }

            return array(
                'ok'  => true,
                /* translators: 1: 邮箱 2: 作废的券数 */
                'msg' => sprintf(__('已对 %1$s 执行放行：作废历史优惠码 %2$d 张并写入放行记录，该邮箱现在可以再次领取。', 'zhiji'), $email, $deleted),
            );
        }

        /* ---------- 表单型：按邮箱清理记录（只删记录，不动作废券） ---------- */
        if ('purge_email' === $op) {
            $email = isset($params['email']) ? sanitize_email(wp_unslash($params['email'])) : '';
            if (!$email || !is_email($email)) {
                return array('ok' => false, 'msg' => __('请填写有效的邮箱地址', 'zhiji'));
            }
            $ret = zhiji_claim_log_clear(array(
                'scene' => ZHIJI_OPS_SCENE_CLAIM,
                'email' => $email,
                'mode'  => 'delete',
            ));
            return array(
                'ok'  => true,
                /* translators: 1: 邮箱 2: 删除条数 */
                'msg' => sprintf(__('已删除 %1$s 的 %2$d 条领取记录（不动作废优惠码）。', 'zhiji'), $email, (int) $ret['affected']),
            );
        }

        /* ---------- 行内/批量：重置放行、删除记录、作废关联券 ---------- */
        if ('reset' === $op || 'delete' === $op) {
            $mode = ('delete' === $op) ? 'delete' : 'reset';
            $ret  = zhiji_claim_log_clear(array(
                'ids'  => $ids,
                'mode' => $mode,
                'note' => ('delete' === $op) ? __('运维页删除', 'zhiji') : __('运维页重置放行', 'zhiji'),
                'by'   => $by,
            ));

            if ('reset' === $mode) {
                // 放行后若名下仍有历史优惠码，给出明确的下一步提示（不静默处理）
                $emails = array();
                foreach ($ids as $row_id) {
                    $row = zhiji_claim_log_get($row_id);
                    if ($row && $row->email) {
                        $emails[$row->email] = zhiji_ops_scene_claim_coupon_count($row->email);
                    }
                }
                $leftover = 0;
                foreach ($emails as $n) {
                    $leftover += (int) $n;
                }
                $msg = sprintf(
                    /* translators: %d: 记录数 */
                    __('已重置并放行 %d 条记录，对应邮箱现在可以再次领取。', 'zhiji'),
                    (int) $ret['affected']
                );
                if ($leftover > 0) {
                    $msg .= ' ' . sprintf(
                        /* translators: %d: 历史优惠码数量 */
                        __('该邮箱名下另有 %d 张历史优惠码，不影响再次领取，可用「作废关联券」清理。', 'zhiji'),
                        $leftover
                    );
                }
                return array('ok' => true, 'msg' => $msg);
            }

            return array(
                'ok'  => true,
                /* translators: %d: 记录数 */
                'msg' => sprintf(__('已删除 %d 条记录（不可恢复）。', 'zhiji'), (int) $ret['affected']),
            );
        }

        if ('purge_coupon' === $op) {
            if (!class_exists('ZibCardPass')) {
                return array('ok' => false, 'msg' => __('商城优惠码模块不可用，无法作废', 'zhiji'));
            }
            $emails  = array();
            foreach ($ids as $row_id) {
                $row = zhiji_claim_log_get($row_id);
                if ($row && $row->email) {
                    $emails[$row->email] = true;
                }
            }
            if (!$emails) {
                return array('ok' => false, 'msg' => __('未找到记录对应的邮箱', 'zhiji'));
            }

            $deleted = 0;
            foreach (array_keys($emails) as $email) {
                foreach (zhiji_ops_scene_claim_coupons_by_email($email) as $code) {
                    if (ZibCardPass::delete(array('password' => $code))) {
                        $deleted++;
                    }
                }
                // 券已作废，同步把该邮箱的日志标记为已放行，确保"恢复可领取"闭环
                zhiji_claim_log_clear(array(
                    'scene' => ZHIJI_OPS_SCENE_CLAIM,
                    'email' => $email,
                    'mode'  => 'reset',
                    'note'  => __('作废关联券后放行', 'zhiji'),
                    'by'    => $by,
                ));
            }

            return array(
                'ok'  => true,
                /* translators: 1: 作废券数 2: 邮箱数 */
                'msg' => sprintf(__('已作废 %1$d 张优惠码（涉及 %2$d 个邮箱），这些邮箱已恢复可领取。', 'zhiji'), $deleted, count($emails)),
            );
        }

        return array('ok' => false, 'msg' => __('未知操作', 'zhiji'));
    },
));
