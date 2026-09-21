<?php
/**
 * @module  LiveFeed
 * @desc    实时展示下载/购买动态
 * @option  live_feed_enabled  总开关
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/LiveFeed.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('live_feed', array(
    'title'    => '实时下载动态',
    'parent'   => 'zhiji_shop',
    'priority' => 20,
    'option'   => 'live_feed_enabled',
));



defined( 'ABSPATH' ) || exit;

/* ============================================================
 * 后台 CSF 设置：商城&商品 → 实时下载动态
 * ============================================================ */
add_action( 'after_setup_theme', function () {
		Zhiji_Registry::csf_section_for_legacy( 'live_feed', array(
		'title'  => '实时下载动态',
		'icon'   => 'fa fa-bolt',
		'parent' => 'zhiji_shop',
		'priority' => 20,
		'fields' => array(
			array(
				'id'      => 'live_feed_enabled',
				'type'    => 'switcher',
				'title'   => '启用实时下载动态',
				'default' => false,
				'desc'    => '记录登录用户下载行为，前台右下/左下角轮换展示脱敏动态（社交证明）。',
			),
			array(
				'id'         => 'live_feed_rate_min',
				'type'       => 'text',
				'title'      => '防刷屏间隔（分钟）',
				'default'    => '10',
				'desc'       => '同一用户同一文章在该时间内不重复记录。',
				'dependency' => array( 'live_feed_enabled', '==', '1' ),
			),
			array(
				'id'         => 'live_feed_mask',
				'type'       => 'switcher',
				'title'      => '昵称脱敏',
				'default'    => true,
				'desc'       => '如「张三点」显示为「张**」，保护用户隐私。',
				'dependency' => array( 'live_feed_enabled', '==', '1' ),
			),
			array(
				'id'         => 'live_feed_max',
				'type'       => 'text',
				'title'      => '同时展示条数',
				'default'    => '5',
				'desc'       => '1~10，前台最多同时展示的动态卡片数。',
				'dependency' => array( 'live_feed_enabled', '==', '1' ),
			),
			array(
				'id'         => 'live_feed_interval',
				'type'       => 'text',
				'title'      => '轮换间隔（秒）',
				'default'    => '6',
				'desc'       => '3~20，卡片轮换展示的间隔时间。',
				'dependency' => array( 'live_feed_enabled', '==', '1' ),
			),
			array(
				'id'         => 'live_feed_position',
				'type'       => 'button_set',
				'title'      => '显示位置',
				'default'    => 'right',
				'options'    => array(
					'right' => '右侧',
					'left'  => '左侧',
				),
				'dependency' => array( 'live_feed_enabled', '==', '1' ),
			),
		),
	) );
}, 20 );



/* ===================== 常量 ===================== */

define( 'ZHIJI_LIVE_FEED_LOG_KEY', 'zhiji_live_feed_log' ); // 日志 option key
define( 'ZHIJI_LIVE_FEED_LOG_MAX', 50 );                    // 日志最大保留条数

/* ===================== 记录下载动态 ===================== */

/**
 * 记录一次下载动态（只记登录用户，防刷屏）。
 *
 * 父主题 zibpay/download.php 下载前钩子 zibpay_download_before。
 *
 * @param int    $post_id    文章 id
 * @param string $down_id    下载项 id
 * @param array  $paid       父主题支付数据（含 paid_type）
 * @param string $file_url   文件/外链地址
 * @param string $file_local 本地文件路径（可能为空）
 */
function zhiji_live_feed_record( $post_id, $down_id, $paid, $file_url, $file_local ) {
	// 开关关闭时不记录，也不产生任何副作用
	if ( ! zhiji_get_option( 'live_feed_enabled', 0 ) ) {
		return;
	}
	$uid = get_current_user_id();
	if ( ! $uid ) {
		return; // 仅记录登录用户（社会证明可信度）
	}

	$post_id = (int) $post_id;
	if ( $post_id < 1 ) {
		return;
	}

	$title = get_the_title( $post_id );
	if ( '' === $title ) {
		return;
	}

	// 防刷：同用户 + 同文章最小间隔分钟
	$rate_min = max( 1, (int) zhiji_get_option( 'live_feed_rate_min', 10 ) );

	$log = get_option( ZHIJI_LIVE_FEED_LOG_KEY, array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}

	$now = time();
	foreach ( $log as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		if ( (int) $item['uid'] === $uid && (int) $item['post_id'] === $post_id ) {
			if ( ( $now - (int) $item['time'] ) < $rate_min * MINUTE_IN_SECONDS ) {
				return; // 间隔内不重复记录
			}
			break;
		}
	}

	// 昵称（可脱敏）
	$user = get_userdata( $uid );
	$name = $user && $user->display_name ? $user->display_name : '';
	if ( '' === $name ) {
		$name = '神秘用户';
	} elseif ( (bool) zhiji_get_option( 'live_feed_mask', true ) ) {
		$name = zhiji_live_feed_mask_name( $name );
	}

	// 头部插入最新一条
	array_unshift(
		$log,
		array(
			'time'    => $now,
			'uid'     => $uid,
			'name'    => $name,
			'post_id' => $post_id,
			'title'   => $title,
		)
	);
	$log = array_slice( $log, 0, ZHIJI_LIVE_FEED_LOG_MAX );
	update_option( ZHIJI_LIVE_FEED_LOG_KEY, $log, false );

	// 联动：向弹幕事件池推送「下载」弹幕（若弹幕模块已启用）
	if ( zhiji_get_option( 'danmu_enabled', 0 ) && function_exists( 'zhiji_danmu_push' ) ) {
		zhiji_danmu_push( 'download', $uid, '下载了《' . wp_trim_words( $title, 8 ) . '》', get_permalink( $post_id ) );
	}
}

/**
 * 昵称脱敏：张三点 → 张**；两字 → 张*；单字/空 → 原样。
 *
 * @param string $name
 * @return string
 */
function zhiji_live_feed_mask_name( $name ) {
	$name = trim( (string) $name );
	$len  = mb_strlen( $name, 'UTF-8' );
	if ( $len <= 1 ) {
		return $name;
	}
	if ( 2 === $len ) {
		return mb_substr( $name, 0, 1, 'UTF-8' ) . '*';
	}
	return mb_substr( $name, 0, 1, 'UTF-8' )
		. str_repeat( '*', $len - 2 )
		. mb_substr( $name, -1, 1, 'UTF-8' );
}

/* ===================== 前端渲染 ===================== */

/**
 * 前台容器 HTML（wp_footer 输出纯 DOM，可靠）。
 * 数据与样式/脚本由 head 内联输出（见 zhiji_live_feed_enqueue）。
 */
function zhiji_live_feed_render() {
	if ( is_admin() ) {
		return;
	}
	if ( ! zhiji_get_option( 'live_feed_enabled', 0 ) ) {
		return;
	}

	$log = get_option( ZHIJI_LIVE_FEED_LOG_KEY, array() );
	if ( ! is_array( $log ) || empty( $log ) ) {
		return;
	}

	$max      = max( 1, min( 10, (int) zhiji_get_option( 'live_feed_max', 5 ) ) );
	$interval = max( 3, min( 20, (int) zhiji_get_option( 'live_feed_interval', 6 ) ) );
	$position = ( 'left' === zhiji_get_option( 'live_feed_position', 'right' ) ) ? 'left' : 'right';

	$items = array_slice( $log, 0, $max );
	if ( empty( $items ) ) {
		return;
	}

	// 组装数据（先转义）
	$out = array();
	foreach ( $items as $it ) {
		$out[] = array(
			'name'  => esc_html( $it['name'] ),
			'title' => esc_html( $it['title'] ),
			'url'   => esc_url( get_permalink( (int) $it['post_id'] ) ),
		);
	}

	$side = ( 'left' === $position ) ? 'left:16px;right:auto' : 'right:16px;left:auto';

	// 容器：数据放 data 属性，供 head 内联 JS 读取（js 不可用则纯 DOM 展示兜底）
	echo '<div class="zhiji-live-feed" id="zhijiLiveFeed" data-interval="' . (int) $interval . '" data-position="' . esc_attr( $position ) . '" style="' . esc_attr( $side ) . '">' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<div class="zhiji-live-feed-card">' . "\n";
	echo '<span class="zhiji-live-feed-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></span>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<div class="zhiji-live-feed-txt" id="zhijiLiveFeedTxt"></div>' . "\n";
	echo '<button class="zhiji-live-feed-close" type="button" aria-label="关闭下载动态">&times;</button>' . "\n";
	echo '</div>' . "\n";
	echo '</div>' . "\n";

	// 数据注入（head 中 window 变量已在 enqueue 输出；此处兜底再给一次供 JS 读取）
	?>
	<script>
	window.ZHIJI_LIVE_FEED_DATA = <?php echo wp_json_encode( $out ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode 已安全转义 ?>;
	</script>
	<?php
}
add_action( 'wp_footer', 'zhiji_live_feed_render', 99 );

/**
 * 注册父主题下载钩子（zibpay_download_before，5 参数）。
 * 文件加载时注册，record 函数内部再做开关判断，避免关闭时产生副作用。
 */
add_action( 'zibpay_download_before', 'zhiji_live_feed_record', 10, 5 );

/**
 * 前端 CSS + JS（wp_enqueue_scripts head 内联输出）。
 * 与抽奖/弹幕模块同方案：head 内联必然生效，JS 外层 jQuery 轮询包装。
 */
function zhiji_live_feed_enqueue() {
	if ( is_admin() ) {
		return;
	}
	if ( ! zhiji_get_option( 'live_feed_enabled', 0 ) ) {
		return;
	}

	$css = <<<'ZHIJI_LIVE_FEED_CSS'
/* 知集 · 实时下载动态（社会证明小卡片） */
.zhiji-live-feed{position:fixed;bottom:84px;z-index:99970;max-width:300px;width:max-content;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"PingFang SC","Microsoft YaHei",sans-serif;user-select:none}
.zhiji-live-feed-card{display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.96);border:1px solid #e7e9f2;border-radius:12px;padding:10px 13px;box-shadow:0 8px 22px rgba(31,35,48,.12);backdrop-filter:blur(8px)}
.zhiji-live-feed-icon{width:18px;height:18px;flex:0 0 auto;color:var(--zhiji-brand, #2e7cf6)}
.zhiji-live-feed-txt{font-size:12.5px;line-height:1.5;color:#444}
.zhiji-live-feed-txt b{color:#333;font-weight:600}
.zhiji-live-feed-txt a{color:var(--zhiji-brand, #2e7cf6);text-decoration:none}
.zhiji-live-feed-txt a:hover{text-decoration:underline}
.zhiji-live-feed-close{flex:0 0 auto;width:16px;height:16px;border:none;background:none;color:#b9bdc7;cursor:pointer;font-size:14px;line-height:1;padding:0;opacity:.75}
.zhiji-live-feed-close:hover{opacity:1;color:#777}
.zhiji-live-feed.hide{display:none}
@media (max-width:600px){.zhiji-live-feed{max-width:230px;bottom:74px}}
ZHIJI_LIVE_FEED_CSS;
	echo '<style id="zhiji-live-feed-css">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	$js = <<<'ZHIJI_LIVE_FEED_JS'
/* 知集 · 实时下载动态前端（jQuery 实现，轮换淡入淡出） */
(function () {
	function zhijiLiveFeedBoot() {
		if (typeof window.jQuery === 'undefined') { setTimeout(zhijiLiveFeedBoot, 80); return; }
		jQuery(function ($) {
			var box = document.getElementById('zhijiLiveFeed');
			if (!box) return;
			var txt = document.getElementById('zhijiLiveFeedTxt');
			if (!txt) return;
			var items = window.ZHIJI_LIVE_FEED_DATA || [];
			if (!items || !items.length) return;
			try { if (sessionStorage.getItem('zhiji_live_feed_closed')) { box.classList.add('hide'); return; } } catch (e) {}
			var i = 0, timer = null;
			function show() {
				var it = items[i % items.length];
				txt.innerHTML = '';
				var strong = document.createElement('b'); strong.textContent = it.name;
				var link = document.createElement('a'); link.href = it.url; link.textContent = '《' + it.title + '》';
				txt.appendChild(strong);
				txt.appendChild(document.createTextNode(' 刚刚下载了 '));
				txt.appendChild(link);
				box.classList.remove('hide');
				$(txt).parent().parent().stop().fadeIn(300);
			}
			function next() { i = (i + 1) % items.length; show(); }
			show();
			var iv = parseInt(box.getAttribute('data-interval'), 10) || 6;
			function startT(){ if (timer) return; timer = setInterval(next, iv * 1000); }
			function stopT(){ if (timer) { clearInterval(timer); timer = null; } }
			// 节能优化：页面在后台（不可见）时暂停轮换，回到前台恢复
			document.addEventListener('visibilitychange', function () {
				if (document.hidden) { stopT(); } else { startT(); }
			});
			startT();
			box.addEventListener('mouseenter', stopT);
			box.addEventListener('mouseleave', startT);
			var closeBtn = box.querySelector('.zhiji-live-feed-close');
			if (closeBtn) closeBtn.addEventListener('click', function () {
				box.classList.add('hide');
				try { sessionStorage.setItem('zhiji_live_feed_closed', '1'); } catch (e) {}
			});
		});
	}
	zhijiLiveFeedBoot();
})();
ZHIJI_LIVE_FEED_JS;
	echo '<script id="zhiji-live-feed-js">(function(){var z_boot=(){if(typeof window.jQuery==="undefined"){setTimeout(z_boot,80);return;}' . $js . '}z_boot();})();</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_enqueue_scripts', 'zhiji_live_feed_enqueue', 98 );
