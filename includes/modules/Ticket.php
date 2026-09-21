<?php
/**
 * @module  Ticket
 * @desc    前台工单提交与后台管理
 * @option  ticket_enabled  总开关
 * @hook    init · 工单路由与处理
 * @hook    admin_menu · 后台工单列表
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/Ticket.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('ticket', array(
    'title'    => '工单系统',
    'parent'   => 'zhiji_page',
    'priority' => 160,
    'option'   => 'ticket_enabled',
));



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 工单状态 meta 键 */
define( 'ZHIJI_TICKET_STATUS_META', 'zhiji_ticket_status' );

/** 工单状态映射 */
function zhiji_ticket_status_map() {
	return array(
		'open'       => '待处理',
		'processing' => '处理中',
		'closed'     => '已关闭',
	);
}

/* ============================================================
 * 后台 CSF 设置：页面&显示 → 工单系统
 * ============================================================ */
add_action( 'after_setup_theme', function () {
	
	Zhiji_Registry::csf_section_for_legacy( 'ticket', array(
		'title'  => '工单系统',
		'icon'   => 'fa fa-life-ring',
		'parent' => 'zhiji_page',
		'priority' => 160,
		'fields' => array(
			array(
				'id'      => 'ticket_enabled',
				'type'    => 'switcher',
				'title'   => '启用工单系统',
				'default' => false,
				'desc'    => '用户可提交工单，后台管理状态，状态变更邮件通知。短代码 [zhiji_ticket_form] 提交工单，[zhiji_ticket_list] 查看我的工单。',
			),
			array(
				'id'         => 'ticket_notify_email',
				'type'       => 'switcher',
				'title'      => '状态变更邮件通知',
				'default'    => true,
				'dependency' => array( 'ticket_enabled', '==', '1' ),
				'desc'       => '工单状态变更时，自动邮件通知提交者。',
			),
		),
	) );
}, 20 );

/**
 * 判断工单系统是否启用。
 *
 * @return bool
 */
function zhiji_ticket_is_enabled() {
	return filter_var( zhiji_get_option( 'ticket_enabled', false ), FILTER_VALIDATE_BOOLEAN );
}

/* ============================================================
 * 注册自定义文章类型 + 刷新重写规则
 * ============================================================ */
add_action( 'init', function () {
	if ( ! zhiji_ticket_is_enabled() ) {
		return;
	}

	register_post_type(
		'ticket',
		array(
			'labels'       => array(
				'name'          => '工单',
				'singular_name' => '工单',
				'add_new_item'  => '新建工单',
				'edit_item'     => '处理工单',
				'menu_name'     => '工单管理',
				'all_items'     => '所有工单',
			),
			'public'       => false,
			'show_ui'      => true,
			'menu_icon'    => 'dashicons-sos',
			'menu_position' => 25,
			'supports'     => array( 'title', 'editor', 'author' ),
			'rewrite'      => false,
			'show_in_rest' => false,
		)
	);

	// 刷新重写规则（只执行一次）
	if ( ! get_option( 'zhiji_ticket_flushed' ) ) {
		flush_rewrite_rules();
		update_option( 'zhiji_ticket_flushed', 1 );
	}
}, 5 );

/* ============================================================
 * 前台短代码：[zhiji_ticket_form] 提交工单
 * ============================================================ */
add_shortcode( 'zhiji_ticket_form', function () {
	if ( ! zhiji_ticket_is_enabled() ) {
		return '';
	}
	if ( ! is_user_logged_in() ) {
		return '<div class="zhiji-ticket-login-tip">请先 <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">登录</a> 后提交工单。</div>';
	}

	ob_start();
	?>
	<div class="zhiji-ticket-form-wrap">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="zhiji-ticket-form">
			<input type="hidden" name="action" value="zhiji_ticket_submit">
			<?php wp_nonce_field( 'zhiji_ticket_submit', '_wpnonce' ); ?>
			<div class="zhiji-ticket-field">
				<input type="text" name="ticket_title" class="zhiji-ticket-input" required placeholder="工单标题，一句话描述问题">
			</div>
			<div class="zhiji-ticket-field">
				<textarea name="ticket_content" class="zhiji-ticket-textarea" rows="5" required placeholder="详细描述问题、报错信息、操作步骤..."></textarea>
			</div>
			<div class="zhiji-ticket-field">
				<button type="submit" class="zhiji-ticket-submit">提交工单</button>
			</div>
		</form>
	</div>
	<style>
		.zhiji-ticket-form-wrap{max-width:600px;margin:20px 0}
		.zhiji-ticket-login-tip{padding:16px;background:#f8f9fa;border-radius:8px;color:#666}
		.zhiji-ticket-field{margin-bottom:14px}
		.zhiji-ticket-input,.zhiji-ticket-textarea{width:100%;padding:12px 16px;border:1px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;transition:border-color .3s}
		.zhiji-ticket-input:focus,.zhiji-ticket-textarea:focus{outline:none;border-color:var(--zhiji-brand, #2e7cf6)}
		.zhiji-ticket-textarea{resize:vertical;min-height:120px}
		.zhiji-ticket-submit{padding:12px 32px;background:linear-gradient(135deg,var(--zhiji-brand, #2e7cf6),var(--zhiji-raw-blue-deep, #1a5fd0));color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;transition:opacity .3s}
		.zhiji-ticket-submit:hover{opacity:0.9}
	</style>
	<?php
	return ob_get_clean();
} );

/* ============================================================
 * 前台短代码：[zhiji_ticket_list] 我的工单
 * ============================================================ */
add_shortcode( 'zhiji_ticket_list', function () {
	if ( ! zhiji_ticket_is_enabled() ) {
		return '';
	}
	if ( ! is_user_logged_in() ) {
		return '<div class="zhiji-ticket-login-tip">请先登录查看我的工单。</div>';
	}

	$tickets = get_posts( array(
		'post_type'      => 'ticket',
		'author'         => get_current_user_id(),
		'posts_per_page' => 20,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );

	if ( ! $tickets ) {
		return '<div class="zhiji-ticket-empty">暂无工单。</div>';
	}

	$status_map = zhiji_ticket_status_map();
	ob_start();
	?>
	<div class="zhiji-ticket-list-wrap">
		<?php foreach ( $tickets as $t ) :
			$status = get_post_meta( $t->ID, ZHIJI_TICKET_STATUS_META, true );
			$status = isset( $status_map[ $status ] ) ? $status : 'open';
			$status_label = $status_map[ $status ];
			$status_class = 'zhiji-ticket-status-' . $status;
		?>
			<div class="zhiji-ticket-item">
				<div class="zhiji-ticket-item-info">
					<h4 class="zhiji-ticket-item-title"><?php echo esc_html( $t->post_title ); ?></h4>
					<div class="zhiji-ticket-item-meta"><?php echo esc_html( mysql2date( 'Y-m-d H:i', $t->post_date ) ); ?></div>
				</div>
				<span class="zhiji-ticket-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
	<style>
		.zhiji-ticket-list-wrap{margin:20px 0}
		.zhiji-ticket-empty{padding:20px;text-align:center;color:#999;background:#f8f9fa;border-radius:8px}
		.zhiji-ticket-item{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;background:#fff;border:1px solid #eee;border-radius:8px;margin-bottom:10px;transition:box-shadow .3s}
		.zhiji-ticket-item:hover{box-shadow:0 2px 12px rgba(0,0,0,0.08)}
		.zhiji-ticket-item-info{flex:1;margin-right:16px}
		.zhiji-ticket-item-title{margin:0 0 4px;font-size:15px;font-weight:600;color:#1d2327}
		.zhiji-ticket-item-meta{font-size:12px;color:#999}
		.zhiji-ticket-status{padding:4px 14px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap}
		.zhiji-ticket-status-open{background:#e3f2fd;color:#1976d2}
		.zhiji-ticket-status-processing{background:#fff8e1;color:#f57c00}
		.zhiji-ticket-status-closed{background:#f5f5f5;color:#999}
	</style>
	<?php
	return ob_get_clean();
} );

/* ============================================================
 * 提交工单处理（admin-post）
 * ============================================================ */
add_action( 'admin_post_zhiji_ticket_submit', function () {
	if ( ! zhiji_ticket_is_enabled() ) {
		wp_die( '工单系统未开启' );
	}
	if ( ! is_user_logged_in() ) {
		wp_die( '请先登录' );
	}
	if ( ! check_admin_referer( 'zhiji_ticket_submit' ) ) {
		wp_die( '非法请求' );
	}

	$title   = isset( $_POST['ticket_title'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket_title'] ) ) : '';
	$content = isset( $_POST['ticket_content'] ) ? wp_kses_post( wp_unslash( $_POST['ticket_content'] ) ) : '';

	if ( ! $title || ! $content ) {
		wp_die( '标题和内容不能为空' );
	}

	$ticket_id = wp_insert_post( array(
		'post_type'    => 'ticket',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_content' => $content,
		'post_author'  => get_current_user_id(),
	), true );

	if ( is_wp_error( $ticket_id ) ) {
		wp_die( $ticket_id->get_error_message() );
	}

	update_post_meta( $ticket_id, ZHIJI_TICKET_STATUS_META, 'open' );

	// 提交成功通知（可选：站内信）
	do_action( 'zhiji_ticket_submitted', $ticket_id, get_current_user_id() );

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
	exit;
} );

// 未登录用户提交工单时跳转到登录页
add_action( 'admin_post_nopriv_zhiji_ticket_submit', function () {
	auth_redirect();
} );

/* ============================================================
 * 后台编辑页：工单状态 meta box
 * ============================================================ */
add_action( 'add_meta_boxes_ticket', function () {
	add_meta_box(
		'zhiji_ticket_status_box',
		'工单状态',
		function ( $post ) {
			$status_map = zhiji_ticket_status_map();
			$status     = get_post_meta( $post->ID, ZHIJI_TICKET_STATUS_META, true );
			$status     = isset( $status_map[ $status ] ) ? $status : 'open';
			wp_nonce_field( 'zhiji_ticket_status', 'zhiji_ticket_status_nonce' );
			echo '<select name="zhiji_ticket_status" style="width:100%;padding:8px;">';
			foreach ( $status_map as $key => $label ) {
				echo '<option value="' . esc_attr( $key ) . '" ' . selected( $status, $key, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select><p class="description">变更状态会邮件通知提交者。</p>';
		},
		'ticket',
		'side',
		'high'
	);
} );

/* ============================================================
 * 保存工单状态 + 状态变更邮件通知
 * ============================================================ */
add_action( 'save_post_ticket', function ( $post_id, $post ) {
	if ( ! isset( $_POST['zhiji_ticket_status_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['zhiji_ticket_status_nonce'] ), 'zhiji_ticket_status' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$status_map = zhiji_ticket_status_map();
	$new_status = isset( $_POST['zhiji_ticket_status'] ) ? sanitize_key( $_POST['zhiji_ticket_status'] ) : '';
	if ( ! isset( $status_map[ $new_status ] ) ) {
		return;
	}

	$old_status = get_post_meta( $post_id, ZHIJI_TICKET_STATUS_META, true );
	update_post_meta( $post_id, ZHIJI_TICKET_STATUS_META, $new_status );

	// 状态变更时邮件通知提交者
	if ( $old_status !== $new_status && $post->post_author && zhiji_get_option( 'ticket_notify_email', true ) ) {
		$user = get_userdata( $post->post_author );
		if ( $user && $user->user_email ) {
			$subject = '工单状态更新：' . $post->post_title;
			$message = "您的工单「{$post->post_title}」状态已变更为：{$status_map[$new_status]}\n\n";
			$message .= "工单内容：\n" . wp_strip_all_tags( $post->post_content ) . "\n\n";
			$message .= "如有疑问，请登录网站查看详情。";
			wp_mail( $user->user_email, $subject, $message );
		}

		// 站内信扩展点
		do_action( 'zhiji_ticket_status_changed', $post_id, $old_status, $new_status, $post->post_author );
	}
}, 10, 2 );
