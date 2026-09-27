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
 * 注册一个运维场景
 *
 * $args 契约：
 *   title         string    场景名（菜单/卡片显示）
 *   desc          string    一句话说明（页面顶部）
 *   priority      int       排序（小在前）
 *   cap           string    所需权限，默认 manage_options
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
 *   notice        string    页面顶部提示（可含 HTML 白名单外的纯文本）
 *
 * @param string $id   场景 ID（sanitize_key 后使用，同时作为页面 slug 一部分）
 * @param array  $args
 * @return void
 */
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
        'cap'           => 'manage_options',
        'enabled'       => true,
        'clear_enabled' => null,
        'stats'         => null,
        'filters'       => array(),
        'columns'       => array(),
        'query'         => null,
        'actions'       => array(),
        'pre_actions'   => array(),
        'handle'        => null,
        'notice'        => '',
        'id'            => $id,
    ));
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
function zhiji_ops_can_clear($id)
{
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
function zhiji_ops_add_activity($action, $detail = '', $scene = '')
{
    $list = get_option(ZHIJI_OPS_ACTIVITY_KEY, array());
    if (!is_array($list)) {
        $list = array();
    }

    $user = wp_get_current_user();
    array_unshift($list, array(
        'time'   => current_time('mysql'),
        'user'   => $user && $user->ID ? $user->user_login : 'system',
        'user_id' => $user ? (int) $user->ID : 0,
        'scene'  => sanitize_key($scene),
        'action' => sanitize_key($action),
        'detail' => (string) $detail,
    ));

    if (count($list) > ZHIJI_OPS_ACTIVITY_MAX) {
        $list = array_slice($list, 0, ZHIJI_OPS_ACTIVITY_MAX);
    }

    update_option(ZHIJI_OPS_ACTIVITY_KEY, $list, false);
}

/**
 * 最近运维操作
 *
 * @param int    $limit
 * @param string $scene 为空则全部场景
 * @return array
 */
function zhiji_ops_activities($limit = 10, $scene = '')
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
    return array_slice($list, 0, max(1, (int) $limit));
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
    );
    $action = sanitize_key($action);
    return isset($map[$action]) ? $map[$action] : $action;
}
