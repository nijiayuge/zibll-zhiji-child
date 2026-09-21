<?php
/**
 * @module  DownloadCenter
 * @desc    独立资源下载中心（列表渲染 + 下载落地）
 * @option  download_center_enabled  总开关
 * @hook    init · 下载落地处理（?zhiji_dl=）
 * @hook    短代码 zhiji_download_center
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/DownloadCenter.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('download_center', array(
    'title'    => '独立下载中心',
    'parent'   => 'zhiji_shop',
    'priority' => 10,
    'option'   => 'download_center_enabled',
));



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 页面模板文件名 */
define( 'ZHIJI_DL_TEMPLATE', 'zhiji-download-center.php' );

/** 下载 nonce action */
define( 'ZHIJI_DL_NONCE', 'zhiji_dl' );

/* ============================================================
 * 后台 CSF 设置：商城&商品 → 独立下载中心
 * ============================================================ */
add_action( 'after_setup_theme', function () {
	
	Zhiji_Registry::csf_section_for_legacy( 'download_center', array(
		'title'  => '独立下载中心',
		'icon'   => 'fa fa-download',
		'parent' => 'zhiji_shop',
		'priority' => 10,
		'fields' => array(
			array(
				'id'      => 'download_center_enabled',
				'type'    => 'switcher',
				'title'   => '启用下载中心',
				'default' => false,
				'desc'    => '集中展示可下载资源；下载消耗「下载次数令牌」（不占用父主题每日免费配额）。',
			),
			array(
				'id'         => 'download_items',
				'type'       => 'repeater',
				'title'      => '下载资源列表',
				'dependency' => array( 'download_center_enabled', '==', '1' ),
				'fields'     => array(
					array(
						'id'      => 'title',
						'type'    => 'text',
						'title'   => '资源名称',
						'default' => '',
					),
					array(
						'id'      => 'desc',
						'type'    => 'textarea',
						'title'   => '简介',
						'default' => '',
					),
					array(
						'id'      => 'file',
						'type'    => 'text',
						'title'   => '文件 URL',
						'default' => '',
						'desc'    => '站内置资源文件直链（仅允许本站域名）。',
					),
					array(
						'id'      => 'cover',
						'type'    => 'text',
						'title'   => '封面图 URL',
						'default' => '',
						'desc'    => '留空则自动用首字渐变占位图。',
					),
					array(
						'id'      => 'cost',
						'type'    => 'number',
						'title'   => '消耗下载次数',
						'default' => 1,
						'min'     => 1,
					),
					array(
						'id'      => 'need_login',
						'type'    => 'switcher',
						'title'   => '需登录',
						'default' => true,
					),
				),
			),
			array(
				'type'    => 'content',
				'content' => '用法：新建页面 → 模板选「独立下载中心（Zhiji）」。用户剩余下载次数可通过抽奖/任务等方式发放；本中心仅做展示与扣减，不写父主题 pay_down_num。',
			),
		),
	) );
}, 20 );

/* ============================================================
 * 页面模板注册
 * ============================================================ */
/* 页面模板注册已移除：v1 的 templates/ZHIJI_DL_TEMPLATE 从未存在（死路径），
 * v2 改为短代码 [zhiji_download_center] 渲染 */



/* ============================================================
 * 短代码：[zhiji_download_center] 资源卡片列表
 * （v2 改造：替代 v1 依赖的页面模板，可放页面 / 模块化首页 HTML 模块）
 * ============================================================ */
add_shortcode( 'zhiji_download_center', 'zhiji_dl_shortcode' );

function zhiji_dl_shortcode() {
	if ( ! zhiji_dl_is_enabled() ) {
		return '';
	}
	$items = (array) zhiji_get_option( 'download_items', array() );
	if ( ! $items ) {
		return '';
	}
	$html = '<div class="zhiji-dl-grid">';
	foreach ( $items as $idx => $item ) {
		$title = isset( $item['title'] ) ? (string) $item['title'] : '';
		if ( '' === $title ) {
			continue;
		}
		$desc  = isset( $item['desc'] ) ? (string) $item['desc'] : '';
		$cover = isset( $item['cover'] ) ? (string) $item['cover'] : '';
		$cost  = isset( $item['cost'] ) ? (int) $item['cost'] : 0;
		$login = ! empty( $item['need_login'] );

		$html .= '<div class="zhiji-dl-card">';
		if ( $cover ) {
			$html .= '<div class="zhiji-dl-cover" style="background-image:url(' . esc_url( $cover ) . ')"></div>';
		} else {
			$html .= '<div class="zhiji-dl-cover zhiji-dl-cover--ph">' . esc_html( mb_substr( $title, 0, 1, 'UTF-8' ) ) . '</div>';
		}
		$html .= '<div class="zhiji-dl-body">';
		$html .= '<div class="zhiji-dl-title">' . esc_html( $title ) . '</div>';
		if ( '' !== $desc ) {
			$html .= '<div class="zhiji-dl-desc">' . esc_html( wp_trim_words( $desc, 30, '…' ) ) . '</div>';
		}
		$html .= '<div class="zhiji-dl-meta">';
		if ( $login ) {
			$html .= '<span class="zhiji-dl-badge">登录可下</span>';
		}
		if ( $cost > 0 ) {
			$html .= '<span class="zhiji-dl-badge">' . esc_html( $cost ) . ' 积分</span>';
		}
		$html .= '</div>';
		$html .= '<a class="zhiji-dl-btn" href="' . esc_url( zhiji_dl_get_url( $idx ) ) . '">立即下载</a>';
		$html .= '</div></div>';
	}
	$html .= '</div>';

	$html .= '<style id="zhiji-dl-css">'
		. '.zhiji-dl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:15px;margin:15px 0}'
		. '.zhiji-dl-card{background:var(--main-bg-color,#fff);border-radius:var(--main-radius,8px);overflow:hidden;box-shadow:var(--main-shadow,0 1px 4px rgba(0,0,0,.06));display:flex;flex-direction:column}'
		. '.zhiji-dl-cover{height:120px;background-size:cover;background-position:center}'
		. '.zhiji-dl-cover--ph{display:flex;align-items:center;justify-content:center;font-size:36px;font-weight:700;color:#fff;background:linear-gradient(135deg,var(--zhiji-brand-deep,#1e4e8c),var(--zhiji-brand,#2e7cf6))}'
		. '.zhiji-dl-body{padding:12px 14px;display:flex;flex-direction:column;gap:8px;flex:1}'
		. '.zhiji-dl-title{font-weight:600;color:var(--main-color,#333)}'
		. '.zhiji-dl-desc{font-size:13px;color:var(--muted-color,#888);line-height:1.6;flex:1}'
		. '.zhiji-dl-meta{display:flex;gap:6px;flex-wrap:wrap}'
		. '.zhiji-dl-badge{font-size:12px;padding:1px 8px;border-radius:10px;background:rgba(46,124,246,.1);color:var(--zhiji-brand,#2e7cf6)}'
		. '.zhiji-dl-btn{display:block;text-align:center;padding:9px 0;border-radius:6px;background:var(--zhiji-brand,#2e7cf6);color:#fff;text-decoration:none;font-size:14px}'
		. '.zhiji-dl-btn:hover{opacity:.9}'
		. '</style>';
	return $html;
}

/* ============================================================
 * 下载落地处理：?zhiji_dl=<item_index>&_wpnonce=xxx
 * ============================================================ */
add_action( 'init', function () {
	if ( empty( $_GET['zhiji_dl'] ) ) {
		return;
	}
	if ( ! zhiji_dl_is_enabled() ) {
		wp_die( '下载中心未开启' );
	}

	$items = (array) zhiji_get_option( 'download_items', array() );
	$idx   = (int) $_GET['zhiji_dl'];
	if ( ! isset( $items[ $idx ] ) || empty( $items[ $idx ]['file'] ) ) {
		wp_die( '资源不存在' );
	}
	$item = $items[ $idx ];

	// 登录校验
	if ( ! empty( $item['need_login'] ) && ! is_user_logged_in() ) {
		auth_redirect();
	}

	// nonce 校验
	if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], ZHIJI_DL_NONCE ) ) {
		wp_die( '安全校验失败' );
	}

	$uid  = get_current_user_id();
	$cost = max( 1, (int) ( $item['cost'] ?? 1 ) );

	// 令牌校验与扣减
	$left = Zhiji_Download_Quota::get( $uid );
	if ( $left < $cost ) {
		wp_die( '下载次数不足，剩余 ' . $left . ' 次，需要 ' . $cost . ' 次。请通过抽奖或任务获取下载次数。' );
	}
	for ( $i = 0; $i < $cost; $i++ ) {
		Zhiji_Download_Quota::consume( $uid );
	}

	// 本站域名校验（防 SSRF/任意文件）
	$url  = esc_url_raw( $item['file'] );
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( $host && $host !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		wp_die( '仅支持站内资源' );
	}

	// 落地文件（重定向到直链）
	wp_redirect( $url );
	exit;
} );

/* ============================================================
 * 后台工具菜单 → 下载次数管理页
 * ============================================================ */
add_action( 'admin_menu', function () {
	add_management_page(
		'下载次数管理',
		'下载次数管理',
		'manage_options',
		'zhiji-download-quota',
		'zhiji_dl_render_admin_page'
	);
} );

/**
 * 下载次数管理页渲染 + 操作处理。
 */
function zhiji_dl_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '无权限' );
	}

	$msg = '';
	if ( ! empty( $_POST['zhiji_dq_action'] ) && check_admin_referer( 'zhiji_dq_nonce' ) ) {
		$uid   = (int) ( $_POST['user_id'] ?? 0 );
		$delta = (int) ( $_POST['delta'] ?? 0 );
		if ( $uid ) {
			if ( 'reset' === $_POST['zhiji_dq_action'] ) {
				Zhiji_Download_Quota::set( $uid, 0 );
				$msg = '已将该用户下载次数清零。';
			} elseif ( 'adjust' === $_POST['zhiji_dq_action'] && 0 !== $delta ) {
				$cur  = Zhiji_Download_Quota::get( $uid );
				$next = max( 0, $cur + $delta );
				Zhiji_Download_Quota::set( $uid, $next );
				$msg = sprintf( '已调整：%d %s %d = %d。', $cur, $delta > 0 ? '+' : '-', abs( $delta ), $next );
			}
		}
	}

	$users      = get_users( array( 'fields' => array( 'ID', 'user_login', 'display_name' ) ) );
	$total_left = 0;
	foreach ( $users as $u ) {
		$total_left += Zhiji_Download_Quota::get( $u->ID );
	}
	?>
	<div class="wrap zhiji-dq-wrap">
		<style>
			.zhiji-dq-wrap .zhiji-dq-header{display:flex;align-items:center;gap:14px;margin:4px 0 2px}
			.zhiji-dq-wrap .zhiji-dq-logo{font-size:38px;color:var(--zhiji-brand, #2e7cf6);width:auto;height:auto}
			.zhiji-dq-wrap .zhiji-dq-sub{color:#646970;font-size:13px;margin:4px 0 0}
			.zhiji-dq-wrap .zhiji-dq-stats{display:flex;gap:14px;margin:18px 0;flex-wrap:wrap}
			.zhiji-dq-wrap .zhiji-stat-card{flex:1;min-width:160px;background:#fff;border:1px solid #e1e1e1;border-radius:8px;padding:14px 18px;display:flex;flex-direction:column;gap:2px}
			.zhiji-dq-wrap .zhiji-stat-num{font-size:26px;font-weight:700;color:#1d2327;line-height:1.2}
			.zhiji-dq-wrap .zhiji-stat-label{color:#646970;font-size:12px}
			.zhiji-dq-wrap .zhiji-dq-card{background:#fff;border:1px solid #e1e1e1;border-radius:8px;padding:6px 4px;margin-top:8px;overflow:hidden}
			.zhiji-dq-wrap table.wp-list-table{margin:0;border:none}
			.zhiji-dq-wrap table.wp-list-table thead th{background:#f6f7f7;border-bottom:1px solid #e1e1e1}
			.zhiji-dq-wrap table.wp-list-table td,.zhiji-dq-wrap table.wp-list-table th{padding:12px 16px;vertical-align:middle}
			.zhiji-dq-wrap .zhiji-dq-num{font-weight:700;color:var(--zhiji-brand, #2e7cf6);font-size:15px}
			.zhiji-dq-wrap .zhiji-dq-form{display:inline-flex;gap:6px;align-items:center}
			.zhiji-dq-wrap .zhiji-dq-form input[type=number]{width:74px}
		</style>

		<div class="zhiji-dq-header">
			<span class="zhiji-dq-logo dashicons dashicons-tickets-alt"></span>
			<div>
				<h1 style="margin:0">下载次数管理</h1>
				<p class="zhiji-dq-sub">管理「知集下载次数令牌」——每个用户剩余的下载次数（与父主题每日免费下载配额互不干涉）。可逐行 <b>+/-</b> 调整或清零。</p>
			</div>
		</div>

		<?php if ( $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
		<?php endif; ?>

		<div class="zhiji-dq-stats">
			<div class="zhiji-stat-card"><span class="zhiji-stat-num"><?php echo (int) count( $users ); ?></span><span class="zhiji-stat-label">用户总数</span></div>
			<div class="zhiji-stat-card"><span class="zhiji-stat-num"><?php echo (int) $total_left; ?></span><span class="zhiji-stat-label">全部剩余下载次数</span></div>
		</div>

		<div class="zhiji-dq-card">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>登录名</th>
						<th>显示名</th>
						<th>剩余下载次数</th>
						<th>调整 (+/-)</th>
						<th>操作</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $users as $u ) : ?>
						<?php $left = Zhiji_Download_Quota::get( $u->ID ); ?>
						<tr>
							<td><?php echo (int) $u->ID; ?></td>
							<td><?php echo esc_html( $u->user_login ); ?></td>
							<td><?php echo esc_html( $u->display_name ); ?></td>
							<td><span class="zhiji-dq-num"><?php echo (int) $left; ?></span></td>
							<td>
								<form method="post" class="zhiji-dq-form">
									<?php wp_nonce_field( 'zhiji_dq_nonce' ); ?>
									<input type="hidden" name="user_id" value="<?php echo (int) $u->ID; ?>">
									<input type="number" name="delta" value="1">
									<button class="button" name="zhiji_dq_action" value="adjust">应用</button>
								</form>
							</td>
							<td>
								<form method="post" style="display:inline;">
									<?php wp_nonce_field( 'zhiji_dq_nonce' ); ?>
									<input type="hidden" name="user_id" value="<?php echo (int) $u->ID; ?>">
									<button class="button" name="zhiji_dq_action" value="reset" onclick="return confirm('确认清零该用户下载次数？');">清零</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php
}

/* ============================================================
 * 辅助函数
 * ============================================================ */

/**
 * 判断下载中心是否启用。
 *
 * @return bool
 */
function zhiji_dl_is_enabled() {
	return filter_var( zhiji_get_option( 'download_center_enabled', false ), FILTER_VALIDATE_BOOLEAN );
}

/**
 * 生成下载链接（带 nonce）。
 *
 * @param int $item_index 资源索引
 * @return string
 */
function zhiji_dl_get_url( $item_index ) {
	return wp_nonce_url( add_query_arg( 'zhiji_dl', (int) $item_index, home_url( '/' ) ), ZHIJI_DL_NONCE );
}
