<?php
/**
 * @module  Danmu
 * @desc    全站弹幕（含 Weiyu/LiveFeed 联动推送）
 * @option  danmu_enabled  总开关
 * @hook    wp_ajax(_nopriv)_zhiji_danmu_* · 弹幕读写
 * @hook    API zhiji_danmu_push
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/Danmu.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('danmu', array(
    'title'    => '弹幕',
    'parent'   => 'zhiji_beautify',
    'priority' => 70,
    'option'   => 'danmu_enabled',
));



defined( 'ABSPATH' ) || exit;

/* ============================================================
 * 后台 CSF 设置：美化效果 → 弹幕
 * ============================================================ */
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('danmu', array(
			array(
				'id'      => 'danmu_enabled',
				'type'    => 'switcher',
				'title'   => '启用弹幕',
				'default' => false,
				'desc'    => '开启后页面右下角实时展示网站动态弹幕。',
			),
			array(
				'id'         => 'danmu_limit',
				'type'       => 'number',
				'title'      => '每次拉取条数',
				'desc'       => __( '每次拉取并展示的弹幕条数。', 'zhiji' ),
				'default'    => '10',
				'dependency' => array( 'danmu_enabled', '==', '1' ),
			),
			array(
				'id'         => 'danmu_poll',
				'type'       => 'number',
				'title'      => '轮询刷新间隔（秒）',
				'desc'       => __( '前端轮询获取新弹幕的间隔（秒）。越小越实时，但请求更频繁。', 'zhiji' ),
				'default'    => '30',
				'dependency' => array( 'danmu_enabled', '==', '1' ),
			),
			array(
				'id'         => 'danmu_mobile',
				'type'       => 'switcher',
				'title'      => '移动端显示',
				'desc'       => __( '是否在移动端也显示弹幕。', 'zhiji' ),
				'default'    => false,
				'dependency' => array( 'danmu_enabled', '==', '1' ),
			),
			array(
				'id'         => 'danmu_event_lottery',
				'type'       => 'switcher',
				'title'      => '事件源：抽奖中奖',
				'desc'       => __( '将「抽奖中奖」事件同步为弹幕。', 'zhiji' ),
				'default'    => true,
				'dependency' => array( 'danmu_enabled', '==', '1' ),
			),
			array(
				'id'         => 'danmu_event_pay',
				'type'       => 'switcher',
				'title'      => '事件源：支付购买',
				'desc'       => __( '将「支付购买」事件同步为弹幕。', 'zhiji' ),
				'default'    => true,
				'dependency' => array( 'danmu_enabled', '==', '1' ),
			),
			array(
				'id'         => 'danmu_event_comment',
				'type'       => 'switcher',
				'title'      => '事件源：新评论',
				'desc'       => __( '将「新评论」事件同步为弹幕。', 'zhiji' ),
				'default'    => true,
				'dependency' => array( 'danmu_enabled', '==', '1' ),
			),
			array(
				'id'         => 'danmu_event_sign',
				'type'       => 'switcher',
				'title'      => '事件源：新用户注册',
				'desc'       => __( '将「新用户注册」事件同步为弹幕。', 'zhiji' ),
				'default'    => true,
				'dependency' => array( 'danmu_enabled', '==', '1' ),
			),
		), 20);


/* ===================== 常量 ===================== */

define( 'ZHIJI_DANMU_POOL_KEY', 'zhiji_danmu_pool' ); // 事件池 transient key (v1.8.6 option->transient)
define( 'ZHIJI_DANMU_POOL_TTL', 2 * DAY_IN_SECONDS );  // 事件池有效时长（48h）
define( 'ZHIJI_DANMU_POOL_MAX', 50 );                 // 事件池最大保留条数

/* ===================== 事件池 ===================== */

/**
 * 读取事件池（transient 优先；兼容迁移旧版 option 存储）。
 *
 * @return array
 */
function zhiji_danmu_pool_read() {
	$pool = get_transient( ZHIJI_DANMU_POOL_KEY );
	if ( false === $pool ) {
		// 迁移：旧版 option 存储（一次性）
		$legacy = get_option( ZHIJI_DANMU_POOL_KEY, false );
		if ( is_array( $legacy ) ) {
			set_transient( ZHIJI_DANMU_POOL_KEY, $legacy, ZHIJI_DANMU_POOL_TTL );
			delete_option( ZHIJI_DANMU_POOL_KEY );
			$pool = $legacy;
		}
	}
	return is_array( $pool ) ? $pool : array();
}

/**
 * 写入一条弹幕事件到事件池。
 *
 * @param string $type    事件类型（lottery/pay/comment/sign/notice ...）
 * @param int    $user_id 用户 ID（0 = 系统/公告）
 * @param string $content 弹幕内容文本
 * @param string $link    点击跳转链接（可选）
 * @param array  $meta    额外元数据（可选）
 */
function zhiji_danmu_push( $type, $user_id, $content, $link = '', $meta = array() ) {
	if ( ! $type || ! $content ) {
		return;
	}

	$pool = zhiji_danmu_pool_read();
	if ( ! is_array( $pool ) ) {
		$pool = array();
	}

	// 去重：同一用户 + 同一类型 + 同一内容 60 秒内不重复（防止连点刷屏）
	$now    = time();
	$sig    = md5( $type . '|' . $user_id . '|' . $content );
	$recent = wp_list_filter( $pool, array( 'sig' => $sig ) );
	if ( ! empty( $recent ) ) {
		$last = reset( $recent );
		if ( $now - (int) $last['time'] < 60 ) {
			return;
		}
	}

	$item = array(
		'type'    => sanitize_key( $type ),
		'user_id' => (int) $user_id,
		'content' => wp_kses_post( $content ),
		'link'    => esc_url_raw( $link ),
		'time'    => $now,
		'sig'     => $sig,
		'meta'    => $meta,
	);

	array_unshift( $pool, $item );
	$pool = array_slice( $pool, 0, ZHIJI_DANMU_POOL_MAX );

	set_transient( ZHIJI_DANMU_POOL_KEY, $pool, ZHIJI_DANMU_POOL_TTL );
}

/**
 * 从事件池读取弹幕（按时间倒序，仅保留 24 小时内）。
 *
 * @param int   $limit 条数
 * @param array $types 允许的事件类型（空数组 = 全部）
 * @return array
 */
function zhiji_danmu_get( $limit = 10, $types = array() ) {
	$pool = zhiji_danmu_pool_read();
	if ( ! is_array( $pool ) || empty( $pool ) ) {
		return array();
	}

	if ( ! empty( $types ) ) {
		$pool = wp_list_filter( $pool, array( 'type' => $types ), 'OR' );
	}

	$cutoff = time() - 86400; // 24 小时
	$pool   = array_filter(
		$pool,
		function ( $item ) use ( $cutoff ) {
			return isset( $item['time'] ) && (int) $item['time'] > $cutoff;
		}
	);

	return array_slice( array_values( $pool ), 0, (int) $limit );
}

/**
 * 清空事件池（调试/后台用）。
 */
function zhiji_danmu_clear() {
	delete_transient( ZHIJI_DANMU_POOL_KEY );
	delete_option( ZHIJI_DANMU_POOL_KEY ); // 兼容清理旧版
}

/**
 * 弹幕事件类型配置（标签 / 颜色 / 图标），前端据此着色。
 *
 * @return array
 */
function zhiji_danmu_type_config() {
	return array(
		'pay'      => array( 'label' => '购买', 'color' => '#fc6976', 'icon' => '🛒' ),
		'comment'  => array( 'label' => '评论', 'color' => '#8ed1fc', 'icon' => '💬' ),
		'sign'     => array( 'label' => '新用户', 'color' => '#7bdcb5', 'icon' => '✅' ),
		'lottery'  => array( 'label' => '中奖', 'color' => '#f78da7', 'icon' => '🎉' ),
		'download' => array( 'label' => '下载', 'color' => '#3b82f6', 'icon' => '📥' ),
		'bargain'  => array( 'label' => '砍价', 'color' => '#f2760b', 'icon' => '🔪' ),
		'notice'   => array( 'label' => '公告', 'color' => '#6366f1', 'icon' => '📢' ),
	);
}

/* ===================== 事件源（联动） ===================== */

/**
 * 事件源 1（核心）：订阅抽奖中奖事件广播。
 *
 * 抽奖模块在用户中奖后调用 zhiji_event_fire('lottery_win', $data)，
 * 这里将中奖信息写入弹幕事件池，实现「抽奖中奖即时上墙」。
 *
 * @param array $data {uid,name,type,value,url}
 */
function zhiji_danmu_on_lottery_win( $data ) {
	if ( ! zhiji_get_option( 'danmu_event_lottery', 1 ) ) {
		return;
	}
	$uid     = isset( $data['uid'] ) ? (int) $data['uid'] : 0;
	$name    = isset( $data['name'] ) ? (string) $data['name'] : '大奖';
	$content = '抽中了「' . $name . '」';
	$link    = isset( $data['url'] ) ? $data['url'] : home_url( '/' );
	zhiji_danmu_push( 'lottery', $uid, $content, $link );
}
add_action( 'zhiji_event_lottery_win', 'zhiji_danmu_on_lottery_win', 10, 1 );

/**
 * 事件源 5（砍价联动）：订阅砍价成功事件广播。
 *
 * 砍价模块在砍到底价发放优惠码后调用 zhiji_event_fire('bargain_success', $data)，
 * 这里将砍价成功信息写入弹幕事件池，实现「砍价成功即时上墙」。
 *
 * @param array $data {uid,name,type,value,content,url}
 */
function zhiji_danmu_on_bargain_success( $data ) {
	if ( ! zhiji_get_option( 'danmu_event_bargain', 1 ) ) {
		return;
	}
	$content = isset( $data['content'] ) ? (string) $data['content'] : '砍价成功，获得砍后价优惠码';
	$link    = isset( $data['url'] ) ? $data['url'] : home_url( '/' );
	$uid     = isset( $data['uid'] ) ? (int) $data['uid'] : 0;
	zhiji_danmu_push( 'bargain', $uid, $content, $link );
}
add_action( 'zhiji_event_bargain_success', 'zhiji_danmu_on_bargain_success', 10, 1 );

/**
 * 事件源 3：支付购买成功（父主题 zibpay payment_order_success 钩子）。
 *
 * @param object $order zibpay 订单对象
 */
function zhiji_danmu_on_payment_success( $order ) {
	if ( ! zhiji_get_option( 'danmu_event_pay', 1 ) ) {
		return;
	}
	if ( ! is_object( $order ) || empty( $order->user_id ) ) {
		return;
	}
	$uid     = (int) $order->user_id;
	$price   = isset( $order->pay_price ) ? (float) $order->pay_price : 0;
	$post_id = isset( $order->post_id ) ? (int) $order->post_id : 0;
	$title   = $post_id ? get_the_title( $post_id ) : '';
	$content = $price > 0 ? '支付 ' . $price . ' 元购买了' . ( $title ? '《' . wp_trim_words( $title, 8 ) . '》' : '商品' ) : '购买了商品';
	$link    = $post_id ? get_permalink( $post_id ) : '';
	zhiji_danmu_push( 'pay', $uid, $content, $link );
}
add_action( 'payment_order_success', 'zhiji_danmu_on_payment_success', 20 );

/**
 * 事件源 3：新评论通过审核（comment_post 钩子）。
 */
function zhiji_danmu_on_comment( $comment_id, $comment_approved ) {
	if ( ! zhiji_get_option( 'danmu_event_comment', 1 ) ) {
		return;
	}
	if ( 1 !== (int) $comment_approved ) {
		return;
	}
	$comment = get_comment( $comment_id );
	if ( ! $comment || 'comment' !== $comment->comment_type ) {
		return;
	}
	$post    = get_post( $comment->comment_post_ID );
	$user_id = $comment->user_id ? (int) $comment->user_id : 0;
	$content = '评论了《' . wp_trim_words( $post->post_title, 10 ) . '》';
	$link    = get_comment_link( $comment_id );
	zhiji_danmu_push( 'comment', $user_id, $content, $link );
}
add_action( 'comment_post', 'zhiji_danmu_on_comment', 20, 2 );

/**
 * 事件源 4：新用户注册（user_register 钩子）。
 */
function zhiji_danmu_on_user_register( $user_id ) {
	if ( ! zhiji_get_option( 'danmu_event_sign', 1 ) ) {
		return;
	}
	zhiji_danmu_push( 'sign', (int) $user_id, '新用户加入了知集', home_url( '/' ) );
}
add_action( 'user_register', 'zhiji_danmu_on_user_register', 20 );

/**
 * 事件源 5：管理员发送公告弹幕（后台 AJAX）。
 */
function zhiji_danmu_ajax_notice() {
	check_ajax_referer( 'zhiji_danmu_notice', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'no_permission' );
	}
	$content = isset( $_POST['content'] ) ? sanitize_text_field( wp_unslash( $_POST['content'] ) ) : '';
	if ( ! $content ) {
		wp_send_json_error( 'empty_content' );
	}
	$link = isset( $_POST['link'] ) ? esc_url_raw( wp_unslash( $_POST['link'] ) ) : '';
	zhiji_danmu_push( 'notice', 0, $content, $link );
	wp_send_json_success( array( 'pushed' => true ) );
}
// 2026-09-26：注册到网关（P2-⑥），旧端点保留为转发入口
zhiji_api_register( 'zhiji_danmu_notice', 'zhiji_danmu_ajax_notice', false, '' );

/**
 * AJAX 前端拉取弹幕数据（JSON）。
 *
 * 返回合并类型配置 + 事件池数据的结构化数组，前端直接渲染。
 */
function zhiji_danmu_fetch() {
	$limit = max( 1, (int) zhiji_get_option( 'danmu_limit', 10 ) );
	$limit = min( 30, $limit );

	$items    = zhiji_danmu_get( $limit );
	$config   = zhiji_danmu_type_config();
	$return   = array();
	$base_uri = get_avatar_url( 0 ); // 兜底头像（公告）

	foreach ( $items as $ev ) {
		$user_id = isset( $ev['user_id'] ) ? (int) $ev['user_id'] : 0;
		$type    = isset( $ev['type'] ) ? $ev['type'] : 'notice';
		$cfg     = isset( $config[ $type ] ) ? $config[ $type ] : array( 'label' => '', 'color' => '', 'icon' => '' );

		// 用户信息：登录用户取头像URL与昵称；系统公告用统一风格
		if ( $user_id > 0 ) {
			$avatar_url = Zhiji_Adapter::avatar_url( $user_id, 64 );
			// 处理协议相对URL（//example.com/...）
			if ( $avatar_url && strpos( $avatar_url, '//' ) === 0 ) {
				$avatar_url = is_ssl() ? 'https:' . $avatar_url : 'http:' . $avatar_url;
			}
			if ( ! $avatar_url ) {
				$avatar_url = $base_uri;
			}
			$name   = get_user_by( 'id', $user_id );
			$name   = $name ? ( $name->display_name ? $name->display_name : $name->user_login ) : '用户';
			$link   = get_author_posts_url( $user_id );
		} else {
			$avatar_url = $base_uri;
			$name   = '知集公告';
			$link   = isset( $ev['link'] ) ? $ev['link'] : '';
		}

		$return[] = array(
			'type'       => $type,
			'type_label' => $cfg['label'],
			'type_color' => $cfg['color'],
			'type_icon'  => $cfg['icon'],
			'link'       => $link,
			'time'       => isset( $ev['time'] ) ? (int) $ev['time'] : time(),
			'avatar'     => esc_url( $avatar_url ), // 纯头像URL，前端直接使用
			'name'       => $name,
			'content'    => isset( $ev['content'] ) ? $ev['content'] : '',
		);
	}

	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( $return );
	exit;
}
// 2026-09-26：注册到网关（P2-⑥），旧端点保留为转发入口
zhiji_api_register( 'zhiji_danmu_fetch', 'zhiji_danmu_fetch', true, '' );
add_action( 'wp_ajax_nopriv_zhiji_danmu_fetch', 'zhiji_danmu_fetch' );

/* ===================== 前端资源（head 内联） ===================== */

/**
 * 前端 CSS + JS（wp_enqueue_scripts 内联输出）。
 *
 * 与抽奖模块同方案：本站点 wp_footer 输出不稳定，head 内联必然生效；
 * JS 包一层 jQuery 就绪轮询，避免先于父主题 jQuery 执行。
 */
function zhiji_danmu_enqueue() {
	if ( is_admin() ) {
		return;
	}
	if ( ! zhiji_get_option( 'danmu_enabled', 0 ) ) {
		return;
	}
	// 移动端不展示（弹幕在右下角浮层，触屏体验差），可后台关闭此限制
	if ( wp_is_mobile() && ! zhiji_get_option( 'danmu_mobile', 0 ) ) {
		return;
	}

	$danmu_css = <<<'ZHIJI_DANMU_CSS'
/* 知集 · 弹幕容器（右下角固定浮层） */
#zhiji-danmu {
	display: none !important; /* v1.9.8: 旧固定位置弹幕已废弃，统一走 NotificationCenter 滚动弹幕 */
}
#zhiji-danmu li {
	display: flex;
	align-items: center;
	white-space: nowrap;
	opacity: 0;
	border-radius: 22px;
	color: #fff;
	padding: 4px 12px 4px 4px;
	font-size: 13px;
	height: 30px;
	line-height: 30px;
	float: right;
	clear: both;
	margin-bottom: 8px;
	box-shadow: 0 4px 14px rgba(17,24,39,.18);
	pointer-events: auto;
}
#zhiji-danmu li .zhiji-danmu-user {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	flex-shrink: 0;
	height: 26px;
	line-height: 26px;
	margin-right: 4px;
	color: #fff;
	text-decoration: none;
	font-weight: 500;
}
#zhiji-danmu li .zhiji-danmu-avatar {
	width: 26px;
	height: 26px;
	border-radius: 100%;
	flex-shrink: 0;
	display: block;
	object-fit: cover;
}
#zhiji-danmu li .zhiji-danmu-type {
	display: inline-flex;
	align-items: center;
	gap: 2px;
	padding: 1px 7px;
	border-radius: 9px;
	font-size: 11px;
	font-weight: 600;
	margin-right: 5px;
	flex-shrink: 0;
	line-height: 1.5;
}
#zhiji-danmu li .zhiji-danmu-content {
	flex-shrink: 0;
}
/* 公告：置顶、加粗、更醒目（停留更久、不被普通弹幕顶掉） */
#zhiji-danmu li.zhiji-danmu-notice {
	font-weight: 600;
	height: 34px;
	line-height: 34px;
	font-size: 14px;
	box-shadow: 0 6px 18px rgba(255,159,28,.35);
	border: 1px solid rgba(255,255,255,.28);
}
#zhiji-danmu li.zhiji-danmu-notice .zhiji-danmu-notice-pin {
	margin-right: 5px;
	flex-shrink: 0;
}
@media screen and (max-width: 900px) {
	#zhiji-danmu { display: none; }
}
ZHIJI_DANMU_CSS;
	// 2026-09-26：改走统一内联资源服务（Assets.php）
	zhiji_asset_add_css( 'danmu', $danmu_css );

	zhiji_asset_add_js( 'danmu-ajax', 'window.ZHIJI_DANMU_AJAX=' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ';' );

	$danmu_js = <<<'ZHIJI_DANMU_JS'
/* 知集 · 弹幕前端（jQuery 实现，右下角逐条淡入） */
(function () {
	function zhijiDanmuBoot() {
		if (typeof window.jQuery === 'undefined') { setTimeout(zhijiDanmuBoot, 80); return; }
		if (typeof window.ZHIJI_DANMU_AJAX === 'undefined') { return; }
		jQuery(function ($) {
			// v1.9.8: 不再创建固定位置弹幕容器，统一走 NotificationCenter
			var box = $('<div id="zhiji-danmu" style="display:none;"></div>');
			var shown = {};
			var rowMax = 3; // 同时最多显示行数（普通弹幕）
			var noticeHold = 8; // 公告置顶停留秒数（秒）

			function trim() {
				// 只清理普通弹幕，公告（.zhiji-danmu-notice）置顶独立、不受行数限制
				var items = box.find('li').not('.zhiji-danmu-notice');
				var removeCount = items.length - rowMax;
				if (removeCount > 0) {
					// 【关键修复】一次性淡出最旧的 removeCount 条，动画完成后各自移除。
					// 旧实现 while(items.length > rowMax){ items.first().animate(...) } 中，
					// animate 是异步移除，while 在同一执行栈内反复给同一条 li 开动画，
					// items.length 永不减少 → 弹幕累积超 3 条即死循环冻结页面（首页卡死/OOM 根因）。
					items.slice(0, removeCount).each(function () {
						$(this).animate({ opacity: 0 }, 300, function () { $(this).remove(); });
					});
				}
			}

			// 公告：v1.9.8 统一走 NotificationCenter 滚动弹幕
			function showNotice(item) {
				var name = '知集公告';
				var text = '📢 ' + (item.content || '');
				function pushNotice() {
					if (typeof window.zhijiAddDanmu === 'function') {
						window.zhijiAddDanmu(name, text, 'notice', '');
					} else if (typeof window.zhijiDanmu === 'function') {
						window.zhijiDanmu(name, text);
					}
				}
				if (typeof window.zhijiAddDanmu === 'function' || typeof window.zhijiDanmu === 'function') {
					pushNotice();
				} else {
					if (document.readyState === 'loading') {
						document.addEventListener('DOMContentLoaded', function() { setTimeout(pushNotice, 300); });
					} else {
						setTimeout(pushNotice, 500);
					}
				}
			}

			function show(item) {
				var key = item.type + '|' + (item.name || '') + '|' + (item.content || '');
				if (shown[key]) return; // 同一条不重复展示
				shown[key] = 1;
				// v1.9.8: 统一走 NotificationCenter 滚动弹幕接口
				var name = item.name || '知集公告';
				var text = item.content || '';
				if (item.type_label) {
					text = (item.type_icon || '') + item.type_label + ' ' + text;
				}
				function pushDanmu() {
					if (typeof window.zhijiAddDanmu === 'function') {
						window.zhijiAddDanmu(name, text, item.type, item.avatar);
					} else if (typeof window.zhijiDanmu === 'function') {
						window.zhijiDanmu(name, text);
					}
				}
				if (typeof window.zhijiAddDanmu === 'function' || typeof window.zhijiDanmu === 'function') {
					pushDanmu();
				} else {
					// NotificationCenter 尚未加载（在wp_footer输出），延迟到DOM就绪后推送
					if (document.readyState === 'loading') {
						document.addEventListener('DOMContentLoaded', function() { setTimeout(pushDanmu, 300); });
					} else {
						setTimeout(pushDanmu, 500);
					}
				}
			}

			function fetchLatest() {
				$.ajax({
					url: window.ZHIJI_DANMU_AJAX,
					method: 'POST',
					data: { action: 'zhiji_api', api: 'zhiji_danmu_fetch' },
					dataType: 'json',
					success: function (res) {
						if (!res || !res.length) return;
						// v1.9.8: 倒序逐条延迟推送（每条间隔1.2秒），避免一次性刷屏
						var idx = res.length - 1;
						function pushNext() {
							if (idx < 0) return;
							show(res[idx]);
							idx--;
							setTimeout(pushNext, 2500);
						}
						pushNext();
					}
				});
			}

			fetchLatest();
			// 轮询刷新（默认 30 秒）
			var poll = parseInt(window.ZHIJI_DANMU_POLL || '30000', 10);
			var timer = null;
			function startPoll(){ if (timer || poll <= 0) return; timer = setInterval(fetchLatest, poll); }
			function stopPoll(){ if (timer) { clearInterval(timer); timer = null; } }
			// 节能优化：页面在后台（不可见）时暂停轮询，回到前台恢复，
			// 避免多个标签页长挂时后台轮询累积内存/CPU（浏览器 Out of Memory 防御）
			document.addEventListener('visibilitychange', function () {
				if (document.hidden) { stopPoll(); } else { startPoll(); }
			});
			startPoll();
		});
	}
	zhijiDanmuBoot();
})();
ZHIJI_DANMU_JS;
	// 轮询间隔（毫秒）由后端配置注入
	$poll_ms = max( 5000, (int) zhiji_get_option( 'danmu_poll', 30 ) * 1000 );
	echo '<script>window.ZHIJI_DANMU_POLL=' . (int) $poll_ms . ';</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	// 2026-09-26：改走统一内联资源服务（保留原有外层 IIFE 包装，语义不变）
	zhiji_asset_add_js( 'danmu', '(function(){function z_boot() {if(typeof window.jQuery==="undefined"){setTimeout(z_boot,80);return;}' . $danmu_js . '}z_boot();})();' );
}
add_action( 'wp_enqueue_scripts', 'zhiji_danmu_enqueue' );

/* ===================== 初始化 ===================== */

/**
 * 模块初始化（after_setup_theme，跟随 Bootstrap 加载顺序）。
 */
function zhiji_danmu_init() {
	// 弹幕开关关闭时：不注册前端/事件钩子（事件源钩子仅在开启时生效，
	// 已用 add_action 注册，但各回调内部再校验开关，避免误写事件池）
}
add_action( 'after_setup_theme', 'zhiji_danmu_init' );
