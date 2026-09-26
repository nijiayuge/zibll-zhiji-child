<?php
/**
 * @module  FriendLinkApply
 * @desc    友链申请表单（AJAX 提交 + 后台审核）
 * @option  friend_link_apply_enabled  总开关
 * @hook    wp_ajax(_nopriv)_zhiji_link_apply_submit · 申请提交
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/FriendLinkApply.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('friend_link_apply', array(
    'title'    => '友链申请',
    'parent'   => 'zhiji_page',
    'priority' => 130,
    'option'   => 'link_apply_enabled',
));



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 注册「友链申请」自定义文章类型
 */
function zhiji_link_apply_register_post_type() {
	// 开关守卫（模块契约：关闭即零开销）——原先无论开关与否都会注册，违反契约。
	// 后台仍放行：该 CPT 承载用户已提交的数据，关闭开关后仍需在后台查看/审核历史申请。
	if ( ! is_admin() && ! zhiji_is_enabled( 'link_apply_enabled', true ) ) {
		return;
	}

	$labels = array(
		'name'               => _x( '友链申请', 'post type general name', 'zhiji' ),
		'singular_name'      => _x( '友链申请', 'post type singular name', 'zhiji' ),
		'add_new'            => _x( '新增申请', 'zhiji' ),
		'add_new_item'       => __( '新增友链申请', 'zhiji' ),
		'edit_item'          => __( '编辑友链申请', 'zhiji' ),
		'new_item'           => __( '新友链申请', 'zhiji' ),
		'view_item'          => __( '查看友链申请', 'zhiji' ),
		'search_items'       => __( '搜索友链申请', 'zhiji' ),
		'not_found'          => __( '没有找到友链申请', 'zhiji' ),
		'not_found_in_trash' => __( '回收站中没有友链申请', 'zhiji' ),
		'menu_name'          => __( '友链申请', 'zhiji' ),
	);

	$args = array(
		'labels'              => $labels,
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'menu_position'       => 25,
		'menu_icon'           => 'dashicons-admin-links',
		'capability_type'     => 'post',
		'hierarchical'        => false,
		'supports'            => array( 'title', 'custom-fields' ),
		'has_archive'         => false,
		'rewrite'             => false,
		'show_in_rest'        => false,
	);

	register_post_type( 'link_apply', $args );
}
add_action( 'init', 'zhiji_link_apply_register_post_type', 0 );

/**
 * 友链申请状态：pending（待审核）、approved（已通过）、rejected（已拒绝）
 */

/**
 * 添加友链申请状态列
 */
function zhiji_link_apply_add_status_column( $columns ) {
	$new_columns = array();
	foreach ( $columns as $key => $value ) {
		$new_columns[ $key ] = $value;
		if ( 'title' === $key ) {
			$new_columns['link_status'] = __( '状态', 'zhiji' );
			$new_columns['link_url']    = __( '网站URL', 'zhiji' );
			$new_columns['link_email']  = __( '站长邮箱', 'zhiji' );
		}
	}
	return $new_columns;
}
add_filter( 'manage_link_apply_posts_columns', 'zhiji_link_apply_add_status_column' );

/**
 * 显示友链申请状态列内容
 */
function zhiji_link_apply_status_column_content( $column, $post_id ) {
	switch ( $column ) {
		case 'link_status':
			$status = get_post_meta( $post_id, '_zhiji_link_status', true );
			$status_text = array(
				'pending'  => '<span style="color:#f0ad4e;">待审核</span>',
				'approved' => '<span style="color:#5cb85c;">已通过</span>',
				'rejected' => '<span style="color:#d9534f;">已拒绝</span>',
			);
			echo isset( $status_text[ $status ] ) ? $status_text[ $status ] : $status_text['pending'];
			break;
		case 'link_url':
			$url = get_post_meta( $post_id, '_zhiji_link_url', true );
			echo $url ? '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $url ) . '</a>' : '-';
			break;
		case 'link_email':
			$email = get_post_meta( $post_id, '_zhiji_link_email', true );
			echo $email ? esc_html( $email ) : '-';
			break;
	}
}
add_action( 'manage_link_apply_posts_custom_column', 'zhiji_link_apply_status_column_content', 10, 2 );

/**
 * 友链申请审核操作（通过/拒绝）
 */
function zhiji_link_apply_row_actions( $actions, $post ) {
	if ( 'link_apply' !== $post->post_type ) {
		return $actions;
	}
	$status = get_post_meta( $post->ID, '_zhiji_link_status', true );
	if ( 'pending' === $status || ! $status ) {
		$actions['approve'] = '<a href="' . wp_nonce_url( admin_url( 'admin-post.php?action=zhiji_approve_link&post_id=' . $post->ID ), 'zhiji_approve_link_' . $post->ID ) . '" style="color:#5cb85c;">通过</a>';
		$actions['reject']  = '<a href="' . wp_nonce_url( admin_url( 'admin-post.php?action=zhiji_reject_link&post_id=' . $post->ID ), 'zhiji_reject_link_' . $post->ID ) . '" style="color:#d9534f;">拒绝</a>';
	}
	return $actions;
}
add_filter( 'post_row_actions', 'zhiji_link_apply_row_actions', 10, 2 );

/**
 * 审核通过友链申请
 */
function zhiji_link_approve_action() {
	if ( ! isset( $_GET['post_id'] ) || ! isset( $_GET['_wpnonce'] ) ) {
		wp_die( '参数错误' );
	}
	$post_id = (int) $_GET['post_id'];
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'zhiji_approve_link_' . $post_id ) ) {
		wp_die( '安全验证失败' );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '权限不足' );
	}

	// 更新状态
	update_post_meta( $post_id, '_zhiji_link_status', 'approved' );

	// 获取申请信息
	$name        = get_the_title( $post_id );
	$url         = get_post_meta( $post_id, '_zhiji_link_url', true );
	$description = get_post_meta( $post_id, '_zhiji_link_description', true );
	$image       = get_post_meta( $post_id, '_zhiji_link_image', true );
	$email       = get_post_meta( $post_id, '_zhiji_link_email', true );

	// 添加到 WordPress 链接管理器
	if ( function_exists( 'wp_insert_link' ) ) {
		$link_id = wp_insert_link( array(
			'link_name'        => $name,
			'link_url'         => $url,
			'link_description' => $description,
			'link_image'       => $image,
			'link_visible'     => 'Y',
			'link_target'      => '_blank',
		) );
	}

	// 发送邮件通知（统一品牌票据模板；模板引擎不可用时回退简单 HTML）
	if ( $email && ( function_exists( 'zhiji_mail_template_render' ) || function_exists( 'zhiji_mail_send' ) ) ) {
		$subject = '【知集】友链申请已通过';
		if ( function_exists( 'zhiji_mail_template_render' ) ) {
			$user = get_user_by( 'email', $email );
			$disp = $user ? ( $user->display_name ? $user->display_name : $user->user_login ) : '';
			$body = zhiji_mail_template_render( array(
				'site'                 => get_bloginfo( 'name' ),
				'name'                 => $disp,
				'headline'             => __( '您的友链申请已通过审核', 'zhiji' ),
				'subline'              => __( '您提交的友链申请已通过审核，站点已展示在首页友链区：', 'zhiji' ),
				'ticket_left_label'    => __( '友链站点', 'zhiji' ),
				'ticket_left_content'  => '<div style="font-size:20px;font-weight:800;color:' . zhiji_token_color( 'brand' ) . ';margin-top:8px;line-height:1.3;word-break:break-all;">' . esc_html( $name ) . '</div>',
				'ticket_right_label'   => __( '审核状态', 'zhiji' ),
				'ticket_right_content' => __( '已通过', 'zhiji' ),
				'ticket_right_sub'     => esc_url( $url ),
				'rule_line'            => __( '* 友链已展示在站点首页底部友链区。', 'zhiji' ),
				'btn_text'             => __( '访问知集 &#8594;', 'zhiji' ),
				'btn_url'              => home_url( '/' ),
			) );
		} else {
			$body = '<p>尊敬的站长：</p><p>您提交的友链申请已通过审核！</p><p>网站名称：' . esc_html( $name ) . '<br>网站URL：' . esc_url( $url ) . '</p><p>感谢您的支持，欢迎常来知集！</p>';
		}
		zhiji_mail_send( $email, $subject, $body );
	}

	wp_redirect( admin_url( 'edit.php?post_type=link_apply' ) );
	exit;
}
add_action( 'admin_post_zhiji_approve_link', 'zhiji_link_approve_action' );

/**
 * 拒绝友链申请
 */
function zhiji_link_reject_action() {
	if ( ! isset( $_GET['post_id'] ) || ! isset( $_GET['_wpnonce'] ) ) {
		wp_die( '参数错误' );
	}
	$post_id = (int) $_GET['post_id'];
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'zhiji_reject_link_' . $post_id ) ) {
		wp_die( '安全验证失败' );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '权限不足' );
	}

	update_post_meta( $post_id, '_zhiji_link_status', 'rejected' );

	$email = get_post_meta( $post_id, '_zhiji_link_email', true );
	if ( $email && function_exists( 'zhiji_mail_send' ) ) {
		$subject = '【知集】友链申请未通过';
		$body    = '<p>尊敬的站长：</p><p>很遗憾，您提交的友链申请未通过审核。</p><p>如有疑问，请联系站长。</p>';
		zhiji_mail_send( $email, $subject, $body );
	}

	wp_redirect( admin_url( 'edit.php?post_type=link_apply' ) );
	exit;
}
add_action( 'admin_post_zhiji_reject_link', 'zhiji_link_reject_action' );

/**
 * 前端友链申请表单短代码
 * 用法：[zhiji_link_apply]
 */
function zhiji_link_apply_shortcode() {
	if ( ! (bool) zhiji_get_option( 'link_apply_enabled', 1 ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="zhiji-link-apply-form-wrap">
		<h3 class="zhiji-link-apply-title">友链申请</h3>
		<p class="zhiji-link-apply-desc">请填写以下信息申请友情链接，审核通过后会自动添加到友链页面。</p>
		<form id="zhiji-link-apply-form" class="zhiji-link-apply-form">
			<div class="zhiji-link-apply-row">
				<div class="zhiji-link-apply-field">
					<label for="zhiji_link_name">网站名称 <span class="required">*</span></label>
					<input type="text" id="zhiji_link_name" name="link_name" required placeholder="请输入网站名称">
				</div>
				<div class="zhiji-link-apply-field">
					<label for="zhiji_link_url">网站URL <span class="required">*</span></label>
					<input type="url" id="zhiji_link_url" name="link_url" required placeholder="https://example.com">
				</div>
			</div>
			<div class="zhiji-link-apply-row">
				<div class="zhiji-link-apply-field">
					<label for="zhiji_link_image">网站图标URL</label>
					<input type="url" id="zhiji_link_image" name="link_image" placeholder="https://example.com/favicon.ico">
				</div>
				<div class="zhiji-link-apply-field">
					<label for="zhiji_link_email">站长邮箱 <span class="required">*</span></label>
					<input type="email" id="zhiji_link_email" name="link_email" required placeholder="admin@example.com">
				</div>
			</div>
			<div class="zhiji-link-apply-field">
				<label for="zhiji_link_description">网站描述 <span class="required">*</span></label>
				<textarea id="zhiji_link_description" name="link_description" rows="3" required placeholder="请简要描述您的网站内容和特色"></textarea>
			</div>
			<div class="zhiji-link-apply-field">
				<label for="zhiji_link_qq">QQ/微信（选填）</label>
				<input type="text" id="zhiji_link_qq" name="link_qq" placeholder="方便联系">
			</div>
			<div class="zhiji-link-apply-submit">
				<button type="submit" class="zhiji-link-apply-btn">提交申请</button>
			</div>
			<div class="zhiji-link-apply-msg" id="zhiji-link-apply-msg"></div>
		</form>
	</div>

	<style>
	.zhiji-link-apply-form-wrap {
		background: #fff;
		border-radius: 12px;
		padding: 24px;
		box-shadow: 0 2px 12px rgba(0,0,0,.06);
		max-width: 640px;
		margin: 20px auto;
	}
	.zhiji-link-apply-title {
		margin: 0 0 8px;
		font-size: 20px;
		font-weight: 700;
		color: #333;
	}
	.zhiji-link-apply-desc {
		margin: 0 0 20px;
		font-size: 13px;
		color: #999;
	}
	.zhiji-link-apply-row {
		display: flex;
		gap: 16px;
		margin-bottom: 16px;
	}
	.zhiji-link-apply-field {
		flex: 1;
		margin-bottom: 16px;
	}
	.zhiji-link-apply-row .zhiji-link-apply-field {
		margin-bottom: 0;
	}
	.zhiji-link-apply-field label {
		display: block;
		margin-bottom: 6px;
		font-size: 13px;
		font-weight: 600;
		color: #555;
	}
	.zhiji-link-apply-field .required {
		color: #e74c3c;
	}
	.zhiji-link-apply-field input,
	.zhiji-link-apply-field textarea {
		width: 100%;
		padding: 10px 14px;
		border: 1px solid #ddd;
		border-radius: 8px;
		font-size: 14px;
		color: #333;
		transition: border-color .2s, box-shadow .2s;
		box-sizing: border-box;
	}
	.zhiji-link-apply-field input:focus,
	.zhiji-link-apply-field textarea:focus {
		outline: none;
		border-color: var(--zhiji-brand, #2e7cf6);
		box-shadow: 0 0 0 3px rgba(240,68,148,.1);
	}
	.zhiji-link-apply-submit {
		text-align: center;
		margin-top: 8px;
	}
	.zhiji-link-apply-btn {
		padding: 12px 40px;
		border: none;
		border-radius: 24px;
		background: linear-gradient(135deg, var(--zhiji-brand, #2e7cf6), var(--zhiji-raw-blue-deep, #1a5fd0));
		color: #fff;
		font-size: 15px;
		font-weight: 600;
		cursor: pointer;
		transition: all .2s;
	}
	.zhiji-link-apply-btn:hover {
		transform: translateY(-2px);
		box-shadow: 0 6px 20px rgba(240,68,148,.4);
	}
	.zhiji-link-apply-btn:disabled {
		opacity: .6;
		cursor: not-allowed;
		transform: none;
	}
	.zhiji-link-apply-msg {
		text-align: center;
		margin-top: 12px;
		font-size: 14px;
		min-height: 20px;
	}
	.zhiji-link-apply-msg.success {
		color: #5cb85c;
	}
	.zhiji-link-apply-msg.error {
		color: #d9534f;
	}
	@media (max-width: 600px) {
		.zhiji-link-apply-row {
			flex-direction: column;
			gap: 0;
		}
		.zhiji-link-apply-row .zhiji-link-apply-field {
			margin-bottom: 16px;
		}
	}
	</style>

	<script>
	(function() {
		var form = document.getElementById('zhiji-link-apply-form');
		if (!form) return;
		var msgEl = document.getElementById('zhiji-link-apply-msg');
		var btn = form.querySelector('.zhiji-link-apply-btn');

		form.addEventListener('submit', function(e) {
			e.preventDefault();
			msgEl.className = 'zhiji-link-apply-msg';
			msgEl.textContent = '';
			btn.disabled = true;
			btn.textContent = '提交中...';

			var formData = new FormData(form);
			formData.append('action', 'zhiji_api');
			formData.append('api', 'zhiji_link_apply_submit');
			formData.append('nonce', '<?php echo wp_create_nonce( 'zhiji_link_apply_nonce' ); ?>');

			fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
				method: 'POST',
				body: formData
			})
			.then(function(r) { return r.json(); })
			.then(function(data) {
				btn.disabled = false;
				btn.textContent = '提交申请';
				if (data.success) {
					msgEl.className = 'zhiji-link-apply-msg success';
					msgEl.textContent = data.data.message || '提交成功，等待审核！';
					form.reset();
				} else {
					msgEl.className = 'zhiji-link-apply-msg error';
					msgEl.textContent = data.data.message || '提交失败，请稍后重试。';
				}
			})
			.catch(function() {
				btn.disabled = false;
				btn.textContent = '提交申请';
				msgEl.className = 'zhiji-link-apply-msg error';
				msgEl.textContent = '网络错误，请稍后重试。';
			});
		});
	})();
	</script>
	<?php
	return ob_get_clean();
}
add_shortcode( 'zhiji_link_apply', 'zhiji_link_apply_shortcode' );

/**
 * AJAX 处理友链申请提交
 */
function zhiji_link_apply_ajax_handler() {
	check_ajax_referer( 'zhiji_link_apply_nonce', 'nonce' );

	if ( ! (bool) zhiji_get_option( 'link_apply_enabled', 1 ) ) {
		wp_send_json_error( array( 'message' => '友链申请已关闭' ) );
	}

	$name        = isset( $_POST['link_name'] ) ? sanitize_text_field( $_POST['link_name'] ) : '';
	$url         = isset( $_POST['link_url'] ) ? esc_url_raw( $_POST['link_url'] ) : '';
	$image       = isset( $_POST['link_image'] ) ? esc_url_raw( $_POST['link_image'] ) : '';
	$email       = isset( $_POST['link_email'] ) ? sanitize_email( $_POST['link_email'] ) : '';
	$description = isset( $_POST['link_description'] ) ? sanitize_textarea_field( $_POST['link_description'] ) : '';
	$qq          = isset( $_POST['link_qq'] ) ? sanitize_text_field( $_POST['link_qq'] ) : '';

	if ( ! $name || ! $url || ! $email || ! $description ) {
		wp_send_json_error( array( 'message' => '请填写所有必填项' ) );
	}
	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => '邮箱格式不正确' ) );
	}
	if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
		wp_send_json_error( array( 'message' => '网站URL格式不正确' ) );
	}

	// 防重复：同一URL 24小时内只能申请一次
	$existing = get_posts( array(
		'post_type'      => 'link_apply',
		'posts_per_page' => 1,
		'meta_query'     => array(
			array(
				'key'     => '_zhiji_link_url',
				'value'   => $url,
				'compare' => '=',
			),
			array(
				'key'     => '_zhiji_link_status',
				'value'   => 'pending',
				'compare' => '=',
			),
		),
		'date_query'     => array(
			array( 'after' => '24 hours ago' ),
		),
	) );
	if ( $existing ) {
		wp_send_json_error( array( 'message' => '该网站已提交过申请，请等待审核' ) );
	}

	// 保存申请
	$post_id = wp_insert_post( array(
		'post_type'    => 'link_apply',
		'post_title'   => $name,
		'post_status'  => 'publish',
	) );

	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( array( 'message' => '提交失败，请稍后重试' ) );
	}

	update_post_meta( $post_id, '_zhiji_link_url', $url );
	update_post_meta( $post_id, '_zhiji_link_image', $image );
	update_post_meta( $post_id, '_zhiji_link_email', $email );
	update_post_meta( $post_id, '_zhiji_link_description', $description );
	update_post_meta( $post_id, '_zhiji_link_qq', $qq );
	update_post_meta( $post_id, '_zhiji_link_status', 'pending' );

	wp_send_json_success( array( 'message' => '提交成功！我们会尽快审核，审核结果会通过邮件通知您。' ) );
}
// 2026-09-26：注册到网关（P2-⑥），旧端点保留为转发入口
zhiji_api_register( 'zhiji_link_apply_submit', 'zhiji_link_apply_ajax_handler', true, '' );
add_action( 'wp_ajax_nopriv_zhiji_link_apply_submit', 'zhiji_link_apply_ajax_handler' );

/**
 * 后台配置：友链申请分区
 */
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('friend_link_apply', array(
			array(
				'id'      => 'link_apply_enabled',
				'type'    => 'switcher',
				'title'   => '启用前端友链申请',
				'desc'    => '在页面中使用短代码 [zhiji_link_apply] 插入申请表单',
				'default' => true,
			),
			array(
				'type'    => 'submessage',
				'style'   => 'info',
				'content' => '使用方法：在任意页面中插入短代码 <code>[zhiji_link_apply]</code>，用户提交后在后台「友链申请」菜单中审核，审核通过后自动添加到 WordPress 链接管理器并发送邮件通知。',
			),
		), 20);
