<?php
/**
 * @module  MailTemplate
 * @desc    全站统一品牌票据邮件模板引擎（zhiji_mail_template_render / zhiji_mail_send）
 * @option  mail_template_enabled  总开关
 * @hook    wp_mail 包装移除 · 避免与父主题双模板叠加
 * @hook    updated_option · 后台测试发送
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/MailTemplate.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('mail_template', array(
    'title'    => '邮件模板',
    'parent'   => 'zhiji_user',
    'priority' => 40,
    'option'   => 'mail_template_enabled',
));



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 统一渲染品牌邮件模板。
 *
 * @param array $params {
 *     模板参数。
 *
 *     @type string $site              站点名称（默认 get_bloginfo('name')）
 *     @type string $logo_html         Logo HTML（默认自动取父主题 logo_src，无则文字 Logo）
 *     @type string $badge             徽章文字（默认「专属福利」）
 *     @type string $badge_color       徽章文字颜色（默认 #6366f1）
 *     @type string $badge_bg          徽章背景色（默认 #eef2ff）
 *     @type string $headline          大标题
 *     @type string $greeting          问候语（默认「您好！」，传用户名自动拼接）
 *     @type string $name              用户名（用于问候语，如「lixiansen，您好！」）
 *     @type string $subline           副文案
 *     @type string $ticket_left_label   票据左栏标签（如「优惠码 COUPON」「奖励 REWARD」）
 *     @type string $ticket_left_content 票据左栏内容 HTML（如优惠码大字/奖励名称）
 *     @type string $ticket_right_label  票据右栏标签（如「优惠内容」「奖励数值」）
 *     @type string $ticket_right_content 票据右栏内容 HTML（如立减¥X/+N积分）
 *     @type string $ticket_right_sub    票据右栏副标题（如「专属折扣」「已发放至账户」）
 *     @type string $rule_line         使用规则/说明文字
 *     @type string $btn_text          CTA 按钮文字（默认「立即前往 →」）
 *     @type string $btn_url           CTA 按钮链接（默认 home_url）
 *     @type string $btn_color         按钮背景色（默认 #2e7cf6，可传 var(--zhiji-brand, #2e7cf6)）
 *     @type string $footer            页脚文字（默认「本邮件由 {site} 自动发送，请勿直接回复。」）
 * }
 * @return string 完整 HTML 邮件内容
 */
function zhiji_mail_template_render( $params = array() ) {
	$site = ! empty( $params['site'] ) ? $params['site'] : get_bloginfo( 'name' );

	// 邮件色值（邮件客户端不支持 CSS var，PHP 侧注入品牌令牌；后台改品牌主色邮件同步）
	$mail_brand        = zhiji_token_color( 'brand' );
	$mail_brand_light  = zhiji_token_color( 'brand_light' );
	$mail_surface_soft = zhiji_token_color( 'surface_soft' );
	$mail_border       = zhiji_token_color( 'border' );

	// Logo
	if ( ! empty( $params['logo_html'] ) ) {
		$logo_html = $params['logo_html'];
	} else {
		$logo_html = '';
		if ( function_exists( '_pz' ) && ( $logo_url = (string) _pz( 'logo_src' ) ) && 0 === strpos( $logo_url, 'http' ) ) {
			$logo_html = '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $site ) . '" style="max-width:200px;max-height:40px;width:auto;height:auto;">';
		}
		if ( '' === $logo_html ) {
			$logo_html = '<div style="font-size:17px;font-weight:700;color:' . $mail_brand . ';letter-spacing:1px;">' . esc_html( $site ) . '</div>';
		}
	}

	// 徽章
	$badge      = ! empty( $params['badge'] ) ? $params['badge'] : __( '专属福利', 'zhiji' );
	$badge_color = ! empty( $params['badge_color'] ) ? $params['badge_color'] : $mail_brand;
	$badge_bg    = ! empty( $params['badge_bg'] ) ? $params['badge_bg'] : $mail_surface_soft;

	// 标题/问候/副文案
	$headline = ! empty( $params['headline'] ) ? $params['headline'] : __( '您获得的奖励已到账', 'zhiji' );
	if ( ! empty( $params['greeting'] ) ) {
		$greeting = $params['greeting'];
	} elseif ( ! empty( $params['name'] ) ) {
		$greeting = sprintf( __( '%s，您好！', 'zhiji' ), $params['name'] );
	} else {
		$greeting = __( '您好！', 'zhiji' );
	}
	$subline = ! empty( $params['subline'] ) ? $params['subline'] : '';

	// 票据卡片
	$ticket_left_label    = ! empty( $params['ticket_left_label'] ) ? $params['ticket_left_label'] : __( '奖励 REWARD', 'zhiji' );
	$ticket_left_content  = ! empty( $params['ticket_left_content'] ) ? $params['ticket_left_content'] : '';
	$ticket_right_label   = ! empty( $params['ticket_right_label'] ) ? $params['ticket_right_label'] : __( '奖励数值', 'zhiji' );
	$ticket_right_content = ! empty( $params['ticket_right_content'] ) ? $params['ticket_right_content'] : '';
	$ticket_right_sub     = isset( $params['ticket_right_sub'] ) ? $params['ticket_right_sub'] : '';

	// 票据下方到期提示（优惠码邮件：有到期时间显示具体日期，无则显示长期有效；非优惠码邮件不传）
	$coupon_expiry_html = '';
	if ( ! empty( $params['coupon_expiry'] ) ) {
		$coupon_expiry_html = '<tr><td style="padding:14px 36px 0;text-align:center;"><div style="display:inline-block;font-size:12px;color:' . $mail_brand . ';background:' . $mail_surface_soft . ';border:1px dashed ' . $mail_border . ';border-radius:8px;padding:8px 18px;line-height:1.6;">' . esc_html( $params['coupon_expiry'] ) . '</div></td></tr>';
	}

	// 票据卡片（默认）或自定义正文（body_html 模式：如订阅邮件图文卡片，不使用票据）
	if ( ! empty( $params['body_html'] ) ) {
		$ticket_html = '<tr><td style="padding:30px 30px 6px;">' . $params['body_html'] . '</td></tr>';
	} else {
		$ticket_html = '<tr><td style="padding:30px 30px 6px;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-radius:14px;overflow:hidden;border:1px solid #d1d5db;box-shadow:0 3px 14px rgba(17,24,39,.08);">
				<tbody><tr>
					<td colspan="2" style="height:12px;line-height:12px;font-size:0;background-image:linear-gradient(135deg,#d1d5db 6px,transparent 6px),linear-gradient(225deg,#d1d5db 6px,transparent 6px);background-size:12px 12px;background-repeat:repeat-x;">&nbsp;</td>
				</tr>
				<tr>
					<td width="62%" align="center" style="background-color:{mail_surface_soft};background-image:radial-gradient(circle at 0 50%,{mail_surface_soft} 0 12px,{mail_brand_light} 12px,{mail_brand_light} 13px,transparent 13px);padding:26px 16px;border-right:2px dashed {mail_border};">
						<div style="font-size:11px;color:{mail_brand};letter-spacing:3px;margin-bottom:14px;">{ticket_right_label}</div>
						<div style="font-size:28px;font-weight:800;color:#111827;margin-top:8px;line-height:1.25;word-break:break-all;">{ticket_right_content}</div>
						<div style="font-size:11px;color:#9ca3af;margin-top:6px;">{ticket_right_sub}</div>
					</td>
					<td align="center" style="background-color:#ffffff;background-image:radial-gradient(circle at 100% 50%,#ffffff 0 12px,{mail_brand_light} 12px,{mail_brand_light} 13px,transparent 13px);padding:24px 12px;">
						<div style="font-size:10px;color:#9ca3af;letter-spacing:2px;">{ticket_left_label}</div>
						{ticket_left_content}
					</td>
				</tr></tbody>
			</table>
		</td></tr>' . $coupon_expiry_html;
	}

	// 规则/按钮/页脚
	$rule_line = ! empty( $params['rule_line'] ) ? $params['rule_line'] : '';
	$btn_text  = ! empty( $params['btn_text'] ) ? $params['btn_text'] : __( '立即前往 &#8594;', 'zhiji' );
	$btn_url   = ! empty( $params['btn_url'] ) ? $params['btn_url'] : home_url( '/' );
	$btn_color = ! empty( $params['btn_color'] ) ? $params['btn_color'] : $mail_brand;
	$footer    = ! empty( $params['footer'] ) ? $params['footer'] : sprintf( __( '本邮件由 %s 自动发送，请勿直接回复。', 'zhiji' ), $site );

	$html = <<<'HTML'
<div style="margin:0;padding:0;background:#f3f4f6;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'PingFang SC','Microsoft YaHei','Segoe UI',sans-serif;">
		<tbody><tr>
			<td align="center" style="padding:36px 16px 20px;">
				<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;background:#ffffff;border-radius:16px;box-shadow:0 1px 2px rgba(17,24,39,.04),0 12px 32px rgba(17,24,39,.07);">
					<tbody><tr><td style="padding:38px 36px 0;text-align:center;">{logo_html}</td></tr>
					<tr><td style="padding:22px 36px 6px;text-align:center;">
						<div style="display:inline-block;padding:5px 14px;border-radius:999px;background:{badge_bg};color:{badge_color};font-size:12px;font-weight:600;letter-spacing:1px;">{badge}</div>
						<div style="font-size:22px;font-weight:800;color:#111827;line-height:1.5;margin-top:16px;">{headline}</div>
						<div style="font-size:15px;font-weight:600;color:#374151;line-height:1.7;margin-top:14px;">{greeting}</div>
						<div style="font-size:14px;color:#6b7280;line-height:1.9;margin-top:4px;">{subline}</div>
					</td></tr>

					{ticket_html}

						<tr><td style="padding:24px 36px 4px;">
						<div style="font-size:12px;color:#9ca3af;line-height:1.9;text-align:center;">{rule_line}</div>
					</td></tr>

					<tr><td style="padding:28px 36px 46px;text-align:center;">
						<a href="{btn_url}" style="display:inline-block;padding:14px 46px;border-radius:999px;background:{btn_color};color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;">{btn_text}</a>
					</td></tr>
				</tbody></table>
			</td>
		</tr></tbody>
	</table>

	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'PingFang SC','Microsoft YaHei','Segoe UI',sans-serif;">
		<tbody><tr>
			<td align="center" style="padding:18px 16px 42px;">
				<div style="width:56px;height:2px;background:#e5e7eb;margin:0 auto 16px;">&nbsp;</div>
				<div style="font-size:12px;color:#9ca3af;">{footer}</div>
				<div style="font-size:12px;color:#d1d5db;margin-top:5px;">&copy; 2026 {site}</div>
			</td>
		</tr></tbody>
	</table>
</div>
HTML;

	return str_replace(
		array(
			'{ticket_html}', '{site}', '{logo_html}', '{badge}', '{badge_color}', '{badge_bg}', '{mail_surface_soft}', '{mail_brand_light}', '{mail_border}', '{mail_brand}',
			'{headline}', '{greeting}', '{subline}',
			'{ticket_left_label}', '{ticket_left_content}',
			'{ticket_right_label}', '{ticket_right_content}', '{ticket_right_sub}',
			'{rule_line}', '{btn_text}', '{btn_url}', '{btn_color}', '{footer}',
		),
		array(
			$ticket_html, esc_html( $site ), $logo_html,
			esc_html( $badge ), esc_html( $badge_color ), esc_html( $badge_bg ), esc_html( $mail_surface_soft ), esc_html( $mail_brand_light ), esc_html( $mail_border ), esc_html( $mail_brand ),
			esc_html( $headline ), esc_html( $greeting ), esc_html( $subline ),
			esc_html( $ticket_left_label ), $ticket_left_content,
			esc_html( $ticket_right_label ), $ticket_right_content, esc_html( $ticket_right_sub ),
			esc_html( $rule_line ), $btn_text, esc_url( $btn_url ), esc_html( $btn_color ),
			esc_html( $footer ),
		),
		$html
	);
}

/**
 * 发送邮件（自动临时移除父主题 zib_get_mail_content 包装，避免双模板叠加）。
 *
 * @param string $to
 * @param string $subject
 * @param string $html_body
 * @return bool
 */
function zhiji_mail_send( $to, $subject, $html_body ) {
	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	$prio    = has_filter( 'wp_mail', 'zib_get_mail_content' );
	if ( false !== $prio ) {
		remove_filter( 'wp_mail', 'zib_get_mail_content', $prio );
	}
	$sent = wp_mail( $to, $subject, $html_body, $headers );
	if ( false !== $prio ) {
		add_filter( 'wp_mail', 'zib_get_mail_content', $prio );
	}
	return (bool) $sent;
}

/**
 * ---------------------------------------------------------------------
 * 邮件模板 · 后台测试发送（v1.9.56）
 * ---------------------------------------------------------------------
 * 后台「用户&互动 → 邮件模板」：
 *   选择场景 + 收件邮箱 + 开启「发送测试邮件」→ 保存 → 自动发送并复位开关。
 */

/**
 * 发送指定场景的测试邮件。
 *
 * @param string $scene coupon|subscribe|friendlink|lottery|reward
 * @param string $to    收件邮箱
 * @return bool
 */
function zhiji_mail_test_send( $scene, $to ) {
	$site = get_bloginfo( 'name' );
	$home = home_url( '/' );
	$subject = '';
	$params  = array();
	switch ( $scene ) {
		case 'coupon':
			$subject = sprintf( '【%s】您的专属优惠码已就绪', $site );
			$params = array(
				'headline'            => '您的专属优惠码已就绪',
				'subline'             => '您在「测试来源」中获得了以下奖励，奖励已发放至您的账户（测试邮件）：',
				'name'                => '测试用户',
				'ticket_left_label'   => '优惠面额',
				'ticket_left_content' => '测试 8.8 折',
				'ticket_right_label'  => '优惠码 COUPON',
				'ticket_right_content'=> 'ZHJI-TEST-2026',
				'coupon_expiry'       => '优惠码有效期至 ' . date_i18n( 'Y年n月j日 H:i', current_time( 'timestamp' ) + 7 * DAY_IN_SECONDS ) . '，逾期自动失效',
				'rule_line'           => '此邮件为系统发送的测试邮件，请勿直接回复。',
				'btn_text'            => '去结算使用',
				'btn_url'             => $home,
			);
			break;
		case 'subscribe':
			$subject = sprintf( '【%s】您订阅的内容有新更新', $site );
			$params = array(
				'name'                => '测试用户',
					'headline'            => '您订阅的内容有新更新',
				'subline'             => '您关注的站点发布了新文章，第一时间为您送达（测试邮件）：',
				'ticket_left_label'   => '发布时间',
				'ticket_left_content' => date_i18n( 'Y-m-d' ),
				'ticket_right_label'  => '最新文章',
				'ticket_right_content'=> '【测试】知集站点更新通知示例文章',
				'rule_line'           => '您可随时在个人中心取消订阅。',
				'btn_text'            => '查看文章',
				'btn_url'             => $home,
			);
			break;
		case 'friendlink':
			$subject = sprintf( '【%s】您的友链申请已通过审核', $site );
			$params = array(
				'name'                => '测试用户',
					'headline'            => '您的友链申请已通过审核',
				'subline'             => '感谢申请友情链接，您的站点已成功加入本站友链（测试邮件）：',
				'ticket_left_label'   => '友链站点',
				'ticket_left_content' => '知集测试友链站',
				'ticket_right_label'  => '审核状态',
				'ticket_right_content'=> '已通过',
				'rule_line'           => '友链已展示在站点首页底部友链区。',
				'btn_text'            => '查看友链',
				'btn_url'             => $home,
			);
			break;
		case 'lottery':
			$subject = sprintf( '【%s】恭喜您抽中大奖！', $site );
			$params = array(
				'name'                => '测试用户',
					'headline'            => '恭喜您抽中大奖！',
				'subline'             => '您在「每日抽奖」中获得了以下奖励，奖励已发放至您的账户（测试邮件）：',
				'ticket_left_label'   => '奖品类型',
				'ticket_left_content' => '站内积分',
				'ticket_right_label'  => '奖品数值',
				'ticket_right_content'=> '+100',
				'ticket_right_sub'    => '已发放至您的账户',
					'rule_line'           => '请登录后在「个人中心-我的奖品」查看。',
				'btn_text'            => '前往领取',
				'btn_url'             => $home,
			);
			break;
		case 'reward':
		default:
			$subject = sprintf( '【%s】您获得的奖励已到账', $site );
			$params = array(
				'name'                => '测试用户',
				'headline'            => '您获得的奖励已到账',
				'subline'             => '您在「测试来源」中获得了以下奖励，奖励已发放至您的账户（测试邮件）：',
				'ticket_left_label'   => '奖励 REWARD',
				'ticket_left_content' => '站内积分',
				'ticket_right_label'  => '奖励数值',
				'ticket_right_content'=> '+88',

				'rule_line'           => '奖励明细可在「个人中心-奖励中心」查看。',
				'btn_text'            => '前往奖励中心',
				'btn_url'             => $home,
			);
			break;
	}

	if ( function_exists( 'zhiji_mail_template_render' ) ) {
		$body = zhiji_mail_template_render( $params );
	} else {
		$body = '<div style="font-family:sans-serif;padding:24px;"><h2>' . esc_html( $params['headline'] ) . '</h2><p>' . esc_html( $params['subline'] ) . '</p></div>';
	}
	if ( function_exists( 'zhiji_mail_send' ) ) {
		return zhiji_mail_send( $to, $subject, $body );
	}
	return false;
}

// 后台设置：用户&互动 → 邮件模板
if ( class_exists( 'CSF' ) ) {
	Zhiji_Registry::csf_section_for_legacy( 'mail_template', array(
		'id'     => 'zhiji_mail_template',
		'title'  => '邮件模板',
		'icon'   => 'fa fa-envelope-o',
		'parent' => 'zhiji_user',
		'priority' => 40,
		'fields' => array(
			array(
				'title'   => '测试收件邮箱',
				'label'   => '填写您自己的邮箱，用于接收测试邮件（默认使用站点管理员邮箱）',
				'id'      => 'mail_test_to',
				'type'    => 'text',
				'default' => get_option( 'admin_email' ),
			),
			array(
				'title'   => '测试场景',
				'label'   => '选择要检查的邮件场景',
				'id'      => 'mail_test_scene',
				'type'    => 'select',
				'options' => array(
					'coupon'     => '邮箱优惠码（领取优惠码）',
					'subscribe'  => '邮件订阅（新文章通知）',
					'friendlink' => '友链申请（审核通过）',
					'lottery'    => '抽奖大转盘（中奖通知）',
					'reward'     => '奖励通知（奖励到账）',
				),
				'default' => 'coupon',
			),
			array(
				'title'   => '发送测试邮件',
				'label'   => '开启后保存，即向收件邮箱发送一封所选场景的测试邮件，随后自动复位',
				'id'      => 'mail_test_send',
				'type'    => 'switcher',
				'default' => false,
			),
			array(
				'title' => '说明',
				'label' => '发送完成后请前往收件邮箱检查排版与配色；如有问题告诉我调整模板。',
				'type'  => 'notice',
				'style' => 'info',
			),
		),
	) );
}

// 保存触发：检测 mail_test_send 开启 → 发送 → 复位开关
add_action( 'updated_option', 'zhiji_mail_test_on_save', 20 );
add_action( 'added_option', 'zhiji_mail_test_on_save', 20 );
function zhiji_mail_test_on_save( $option ) {
	if ( ZHIJI_OPTION_KEY !== $option ) {
		return;
	}
	$opts = get_option( ZHIJI_OPTION_KEY, array() );
	if ( empty( $opts['mail_test_send'] ) ) {
		return;
	}
	$scene = isset( $opts['mail_test_scene'] ) ? $opts['mail_test_scene'] : 'coupon';
	$to    = ! empty( $opts['mail_test_to'] ) ? $opts['mail_test_to'] : get_option( 'admin_email' );
	$ok    = zhiji_mail_test_send( $scene, $to );
	update_option( 'zhiji_mail_test_result', $ok ? 'success' : 'fail', false );
	$opts['mail_test_send'] = 0;
	update_option( ZHIJI_OPTION_KEY, $opts );
}