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
    // 2026-09-29：移除「AJAX 统一网关」后台分节 —— 网关常驻启用（见上方 always_on），
    // 该分节只有一条公告、没有任何可配置项，属于「空分类」（用户反馈：左侧菜单出现
    // 无开关项的空分类）。公告语义已并入上方文件头注释。

/* ============================================================
 * Api 契约实现（P5，5 个契约的最后一个）
 * ------------------------------------------------------------
 * 契约签名与 core/ApiRegistry 的实际能力已对齐（这里同样按现实而非按原始设计稿）：
 *   · register($name, $handler, $login, $group)
 *     实际注册表第三参是 **$public**（是否允许游客），第四参是 $nonce_action，
 *     没有 $group。→ 契约的 $group 映射为 nonce 分组前缀，$login 取反映射到 $public。
 *   · has($name) 与注册表的 zhiji_api_has() 同义。
 *   · 额外补 handlers() —— 「有哪些端点」是排障与自检的刚需，
 *     原始契约没考虑，实际 ops/ajax 审计工具都要用。
 * ============================================================ */

if ( ! class_exists( 'Zhiji_Api_ApiGateway' ) ) :

	/**
	 * 统一 AJAX 网关（实现 Zhiji_Contract_Api）
	 */
	class Zhiji_Api_ApiGateway implements Zhiji_Contract_Api {

		/**
		 * 注册端点
		 *
		 * @param string   $name    端点名
		 * @param callable $handler 处理函数
		 * @param bool     $login   true=要求登录（契约语义）→ 内部取反成 public
		 * @param string   $group   分组：作为 nonce 动作前缀，便于按组批量跳过校验
		 * @return void
		 */
		public function register( $name, $handler, $login = true, $group = '' ) {
			$nonce = ( '' === (string) $group )
				? 'zhiji_nonce'
				: 'zhiji_nonce_' . sanitize_key( $group );
			// ⚠️ 契约的 $login 与注册表的 $public 语义相反，这里显式取反；
			//    写反会导致「要求登录」变成「允许游客」—— 安全后果，故单独注释。
			zhiji_api_register( $name, $handler, ! $login, $nonce );
		}

		/**
		 * 端点是否存在
		 *
		 * @param string $name
		 * @return bool
		 */
		public function has( $name ) {
			return (bool) zhiji_api_has( $name );
		}

		/**
		 * 全部已注册端点（排障 / 自检 / 审计用）
		 *
		 * @return array<string,array>
		 */
		public function handlers() {
			return (array) zhiji_api_get_handlers();
		}

		/**
		 * 端点清单的可读摘要（后台展示用）
		 *
		 * @return array<int,string>
		 */
		public function summary() {
			$out = array();
			foreach ( $this->handlers() as $name => $h ) {
				$out[] = sprintf(
					'%s（%s%s）',
					$name,
					! empty( $h['public'] ) ? '游客可访问' : '需登录',
					'' === (string) $h['nonce'] ? ' / 自校验 nonce' : ''
				);
			}
			sort( $out );
			return $out;
		}
	}

endif;

/**
 * 契约工厂：供 ContractRegistry 解析（工厂名规则 zhiji_contract_implementor_{模块key}）
 *
 * @return Zhiji_Contract_Api
 */
function zhiji_contract_implementor_api_gateway() {
	static $impl = null;
	if ( null === $impl ) {
		$impl = new Zhiji_Api_ApiGateway();
	}
	return $impl;
}

