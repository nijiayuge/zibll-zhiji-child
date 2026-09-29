<?php
/**
 * @module  Ops
 * @desc    运维管理页面的场景注册表与共用助手（基础设施层）
 *
 *          设计目标：**加新场景 = 新增一个声明文件**，页面渲染/查询/操作/审计全部复用。
 *          每个场景声明：标题、说明、统计卡片、查询筛选项、表格列、行内/批量操作、
 *          查询回调、操作回调。页面层（includes/admin/OpsPage.php）只做通用渲染与分发。
 *
 * @api     zhiji_ops_register_scene($id, $args)   注册场景
 *          zhiji_ops_scenes() / zhiji_ops_scene() 读取场景
 *          zhiji_ops_enabled()                    运维页面总开关
 *          zhiji_ops_can_clear($id)               该场景是否允许"清除"类操作
 *          zhiji_ops_page_url($id)                生成页面链接
 *          zhiji_ops_add_activity() / zhiji_ops_activities()  操作审计
 * @since   2.0.0
 */

defined('ABSPATH') || exit;

if (!defined('ZHIJI_OPS_ACTIVITY_KEY')) {
    define('ZHIJI_OPS_ACTIVITY_KEY', 'zhiji_ops_activity');
}
if (!defined('ZHIJI_OPS_ACTIVITY_MAX')) {
    define('ZHIJI_OPS_ACTIVITY_MAX', 50);
}

/**
 * 解析勋章图标 URL（2026-09-29 勋章本地化）
 *
 * 子主题自绘 SVG 优先（assets/zhiji/img/medals/，版权归知集）；
 * 清单见同目录 medal-manifest.json；缺失时回退父主题 medal-background（已购 zibll 9.1 商用授权）。
 *
 * @param string $name 勋章名（须与 user_medal_args / manifest 的 name 一致）
 * @return string 图标 URL
 */
function zhiji_medal_icon($name)
{
    static $map = null;
    if (null === $map) {
        $json = get_theme_file_path() . '/assets/zhiji/img/medals/medal-manifest.json';
        if (file_exists($json)) {
            $dec = json_decode(file_get_contents($json), true);
            $map = (is_array($dec) && !empty($dec['medals'])) ? $dec['medals'] : array();
        } else {
            $map = array();
        }
    }
    $dir  = '/assets/zhiji/img/medals/';
    $base = get_theme_file_uri() . $dir;
    if (isset($map[$name]) && !empty($map[$name]['file'])) {
        $file = $map[$name]['file'];
        if (file_exists(get_theme_file_path() . $dir . $file)) {
            return $base . $file;
        }
    }
    // 兜底：父主题 medal-background（已购商用授权，合法复用）
    // ⚠️ 必须把路径作为参数传入 get_theme_file_uri()：无参调用返回子主题 URI，
    //    子主题没有 img/medal/ 目录会 404 破图；带参会自动回退父主题文件。
    return get_theme_file_uri('img/medal/medal-background.svg');
}

/**
 * 注册一个运维场景
 *
 * $args 契约：
 *   title         string    场景名（菜单/卡片显示）
 *   desc          string    一句话说明（页面顶部）
 *   priority      int       排序（小在前）
 *   cap           string    **查看**场景页所需权限，默认 zhiji_ops_view（管理员经能力桥隐式拥有；
 *                           2026-09-29 RBAC：变更类操作另需 zhiji_ops_manage，见 can_clear）
 *   enabled       bool      场景级开关，默认 true
 *   clear_enabled bool|null 是否允许"清除/重置/删除"；null = 跟随全局开关
 *   stats         callable  function(): array( array('label'=>,'value'=>,'hint'=>,'tone'=>'') )
 *   filters       array     筛选项：array('key','label','type'(text|select|date),'options','placeholder','default')
 *   columns       array     列：array('key','label','width','render'=>function($row,$scene))
 *   query         callable  function(array $args): array('rows','total','pages','page','per_page')
 *   actions       array     行内/批量操作：array('key','label','mode'(single|bulk),'confirm','tone')
 *   pre_actions   array     **表单型操作**（不需要先选中记录，页面顶部直接填参执行）：
 *                           array('key','label','desc','confirm','tone',
 *                                 'fields'=>array(array('name','label','type'(text|email),'placeholder','required')))
 *                           适用场景：目标数据不在当前列表里（例如"某个邮箱没有任何记录，但被历史券拦住"）。
 *                           处理器通过 $params（= $_POST）拿字段值。
 *   handle        callable  function($action, array $params, array $ids, array $scene): array('ok'=>,'msg'=>)
 *   detail        callable  **详情抽屉扩展**（2026-09-28 新增，方案 P2-C）：
 *                           function($row, array $scene): array
 *                           用于补充**计算字段**（不在原始行里的值，如按券码查出的「优惠内容」）。
 *                           可返回的键（全部可选）：
 *                             primary => array( array('k'=>…,'v'=>…), … )   追加到概览区
 *                             fields  => array( array('k'=>…,'v'=>…,'pre'=>bool), … )  追加到明细区
 *                             status  => array('text'=>…,'tone'=>'ok|warn')  覆盖状态徽标
 *                             replace => bool  **整体接管**（2026-09-29 新增）：
 *                                              场景行形态不是 ClaimLog 形状时（如抽奖日志），
 *                                              跳过通用字段循环，只用本回调提供的字段。
 *                                              ⚠️ 不加 replace 时通用循环仍会把原始英文键
 *                                              （uid/name/value…）原样列出，造成重复噪声。
 *                           ⚠️ 页面层负责通用分组与 JSON 编码；本回调只提供**内容**，不输出 HTML。
 *                           未声明时页面层走通用逻辑（仅行字段）。
 *   notice        string    页面顶部提示（可含 HTML 白名单外的纯文本）
 *
 * @param string $id   场景 ID（sanitize_key 后使用，同时作为页面 slug 一部分）
 * @param array  $args
 * @return void
 */

/* ============================================================
 * 〇、能力模型（2026-09-29 新增，附录 Y ⭐⭐⭐：RBAC 只读/操作分离）
 * ============================================================ */

/**
 * 运维台**查看**能力（列表 / 详情 / 导出 —— 只读）
 *
 * @return string
 */
function zhiji_ops_view_cap()
{
    return 'zhiji_ops_view';
}

/**
 * 运维台**操作**能力（清除 / 重置 / 删除 / 放行 —— 变更状态）
 *
 * @return string
 */
function zhiji_ops_manage_cap()
{
    return 'zhiji_ops_manage';
}

/**
 * 能力桥：administrator（manage_options）**隐式**获得两个运维能力
 *
 * 为什么用 user_has_cap 桥而不是给角色写能力：
 *  ① 零行为变化 —— 管理员无需任何迁移就保持完整访问（默认语义与升级前一致）；
 *  ② 主题不改角色数据（角色归站点管理员管，主题只在"判定时"动态放行）；
 *  ③ 站长想给非管理员授权时，用任意角色编辑器 / WP-CLI 给某角色加
 *     `zhiji_ops_view`（只读）或 `zhiji_ops_manage`（可操作）即可，互不牵连。
 *
 * ⚠️ 只做"放行"，绝不"收回"：没有 manage_options 的用户，两个能力都为 false。
 */
add_filter('user_has_cap', function ($allcaps, $caps, $args) {
    if (!empty($allcaps['manage_options'])) {
        foreach ((array) $caps as $cap) {
            if (zhiji_ops_view_cap() === $cap || zhiji_ops_manage_cap() === $cap) {
                $allcaps[$cap] = true;
            }
        }
    }
    return $allcaps;
}, 10, 3);

/* ============================================================
 * 〇·B、勋章增强（2026-09-29 新增）：事件驱动自动授予
 * ============================================================ */

/**
 * 向父主题勋章系统注册新模块勋章（通过 user_medal_args filter 注入）
 *
 * 父主题勋章引擎支持事件驱动自动判定（get_type + get_val），
 * 但预置类型不包含 v2 新模块事件。通过 filter 注入让引擎自动判定与展示。
 */
add_filter('user_medal_args', function ($args) {
    if (empty($args) || !is_array($args)) {
        $args = array(array('cat_name' => __('知集互动', 'zhiji'), 'items' => array()));
    }
    $cat_label = __('知集互动', 'zhiji');
    $found = false;
    foreach ($args as $i => $cat) {
        if (isset($cat['cat_name']) && $cat_label === $cat['cat_name']) { $found = true; break; }
    }
    if (!$found) {
        $args[] = array('cat_name' => $cat_label, 'items' => array());
        $i = count($args) - 1;
    }
    // 图标走 zhiji_medal_icon()：子主题自绘优先，缺失回退父主题兜底（2026-09-29 勋章本地化）
    $new = array(
        array('name' => '首兑新人', 'desc' => '首次在积分商城兑换', 'icon' => zhiji_medal_icon('首兑新人'), 'get_type' => 'points_mall_exchange', 'get_val' => 1),
        array('name' => '兑换达人', 'desc' => '累计兑换 10 次', 'icon' => zhiji_medal_icon('兑换达人'), 'get_type' => 'points_mall_exchange', 'get_val' => 10),
        array('name' => '谈判专家', 'desc' => '砍价成功 1 次', 'icon' => zhiji_medal_icon('谈判专家'), 'get_type' => 'bargain_success', 'get_val' => 1),
        array('name' => '学神认证', 'desc' => '答题满分 3 次', 'icon' => zhiji_medal_icon('学神认证'), 'get_type' => 'quiz_perfect', 'get_val' => 3),
        // 隐藏成就（2026-09-29）：触发条件不公示，desc 仅作解锁后注解；无 get_type → 只能由事件授予
        array('name' => '夜猫子', 'desc' => '隐藏成就 · 凌晨的秘密行动', 'icon' => zhiji_medal_icon('夜猫子')),
        array('name' => '彩蛋猎人', 'desc' => '隐藏成就 · 站点里藏着一个小秘密', 'icon' => zhiji_medal_icon('彩蛋猎人')),
        array('name' => '坚持之王', 'desc' => '隐藏成就 · 连续坚持整整一个月', 'icon' => zhiji_medal_icon('坚持之王')),
    );
    $existing = array_column($args[$i]['items'] ?? array(), 'name');
    foreach ($new as $item) {
        if (!in_array($item['name'], $existing)) {
            $args[$i]['items'][] = $item;
        }
    }
    return $args;
}, 20);

/**
 * 事件监听：v2 新模块关键行为 → 计数 + 自动授予勋章
 */
add_action('zhiji_pmall_exchanged', function ($uid) {
    if (!function_exists('zib_add_user_medal')) { return; }
    $n = (int) get_user_meta($uid, 'zhiji_pmall_exchange_count', true) + 1;
    update_user_meta($uid, 'zhiji_pmall_exchange_count', $n);
    if (1 === $n)       { Zhiji_Adapter::add_user_medal($uid, '首兑新人', '首次在积分商城兑换'); }
    if (10 === $n)      { Zhiji_Adapter::add_user_medal($uid, '兑换达人', '累计兑换 10 次'); }
}, 10, 1);

add_action('zhiji_bargain_success', function ($uid) {
    if (!function_exists('zib_add_user_medal')) { return; }
    Zhiji_Adapter::add_user_medal($uid, '谈判专家', '砍价成功');
}, 10, 1);

/**
 * 一次性授勋（2026-09-29 隐藏成就体系）：幂等，meta 旗标防重复授予 + notify 解锁提醒
 *
 * @param int    $uid        用户 ID
 * @param string $medal_name 勋章名（须与 user_medal_args 注册名一致）
 * @param string $flag_key   防重旗标 user_meta key
 * @param string $remark     授勋备注
 * @param string $event      通知事件名（空 = 不发通知）
 * @return bool 是否实际授予（false = 已有/环境不具备）
 */
function zhiji_medal_award_once($uid, $medal_name, $flag_key, $remark, $event = '')
{
    $uid = (int) $uid;
    if (!$uid || !function_exists('zib_add_user_medal')) {
        return false;
    }
    if (get_user_meta($uid, $flag_key, true)) {
        return false; // 已授予，幂等返回
    }
    update_user_meta($uid, $flag_key, 1);
    Zhiji_Adapter::add_user_medal($uid, $medal_name, $remark);
    if ($event && function_exists('zhiji_notify')) {
        zhiji_notify($event, array(
            'user_id'    => $uid,
            'title'      => '🎉 解锁隐藏成就',
            'content'    => '恭喜！你获得了隐藏勋章「' . $medal_name . '」：' . $remark,
            'dedupe_key' => 'medal_' . $flag_key,
        ));
    }
    do_action('zhiji_medal_unlocked', $uid, $medal_name);
    return true;
}

/**
 * 隐藏成就「夜猫子」：凌晨 0~5 点签到或评论触发
 */
add_action('comment_post', function ($comment_id, $approved) {
    if (1 !== (int) $approved) { return; }
    $c = get_comment($comment_id);
    if (!$c || empty($c->user_id)) { return; }
    if ((int) current_time('G') < 6) {
        zhiji_medal_award_once((int) $c->user_id, '夜猫子', 'zhiji_medal_owl', '凌晨的秘密行动', 'medal_owl');
    }
}, 20, 2);

add_action('user_checkined', function ($user_id) {
    if ((int) current_time('G') < 6) {
        zhiji_medal_award_once((int) $user_id, '夜猫子', 'zhiji_medal_owl', '凌晨的秘密行动', 'medal_owl');
    }
}, 20, 1);

function zhiji_ops_register_scene($id, array $args = array())
{
    $id = sanitize_key($id);
    if ('' === $id) {
        return;
    }
    if (empty($GLOBALS['__zhiji_ops_scenes']) || !is_array($GLOBALS['__zhiji_ops_scenes'])) {
        $GLOBALS['__zhiji_ops_scenes'] = array();
    }
    $GLOBALS['__zhiji_ops_scenes'][$id] = wp_parse_args($args, array(
        'title'         => $id,
        'desc'          => '',
        'priority'      => 50,
        'cap'           => null, // 默认 zhiji_ops_view（见 zhiji_ops_scene_cap()）
        'enabled'       => true,
        'clear_enabled' => null,
        'stats'         => null,
        'filters'       => array(),
        'columns'       => array(),
        'query'         => null,
        'actions'       => array(),
        'pre_actions'   => array(),
        'handle'        => null,
        // 详情抽屉扩展回调（2026-09-28 新增，见上方契约说明）
        'detail'        => null,
        'notice'        => '',
        'id'            => $id,
    ));

    // 归一化 cap：未声明（或声明为空）→ 查看（zhiji_ops_view）
    // ⚠️ 管理员经能力桥隐式拥有 view/manage，默认行为与升级前一致
    if (empty($GLOBALS['__zhiji_ops_scenes'][$id]['cap'])
        || !is_string($GLOBALS['__zhiji_ops_scenes'][$id]['cap'])) {
        $GLOBALS['__zhiji_ops_scenes'][$id]['cap'] = zhiji_ops_view_cap();
    }
}

/**
 * 全部已注册场景（按 priority 升序）
 *
 * @param bool $only_enabled 仅返回启用的场景
 * @return array
 */
function zhiji_ops_scenes($only_enabled = true)
{
    $scenes = (empty($GLOBALS['__zhiji_ops_scenes']) || !is_array($GLOBALS['__zhiji_ops_scenes']))
        ? array()
        : $GLOBALS['__zhiji_ops_scenes'];

    if ($only_enabled) {
        foreach ($scenes as $id => $scene) {
            $on = is_callable($scene['enabled']) ? (bool) call_user_func($scene['enabled']) : (bool) $scene['enabled'];
            if (!$on) {
                unset($scenes[$id]);
            }
        }
    }

    uasort($scenes, function ($a, $b) {
        return (int) $a['priority'] - (int) $b['priority'];
    });

    return $scenes;
}

/**
 * 取单个场景
 *
 * @param string $id
 * @return array|null
 */
function zhiji_ops_scene($id)
{
    $scenes = zhiji_ops_scenes(false);
    $id     = sanitize_key($id);
    return isset($scenes[$id]) ? $scenes[$id] : null;
}

/**
 * 运维页面总开关
 *
 * @return bool
 */
function zhiji_ops_enabled()
{
    return zhiji_is_enabled('ops_console_enabled', true);
}

/**
 * 是否允许对该场景执行"清除/重置/删除"
 *
 * 两层判定：全局开关 ops_console_clear_enabled && 场景未显式关闭
 *
 * @param string $id 场景 ID
 * @return bool
 */
/**
 * 当前用户能否对该场景执行**清除类操作**（渲染按钮与接口共用的同一收口）
 *
 * 2026-09-29 RBAC：在原「总闸 + 场景开关」之上叠加**当前用户能力**
 * （zhiji_ops_manage）—— 只读用户（仅 zhiji_ops_view）看不到也不会拿到操作入口。
 * 管理员经能力桥隐式拥有 manage，默认行为不变。
 *
 * @param string $id
 * @return bool
 */
function zhiji_ops_can_clear($id)
{
    // 当前用户必须具备"操作"能力（只读用户在此被拦下）
    if (!current_user_can(zhiji_ops_manage_cap())) {
        return false;
    }
    if (!zhiji_is_enabled('ops_console_clear_enabled', true)) {
        return false;
    }
    $scene = zhiji_ops_scene($id);
    if (!$scene) {
        return false;
    }
    return (false !== $scene['clear_enabled']);
}

/**
 * 运维页面 URL
 *
 * @param string $scene 场景 ID（空 = 总览）
 * @param array  $extra 附加查询参数
 * @return string
 */
function zhiji_ops_page_url($scene = '', array $extra = array())
{
    $page = '' === $scene ? 'zhiji-ops' : 'zhiji-ops-' . sanitize_key($scene);
    $args = array('page' => $page);
    if ($extra) {
        $args = array_merge($args, $extra);
    }
    return add_query_arg($args, admin_url('admin.php'));
}

/**
 * 写入一条运维操作审计
 *
 * 独立 option 存储（autoload=false），只保留最近 N 条，供运维页面展示。
 *
 * @param string $action 动作标识（如 reset / delete / purge_coupon）
 * @param string $detail 说明
 * @param string $scene  场景 ID
 * @return void
 */
/**
 * 写一条**运维审计日志**
 *
 * 2026-09-28 升级为「审计级」（对照行业标准 SOC 2 CC6.1/CC7.1 与常见后台审计规范）：
 * 原实现只记 `time/user/scene/action/detail`，缺了溯源与安全分析最关键的几项 ——
 * **事件 ID**、**UTC 时间**、**来源 IP**、**结果（成功/被拒/失败）**。
 * 现已补齐，且**被拒绝的操作也会留痕**（权限拒绝是安全事件的第一指标）。
 *
 * 字段含义（对齐 OCSF 风格的审计要素）：
 *   event_id  稳定事件 ID，可在工单/沟通里直接引用
 *   time      站点本地时间（展示用，与旧数据一致）
 *   time_utc  UTC ISO-8601（跨时区审计用，审计规范明确要求 UTC）
 *   user/uid  操作者（0 = system）
 *   ip/ua     来源 IP 与浏览器标识
 *   outcome   success | denied | error
 *   target    影响面描述（如 "ids=3"）
 *   changes   关键字段的 before→after 摘要（可空）
 *   reason    操作理由（可空；行业规范要求敏感操作可说明原因）
 *
 * ⚠️ 诚实说明存储局限：本日志存于 **option（autoload=false）**，
 *    是"可审计的记录"而非"防篡改存证" —— 具备 manage_options 的人仍可改它，
 *    且按 ZHIJI_OPS_ACTIVITY_MAX 做**环形截断**（只留最近 N 条）。
 *    若要满足"不可篡改 + 保留 12 个月"，需外挂独立存储（独立表/WORM/外部日志），
 *    当前规模（自有单站运维台）不引入这层复杂度。
 *
 * @param string $action 动作标识（如 reset / delete / purge_coupon）
 * @param string $detail 说明
 * @param string $scene  场景 ID
 * @param array  $ctx    可选上下文：
 *                       outcome(success|denied|error) · target · changes · reason
 *                       ⚠️ outcome 为 denied/error 时**务必**传入，否则会被记成成功
 * @return string 事件 ID（便于调用方回显/引用）
 */
function zhiji_ops_add_activity($action, $detail = '', $scene = '', array $ctx = array())
{
    $list = get_option(ZHIJI_OPS_ACTIVITY_KEY, array());
    if (!is_array($list)) {
        $list = array();
    }

    $user = wp_get_current_user();
    $outcome = isset($ctx['outcome']) ? sanitize_key($ctx['outcome']) : 'success';
    if (!in_array($outcome, array('success', 'denied', 'error'), true)) {
        $outcome = 'success';
    }

    // 来源 IP：只用服务器直连地址（REMOTE_ADDR）。
    // 不采信 X-Forwarded-For —— 它可被伪造，写进审计日志反而会误导溯源
    // （业务侧领券用 XFF 是另一回事，那是风控输入不是审计证据）。
    // ⚠️ CLI/cron 环境没有 REMOTE_ADDR → 记 'cli'，让审计条目**永远可判别调用渠道**
    //   （而不是留一个空字段让人怀疑是采集遗漏）。
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    if ('' === $ip) {
        $ip = 'cli';
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        // 保留真实代理链信息仅作补充字段，不覆盖主 IP
        $ip .= ' (via ' . sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP'])) . ')';
    }
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

    $entry = array(
        'event_id' => wp_generate_uuid4(),
        'time'     => current_time('mysql'),
        'time_utc' => gmdate('c'),
        'user'     => $user && $user->ID ? $user->user_login : 'system',
        'user_id'  => $user ? (int) $user->ID : 0,
        'ip'       => $ip,
        'ua'       => mb_substr($ua, 0, 180),
        'scene'    => sanitize_key($scene),
        'action'   => sanitize_key($action),
        'detail'   => (string) $detail,
        'outcome'  => $outcome,
        'target'   => isset($ctx['target']) ? (string) $ctx['target'] : '',
        'changes'  => (isset($ctx['changes']) && is_array($ctx['changes'])) ? $ctx['changes'] : array(),
        'reason'   => isset($ctx['reason']) ? (string) $ctx['reason'] : '',
    );
    array_unshift($list, $entry);

    if (count($list) > ZHIJI_OPS_ACTIVITY_MAX) {
        $list = array_slice($list, 0, ZHIJI_OPS_ACTIVITY_MAX);
    }

    update_option(ZHIJI_OPS_ACTIVITY_KEY, $list, false);
    return $entry['event_id'];
}

/**
 * 最近运维操作（审计日志读取）
 *
 * 2026-09-29 新增可选 $args 筛选（附录 Y：审计日志筛选能力，标准要求"没人看的日志不是控制"）：
 *   outcome => success|denied|error（只看该结果）
 *   user    => 操作者登录名精确匹配
 *   search  => 在 detail / target / reason 里模糊匹配
 *
 * @param int    $limit
 * @param string $scene 为空则全部场景
 * @param array  $args  可选筛选（见上）
 * @return array
 */
function zhiji_ops_activities($limit = 10, $scene = '', array $args = array())
{
    $list = get_option(ZHIJI_OPS_ACTIVITY_KEY, array());
    if (!is_array($list)) {
        return array();
    }
    if ('' !== (string) $scene) {
        $scene = sanitize_key($scene);
        $list  = array_values(array_filter($list, function ($row) use ($scene) {
            return isset($row['scene']) && $row['scene'] === $scene;
        }));
    }

    $outcome = isset($args['outcome']) ? sanitize_key($args['outcome']) : '';
    if ('' !== $outcome) {
        $list = array_values(array_filter($list, function ($row) use ($outcome) {
            // ⚠️ 旧条目（升级前写入）没有 outcome 字段 → **不参与**结果筛选
            //   （它们语义上是 success，但没有证据，不该在"只看拒绝"时混进来）
            return isset($row['outcome']) && $row['outcome'] === $outcome;
        }));
    }

    $user = isset($args['user']) ? sanitize_user((string) $args['user']) : '';
    if ('' !== $user) {
        $list = array_values(array_filter($list, function ($row) use ($user) {
            return isset($row['user']) && $row['user'] === $user;
        }));
    }

    $search = isset($args['search']) ? sanitize_text_field((string) $args['search']) : '';
    if ('' !== $search) {
        $needle = mb_strtolower($search);
        $list   = array_values(array_filter($list, function ($row) use ($needle) {
            $hay = mb_strtolower((
                (isset($row['detail']) ? $row['detail'] : '') . ' '
                . (isset($row['target']) ? $row['target'] : '') . ' '
                . (isset($row['reason']) ? $row['reason'] : '')
            ));
            return false !== mb_strpos($hay, $needle);
        }));
    }

    return array_slice($list, 0, max(1, (int) $limit));
}

/**
 * 运行健康自检（2026-09-29 新增，附录 Y.6 ⭐⭐：行业标准 System Health 视图）
 *
 * 全部为**只读、确定性**检查（不发测试邮件、不写任何状态），tone 三档：
 *   ok   绿  —— 正常
 *   warn 红  —— 需要关注（生产环境问题）
 *   info 灰  —— 仅供知悉（无对错）
 *
 * @return array array( array('label'=>…, 'value'=>…, 'tone'=>ok|warn|info, 'hint'=>…), … )
 */
function zhiji_ops_health_checks()
{
    global $wpdb;
    $checks = array();

    // 1) 运行环境（info：版本本身无对错；PHP 低于 7.4 才 warn —— 主题最低要求）
    $checks[] = array(
        'label' => __('PHP 版本', 'zhiji'),
        'value' => PHP_VERSION,
        'tone'  => version_compare(PHP_VERSION, '7.4', '>=') ? 'info' : 'warn',
        'hint'  => __('主题最低要求 7.4', 'zhiji'),
    );
    $checks[] = array(
        'label' => __('WordPress', 'zhiji'),
        'value' => get_bloginfo('version'),
        'tone'  => 'info',
        'hint'  => '',
    );

    // 2) HTTPS（生产应启用；本地 http 属预期 → info 而非 warn）
    $checks[] = array(
        'label' => __('HTTPS', 'zhiji'),
        'value' => is_ssl() ? __('已启用', 'zhiji') : __('未启用', 'zhiji'),
        'tone'  => is_ssl() ? 'ok' : 'info',
        'hint'  => is_ssl() ? '' : __('生产环境建议启用（站点已列入 SSL 改造计划）', 'zhiji'),
    );

    // 3) 调试模式（生产必须关：否则 PHP 报错会直接打到页面，信息泄露 + 破坏布局）
    $checks[] = array(
        'label' => __('WP_DEBUG', 'zhiji'),
        'value' => (defined('WP_DEBUG') && WP_DEBUG) ? __('开启', 'zhiji') : __('关闭', 'zhiji'),
        'tone'  => (defined('WP_DEBUG') && WP_DEBUG) ? 'warn' : 'ok',
        'hint'  => (defined('WP_DEBUG') && WP_DEBUG) ? __('生产环境必须关闭（见上线 Checklist §三 2.1）', 'zhiji') : '',
    );

    // 4) GD / imagewebp（WebP 模块的硬依赖；缺失则该模块静默不工作）
    $checks[] = array(
        'label' => __('GD / WebP', 'zhiji'),
        'value' => function_exists('imagewebp') ? __('可用', 'zhiji') : __('不可用', 'zhiji'),
        'tone'  => function_exists('imagewebp') ? 'ok' : 'warn',
        'hint'  => function_exists('imagewebp') ? '' : __('WebP 转换模块依赖，需主机启用 GD WebP 支持', 'zhiji'),
    );

    // 5) 定时任务：逾期未执行的事件组（0 = 调度健康；大量积压 = cron 没在跑）
    $ready = function_exists('wp_get_ready_cron_jobs') ? (array) wp_get_ready_cron_jobs() : array();
    $ready_n = 0;
    foreach ($ready as $hook_jobs) {
        $ready_n += count((array) $hook_jobs);
    }
    $checks[] = array(
        'label' => __('定时任务积压', 'zhiji'),
        'value' => 0 === $ready_n ? __('无逾期', 'zhiji') : sprintf(__('%d 个逾期', 'zhiji'), $ready_n),
        'tone'  => 0 === $ready_n ? 'ok' : ($ready_n > 50 ? 'warn' : 'info'),
        'hint'  => $ready_n > 50 ? __('疑似 WP-Cron 长期未运行，需排查访问触发或改系统 cron', 'zhiji') : '',
    );

    // 6) 上传目录可写（媒体/WebP 输出的前提）
    $up = wp_get_upload_dir();
    $writable = !empty($up['basedir']) && wp_is_writable($up['basedir']);
    $checks[] = array(
        'label' => __('上传目录', 'zhiji'),
        'value' => $writable ? __('可写', 'zhiji') : __('不可写', 'zhiji'),
        'tone'  => $writable ? 'ok' : 'warn',
        'hint'  => $writable ? '' : __('媒体上传与 WebP 输出会失败', 'zhiji'),
    );

    // 7) 审计日志水位（环形缓冲接近上限 = 老记录开始被挤掉）
    $audit = get_option(ZHIJI_OPS_ACTIVITY_KEY, array());
    $audit_n = is_array($audit) ? count($audit) : 0;
    $checks[] = array(
        'label' => __('审计日志水位', 'zhiji'),
        'value' => sprintf(__('%d / %d 条', 'zhiji'), $audit_n, (int) ZHIJI_OPS_ACTIVITY_MAX),
        'tone'  => $audit_n >= (int) ZHIJI_OPS_ACTIVITY_MAX ? 'warn' : 'info',
        'hint'  => $audit_n >= (int) ZHIJI_OPS_ACTIVITY_MAX ? __('已满，最早的操作记录开始被环形淘汰', 'zhiji') : '',
    );

    // 7.5) 应急模式（开启时全页置顶横幅 + 此处 warn，确保一眼可见）
    $checks[] = array(
        'label' => __('应急模式', 'zhiji'),
        'value' => zhiji_ops_kill_active() ? __('已开启（前台互动暂停）', 'zhiji') : __('未开启', 'zhiji'),
        'tone'  => zhiji_ops_kill_active() ? 'warn' : 'ok',
        'hint'  => zhiji_ops_kill_active() ? __('在总览页顶部可一键关闭', 'zhiji') : '',
    );

    // 8) 404 监控（表可能未建：模块未激活/父主题未建表 → 显示 — 而不是报错）
    $t404 = $wpdb->prefix . 'zhiji_404_logs';
    $n404 = $wpdb->get_var("SELECT COUNT(*) FROM {$t404}");
    $checks[] = array(
        'label' => __('404 监控记录', 'zhiji'),
        'value' => (null !== $n404) ? number_format_i18n((int) $n404) . ' 行' : '—',
        'tone'  => 'info',
        'hint'  => '',
    );

    // 9) 抽奖日志体积（option 存储、autoload 已关；过大影响每次读写）
    $lot = get_option('zhiji_lottery_log');
    $lot_kb = $lot ? round(strlen(wp_json_encode($lot)) / 1024, 1) : 0;
    $checks[] = array(
        'label' => __('抽奖日志体积', 'zhiji'),
        'value' => sprintf(__('%s KB（%d 条）', 'zhiji'), $lot_kb, is_array($lot) ? count($lot) : 0),
        'tone'  => $lot_kb > 2048 ? 'warn' : 'info',
        'hint'  => $lot_kb > 2048 ? __('建议裁剪历史记录，避免每次读写代价过大', 'zhiji') : '',
    );

    return $checks;
}

/**
 * 近 N 天领取记录趋势（2026-09-29 新增，附录 Y.6 ⭐⭐：标准要求"趋势而不只快照"）
 *
 * 数据源：ClaimLog（coupon_give + comment_fortune 两场景**合并口径**）。
 * 只读查询；按天聚合后**补零对齐**到连续 N 天（没有记录的日子也要有 0，否则图会错位）。
 *
 * @param int $days 天数（1..30，越界收敛）
 * @return array array( array('date'=>Y-m-d,'label'=>MM-DD,'total'=>n,'cleared'=>n,'active'=>n), … )，时间升序
 */
function zhiji_ops_trend($days = 7)
{
    global $wpdb;
    $days  = max(1, min(30, (int) $days));
    $table = $wpdb->prefix . 'zhiji_claim_log';

    $map = array();
    // 表可能未建（模块从未激活）→ 全零序列，渲染仍可出面板而不报错
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
        $since = date('Y-m-d 00:00:00', current_time('timestamp') - ($days - 1) * DAY_IN_SECONDS);
        $rows  = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created) AS d, COUNT(*) AS n,
                    SUM(CASE WHEN status = 'cleared' THEN 1 ELSE 0 END) AS cleared,
                    SUM(CASE WHEN status = 'active'  THEN 1 ELSE 0 END) AS active
             FROM {$table}
             WHERE created >= %s
             GROUP BY DATE(created)",
            $since
        ));
        foreach ((array) $rows as $r) {
            $map[(string) $r->d] = $r;
        }
    }

    $out = array();
    for ($i = $days - 1; $i >= 0; $i--) {
        $day = date('Y-m-d', current_time('timestamp') - $i * DAY_IN_SECONDS);
        $r   = isset($map[$day]) ? $map[$day] : null;
        $out[] = array(
            'date'    => $day,
            'label'   => substr($day, 5),
            'total'   => $r ? (int) $r->n : 0,
            'cleared' => $r ? (int) $r->cleared : 0,
            'active'  => $r ? (int) $r->active : 0,
        );
    }
    return $out;
}

/**
 * 应急开关是否开启（2026-09-29 新增，附录 Y 最后一个候选：kill switch）
 *
 * 效果：开启后**前台互动三入口**（邮箱领券 / 评论福袋领取 / 抽奖）立即暂停，
 * 返回「应急模式已开启」。用于突发情况下一键止血（如奖励配置出错、被刷）。
 *
 * ⚠️ 边界（刻意设计）：
 *  - **不触碰收款**（zibpay 支付/卡密不受任何影响 —— 红线）；
 *  - 不影响后台、运维台、记录查询与导出（运维照常取证）；
 *  - 每次开/关都会写入操作审计（操作者/IP/事件 ID）。
 *
 * @return bool
 */
function zhiji_ops_kill_active()
{
    return (bool) zhiji_is_enabled('ops_kill_switch', false);
}

/**
 * 操作标识 → 中文名（页面展示用）
 *
 * @param string $action
 * @return string
 */
function zhiji_ops_action_label($action)
{
    $map = array(
        'reset'        => '重置（恢复可领取）',
        'delete'       => '删除记录',
        'purge_coupon' => '作废关联优惠码',
        'clear_all'    => '批量清除',
        'release_email' => '按邮箱放行',
        'purge_email'  => '按邮箱清理记录',
        'fortune_resend' => '补发福袋弹窗',
        'fortune_consumed' => '标记福袋已领取',
        'export'       => '导出 CSV',
        // 2026-09-29 审计合规化新增（附录 Y）：导出审计日志 / API 层动作
        'export_audit' => '导出审计日志（CSV）',
        'ops_query'    => '查询接口（被拒绝）',
        'ops_clear'    => '清除接口',
        'kill_switch'  => '应急模式开关',
    );
    $action = sanitize_key($action);
    return isset($map[$action]) ? $map[$action] : $action;
}
