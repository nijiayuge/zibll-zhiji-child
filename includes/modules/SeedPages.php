<?php
/**
 * @module  SeedPages
 * @desc    后台一键生成/清理演示页面（工具）
 * @option  seed_pages_enabled  总开关
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/SeedPages.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('seed_pages', array(
    'title'    => '演示页面播种',
    'parent'   => 'zhiji_basic',
    'priority' => 200,
    'option'   => 'seed_pages_enabled',
));



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
 * 模板清单：模板 key => [显示标题, 页面 slug]
 * key 必须与主题目录结构完全一致。
 * ============================================================ */
function zhiji_seed_pages_templates() {
	return array(
		// ---- 父主题 zibll/pages/ 自带模板 ----
		'pages/documentnav.php'     => array( 'title' => '文档导航（演示）',   'slug' => 'doc-nav' ),
		'pages/download.php'        => array( 'title' => '资源下载（演示）',   'slug' => 'resources' ),
		'pages/forums.php'          => array( 'title' => '论坛首页（演示）',   'slug' => 'forums' ),
		'pages/links.php'           => array( 'title' => '网址导航（演示）',   'slug' => 'url-nav' ),
		'pages/archives.php'        => array( 'title' => '文章归档（演示）',   'slug' => 'archives' ),
		'pages/newposts.php'        => array( 'title' => '写文章/投稿（演示）', 'slug' => 'newposts' ),
		'pages/postsnavs.php'       => array( 'title' => '文章导航（演示）',   'slug' => 'posts-nav' ),
		'pages/user-auth.php'       => array( 'title' => '用户身份认证（演示）', 'slug' => 'user-auth' ),
		'pages/user-sign.php'       => array( 'title' => '登录/注册/找回密码（演示）', 'slug' => 'user-sign' ),
		// ---- 子主题 zhiji-child/templates/ 自定义模板 ----
		'zhiji-weiyu.php'            => array( 'title' => '微语时间线（演示）', 'slug' => 'weiyu-demo' ),
	);
}

/* ============================================================
 * 后台菜单：工具 → 模板演示页面
 * ============================================================ */
add_action( 'admin_menu', function () {
	add_management_page(
		'模板演示页面',
		'模板演示页面',
		'manage_options',
		'zhiji-seed-pages',
		'zhiji_seed_pages_render'
	);
} );

/* ============================================================
 * 处理表单提交
 * ============================================================ */
add_action( 'admin_post_zhiji_seed_pages_create', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '无权限' );
	}
	check_admin_referer( 'zhiji_seed_pages_create' );
	$r = zhiji_seed_pages_create();
	$msg = '已创建 ' . $r['created'] . ' 个，跳过已存在 ' . $r['skipped'] . ' 个';
	if ( ! empty( $r['errors'] ) ) {
		$msg .= '；错误：' . implode( '；', $r['errors'] );
	}
	wp_redirect( add_query_arg( 'zhiji_seed_msg', urlencode( $msg ), admin_url( 'tools.php?page=zhiji-seed-pages' ) ) );
	exit;
} );

add_action( 'admin_post_zhiji_seed_pages_clean', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '无权限' );
	}
	check_admin_referer( 'zhiji_seed_pages_clean' );
	$count = zhiji_seed_pages_clean();
	wp_redirect( add_query_arg( 'zhiji_seed_msg', urlencode( '已清理 ' . $count . ' 个演示页面' ), admin_url( 'tools.php?page=zhiji-seed-pages' ) ) );
	exit;
} );

/* ============================================================
 * 核心函数
 * ============================================================ */

/**
 * 是否已存在使用同一模板的页面（按 _wp_page_template meta 去重）。
 */
function zhiji_seed_pages_page_exists( $tpl ) {
	$pages = get_pages(
		array(
			'meta_key'   => '_wp_page_template',
			'meta_value' => $tpl,
			'number'     => 1,
		)
	);
	return ! empty( $pages ) ? $pages[0] : null;
}

/**
 * 批量创建演示页面（幂等）。
 */
function zhiji_seed_pages_create() {
	$templates = zhiji_seed_pages_templates();
	$created   = 0;
	$skipped   = 0;
	$errors    = array();

	foreach ( $templates as $tpl => $info ) {
		$existing = zhiji_seed_pages_page_exists( $tpl );
		if ( $existing ) {
			$skipped++;
			continue;
		}

		$pid = wp_insert_post(
			array(
				'post_title'   => $info['title'],
				'post_name'    => $info['slug'],
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $pid ) ) {
			$errors[] = $info['title'] . '：' . $pid->get_error_message();
			continue;
		}

		update_post_meta( $pid, '_wp_page_template', $tpl );
		update_post_meta( $pid, '_zhiji_seed', 1 );
		$created++;
	}

	return array(
		'created' => $created,
		'skipped' => $skipped,
		'errors'  => $errors,
	);
}

/**
 * 清理所有演示页面（仅删 _zhiji_seed=1 标记者）。
 */
function zhiji_seed_pages_clean() {
	$pages = get_posts(
		array(
			'post_type'   => 'page',
			'meta_key'    => '_zhiji_seed',
			'meta_value'  => 1,
			'numberposts' => 200,
			'fields'      => 'ids',
			'post_status' => 'any',
		)
	);
	$count = 0;
	foreach ( (array) $pages as $id ) {
		if ( wp_delete_post( $id, true ) ) {
			$count++;
		}
	}
	return $count;
}

/* ============================================================
 * 渲染后台管理页面
 * ============================================================ */
function zhiji_seed_pages_render() {
	$msg = isset( $_GET['zhiji_seed_msg'] ) ? wp_kses_post( wp_unslash( $_GET['zhiji_seed_msg'] ) ) : '';

	$templates = zhiji_seed_pages_templates();
	$rows      = array();
	$created   = 0;
	$pending   = 0;

	foreach ( $templates as $tpl => $info ) {
		$page = zhiji_seed_pages_page_exists( $tpl );
		if ( $page ) {
			$created++;
			$status = 'exist';
		} else {
			$pending++;
			$status = 'missing';
		}
		$rows[] = array(
			'tpl'    => $tpl,
			'info'   => $info,
			'page'   => $page,
			'status' => $status,
		);
	}
	$total = count( $templates );
	?>
	<div class="wrap zhiji-seed-wrap">
		<style>
			.zhiji-seed-wrap .zhiji-seed-header{display:flex;align-items:center;gap:14px;margin:4px 0 2px}
			.zhiji-seed-wrap .zhiji-seed-logo{font-size:40px;color:#2271b1;width:auto;height:auto}
			.zhiji-seed-wrap .zhiji-seed-sub{color:#646970;font-size:13px;margin:4px 0 0}
			.zhiji-seed-wrap .zhiji-seed-stats{display:flex;gap:16px;margin:20px 0;flex-wrap:wrap}
			.zhiji-seed-wrap .zhiji-stat-card{flex:1;min-width:150px;background:#fff;border:1px solid #e1e1e1;border-radius:8px;padding:16px 20px;display:flex;flex-direction:column;gap:2px}
			.zhiji-seed-wrap .zhiji-stat-num{font-size:28px;font-weight:700;color:#1d2327;line-height:1.2}
			.zhiji-seed-wrap .zhiji-stat-label{color:#646970;font-size:13px}
			.zhiji-seed-wrap .zhiji-stat-card.ok .zhiji-stat-num{color:#1a7f43}
			.zhiji-seed-wrap .zhiji-stat-card.warn .zhiji-stat-num{color:#b26200}
			.zhiji-seed-wrap .zhiji-seed-card{background:#fff;border:1px solid #e1e1e1;border-radius:8px;padding:18px 20px;margin-top:18px}
			.zhiji-seed-wrap .zhiji-seed-card h2{font-size:15px;margin:0 0 6px}
			.zhiji-seed-wrap .zhiji-seed-card p{color:#50575e;margin:0 0 14px}
			.zhiji-seed-wrap .zhiji-seed-btns{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
			.zhiji-seed-wrap .zhiji-badge{display:inline-block;padding:3px 11px;border-radius:12px;font-size:12px;font-weight:600;line-height:1.6;border:1px solid transparent}
			.zhiji-seed-wrap .zhiji-badge.ok{background:#e6f7ec;color:#1a7f43;border-color:#b7e2c7}
			.zhiji-seed-wrap .zhiji-badge.warn{background:#fff4e5;color:#b26200;border-color:#f5d9a8}
			.zhiji-seed-wrap .zhiji-link{color:#2271b1;text-decoration:none;font-weight:600}
			.zhiji-seed-wrap .zhiji-link:hover{text-decoration:underline}
			.zhiji-seed-wrap .zhiji-muted{color:#a7aaad}
			.zhiji-seed-wrap .zhiji-seed-table{padding:0;overflow:hidden}
			.zhiji-seed-wrap .zhiji-seed-table table{border:none;margin:0}
			.zhiji-seed-wrap .zhiji-seed-table thead th{background:#f6f7f7;border-bottom:1px solid #e1e1e1}
			.zhiji-seed-wrap .zhiji-seed-table td,.zhiji-seed-wrap .zhiji-seed-table th{padding:12px 14px;vertical-align:middle}
			.zhiji-seed-wrap code{background:#f0f0f1;padding:2px 6px;border-radius:4px;font-size:12px}
		</style>

		<div class="zhiji-seed-header">
			<span class="zhiji-seed-logo dashicons dashicons-admin-page"></span>
			<div>
				<h1 style="margin:0">模板演示页面</h1>
				<p class="zhiji-seed-sub">为「页面 → 模板」下拉框里的全部可用模板，一键创建演示页面，方便走查前台效果。</p>
			</div>
		</div>

		<?php if ( $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo wp_kses_post( $msg ); ?></p></div>
		<?php endif; ?>

		<div class="zhiji-seed-stats">
			<div class="zhiji-stat-card"><span class="zhiji-stat-num"><?php echo esc_html( $total ); ?></span><span class="zhiji-stat-label">模板总数</span></div>
			<div class="zhiji-stat-card ok"><span class="zhiji-stat-num"><?php echo esc_html( $created ); ?></span><span class="zhiji-stat-label">已创建</span></div>
			<div class="zhiji-stat-card warn"><span class="zhiji-stat-num"><?php echo esc_html( $pending ); ?></span><span class="zhiji-stat-label">待创建</span></div>
		</div>

		<div class="zhiji-seed-card zhiji-seed-actions">
			<h2>操作</h2>
			<p>演示页均带 <code>_zhiji_seed=1</code> 标记，清理时只删这些页，不影响业务页面。</p>
			<div class="zhiji-seed-btns">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'zhiji_seed_pages_create' ); ?>
					<input type="hidden" name="action" value="zhiji_seed_pages_create" />
					<?php submit_button( '创建演示页面', 'primary', '', false ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'zhiji_seed_pages_clean' ); ?>
					<input type="hidden" name="action" value="zhiji_seed_pages_clean" />
					<?php submit_button( '清理演示页面', 'delete', '', false, array( 'onclick' => 'return confirm("确认删除所有演示页面？此操作不可恢复。");' ) ); ?>
				</form>
			</div>
		</div>

		<div class="zhiji-seed-card zhiji-seed-table">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr><th>模板 Key</th><th>页面标题</th><th>状态</th><th>前台链接</th></tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<td><code><?php echo esc_html( $r['tpl'] ); ?></code></td>
							<td><?php echo esc_html( $r['info']['title'] ); ?></td>
							<td>
								<?php if ( 'exist' === $r['status'] ) : ?>
									<span class="zhiji-badge ok">✓ 已创建</span>
								<?php else : ?>
									<span class="zhiji-badge warn">+ 待创建</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $r['page'] ) : ?>
									<a class="zhiji-link" href="<?php echo esc_url( get_permalink( $r['page']->ID ) ); ?>" target="_blank">查看前台 ↗</a>
								<?php else : ?>
									<span class="zhiji-muted">尚未创建</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php
}
