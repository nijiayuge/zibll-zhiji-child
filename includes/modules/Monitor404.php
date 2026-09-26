<?php
/**
 * @module  Monitor404
 * @desc    404 访问监控与后台记录
 * @option  monitor_404_enabled  总开关
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/Monitor404.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('monitor_404', array(
    'title'    => '404监控',
    'parent'   => 'zhiji_over',
    'priority' => 60,
    'option'   => 'monitor_404_enabled',
));



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 404 旧 option 键（仅迁移用） */
define( 'ZHIJI_404_STATS_KEY', 'zhiji_404_stats' );
/** 404 统计表名（v1.8.6 由 option 改自定义表） */
define( 'ZHIJI_404_TABLE', 'zhiji_404_logs' );
/** 每日最大唯一 URL 数 */
define( 'ZHIJI_404_MAX_DAILY', 500 );

/* ============================================================
 * 建表 + 旧数据迁移（wp_loaded 幂等；仅启用时执行）
 * ============================================================ */
add_action( 'wp_loaded', function () {
	if ( ! zhiji_monitor_404_is_enabled() ) {
		return;
	}
	if ( get_option( 'zhiji_404_table_version' ) === '1.0' ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = $wpdb->prefix . ZHIJI_404_TABLE;
	$sql   = "CREATE TABLE {$table} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		url VARCHAR(255) NOT NULL DEFAULT '',
		url_key CHAR(32) NOT NULL DEFAULT '',
		count INT(10) UNSIGNED NOT NULL DEFAULT 1,
		first DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
		last DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
		referer VARCHAR(255) NOT NULL DEFAULT '',
		ip VARCHAR(45) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		UNIQUE KEY url_key (url_key),
		KEY last (last)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
	dbDelta( $sql );
	update_option( 'zhiji_404_table_version', '1.0' );
	zhiji_monitor_404_migrate_legacy();
} );

/**
 * 迁移旧 option 统计到自定义表（一次性）。
 */
function zhiji_monitor_404_migrate_legacy() {
	global $wpdb;
	$table = $wpdb->prefix . ZHIJI_404_TABLE;
	$stats = get_option( ZHIJI_404_STATS_KEY, array() );
	if ( ! is_array( $stats ) || empty( $stats['items'] ) ) {
		delete_option( ZHIJI_404_STATS_KEY );
		return;
	}
	$now = current_time( 'mysql' );
	foreach ( $stats['items'] as $item ) {
		if ( ! isset( $item['url'] ) ) {
			continue;
		}
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table} (url, url_key, count, first, last, referer, ip)
			 VALUES (%s, %s, %d, %s, %s, %s, %s)
			 ON DUPLICATE KEY UPDATE count = count + VALUES(count)",
			$item['url'],
			md5( $item['url'] ),
			(int) ( $item['count'] ?? 1 ),
			isset( $item['first'] ) ? $item['first'] : $now,
			isset( $item['last'] ) ? $item['last'] : $now,
			isset( $item['referer'] ) ? $item['referer'] : '',
			isset( $item['ip'] ) ? $item['ip'] : ''
		) );
	}
	delete_option( ZHIJI_404_STATS_KEY );
}

/* ============================================================
 * 后台 CSF 设置：扩展&增强 → 404监控
 * ============================================================ */
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('monitor_404', array(
			array(
				'id'      => 'monitor_404_enabled',
				'type'    => 'switcher',
				'title'   => '启用404监控',
				'default' => false,
				'desc'    => '监控网站404请求，URL去重计数，后台「工具 → 404监控」查看明细和趋势。白名单过滤favicon/robots/静态资源，登录用户不统计。',
			),
			array(
				'id'         => 'monitor_404_track_logged_in',
				'type'       => 'switcher',
				'title'      => '统计登录用户',
				'default'    => false,
				'dependency' => array( 'monitor_404_enabled', '==', '1' ),
				'desc'       => '默认不统计登录用户的404请求，开启后也会统计。',
			),
		), 20);

/**
 * 判断404监控是否启用。
 *
 * @return bool
 */
function zhiji_monitor_404_is_enabled() {
	return filter_var( zhiji_get_option( 'monitor_404_enabled', false ), FILTER_VALIDATE_BOOLEAN );
}

/* ============================================================
 * 404 打点：template_redirect + is_404
 * ============================================================ */
add_action( 'template_redirect', function () {
	if ( ! zhiji_monitor_404_is_enabled() ) {
		return;
	}
	if ( ! is_404() ) {
		return;
	}
	// 默认不统计登录用户
	if ( is_user_logged_in() && ! filter_var( zhiji_get_option( 'monitor_404_track_logged_in', false ), FILTER_VALIDATE_BOOLEAN ) ) {
		return;
	}

	$url = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	if ( ! $url ) {
		return;
	}

	// 常见误报白名单：favicon/robots/sitemap/静态资源
	if ( preg_match( '/(?:favicon\.ico|robots\.txt|sitemap[^\/]*\.xml|\.(?:js|css|png|jpg|jpeg|gif|webp|svg|ico|woff2?|ttf|eot)$)/i', $url ) ) {
		return;
	}

	$key     = md5( $url );
	$referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
	$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	// 自定义表存储（v1.8.6 由 option 改表：避免每次 404 全量重写 option 数组）
	global $wpdb;
	$table = $wpdb->prefix . ZHIJI_404_TABLE;

	// 防爆：当日唯一 URL 上限（沿用 500 条/天）
	$today_start = current_time( 'Y-m-d 00:00:00' );
	$today_count = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(DISTINCT url_key) FROM {$table} WHERE last >= %s", $today_start
	) );
	if ( $today_count >= ZHIJI_404_MAX_DAILY ) {
		return;
	}

	$now = current_time( 'mysql' );
	$wpdb->query( $wpdb->prepare(
		"INSERT INTO {$table} (url, url_key, count, first, last, referer, ip)
		 VALUES (%s, %s, 1, %s, %s, %s, %s)
		 ON DUPLICATE KEY UPDATE count = count + 1, last = %s",
		$url, $key, $now, $now, $referer, $ip, $now
	) );
}, 5 );


/* ============================================================
 * 后台工具菜单：404监控页
 * ============================================================ */
add_action( 'admin_menu', function () {
	if ( ! zhiji_monitor_404_is_enabled() ) {
		return;
	}
	add_management_page(
		'404监控',
		'404监控',
		'manage_options',
		'zhiji-404-monitor',
		'zhiji_monitor_404_render_page'
	);
} );

/**
 * 渲染404监控后台页。
 */
function zhiji_monitor_404_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '权限不足' );
	}

	// 自定义表查询（v1.8.6 由 option 改表）
	global $wpdb;
	$table       = $wpdb->prefix . ZHIJI_404_TABLE;
	$today_start = current_time( 'Y-m-d 00:00:00' );

	$items = $wpdb->get_results( $wpdb->prepare(
		"SELECT url, url_key, count, first, last, referer, ip FROM {$table} WHERE last >= %s ORDER BY count DESC, last DESC",
		$today_start
	) );
	$total = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COALESCE(SUM(count), 0) FROM {$table} WHERE last >= %s",
		$today_start
	) );

	echo '<div class="wrap"><h1>404监控</h1>'
		. '<p>今日 404 请求 <strong>' . $total . '</strong> 次，唯一 URL <strong>' . count( $items ) . '</strong> 条；'
		. '白名单已过滤 favicon/robots/静态资源，登录用户不统计。</p>'
		. '<h2>今日 404 明细（按次数降序）</h2>'
		. '<table class="widefat striped"><thead><tr><th>URL</th><th>次数</th><th>首次</th><th>最后</th><th>来源</th><th>IP</th><th>操作</th></tr></thead><tbody>';

	if ( empty( $items ) ) {
		echo '<tr><td colspan="7">今日暂无 404 记录。</td></tr>';
	}
	foreach ( $items as $item ) {
		echo '<tr><td><code>' . esc_html( $item->url ) . '</code></td>'
			. '<td>' . (int) $item->count . '</td>'
			. '<td>' . esc_html( $item->first ) . '</td>'
			. '<td>' . esc_html( $item->last ) . '</td>'
			. '<td>' . esc_html( mb_strimwidth( (string) $item->referer, 0, 40, '…' ) ) . '</td>'
			. '<td>' . esc_html( $item->ip ) . '</td>'
			. '<td><a href="javascript:;" class="zhiji-404-ignore button button-small" data-key="' . esc_attr( $item->url_key ) . '">忽略</a></td></tr>';
	}
	echo '</tbody></table>';

	// 近 7 天趋势（按日期聚合）
	$days_ago = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) . ' -6 days' ) );
	$rows     = $wpdb->get_results( $wpdb->prepare(
		"SELECT DATE(last) AS d, SUM(count) AS c FROM {$table} WHERE last >= %s GROUP BY DATE(last)",
		$days_ago
	) );
	$trend = array();
	foreach ( $rows as $r ) {
		$trend[ $r->d ] = (int) $r->c;
	}

	$days = array();
	$cursor = current_time( 'Y-m-d' );
	for ( $i = 0; $i < 7; $i++ ) {
		$days[] = $cursor;
		$cursor = gmdate( 'Y-m-d', strtotime( $cursor . ' -1 day' ) );
	}
	$days = array_reverse( $days );
	$max  = 1;
	foreach ( $days as $d ) {
		$c = isset( $trend[ $d ] ) ? $trend[ $d ] : 0;
		if ( $c > $max ) {
			$max = $c;
		}
	}

	echo '<h2>近 7 天 404 趋势</h2><div style="display:flex;align-items:flex-end;gap:8px;height:100px;padding:10px 0;">';
	foreach ( $days as $d ) {
		$c = isset( $trend[ $d ] ) ? $trend[ $d ] : 0;
		$height = max( 4, round( $c / $max * 70 ) );
		echo '<div style="flex:1;text-align:center;">'
			. '<div style="height:' . $height . 'px;background:linear-gradient(180deg,var(--zhiji-brand, #2e7cf6),var(--zhiji-raw-blue-deep, #1a5fd0));border-radius:4px 4px 0 0;min-width:20px;margin:0 auto;"></div>'
			. '<div class="description" style="font-size:11px;margin-top:4px;">' . esc_html( mb_substr( $d, 5 ) ) . '<br>' . $c . '次</div></div>';
	}
	echo '</div></div>';

	// 忽略按钮 JS
	echo '<script>
jQuery(function($){
	$(document).on("click", ".zhiji-404-ignore", function(){
		var a = $(this);
		$.post(ajaxurl, { action: "zhiji_api", api: "zhiji_404_ignore", key: a.data("key"), nonce: "' . esc_js( wp_create_nonce( 'zhiji_404_ignore' ) ) . '" }, function(res){
			if(!res.error) a.closest("tr").remove();
		});
	});
});
</script>';
}

/* ============================================================
 * AJAX：忽略404记录
 * ============================================================ */
zhiji_api_register( 'zhiji_404_ignore', 'zhiji_404_ignore_handler', false, '' );
function zhiji_404_ignore_handler() {
	check_ajax_referer( 'zhiji_404_ignore', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json( array( 'error' => 1 ) );
	}
	$key = isset( $_POST['key'] ) ? sanitize_key( $_POST['key'] ) : '';
	if ( $key ) {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . ZHIJI_404_TABLE, array( 'url_key' => $key ) );
	}
	wp_send_json( array( 'error' => 0 ) );
}

/**
 * 每日定时清理 30 天前的 404 日志（行业标准：日志定期归档清理，防止表无限增长）
 * 挂载在 after_setup_theme（父主题就绪后），首次注册顺延 1 小时执行。
 */
add_action( 'after_setup_theme', function () {
	if ( ! wp_next_scheduled( 'zhiji_404_log_cleanup' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'zhiji_404_log_cleanup' );
	}
}, 20 );
add_action( 'zhiji_404_log_cleanup', 'zhiji_404_log_cleanup_run' );
function zhiji_404_log_cleanup_run() {
	global $wpdb;
	$table = $wpdb->prefix . ZHIJI_404_TABLE;
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return;
	}
	$threshold = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) . ' -30 days' ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE last < %s", $threshold ) );
}