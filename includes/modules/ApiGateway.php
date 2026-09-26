<?php
/**
 * @module  ApiGateway
 * @desc    AJAX 统一网关：单端点 zhiji_api + 处理器注册表 + 参数校验辅助
 * @option  api_gateway_enabled  网关总开关（默认开）
 * @hook    wp_ajax(_nopriv)_zhiji_api · 统一端点（nonce 'zhiji_nonce' 校验）
 * @api     zhiji_api_register($name, $handler, $public)  注册处理器
 *          zhiji_api_digits/enum/str($_REQUEST, $key, ...)  白名单式取参
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/ApiGateway.php`（基础设施模块，默认启用）
 */

defined('ABSPATH') || exit;

/* ============================================================
 * 模块自注册
 * ============================================================ */
Zhiji_Registry::register_module('api_gateway', array(
    'title'    => 'AJAX 统一网关',
    'parent'   => 'zhiji_basic',
    'priority' => 5,
    'option'   => 'api_gateway_enabled',
));

/* ============================================================
 * 处理器注册表
 *
 * ⚠️ 2026-09-26：注册表与转发入口已迁至 **core/ApiRegistry.php**
 *    （基础设施层，保证任何模块都能安全调用，不受加载顺序影响）。
 *    本文件只保留：参数校验辅助 + 网关端点。
 * ============================================================ */

/* ============================================================
 * 参数校验辅助（白名单式，拒绝一切未预期的输入形态）
 * ============================================================ */

/**
 * 取纯数字参数
 *
 * @param array  $request
 * @param string $key
 * @param int    $default
 * @return int
 */
function zhiji_api_digits(array $request, $key, $default = 0)
{
    $v = isset($request[$key]) ? (string) $request[$key] : '';
    if ('' === $v || !preg_match('/^\d+$/', $v)) {
        return $default;
    }
    return (int) $v;
}

/**
 * 取枚举参数（仅允许白名单值）
 *
 * @param array  $request
 * @param string $key
 * @param array  $allow
 * @param mixed  $default
 * @return mixed
 */
function zhiji_api_enum(array $request, $key, array $allow, $default = null)
{
    $v = isset($request[$key]) ? (string) $request[$key] : '';
    if (in_array($v, $allow, true)) {
        return $v;
    }
    return $default;
}

/**
 * 取字符串参数（截断到上限）
 *
 * @param array  $request
 * @param string $key
 * @param int    $max
 * @param string $default
 * @return string
 */
function zhiji_api_str(array $request, $key, $max = 500, $default = '')
{
    $v = isset($request[$key]) ? (string) $request[$key] : '';
    $v = trim(wp_unslash($v));
    if ('' === $v) {
        return $default;
    }
    if (mb_strlen($v, 'UTF-8') > $max) {
        $v = mb_substr($v, 0, $max, 'UTF-8');
    }
    return $v;
}

/* ============================================================
 * 网关端点
 * ============================================================ */
add_action('wp_ajax_zhiji_api', 'zhiji_api_gateway');
add_action('wp_ajax_nopriv_zhiji_api', 'zhiji_api_gateway');

function zhiji_api_gateway()
{
    // 网关总开关（默认开）
    if (!zhiji_is_enabled('api_gateway_enabled', true)) {
        wp_send_json_error(array('msg' => 'API 网关未启用'), 403);
    }
    $name = zhiji_api_str($_REQUEST, 'api', 64);
    if ('' === $name) {
        wp_send_json_error(array('msg' => '缺少 api 参数'), 400);
    }
    $handlers = zhiji_api_get_handlers();
    if (!isset($handlers[$name])) {
        wp_send_json_error(array('msg' => '未知接口'), 404);
    }
    $handler = $handlers[$name];
    // nonce 校验（2026-09-26）：
    //  · 传 'zhiji_nonce'（默认）→ 网关统一校验；
    //  · 传 ''（空串）→ 跳过网关校验，由处理器自行校验（存量端点迁移时使用，
    //    其内部已有 check_ajax_referer，行为与迁移前完全一致）。
    $nonce_action = isset($handler['nonce']) ? (string) $handler['nonce'] : 'zhiji_nonce';
    if ('' !== $nonce_action) {
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, $nonce_action)) {
            wp_send_json_error(array('msg' => '安全校验失败，请刷新页面重试'), 403);
        }
    }
    // 登录要求
    if (!$handler['public'] && !is_user_logged_in()) {
        wp_send_json_error(array('msg' => '请先登录'), 401);
    }
    call_user_func($handler['cb'], $_REQUEST);
    // 处理器自行输出并 exit；未输出的按服务器错误兜底
    wp_send_json_error(array('msg' => '接口无响应'), 500);
}

/* ============================================================
 * 后台字段
 * ============================================================ */
add_action('after_setup_theme', function () {
    Zhiji_Registry::csf_section_for('api_gateway', array(
        array(
            'id'      => 'api_gateway_enabled',
            'type'    => 'switcher',
            'title'   => '启用 AJAX 统一网关',
            'desc'    => '单端点 + nonce 统一校验。关闭后依赖网关的前台交互将不可用。',
            'default' => true,
        ),
    ));
}, 20);
