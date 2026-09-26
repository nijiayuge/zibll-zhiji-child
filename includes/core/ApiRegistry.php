<?php
/**
 * AJAX 处理器注册表（基础设施层）
 *
 * @module  ApiRegistry
 * @desc    网关处理器的注册与转发基础设施。放在 core 层而非模块内，
 *          以保证任何业务模块都能安全调用（不受模块加载顺序影响）。
 * @since   2.0.0
 * @see     includes/modules/ApiGateway.php  端点与参数校验辅助
 *
 * 设计（2026-09-26，配置统一化探查报告 P2-⑥）：
 *   · 业务模块把 AJAX 处理器注册进**唯一的网关**，处理逻辑只有一份；
 *   · 存量模块的原有 `wp_ajax_<name>` 端点保留为**转发入口**
 *     （zhiji_api_legacy_forward），前端调用点无需改动；
 *   · nonce 策略：新接口用网关统一校验（默认 'zhiji_nonce'）；
 *     存量迁移接口传 '' → 跳过网关校验，由处理器自身 check_ajax_referer 负责，
 *     与迁移前行为完全一致。
 */

defined('ABSPATH') || exit;

/**
 * 注册一个网关处理器
 *
 * @param string   $name         接口名（即 api 参数值，也是旧端点 action 名）
 * @param callable $handler      处理器函数名（入参 $_REQUEST 数组，自行输出 JSON）
 * @param bool     $public       是否允许游客访问（false 时网关会拦截未登录请求）
 * @param string   $nonce_action nonce 动作名；传 '' 表示跳过网关校验（由处理器自校验）
 * @return void
 */
function zhiji_api_register($name, $handler, $public = true, $nonce_action = 'zhiji_nonce')
{
    if (empty($GLOBALS['__zhiji_api_handlers']) || !is_array($GLOBALS['__zhiji_api_handlers'])) {
        $GLOBALS['__zhiji_api_handlers'] = array();
    }
    $GLOBALS['__zhiji_api_handlers'][(string) $name] = array(
        'cb'     => $handler,
        'public' => (bool) $public,
        'nonce'  => (string) $nonce_action,
    );
}

/**
 * 取全部已注册处理器
 *
 * @return array
 */
function zhiji_api_get_handlers()
{
    if (empty($GLOBALS['__zhiji_api_handlers']) || !is_array($GLOBALS['__zhiji_api_handlers'])) {
        $GLOBALS['__zhiji_api_handlers'] = array();
    }
    return $GLOBALS['__zhiji_api_handlers'];
}

/**
 * 旧端点转发入口
 *
 * 把存量 `wp_ajax_<name>` 端点的请求转发给网关的同一处理器，
 * 使处理逻辑只有一份，而前端调用点无需改动。
 *
 * 用法（业务模块内）：
 *   zhiji_api_register('zhiji_lottery_draw', 'zhiji_lottery_ajax_draw', false, '');
 *   add_action('wp_ajax_zhiji_lottery_draw', 'zhiji_api_legacy_forward');
 *
 * @return void
 */
function zhiji_api_legacy_forward()
{
    $action = current_filter();                        // 形如 wp_ajax_zhiji_lottery_draw
    $name   = preg_replace('/^wp_ajax(_nopriv)?_/', '', (string) $action);
    if ('' === $name) {
        wp_send_json_error(array('msg' => '无效的接口名'), 400);
    }
    $_REQUEST['api'] = $name;
    if (function_exists('zhiji_api_gateway')) {
        zhiji_api_gateway();
    } else {
        wp_send_json_error(array('msg' => 'API 网关不可用'), 500);
    }
}

/**
 * 查询是否已注册某接口（调试 / 自查用）
 *
 * @param string $name
 * @return bool
 */
function zhiji_api_has($name)
{
    $handlers = zhiji_api_get_handlers();
    return isset($handlers[(string) $name]);
}
