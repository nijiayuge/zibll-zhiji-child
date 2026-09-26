<?php
/**
 * @module  EmailSubscribe
 * @desc    邮件订阅（新文通知 + 订阅积分奖励）
 * @option  email_sub_enabled  总开关
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/EmailSubscribe.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('email_subscribe', array(
    'title'    => '邮件订阅',
    'parent'   => 'zhiji_user',
    'priority' => 50,
    'option'   => 'email_sub_enabled',
));



if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 注册CSF设置字段
 */
add_action( 'after_setup_theme', function () {
    
    Zhiji_Registry::csf_section_for_legacy( 'email_subscribe', array(
        'parent' => 'zhiji_user',
        'priority' => 50,
        'title'  => '邮件订阅',
        'icon'   => 'fa fa-envelope',
        'fields' => array(

            // 开关
            array(
                'id'      => 'email_sub_enabled',
                'type'    => 'switcher',
                'title'   => '启用邮件订阅',
                'label'   => '注册页显示订阅勾选框，新文章自动通知订阅用户',
                'default' => false,
            ),

            // 勾选框文字
            array(
                'id'         => 'email_sub_label',
                'type'       => 'text',
                'title'      => '勾选框文字',
                'desc' => __( '订阅勾选框旁展示的文字。', 'zhiji' ),
                'default'    => '订阅站点邮件（新品 / 活动通知）',
                'dependency' => array( 'email_sub_enabled', '==', '1' ),
            ),

            // 新文章通知
            array(
                'id'         => 'email_sub_new_post',
                'type'       => 'switcher',
                'title'      => '新文章自动通知',
                'desc' => __( '发布新文章时自动向订阅者发送通知邮件。', 'zhiji' ),
                'label'      => '发布新文章时自动发送邮件通知所有订阅用户',
                'default'    => true,
                'dependency' => array( 'email_sub_enabled', '==', '1' ),
            ),

            // 通知延迟
            array(
                'id'         => 'email_sub_delay',
                'type'       => 'number',
                'title'      => '通知延迟（分钟）',
                'desc' => __( '新文章发布后延迟多少分钟再发送通知（避免编辑反复修改）。', 'zhiji' ),
                'label'      => '文章发布后延迟多少分钟发送通知，0表示立即发送',
                'default'    => 0,
                'min'        => 0,
                'max'        => 1440,
                'dependency' => array( 'email_sub_enabled', '==', '1' ),
            ),

            // 订阅送奖励
            array(
                'id'         => 'email_sub_reward_enabled',
                'type'       => 'switcher',
                'title'      => '订阅送奖励',
                'label'      => '用户首次订阅邮件时赠送积分/优惠码',
                'default'    => false,
                'dependency' => array( 'email_sub_enabled', '==', '1' ),
            ),

            // 奖励积分
            array(
                'id'         => 'email_sub_reward_points',
                'type'       => 'number',
                'title'      => '赠送积分',
                'desc' => __( '完成邮件订阅后赠送的积分（0 = 不赠送）。', 'zhiji' ),
                'default'    => 10,
                'min'        => 0,
                'dependency' => array( 'email_sub_reward_enabled', '==', '1' ),
            ),

            // 奖励说明
            array(
                'type'    => 'submessage',
                'style'   => 'info',
                'content' => '订阅者查询：get_users([\'meta_key\'=>\'zhiji_email_subscribe\',\'meta_value\'=>1])',
                'dependency' => array( 'email_sub_enabled', '==', '1' ),
            ),

        ),
    ) );
}, 20 );

/**
 * 检查邮件订阅是否启用
 *
 * @return bool
 */
function zhiji_email_subscribe_is_enabled() {
    $val = zhiji_get_option( 'email_sub_enabled', false );
    return filter_var( $val, FILTER_VALIDATE_BOOLEAN );
}

/**
 * 注册成功后根据勾选写入订阅标记
 *
 * @param int $user_id 用户ID
 */
add_action( 'user_register', 'zhiji_email_subscribe_save' );
function zhiji_email_subscribe_save( $user_id ) {
    if ( ! zhiji_email_subscribe_is_enabled() ) {
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if ( empty( $_POST['zhiji_email_subscribe'] ) ) {
        return;
    }

    // 检查是否已订阅（防止重复奖励）
    $already_subscribed = get_user_meta( $user_id, 'zhiji_email_subscribe', true );

    // 写入订阅标记
    update_user_meta( $user_id, 'zhiji_email_subscribe', 1 );
    update_user_meta( $user_id, 'zhiji_email_subscribe_time', current_time( 'mysql' ) );

    // 首次订阅送奖励
    if ( ! $already_subscribed ) {
        zhiji_email_subscribe_grant_reward( $user_id );
    }
}

/**
 * 订阅送奖励
 *
 * @param int $user_id 用户ID
 */
function zhiji_email_subscribe_grant_reward( $user_id ) {
    $reward_enabled = filter_var( zhiji_get_option( 'email_sub_reward_enabled', false ), FILTER_VALIDATE_BOOLEAN );
    if ( ! $reward_enabled ) {
        return;
    }

    $points = intval( zhiji_get_option( 'email_sub_reward_points', 10 ) );
    if ( $points <= 0 ) {
        return;
    }

    // 调用奖励中心赠送积分
    if ( function_exists( 'zhiji_reward_center_grant_one' ) ) {
        zhiji_reward_center_grant_one( $user_id, 'points', $points, '邮件订阅奖励' );
    } else {
        Zhiji_Adapter::update_user_points( $user_id, array(
            'value' => $points,
            'type'  => '订阅奖励',
            'desc'  => '邮件订阅奖励',
        ) );
    }
}

/**
 * 前端注入勾选框到 zibll 注册表单
 */
add_action( 'wp_footer', 'zhiji_email_subscribe_inject', 30 );
function zhiji_email_subscribe_inject() {
    if ( ! zhiji_email_subscribe_is_enabled() ) {
        return;
    }

    $label = trim( (string) zhiji_get_option( 'email_sub_label', '' ) );
    if ( '' === $label ) {
        $label = '订阅站点邮件（新品 / 活动通知）';
    }
    $label_js = wp_json_encode( $label );
    ?>
    <style>
        .zhiji-subscribe-line {
            display: block;
            font-size: 12px;
            opacity: .85;
            cursor: pointer;
            margin: 6px 0 2px;
        }
        .zhiji-subscribe-line input {
            margin-right: 4px;
            vertical-align: middle;
        }
    </style>
    <script>
    (function(){
        function inject() {
            var pwd = document.querySelector('input[name="password2"]');
            if (!pwd) return false;
            var form = pwd.closest('form');
            if (!form) return false;
            if (form.querySelector('input[name="zhiji_email_subscribe"]')) return true;
            var lab = document.createElement('label');
            lab.className = 'zhiji-subscribe-line muted-color';
            lab.innerHTML = '<input type="checkbox" name="zhiji_email_subscribe" value="1" checked> ' + <?php echo $label_js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
            var btn = form.querySelector('button.signsubmit-loader');
            if (btn && btn.parentNode) {
                // zhiji 修复（2026-09-23）：btn 常嵌在 form 内的 div 中，不是 form 的直接子节点，
                // 直接 form.insertBefore 会抛 NotFoundError 导致订阅框注入中断
                btn.parentNode.insertBefore(lab, btn);
            } else {
                form.appendChild(lab);
            }
            return true;
        }
        function tryInject() {
            if (inject()) return;
            var t = setInterval(function(){
                if (inject()) { clearInterval(t); }
            }, 700);
            setTimeout(function(){ clearInterval(t); }, 15000);
        }
        if (document.readyState !== 'loading') {
            tryInject();
        } else {
            document.addEventListener('DOMContentLoaded', tryInject);
        }
    })();
    </script>
    <?php
}

/**
 * 新文章发布时自动发送邮件通知
 *
 * @param int     $post_id 文章ID
 * @param WP_Post $post    文章对象
 */
add_action( 'transition_post_status', 'zhiji_email_subscribe_new_post_notify', 10, 3 );
function zhiji_email_subscribe_new_post_notify( $new_status, $old_status, $post ) {
    // 只处理从非发布状态转为发布状态
    if ( 'publish' !== $new_status || 'publish' === $old_status ) {
        return;
    }

    // 只处理文章类型
    if ( 'post' !== $post->post_type ) {
        return;
    }

    if ( ! zhiji_email_subscribe_is_enabled() ) {
        return;
    }

    $new_post_notify = filter_var( zhiji_get_option( 'email_sub_new_post', true ), FILTER_VALIDATE_BOOLEAN );
    if ( ! $new_post_notify ) {
        return;
    }

    $delay = intval( zhiji_get_option( 'email_sub_delay', 0 ) );

    if ( $delay > 0 ) {
        // 延迟发送
        wp_schedule_single_event(
            time() + $delay * 60,
            'zhiji_email_subscribe_send_notification',
            array( $post->ID )
        );
    } else {
        // 立即发送
        zhiji_email_subscribe_send_notification( $post->ID );
    }
}

/**
 * 发送新文章邮件通知（定时任务钩子）
 *
 * @param int $post_id 文章ID
 */
add_action( 'zhiji_email_subscribe_send_notification', 'zhiji_email_subscribe_send_notification' );
function zhiji_email_subscribe_send_notification( $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post || 'publish' !== $post->post_status ) {
        return;
    }

    // 获取所有订阅用户
    $subscribers = get_users( array(
        'meta_key'     => 'zhiji_email_subscribe',
        'meta_value'   => '1',
        'number'       => 500,
        'fields'       => array( 'ID', 'user_email', 'display_name' ),
    ) );

    if ( empty( $subscribers ) ) {
        return;
    }

    // 邮件标题
    $subject = sprintf( '【新文章】%s', wp_strip_all_tags( $post->post_title ) );

    // 邮件内容在循环内按用户渲染（带用户名问候）

    // 批量发送（每次50封，避免超时）
    $batch_size = 50;
    $batches = array_chunk( $subscribers, $batch_size );

    foreach ( $batches as $batch_index => $batch ) {
        if ( $batch_index > 0 ) {
            // 每批之间延迟2秒
            sleep( 2 );
        }

        foreach ( $batch as $user ) {
            // 个性化邮件内容（用户名问候 + 兜底占位替换）
            $user_content = zhiji_email_subscribe_render_email_content( $post, $user->display_name );
            $user_content = str_replace( '{{username}}', esc_html( $user->display_name ), $user_content );
            $user_content = str_replace( '{{user_email}}', esc_html( $user->user_email ), $user_content );

            // 发送邮件
            if ( function_exists( 'zhiji_mail_send' ) ) {
                zhiji_mail_send( $user->user_email, $subject, $user_content );
            } else {
                wp_mail( $user->user_email, $subject, $user_content, array( 'Content-Type: text/html; charset=UTF-8' ) );
            }
        }
    }
}

/**
 * 渲染新文章通知邮件内容
 *
 * @param WP_Post $post 文章对象
 * @return string
 */
function zhiji_email_subscribe_render_email_content( $post, $name = '' ) {
    $permalink = get_permalink( $post->ID );
    $title = wp_strip_all_tags( $post->post_title );
    $excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 50, '...' );
    $featured_image = get_the_post_thumbnail_url( $post->ID, 'large' );
    $site_name = get_bloginfo( 'name' );
    $site_url = home_url();

    // 如果有邮件模板模块，使用统一品牌模板（图文卡片正文，不使用票据样式）
    if ( function_exists( 'zhiji_mail_template_render' ) ) {
        $body_html = '';
        // 仅显示真正的特色图（get_post_thumbnail_id 判断），避免 zibll 取文章首图（可能是弹窗/广告截图）进邮件
        if ( get_post_thumbnail_id( $post->ID ) && $featured_image ) {
            $body_html .= '<div style="margin-bottom:16px;text-align:center;"><img src="' . esc_url( $featured_image ) . '" alt="' . esc_attr( $title ) . '" style="max-width:100%;height:auto;border-radius:8px;"></div>';
        }
        $body_html .= '<h2 style="color:#111827;font-size:18px;line-height:1.5;margin:0 0 12px;text-align:center;">' . esc_html( $title ) . '</h2>';
        $body_html .= '<p style="color:#6b7280;font-size:13px;line-height:1.8;margin:0;">' . esc_html( $excerpt ) . '</p>';
        return zhiji_mail_template_render( array(
            'headline'  => '您订阅的内容有新更新',
            'subline'   => '您关注的站点发布了新文章，第一时间为您送达：',
            'name'      => $name,
            'body_html' => $body_html,
            'rule_line' => '您可随时登录个人中心管理邮件订阅。',
            'btn_text'  => '查看文章',
            'btn_url'   => $permalink,
        ) );
    }

    // 没有邮件模板模块，使用简单HTML
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo esc_html( $title ); ?></title>
    </head>
    <body style="margin:0;padding:20px;background:#f5f5f5;font-family:Arial,sans-serif;">
        <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;padding:30px;">
            <h1 style="color:#333;font-size:24px;margin:0 0 20px 0;text-align:center;"><?php echo esc_html( $site_name ); ?></h1>
            <p style="color:#666;font-size:14px;margin:0 0 20px 0;">尊敬的 {{username}}，您好！</p>
            <p style="color:#666;font-size:14px;margin:0 0 20px 0;">我们发布了一篇新文章，快来看看吧：</p>
            <?php if ( $featured_image ) : ?>
                <div style="margin-bottom:20px;text-align:center;">
                    <img src="<?php echo esc_url( $featured_image ); ?>" alt="<?php echo esc_attr( $title ); ?>" style="max-width:100%;height:auto;border-radius:8px;">
                </div>
            <?php endif; ?>
            <h2 style="color:#333;font-size:20px;margin:0 0 15px 0;"><?php echo esc_html( $title ); ?></h2>
            <p style="color:#666;font-size:14px;line-height:1.8;margin:0 0 20px 0;"><?php echo esc_html( $excerpt ); ?></p>
            <div style="text-align:center;margin:25px 0;">
                <a href="<?php echo esc_url( $permalink ); ?>" style="display:inline-block;padding:12px 30px;background:<?php echo zhiji_token_color( 'brand' ); ?>;color:#fff;text-decoration:none;border-radius:25px;font-size:14px;">阅读全文</a>
            </div>
            <p style="color:#999;font-size:12px;text-align:center;margin-top:30px;">
                您收到此邮件是因为在 <?php echo esc_html( $site_name ); ?> 订阅了邮件通知。<br>
                如需退订，请登录网站在个人中心取消订阅。
            </p>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * 后台工具页：订阅者列表
 */
add_action( 'admin_menu', function () {
    if ( ! zhiji_email_subscribe_is_enabled() ) {
        return;
    }

    add_management_page(
        '邮件订阅者列表',
        '邮件订阅',
        'manage_options',
        'zhiji-email-subscribers',
        'zhiji_email_subscribe_render_admin_page'
    );
} );

/**
 * 渲染后台订阅者列表页面
 */
function zhiji_email_subscribe_render_admin_page() {
    // 处理导出
    if ( isset( $_POST['zhiji_export_subscribers'] ) && check_admin_referer( 'zhiji_export_subscribers' ) ) {
        zhiji_email_subscribe_export_csv();
        return;
    }

    // 分页
    $paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
    $per_page = 20;
    $offset = ( $paged - 1 ) * $per_page;

    // 获取订阅者
    $subscribers = get_users( array(
        'meta_key'   => 'zhiji_email_subscribe',
        'meta_value' => '1',
        'number'     => $per_page,
        'offset'     => $offset,
        'orderby'    => 'registered',
        'order'      => 'DESC',
    ) );

    $total = count( get_users( array(
        'meta_key'   => 'zhiji_email_subscribe',
        'meta_value' => '1',
        'number'     => -1,
        'fields'     => 'ids',
    ) ) );

    $total_pages = ceil( $total / $per_page );
    ?>
    <div class="wrap">
        <h1>邮件订阅者列表</h1>
        <p>共 <strong><?php echo esc_html( $total ); ?></strong> 位订阅者</p>

        <form method="post" style="margin-bottom:20px;">
            <?php wp_nonce_field( 'zhiji_export_subscribers' ); ?>
            <input type="submit" name="zhiji_export_subscribers" class="button button-primary" value="导出CSV">
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>用户ID</th>
                    <th>用户名</th>
                    <th>邮箱</th>
                    <th>订阅时间</th>
                    <th>注册时间</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $subscribers ) ) : ?>
                    <tr><td colspan="5">暂无订阅者</td></tr>
                <?php else : ?>
                    <?php foreach ( $subscribers as $user ) : ?>
                        <tr>
                            <td><?php echo esc_html( $user->ID ); ?></td>
                            <td><?php echo esc_html( $user->display_name ); ?></td>
                            <td><?php echo esc_html( $user->user_email ); ?></td>
                            <td><?php echo esc_html( get_user_meta( $user->ID, 'zhiji_email_subscribe_time', true ) ); ?></td>
                            <td><?php echo esc_html( $user->user_registered ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links( array(
                        'base'      => add_query_arg( 'paged', '%#%' ),
                        'format'    => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total'     => $total_pages,
                        'current'   => $paged,
                    ) );
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * 导出订阅者CSV
 */
function zhiji_email_subscribe_export_csv() {
    $subscribers = get_users( array(
        'meta_key'   => 'zhiji_email_subscribe',
        'meta_value' => '1',
        'number'     => -1,
        'fields'     => array( 'ID', 'user_email', 'display_name' ),
    ) );

    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="email-subscribers-' . date( 'Y-m-d' ) . '.csv"' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    $output = fopen( 'php://output', 'w' );
    fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // UTF-8 BOM

    fputcsv( $output, array( '用户ID', '用户名', '邮箱' ) );

    foreach ( $subscribers as $user ) {
        fputcsv( $output, array( $user->ID, $user->display_name, $user->user_email ) );
    }

    fclose( $output );
    exit;
}

/**
 * 用户中心注入邮件订阅管理卡片（不修改父主题：JS 注入 + AJAX 切换）
 */
add_action( 'wp_footer', 'zhiji_email_subscribe_user_center_card', 99 );
function zhiji_email_subscribe_user_center_card() {
	if ( ! zhiji_email_subscribe_is_enabled() || ! is_user_logged_in() ) {
		return;
	}
	$uid        = get_current_user_id();
	$subscribed = ( 1 === (int) get_user_meta( $uid, 'zhiji_email_subscribe', true ) );
	$ajax_url   = admin_url( 'admin-ajax.php' );
	$nonce      = wp_create_nonce( 'zhiji_email_subscribe_toggle' );
	$status_txt = $subscribed ? __( '已订阅', 'zhiji' ) : __( '未订阅', 'zhiji' );
	$btn_txt    = $subscribed ? __( '取消订阅', 'zhiji' ) : __( '重新订阅', 'zhiji' );
	$op         = $subscribed ? 'unsub' : 'sub';
	$badge_cls  = $subscribed ? 'on' : 'off';
	$brand      = function_exists( 'zhiji_token_color' ) ? zhiji_token_color( 'brand' ) : '#2e7cf6';
	?>
	<style>
	.zhiji-sub-card .zhiji-sub-badge{display:inline-flex;align-items:center;gap:4px;font-size:12px;padding:2px 10px;border-radius:999px;font-weight:600;}
	.zhiji-sub-card .zhiji-sub-badge.on{color:#16a34a;background:#ecfdf5;}
	.zhiji-sub-card .zhiji-sub-badge.off{color:#6b7280;background:#f3f4f6;}
	.zhiji-sub-card .zhiji-sub-btn{display:block;width:100%;margin-top:2px;border:1px solid <?php echo esc_attr( $brand ); ?>;color:<?php echo esc_attr( $brand ); ?>;background:#fff;border-radius:8px;font-size:13px;padding:8px 0;cursor:pointer;transition:all .2s;text-align:center;line-height:1.4;}
	.zhiji-sub-card .zhiji-sub-btn:hover{background:<?php echo esc_attr( $brand ); ?>;color:#fff;}
	.zhiji-sub-card .zhiji-sub-icon{color:<?php echo esc_attr( $brand ); ?>;}
	</style>
	<script>
	(function(){
		function inject() {
			var box = document.querySelector('.user-center-sidebar');
			if (!box) return false;
			if (document.getElementById('zhiji-sub-card')) return true;
			var div = document.createElement('div');
			div.id = 'zhiji-sub-card';
			div.className = 'zib-widget padding-10 mb10 radius8 main-bg zhiji-sub-card';
			div.innerHTML =
				'<div class="flex jsb ac mb10">' +
					'<span class="flex ac"><svg class="em16 mr6 zhiji-sub-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg><span class="em095 font-bold">邮件订阅</span></span>' +
					'<span class="zhiji-sub-badge <?php echo esc_attr( $badge_cls ); ?>">● <?php echo esc_html( $status_txt ); ?></span>' +
				'</div>' +
				'<div class="em12 muted-color mb10">新文章发布时邮件通知</div>' +
				'<button type="button" class="zhiji-sub-btn" data-op="<?php echo esc_attr( $op ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-url="<?php echo esc_url( $ajax_url ); ?>"><?php echo esc_html( $btn_txt ); ?></button>';
			box.insertBefore(div, box.firstChild);
			div.querySelector('.zhiji-sub-btn').addEventListener('click', function(){
				var btn = this, fd = new FormData();
				fd.append('action', 'zhiji_email_subscribe_toggle');
				fd.append('op', btn.getAttribute('data-op'));
				fd.append('nonce', btn.getAttribute('data-nonce'));
				fetch(btn.getAttribute('data-url'), {method:'POST', credentials:'same-origin', body: fd})
				.then(function(r){return r.json();})
				.then(function(j){
					if (j && j.success) {
						var badge = document.querySelector('.zhiji-sub-badge');
						if (btn.getAttribute('data-op') === 'unsub') {
							badge.textContent = '● 未订阅'; badge.className = 'zhiji-sub-badge off';
							btn.textContent = '重新订阅'; btn.setAttribute('data-op','sub');
						} else {
							badge.textContent = '● 已订阅'; badge.className = 'zhiji-sub-badge on';
							btn.textContent = '取消订阅'; btn.setAttribute('data-op','unsub');
						}
					}
				});
			});
			return true;
		}
		function tryInject() {
			if (inject()) return;
			var t = setInterval(function(){ if (inject()) clearInterval(t); }, 500);
			setTimeout(function(){ clearInterval(t); }, 15000);
		}
		if (document.readyState !== 'loading') { tryInject(); }
		else { document.addEventListener('DOMContentLoaded', tryInject); }
	})();
	</script>
	<?php
}

/**
 * AJAX 切换邮件订阅状态
 */
// 2026-09-26：注册到网关（P2-⑥），旧端点保留为转发入口
zhiji_api_register( 'zhiji_email_subscribe_toggle', 'zhiji_email_subscribe_ajax_toggle', false, '' );
add_action( 'wp_ajax_zhiji_email_subscribe_toggle', 'zhiji_api_legacy_forward' );
function zhiji_email_subscribe_ajax_toggle() {
	check_ajax_referer( 'zhiji_email_subscribe_toggle', 'nonce' );
	$uid = get_current_user_id();
	if ( ! $uid ) {
		wp_send_json_error( array( 'msg' => 'not logged in' ) );
	}
	$op = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '';
	if ( 'unsub' === $op ) {
		update_user_meta( $uid, 'zhiji_email_subscribe', 0 );
		zhiji_email_subscribe_send_toggle_mail( $uid, 'unsub' );
		wp_send_json_success( array( 'status' => 'unsubscribed' ) );
	}
	if ( 'sub' === $op ) {
		update_user_meta( $uid, 'zhiji_email_subscribe', 1 );
		zhiji_email_subscribe_send_toggle_mail( $uid, 'sub' );
		wp_send_json_success( array( 'status' => 'subscribed' ) );
	}
	wp_send_json_error( array( 'msg' => 'bad op' ) );
}
/**
 * 订阅状态切换通知邮件（取消订阅 / 重新订阅确认，品牌模板）
 *
 * @param int    $uid 用户ID
 * @param string $op  unsub|sub
 */
function zhiji_email_subscribe_send_toggle_mail( $uid, $op ) {
	$user = get_userdata( $uid );
	if ( ! $user || ! $user->user_email ) {
		return;
	}
	$site   = get_bloginfo( 'name' );
	$uc_url = home_url( '/user/' );
	if ( 'unsub' === $op ) {
		$subject = sprintf( '【%s】邮件订阅已取消', $site );
		$body    = zhiji_mail_template_render( array(
			'headline'  => '邮件订阅已取消',
			'name'      => $user->display_name,
			'subline'   => '您已成功取消邮件订阅，将不再收到新文章发布通知。',
			'body_html' => '<div style="text-align:center;padding:8px 0 4px;"><div style="font-size:13px;color:#6b7280;line-height:1.8;">如想再次接收新文章通知，可随时在个人中心重新订阅。</div></div>',
			'rule_line' => sprintf( '此邮件由 %s 自动发送，无需回复。', $site ),
			'btn_text'  => '重新订阅',
			'btn_url'   => $uc_url,
		) );
	} else {
		$subject = sprintf( '【%s】邮件订阅已恢复', $site );
		$body    = zhiji_mail_template_render( array(
			'headline'  => '邮件订阅已恢复',
			'name'      => $user->display_name,
			'subline'   => '您已重新订阅邮件通知，新文章发布时会第一时间通知您。',
			'body_html' => '<div style="text-align:center;padding:8px 0 4px;"><div style="font-size:13px;color:#6b7280;line-height:1.8;">感谢您的关注，精彩内容不再错过。</div></div>',
			'rule_line' => sprintf( '此邮件由 %s 自动发送，无需回复。', $site ),
			'btn_text'  => '返回个人中心',
			'btn_url'   => $uc_url,
		) );
	}
	if ( function_exists( 'zhiji_mail_send' ) ) {
		zhiji_mail_send( $user->user_email, $subject, $body );
	} else {
		wp_mail( $user->user_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}
}