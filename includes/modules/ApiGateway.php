<?php
/**
 * @module  ApiGateway
 * @desc    AJAX 统一网关：单端点 zhiji_api + 处理器注册表 + 参数校验辅助
 * @option  （无常开/可配置开关）—— 2026-09-28 起 AJAX 网关**常驻启用**，
 *          原 `api_gateway_enabled` 开关已按「开关评估 A1」移除（见后台分节内的说明）
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
    'title'     => 'AJAX 统一网关',
    'parent'    => 'zhiji_basic',
    'priority'  => 5,
    // 保留 option 名义值仅为兼容历史引用；always_on 才是运行期依据（开关已移除）
    'option'    => 'api_gateway_enabled',
    'always_on' => true,
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
    // 2026-09-28：原「网关总开关」已移除（评估报告 A1）。
    // 理由：网关是**基础设施**而非功能偏好 —— 关闭它会让依赖网关的前台交互全部失效
    // （退出挽留弹窗领券、福袋查询、消息角标刷新…），不存在"想关掉网关"的正常场景；
    // 留一个能不启用它的开关，只会带来"误关导致前台半瘫"的风险。
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
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('api_gateway', array(
        zhiji_notice(
            __('<strong>AJAX 统一网关已常驻启用</strong>（不再是可配置项）。'
                . '全站 AJAX 交互都走这一个端点并统一做 nonce 校验；'
                . '关闭它会让依赖网关的前台功能（领券、福袋查询、消息角标刷新等）全部失效，'
                . '因此该开关已于 2026-09-28 移除。', 'zhiji')
        ),
    ), 20);
