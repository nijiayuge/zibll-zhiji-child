<?php
/**
 * @module  Quiz
 * @desc    互动答题赢奖励：题库 + 随机抽题 + 服务端判分 + 答对发积分。
 *          防刷（调研口径落地）：仅登录用户 / 每日次数上限 / 单日积分上限 /
 *          正确序号不落前端（提交后服务端判分）。
 * @option  quiz_enabled           总开关
 *          quiz_pool              题库（每行：题目|选项;选项;…|正确序号(1起)|解析）
 *          quiz_count             每次抽题数（默认 5）
 *          quiz_daily             每日答题次数上限（默认 3）
 *          quiz_points_per        每答对 1 题积分（默认 1）
 *          quiz_points_daily_max  单日积分上限（默认 15）
 * @hook    wp_ajax(_nopriv)_zhiji_quiz_start · 抽题
 *          wp_ajax(_nopriv)_zhiji_quiz_submit · 提交判分
 * @short   [zhiji_quiz]
 * @since   2.0.0（2026-09-29 新增）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('quiz', array(
    'title'    => '互动答题',
    'parent'   => 'zhiji_interact',
    'priority' => 130,
    'option'   => 'quiz_enabled',
));

/* ============================================================
 * 后台配置
 * ============================================================ */
Zhiji_Registry::register_options('quiz', array(
    array(
        'id'      => 'quiz_enabled',
        'type'    => 'switcher',
        'title'   => __( '启用互动答题', 'zhiji' ),
        'label'   => __( '开启后前台可用短码 [zhiji_quiz] 答题赚积分。', 'zhiji' ),
        'default' => true,
    ),
    array(
        'type'    => 'submessage',
        'style'   => 'info',
        'content' => __('题库每行一条：<code>题目|选项A;选项B;选项C;选项D|正确序号(1起)|解析</code>。'
            . '正确序号只存服务端，前端抽题不回传答案；答对发积分，单日积分封顶。', 'zhiji'),
    ),
    array(
        'type'  => 'subheading',
        'title' => __( '① 题库', 'zhiji' ),
    ),
    array(
        'id'         => 'quiz_pool',
        'type'       => 'textarea',
        'title'      => '题库',
        'rows'       => 8,
        'sanitize'   => false,
        'placeholder' => "知集子主题基于哪个父主题？|Vela;子比主题(Zibll);Storefront|2|子比主题是父主题。",
        'desc'       => __('选项用半角分号分隔。', 'zhiji'),
    ),
    array(
        'type'  => 'subheading',
        'title' => __( '② 答题规则', 'zhiji' ),
    ),
    array('id' => 'quiz_count', 'type' => 'number', 'title' => '每次抽题数', 'default' => 5, 'min' => 1, 'max' => 20),
    array('id' => 'quiz_daily', 'type' => 'number', 'title' => '每日答题次数上限', 'default' => 3, 'min' => 1, 'max' => 50),
    array(
        'type'  => 'subheading',
        'title' => __( '③ 积分奖励', 'zhiji' ),
    ),
    array('id' => 'quiz_points_per', 'type' => 'number', 'title' => '每答对 1 题积分', 'default' => 1, 'min' => 0, 'max' => 100),
    array('id' => 'quiz_points_daily_max', 'type' => 'number', 'title' => '单日积分上限', 'default' => 15, 'min' => 0, 'max' => 500,
        'desc' => __('防刷红线：单日答题积分到此封顶（行业口径 15 分/日）。', 'zhiji')),
), 130);

/* ============================================================
 * 题库与判分（纯函数，探针可测）
 * ============================================================ */

/**
 * 解析题库（容错：选项不足 2 个 / 正确序号越界 → 跳过）
 *
 * @return array array( array('q'=>,'choices'=>array,'answer'=>int(1起),'explain'=>), … )
 */
function zhiji_quiz_pool()
{
    $raw = (string) zhiji_get_option('quiz_pool', '');
    $out = array();
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        $line = trim($line);
        if ('' === $line) {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) < 3) {
            continue;
        }
        $choices = array_values(array_filter(array_map('trim', explode(';', $parts[1])), 'strlen'));
        if (count($choices) < 2) {
            continue;
        }
        $ans = (int) $parts[2];
        if ($ans < 1 || $ans > count($choices)) {
            continue;
        }
        $out[] = array(
            'q'       => $parts[0],
            'choices' => $choices,
            'answer'  => $ans,
            'explain' => isset($parts[3]) ? $parts[3] : '',
        );
    }
    return $out;
}

/**
 * 抽题（随机 N 题，**不暴露正确答案**）
 *
 * @param int $n
 * @return array array( array('id'=>题库下标,'q'=>,'choices'=>array), … )
 */
function zhiji_quiz_pick($n = 5)
{
    $pool = zhiji_quiz_pool();
    if (!$pool) {
        return array();
    }
    $ids = array_keys($pool);
    shuffle($ids);
    $ids = array_slice($ids, 0, max(1, min($n, count($pool))));

    $out = array();
    foreach ($ids as $id) {
        $out[] = array(
            'id'      => $id,
            'q'       => $pool[$id]['q'],
            'choices' => $pool[$id]['choices'], // 不含 answer/explain
        );
    }
    return $out;
}

/**
 * 用户当日用量（次数 / 已得积分）
 *
 * @param int $uid
 * @return array array('count'=>int,'points'=>int,'date'=>Y-m-d)
 */
function zhiji_quiz_usage($uid)
{
    $m = get_user_meta($uid, 'zhiji_quiz_usage', true);
    $m = is_array($m) ? $m : array();
    $today = current_time('Y-m-d');
    if (!isset($m['date']) || $m['date'] !== $today) {
        return array('count' => 0, 'points' => 0, 'date' => $today);
    }
    return array('count' => (int) $m['count'], 'points' => (int) $m['points'], 'date' => $today);
}

/**
 * 判分并发奖（服务端唯一裁决点）
 *
 * @param int   $uid
 * @param array $answers array( 题库下标 => 用户选择序号(1起) )
 * @return array array('ok'=>,'correct'=>,'total'=>,'points'=>,'capped'=>bool,'results'=>[])
 */
function zhiji_quiz_grade($uid, array $answers)
{
    $pool   = zhiji_quiz_pool();
    $usage  = zhiji_quiz_usage($uid);
    $daily  = max(1, (int) zhiji_get_option('quiz_daily', 3));
    $per    = max(0, (int) zhiji_get_option('quiz_points_per', 1));
    $cap    = max(0, (int) zhiji_get_option('quiz_points_daily_max', 15));

    if (!$pool) {
        return array('ok' => false, 'msg' => __('题库为空', 'zhiji'));
    }

    // 每日次数上限
    if ($usage['count'] >= $daily) {
        return array('ok' => false, 'msg' => sprintf(__('今日答题次数已达上限（%d 次）', 'zhiji'), $daily));
    }

    $correct = 0;
    $total   = 0;
    $results = array();
    $earned  = 0;
    $capped  = false;

    foreach ($answers as $id => $choice) {
        $id     = (int) $id;
        $choice = (int) $choice;
        if (!isset($pool[$id])) {
            continue;
        }
        $total++;
        $right = ((int) $pool[$id]['answer'] === $choice);
        if ($right) {
            $correct++;
        }
        // 积分：答对才给；受单日上限约束
        $got = 0;
        if ($right && $per > 0) {
            $room = $cap - $usage['points'] - $earned;
            if ($room >= $per) {
                $got    = $per;
                $earned += $per;
            } elseif ($room > 0) {
                $got    = $room; // 封顶前的最后一题按剩余额度给（不超上限）
                $earned += $room;
            } else {
                $capped = true;
            }
        }
        $results[] = array(
            'q'       => $pool[$id]['q'],
            'choice'  => $choice,
            'answer'  => (int) $pool[$id]['answer'],
            'right'   => $right,
            'explain' => $pool[$id]['explain'],
            'points'  => $got,
        );
    }

    // 发奖
    if ($earned > 0) {
        Zhiji_Adapter::update_user_points($uid, array(
            'value' => $earned,
            'type'  => __('答题赢奖', 'zhiji'),
            'desc'  => sprintf(__('答题答对 %d/%d 题', 'zhiji'), $correct, $total),
        ));
    }

    // 用量记账
    update_user_meta($uid, 'zhiji_quiz_usage', array(
        'date'   => current_time('Y-m-d'),
        'count'  => $usage['count'] + 1,
        'points' => $usage['points'] + $earned,
    ));

    return array(
        'ok'      => true,
        'correct' => $correct,
        'total'   => $total,
        'points'  => $earned,
        'capped'  => $capped,
        'results' => $results,
    );
}

/* ============================================================
 * AJAX
 * ============================================================ */

zhiji_api_register('zhiji_quiz_start', 'zhiji_quiz_ajax_start', true, '');
zhiji_api_register('zhiji_quiz_submit', 'zhiji_quiz_ajax_submit', true, '');
add_action('wp_ajax_zhiji_quiz_start', 'zhiji_quiz_ajax_start');
add_action('wp_ajax_nopriv_zhiji_quiz_start', 'zhiji_quiz_ajax_start');
add_action('wp_ajax_zhiji_quiz_submit', 'zhiji_quiz_ajax_submit');
add_action('wp_ajax_nopriv_zhiji_quiz_submit', 'zhiji_quiz_ajax_submit');

/**
 * 抽题（需登录；不计次 —— 提交才计次）
 *
 * @return void
 */
function zhiji_quiz_ajax_start()
{
    if (function_exists('zhiji_ops_kill_active') && zhiji_ops_kill_active()) {
        wp_send_json_error(array('msg' => __('应急模式已开启，互动功能暂停', 'zhiji')), 503);
    }
    if (!is_user_logged_in()) {
        wp_send_json_error(array('msg' => __('请先登录', 'zhiji')), 200);
    }
    wp_send_json_success(array(
        'questions' => zhiji_quiz_pick((int) zhiji_get_option('quiz_count', 5)),
        'usage'     => zhiji_quiz_usage(get_current_user_id()),
    ));
}

/**
 * 提交判分
 *
 * @return void
 */
function zhiji_quiz_ajax_submit()
{
    if (function_exists('zhiji_ops_kill_active') && zhiji_ops_kill_active()) {
        wp_send_json_error(array('msg' => __('应急模式已开启，互动功能暂停', 'zhiji')), 503);
    }
    $uid   = get_current_user_id();
    $nonce = isset($_POST['nonce']) ? wp_unslash($_POST['nonce']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if (!wp_verify_nonce($nonce, 'zhiji_quiz')) {
        wp_send_json_error(array('msg' => __('页面已过期，请刷新后重试', 'zhiji')), 403);
    }
    if (!$uid) {
        wp_send_json_error(array('msg' => __('请先登录', 'zhiji')), 200);
    }
    $answers = isset($_POST['answers']) && is_array($_POST['answers']) ? array_map('absint', wp_unslash($_POST['answers'])) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
    $r = zhiji_quiz_grade($uid, $answers);
    if (empty($r['ok'])) {
        wp_send_json_error(array('msg' => $r['msg']), 200);
    }
    wp_send_json_success($r);
}

/* ============================================================
 * 前台短码与资源（JS 独立文件：assets/zhiji/js/quiz.js）
 * ============================================================ */

/**
 * 短码：[zhiji_quiz]
 *
 * @return string
 */
function zhiji_quiz_shortcode()
{
    if (!zhiji_is_enabled('quiz_enabled', true)) {
        return '';
    }

    zhiji_quiz_enqueue();

    $out = '<div class="zhiji-quiz" id="zhiji-quiz">';
    if (!is_user_logged_in()) {
        $out .= '<p class="description">' . esc_html__('登录后即可参与答题赢积分。', 'zhiji') . '</p></div>';
        return $out;
    }
    $out .= '<div class="zhiji-quiz-box"><p class="description">' . esc_html__('点击「开始答题」随机抽取题目，答对得积分。', 'zhiji') . '</p>'
          . '<button class="button button-primary" id="zhiji-quiz-start">' . esc_html__('开始答题', 'zhiji') . '</button>'
          . '<div id="zhiji-quiz-body" style="margin-top:12px"></div></div></div>';
    return $out;
}
add_shortcode('zhiji_quiz', 'zhiji_quiz_shortcode');

/**
 * 前台资源
 *
 * ⚠️ JS 为独立文件 assets/zhiji/js/quiz.js（head 输出；同 ops-drawer 结论：
 *    本项目 wp_footer 不可靠 → head + 脚本内部 DOM 安全启动）。
 *    配置（nonce/ajax）经 wp_add_inline_script 置于脚本之前（WP 保证顺序）。
 *
 * @return void
 */
function zhiji_quiz_enqueue()
{
    if (!zhiji_is_enabled('quiz_enabled', true)) {
        return;
    }
    wp_enqueue_script('zhiji-quiz', zhiji_asset_url('js/quiz.js'), array(), ZHIJI_VERSION, false);
    wp_add_inline_script('zhiji-quiz', 'window.ZHIJI_QUIZ_CFG=' . wp_json_encode(array(
        'nonce' => wp_create_nonce('zhiji_quiz'),
        'ajax'  => admin_url('admin-ajax.php'),
    )) . ';', 'before');
    zhiji_asset_add_css('quiz', '.zhiji-quiz-box{border:1px solid #eee;border-radius:8px;padding:14px;background:#fff}.zhiji-quiz-q strong{display:block;margin-bottom:4px}');
}
add_action('wp_enqueue_scripts', 'zhiji_quiz_enqueue', 20);
