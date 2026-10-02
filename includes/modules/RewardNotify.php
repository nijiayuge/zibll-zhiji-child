<?php
/**
 * @module  RewardNotify
 * @desc    奖励到账通知（站内信 + 邮件，每日任务/评论福袋/积分任务调用）
 * @option  reward_notify_enabled  总开关
 * @hook    API zhiji_reward_notify · 奖励通知入口
 * @hook    wp_footer · 前端余额高亮
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/RewardNotify.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('reward_notify', array(
    'title'    => '奖励通知',
    'parent'   => 'zhiji_user',
    'priority' => 30,
    'option'   => 'reward_notify_enabled',
));



// 安全门
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * 统一奖励通知入口。
 *
 * P3 架构重构：编排层已委托给统一通知中心（notify/），本函数退化为
 * 「参数归一 + 事件组装 + 一次 dispatch」的薄适配。
 *
 * 【合并了什么】
 *   改造前本函数自带一套编排（自己判断开关、自己写 msg、自己发邮件），
 *   与 notify/ 的编排能力（幂等 / 频控 / 异常兜底 / 环形日志）**各干各的**，
 *   于是「双轨」：奖励通知既没有频控保护，也没法被统一观测。
 *   现在编排统一走 zhiji_notify()，奖励通知免费获得这四项能力。
 *
 * 【刻意没合并什么：文案渲染】
 *   奖励邮件有专属的票据排版（优惠码到期提示、奖励数值大字号、品牌色徽章），
 *   与通知中心的通用票据模板不是一回事；站内信的「【】高亮」也是本模块独有的
 *   前端契约。**强行统一会把这些细节压平**。
 *   故：编排交给 notify/，渲染仍在本模块内做（见 mail_template / msg_body 两个私有方法）。
 *
 * 【对调用方的兼容性】签名与返回值均未变（仍返回 bool = 至少成功投递一条），
 * 现有唯一调用方 CommentFortune 无需改动。
 *
 * @param int    $uid    接收用户 ID（0 = 游客，仅可邮件；无邮箱则跳过）
 * @param array  $reward 奖励结构 {type,name,val,desc,code}
 * @param string $source 来源标识（如 comment_fortune / bargain / lottery）
 * @return bool 是否至少成功投递一条通知
 */
function zhiji_reward_notify( $uid, $reward, $source = '' ) {
	if ( ! zhiji_get_option( 'reward_notify_enabled', 1 ) ) {
		return false;
	}
	if ( ! is_array( $reward ) ) {
		return false;
	}
	// 兼容历史调用方字段差异：DailyTask/CreditTasks 等模块传 'value'，统一归一为 'val'（本模块读取字段）
	if ( isset( $reward['value'] ) && ! isset( $reward['val'] ) ) {
		$reward['val'] = $reward['value'];
	}
	// zhiji 修复（2026-09-23）：各调用方习惯把来源写在 $reward['source'] 里（如 DailyTask/CreditTasks），
	// 而本函数读的是第三参数 $source → 导致通知里「奖励来源」永远为空。此处做兼容取值。
	if ( '' === $source && ! empty( $reward['source'] ) ) {
		$source = (string) $reward['source'];
	}
	// 同理兼容 'desc' 作为奖励名称（desc_text 的 default 分支读 'name'）
	if ( ! isset( $reward['name'] ) && ! empty( $reward['desc'] ) ) {
		$reward['name'] = (string) $reward['desc'];
	}

	$uid = (int) $uid;

	// 渠道编排：沿用本模块既有的两个开关（默认全开），逐项映射到通知渠道
	$channels = array();
	if ( zhiji_get_option( 'reward_notify_msg_enabled', 1 ) && $uid ) {
		$channels[] = 'msg';
	}
	if ( zhiji_get_option( 'reward_notify_mail_enabled', 1 ) ) {
		$user = get_userdata( $uid );
		if ( $user && is_email( $user->user_email ) && ! stristr( $user->user_email, '@no' ) ) {
			$channels[] = 'mail';
		}
	}
	if ( empty( $channels ) ) {
		return false;
	}

	$dispatcher = zhiji_contract( 'Dispatcher' );
	if ( $dispatcher ) {
		$res = $dispatcher->dispatch( array(
			'uid'        => $uid,
			'event'      => 'reward_arrived',
			'channels'   => $channels,
			'vars'       => array(
				'reward' => $reward,
				'source' => $source,
			),
			// 幂等键含「来源+奖励类型+数值+用户」：同一笔奖励重复触发（如重试）只发一次；
			// 不同笔（同类型但数值不同）仍各自通知。
			'dedupe_key' => 'reward_' . $uid . '_' . md5(
				$source . '|' . ( isset( $reward['type'] ) ? $reward['type'] : '' ) . '|'
				. ( isset( $reward['val'] ) ? $reward['val'] : '' ) . '|'
				. ( isset( $reward['code'] ) ? $reward['code'] : '' )
			),
		) );
		return ! empty( $res['ok'] );
	}

	// 契约不可用（Dispatcher 尚未接线等异常态）：回退到本模块原有的自实现路径，
	// 保证「通知」这个动作不因架构改造而丢失。
	return zhiji_reward_notify_legacy( $uid, $reward, $source, $channels );
}

/**
 * 契约不可用时的降级路径（改造前的原实现，逐字保留）
 *
 * 保留而非删除的原因：P3 是唯一有行为变更的阶段，任何一处判断失误都可能
 * 让用户**收不到奖励通知**（比重复通知严重得多）。留一条不依赖任何新代码的
 * 通路，是这类重构的安全底线。
 *
 * @param int    $uid
 * @param array  $reward
 * @param string $source
 * @param array  $channels 已按开关过滤好的渠道列表
 * @return bool
 */
function zhiji_reward_notify_legacy( $uid, $reward, $source = '', array $channels = array() ) {
	if ( empty( $channels ) ) {
		$channels = array( 'msg', 'mail' );
	}
	$ok = false;
	if ( in_array( 'msg', $channels, true ) && $uid ) {
		$ok = zhiji_reward_notify_msg( $uid, $reward, $source ) || $ok;
	}
	if ( in_array( 'mail', $channels, true ) ) {
		$user = get_userdata( $uid );
		if ( $user && is_email( $user->user_email ) && ! stristr( $user->user_email, '@no' ) ) {
			$ok = zhiji_reward_notify_mail( $user, $reward, $source ) || $ok;
		}
	}
	return $ok;
}

/**
 * 生成奖励文本摘要（用于站内通知 / 邮件正文的奖励行）。
 *
 * @param array $reward
 * @return string
 */
function zhiji_reward_notify_desc_text( $reward ) {
	// 防御：本函数也可能被邮件模板等处直接调用，这里同样做一次 value→val 归一化
	if ( is_array( $reward ) && isset( $reward['value'] ) && ! isset( $reward['val'] ) ) {
		$reward['val'] = $reward['value'];
	}
	$type = isset( $reward['type'] ) ? $reward['type'] : '';
	$val  = isset( $reward['val'] ) ? $reward['val'] : '';
	$name = isset( $reward['name'] ) ? $reward['name'] : ( isset( $reward['desc'] ) ? $reward['desc'] : '' );
	$unit = isset( $reward['unit'] ) ? (string) $reward['unit'] : '';
	switch ( $type ) {
		case 'points':
			if ( '' === $val || null === $val ) {
				return __( '积分奖励', 'zhiji' );
			}
			return sprintf( __( '积分 +%s', 'zhiji' ), $val );
		case 'balance':
			return sprintf( __( '余额 +¥%s', 'zhiji' ), number_format( (float) $val, 2 ) );
		case 'vip':
			if ( '' === $val || null === $val ) {
				return __( '会员权益奖励', 'zhiji' );
			}
			return sprintf( __( '会员权益 +%s 天', 'zhiji' ), $val );
		case 'coupon':
			return sprintf( __( '优惠码：%s（%s）', 'zhiji' ), isset( $reward['code'] ) ? $reward['code'] : $val, isset( $reward['desc'] ) ? $reward['desc'] : '' );
		case 'free':
			// 文案口语化（2026-09-27）：原「免单券：%s（本单全额免费）」偏书面
			return sprintf( __( '免单券 %s（下单不用付钱）', 'zhiji' ), isset( $reward['code'] ) ? $reward['code'] : $val );
		case 'experience':
			if ( '' === $val || null === $val ) {
				return __( '经验奖励', 'zhiji' );
			}
			return sprintf( __( '经验 +%s', 'zhiji' ), $val );
		default:
			// zhiji 修复（2026-09-23）：原实现只输出「name：val」，调用方若只传 unit/desc
			// 或未传 name，会渲染成「：5」这类残缺文案（用户反馈「奖励了什么？没有」）。
			// 这里按 unit → name → val → 兜底 依次降级，保证永不出现空奖励文案。
			if ( '' !== $unit ) {
				return '' !== $val ? sprintf( __( '%s +%s', 'zhiji' ), $unit, $val ) : $unit;
			}
			if ( '' !== $name ) {
				return '' !== $val ? sprintf( __( '%s：%s', 'zhiji' ), $name, $val ) : $name;
			}
			if ( '' !== $val ) {
				return (string) $val;
			}
			return __( '奖励', 'zhiji' );
	}
}

/**
 * 站内系统通知（复用父主题 ZibMsg）。
 *
 * @param int    $uid
 * @param array  $reward
 * @param string $source
 * @return bool
 */
function zhiji_reward_notify_msg( $uid, $reward, $source = '' ) {
	if ( ! class_exists( 'ZibMsg' ) ) {
		return false;
	}
	// 来源文案
	$src_text = '';
	if ( $source ) {
		$src_text = zhiji_reward_notify_source_text( $source );
	}
	$src_prefix = $src_text ? '【' . $src_text . '】' : '';

	$title = $src_prefix . __( '您获得的奖励已到账', 'zhiji' );
	if ( isset( $reward['code'] ) && $reward['code'] ) {
		// 有优惠码/免单券：标题点明到账，正文含高亮码（CouponHighlight 负责复制高亮）
		$title  = $src_prefix . __( '您的奖励优惠券已到账', 'zhiji' );
		$content = sprintf(
			__( '恭喜！您获得了一份%s：【%s】，请在有效期内使用。', 'zhiji' ),
			isset( $reward['desc'] ) ? $reward['desc'] : __( '奖励', 'zhiji' ),
			esc_html( $reward['code'] )
		);
	} else {
		// 无优惠码：奖励数值用【...】标记（前端JS替换成高亮span，因ZibMsg过滤HTML）
		$content = sprintf(
			__( '恭喜！您获得了一份奖励：【%s】。', 'zhiji' ),
			zhiji_reward_notify_desc_text( $reward )
		);
	}
	// 附来源说明（友好文案）
	if ( $src_text ) {
		$content .= '（来自：' . $src_text . '）';
	}

	Zhiji_Adapter::msg_add(
		array(
			'send_user'    => 'admin',
			'receive_user' => $uid,
			'type'         => 'system',
			'title'        => $title,
			'content'      => $content,
			'meta'         => '',
			'other'        => '',
		)
	);
	return true;
}

/**
 * 来源友好文案映射。
 *
 * @param string $source
 * @return string
 */
function zhiji_reward_notify_source_text( $source ) {
	$map = array(
		'comment_fortune'      => __( '评论福袋', 'zhiji' ),
		'comment_fortune_free' => __( '评论福袋免单券', 'zhiji' ),
		'lottery'              => __( '每日抽奖', 'zhiji' ),
		'direct'               => __( '挽留弹窗福利', 'zhiji' ),
		'ref_bonus'            => __( '分享奖励', 'zhiji' ),
		'quiz'                 => __( '互动答题', 'zhiji' ),
		'daily_task'           => __( '每日任务', 'zhiji' ),
		'credit_task'          => __( '成长任务', 'zhiji' ),
	);
	return isset( $map[ $source ] ) ? $map[ $source ] : '';
}

/**
 * 发送品牌卡片奖励邮件。
 *
 * @param WP_User $user
 * @param array   $reward
 * @param string  $source
 * @return bool
 */
function zhiji_reward_notify_mail( $user, $reward, $source = '' ) {
	$site  = get_bloginfo( 'name' );
	$email = $user->user_email;
	$name  = $user->display_name ? $user->display_name : $user->user_login;

	$reward_text = zhiji_reward_notify_desc_text( $reward );
	$has_code    = ! empty( $reward['code'] );

	// 标题：后台可自定义，默认带奖励类型
	$subject = (string) zhiji_get_option( 'reward_notify_mail_title', '' );
	if ( '' === trim( $subject ) ) {
		$subject = sprintf( __( '【%s】您获得的奖励已到账', 'zhiji' ), $site );
	}
	$subject = str_replace( array( '{site}', '{reward}', '{name}' ), array( $site, $reward_text, $name ), $subject );

	// 正文：后台可自定义（占位符），否则用内置品牌模板
	$custom = (string) zhiji_get_option( 'reward_notify_mail_content', '' );
	if ( '' !== trim( $custom ) ) {
		$body = str_replace( array( '{site}', '{reward}', '{name}', '{code}' ), array( $site, $reward_text, $name, isset( $reward['code'] ) ? $reward['code'] : '' ), $custom );
		$body = nl2br( esc_html( $body ) );
	} else {
		$body = zhiji_reward_notify_mail_template( $site, $name, $reward, $source );
	}

	$headers = array( 'Content-Type: text/html; charset=UTF-8' );

	// 统一邮件发送（自动临时移除父主题 zib_get_mail_content 包装，避免双模板叠加）
	if ( zhiji_contract_available( 'Template' ) ) {
		return zhiji_mail_deliver( $email, $subject, $body );
	}

	// 兜底：手动发送（MailTemplate 模块未加载时）
	$zib_mail_priority = has_filter( 'wp_mail', 'zib_get_mail_content' );
	if ( false !== $zib_mail_priority ) {
		remove_filter( 'wp_mail', 'zib_get_mail_content', $zib_mail_priority );
	}
	$sent = Zhiji_Adapter::mail_raw( $email, $subject, $body, $headers );
	if ( false !== $zib_mail_priority ) {
		add_filter( 'wp_mail', 'zib_get_mail_content', $zib_mail_priority );
	}

	return (bool) $sent;
}

/**
 * 奖励邮件品牌模板（全内联样式，兼容主流邮件客户端）。
 *
 * @param string $site
 * @param string $name
 * @param array  $reward
 * @param string $source
 * @return string
 */
function zhiji_reward_notify_mail_template( $site, $name, $reward, $source = '' ) {
	// 统一模板引擎可用时调用它（MailTemplate 模块）
	if ( zhiji_contract_available( 'Template' ) ) {
		$has_code = ! empty( $reward['code'] );
		$code     = $has_code ? $reward['code'] : '';
		$desc     = isset( $reward['desc'] ) ? $reward['desc'] : '';
		$src_text = zhiji_reward_notify_source_text( $source );
	$mail_brand = zhiji_token_color( 'brand' ); // 邮件品牌色（PHP 注入）
		$type     = isset( $reward['type'] ) ? $reward['type'] : '';
		$val      = isset( $reward['val'] ) ? $reward['val'] : '';
		$rname    = isset( $reward['name'] ) ? $reward['name'] : '';

		// 优惠码长度自适应字号
		$code_fs = '23px'; $code_ls = '2px';
		if ( $has_code ) {
			$code_len = mb_strlen( $code );
			if ( $code_len > 16 ) { $code_fs = '17px'; $code_ls = '1px'; }
			elseif ( $code_len > 12 ) { $code_fs = '20px'; $code_ls = '1px'; }
		}

		if ( $has_code ) {
			$left_label    = '优惠内容';
			$left_content  = '<div style="font-size:20px;font-weight:800;color:#111827;margin-top:8px;line-height:1.3;">' . esc_html( $desc ? $desc : __( '专属折扣', 'zhiji' ) ) . '</div>';
			$right_label   = '优惠码 COUPON';
			$right_content = '<div style="font-size:' . $code_fs . ';font-weight:800;color:' . $mail_brand . ';letter-spacing:' . $code_ls . ';word-break:break-all;font-family:Menlo,Consolas,Monaco,monospace;">' . esc_html( $code ) . '</div>';
			$right_sub     = __( '专属折扣', 'zhiji' );
			$rule_line    = __( '* 一张券只能用一次；下单时粘贴到「优惠码」框里就能抵扣，过期作废。', 'zhiji' );
			$btn_text     = __( '立即使用优惠码 &#8594;', 'zhiji' );
			$subline      = $src_text
				? sprintf( __( '感谢您对本站的支持，这是来自「%s」的专属奖励，请在有效期内使用：', 'zhiji' ), $src_text )
				: __( '感谢您对本站的支持，这是为您准备的专属优惠码，请在有效期内使用：', 'zhiji' );
		} else {
			switch ( $type ) {
				case 'points':     $type_name='积分'; $right_val='+' . (int)$val; $right_sub='已发放至您的账户'; break;
				case 'balance':    $type_name='余额'; $right_val='¥' . number_format((float)$val,2); $right_sub='已充值至您的账户'; break;
				case 'vip':        $type_name='会员权益'; $right_val='+' . (int)$val . ' 天'; $right_sub='会员有效期已延长'; break;
				case 'experience': $type_name='经验值'; $right_val='+' . (int)$val; $right_sub='已发放至等级经验'; break;
				default:           $type_name=$rname?$rname:'奖励'; $right_val=(string)$val; $right_sub='已发放至您的账户'; break;
			}
			// 票据左大栏（模板对调后渲染 right_*）：上方小字数值标签，下方大字数值
			$right_label   = __( '奖励数值', 'zhiji' );
			$right_content = '<div style="font-size:30px;font-weight:800;color:#111827;line-height:1.2;">' . esc_html( $right_val ) . '</div>';
			// 票据右小栏（模板对调后渲染 left_*）：奖励类型
			$left_label    = __( '奖励类型', 'zhiji' );
			$left_content  = '<div style="font-size:16px;font-weight:800;color:' . $mail_brand . ';margin-top:8px;line-height:1.3;">' . esc_html( $type_name ) . '</div>';
			$rule_line     = __( '* 奖励已发放至您的账户，可在个人中心查看明细。', 'zhiji' );
			$btn_text      = __( '立即前往查看 &#8594;', 'zhiji' );
			$subline       = $src_text
				? sprintf( __( '您在「%s」中获得了以下奖励，奖励已发放至您的账户：', 'zhiji' ), $src_text )
				: __( '您获得了以下奖励，奖励已发放至您的账户：', 'zhiji' );
		}

		return zhiji_template_render( array(
			'site'                 => $site,
			'name'                 => $name,
			'headline'             => __( '您获得的奖励已到账', 'zhiji' ),
			'subline'              => $subline,
			'ticket_left_label'    => $left_label,
			'ticket_left_content'  => $left_content,
			'ticket_right_label'   => $right_label,
			'ticket_right_content' => $right_content,
			'ticket_right_sub'     => $right_sub,
			'rule_line'            => $rule_line,
			'btn_text'             => $btn_text,
		) );
	}

	// 兜底：MailTemplate 未加载时返回简单文本
	return '<div style="padding:20px;font-family:sans-serif;"><h2>' . esc_html__( '您获得的奖励已到账', 'zhiji' ) . '</h2><p>' . esc_html( zhiji_reward_notify_desc_text( $reward ) ) . '</p></div>';
}

/**
 * 注册「奖励到账」通知事件（P3：接入统一通知中心）
 *
 * 放在 init 上注册（而非文件加载时），确保在其它模块之后 ——
 * 这样 zhiji_notify_channels 过滤器链上，业务方的调整能生效。
 *
 * 频控说明：奖励通知**必须送达**，故设 0（不限频）。
 * 统一通知中心的默认是 20 条/小时，那是给勋章、签到提醒这类「可丢」的通知设计的；
 * 奖励到账若被频控吞掉，用户会看不到已到账的奖励 —— 这是丢业务信息，不是丢打扰。
 * 幂等仍保留（同一笔奖励不重复发），去重的是**重复**而非**延迟**。
 */
add_action( 'init', function () {
	zhiji_notify_register_event( 'reward_arrived', array(
		'label'       => '奖励到账',
		'title'       => __( '您获得的奖励已到账', 'zhiji' ),
		'channels'    => array( 'msg', 'mail' ),
		'toast'       => 'success',
		'mail'        => 'ticket',
		'link'        => '{user_center}',
		'dedupe_ttl'  => DAY_IN_SECONDS,
		'throttle'    => array( 0, HOUR_IN_SECONDS ),   // 0 = 不限频
		// 官方通知：站内信显示为「管理员」而非匿名系统
		'msg_send_user' => 'admin',
	) );
}, 25 );

/**
 * 后台 CSF 设置：用户&互动 → 奖励通知
 */
function zhiji_reward_notify_register_options() {
	if ( ! class_exists( 'CSF' ) || ! is_admin() ) {
		return;
	}
	Zhiji_Registry::register_options( 'reward_notify', array(
				array(
					'id'      => 'reward_notify_enabled',
					'type'    => 'switcher',
					'title'   => __( '奖励通知总开关', 'zhiji' ),
					'default' => true,
					'desc'    => __( '全站所有功能发放奖励（积分/余额/优惠码/会员/免单券）时统一发送站内系统通知 + 邮件。', 'zhiji' ),
				),
				array(
					'dependency' => array( 'reward_notify_enabled', '==', '1' ),
					'id'         => 'reward_notify_msg_enabled',
					'type'       => 'switcher',
					'title'      => __( '站内系统通知', 'zhiji' ),
					'default'    => true,
					'desc'       => __( '写入父主题消息中心（type=system，前台显示「系统」）。', 'zhiji' ),
				),
				array(
					'dependency' => array( 'reward_notify_enabled', '==', '1' ),
					'id'         => 'reward_notify_mail_enabled',
					'type'       => 'switcher',
					'title'      => __( '邮件通知', 'zhiji' ),
					'default'    => true,
					'desc'       => __( '发送品牌卡片奖励邮件到用户注册邮箱（含 Logo + 用户名 + 奖励明细 + 优惠码票据）。', 'zhiji' ),
				),
				array(
					'dependency' => array( 'reward_notify_enabled', '==', '1' ),
					'id'         => 'reward_notify_mail_title',
					'type'       => 'text',
					'title'      => __( '邮件标题（可自定义）', 'zhiji' ),
					'placeholder' => '【{site}】您获得的奖励已到账',
					'desc'       => __( '支持占位符 {site} 站点名、{reward} 奖励内容、{name} 用户名。留空用默认。', 'zhiji' ),
				),
				array(
					'dependency' => array( 'reward_notify_enabled', '==', '1' ),
					'id'         => 'reward_notify_mail_content',
					'type'       => 'textarea',
					'title'      => __( '邮件正文（可自定义）', 'zhiji' ),
					'rows'       => 5,
					'placeholder' => '恭喜！您获得了一份奖励：{reward}',
					'sanitize'   => false,
					'desc'       => __( '支持占位符 {site} {reward} {name} {code}。留空使用内置精美品牌模板。', 'zhiji' ),
				),
				array(
					'type'    => 'content',
					'content' => __( '说明：本模块为全站奖励通知的统一入口；评论福袋等已接入；抽奖、挽留弹窗/裂变奖励沿用各自已有的站内通知+邮件。', 'zhiji' ),
				),
			), 30 );
}
// 2026-09-26：改为 Registry 统一登记（P3-⑨），此处直接调用替代钩子
zhiji_reward_notify_register_options();


/**
 * 前端：消息中心奖励数值高亮（ZibMsg 过滤 HTML，故用 JS 把【...】替换成高亮 span）。
 */
zhiji_footer_add( 'reward-notify-hl', 'zhiji_reward_notify_frontend_highlight', 10 );
function zhiji_reward_notify_frontend_highlight() {
	if ( ! zhiji_get_option( 'reward_notify_enabled', 1 ) ) {
		return;
	}
	?>
	<script>
	(function(){
		var HL_COLOR = (getComputedStyle(document.documentElement).getPropertyValue('--zhiji-brand') || '#2e7cf6').trim();
		var HL_RE = /【([^】]{1,50})】/g;
		function makeSpan(text) {
			var s = document.createElement('span');
			s.style.cssText = 'display:inline-block;padding:3px 12px;margin:0 3px;border-radius:8px;background:#f0f7ff;color:'+HL_COLOR+';font-weight:700;border:1px dashed #c9a;font-size:14px;';
			s.textContent = text;
			return s;
		}
		function highlightTextNode(node) {
			if(!node.nodeValue || node.nodeValue.indexOf('【') < 0) return;
			var frag = document.createDocumentFragment();
			var last = 0;
			node.nodeValue.replace(HL_RE, function(m, g1, offset){
				if(offset > last) frag.appendChild(document.createTextNode(node.nodeValue.slice(last, offset)));
				frag.appendChild(makeSpan(g1));
				last = offset + m.length;
				return m;
			});
			if(last < node.nodeValue.length) frag.appendChild(document.createTextNode(node.nodeValue.slice(last)));
			node.parentNode.replaceChild(frag, node);
		}
		function doHighlight(root) {
			if(!root) root = document;
			var selectors = '.msg-center-content, .message-content, .zib-msg-content, .msg-detail, [class*=message] [class*=content]';
			root.querySelectorAll(selectors).forEach(function(box){
				if (box.textContent.indexOf('【') < 0) return;
				if (box.getAttribute('data-zhiji-highlighted')) {
					// 已处理过但仍含【：说明父主题 AJAX 刷新了该容器内容（返回列表再打开），需重新高亮
					box.removeAttribute('data-zhiji-highlighted');
				}
				box.setAttribute('data-zhiji-highlighted','1');
				var walker = document.createTreeWalker(box, NodeFilter.SHOW_TEXT, null);
				var nodes = [];
				while(walker.nextNode()) nodes.push(walker.currentNode);
				nodes.forEach(highlightTextNode);
			});
		}
		function highlightRewards() { doHighlight(document); }
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', highlightRewards);
		} else {
			highlightRewards();
		}
		[500, 1200, 2500, 4000].forEach(function(t){ setTimeout(highlightRewards, t); });
		if (typeof MutationObserver !== 'undefined') {
			var observer = new MutationObserver(function(mutations){
				mutations.forEach(function(m){
					if (m.addedNodes && m.addedNodes.length) {
						doHighlight(document);
					}
				});
			});
			observer.observe(document.body, { childList: true, subtree: true, characterData: true });
		}
	})();
	</script>
	<?php
}

/* ============================================================
 * Dispatcher 契约实现（P3：RewardNotify 与 notify/ 双轨合并）
 * ------------------------------------------------------------
 * 双轨指的是：改造前存在**两套并行的通知编排** ——
 *   轨道 A：notify/Notify.php 的 zhiji_notify()   —— 有幂等 / 频控 / 异常兜底 / 环形日志
 *   轨道 B：本模块的 zhiji_reward_notify()         —— 自己判断开关、自己发、无任何保护
 * 奖励通知走的是轨道 B，于是拿不到轨道 A 的四项能力，也无法被统一观测。
 *
 * 本类 = 轨道 B 接到轨道 A 上的适配器：
 *   · dispatch()  把「奖励」翻译成通知中心的入参（title / content / body_html）
 *   · 文案渲染仍留在本模块（见 msg_body / mail_body），不丢品牌票据排版
 * ============================================================ */

if ( ! class_exists( 'Zhiji_Dispatcher_RewardNotify' ) ) :

	/**
	 * 奖励通知投递（实现 Zhiji_Contract_Dispatcher）
	 */
	class Zhiji_Dispatcher_RewardNotify implements Zhiji_Contract_Dispatcher {

		/**
		 * 投递
		 *
		 * @param array $payload {
		 *     @type int    $uid        接收人
		 *     @type string $event      事件名（默认 reward_arrived）
		 *     @type array  $channels   指定渠道；空 = 用事件表默认
		 *     @type array  $vars       业务变量 { reward, source }
		 *     @type string $dedupe_key 幂等键
		 * }
		 * @return array{ok:bool,sent:int,skip:int,message:string}
		 */
		public function dispatch( array $payload ) {
			$payload = array_merge( array(
				'uid'        => 0,
				'event'      => 'reward_arrived',
				'channels'   => array(),
				'vars'       => array(),
				'dedupe_key' => '',
			), $payload );

			$uid    = (int) $payload['uid'];
			$reward = isset( $payload['vars']['reward'] ) ? (array) $payload['vars']['reward'] : array();
			$source = isset( $payload['vars']['source'] ) ? (string) $payload['vars']['source'] : '';

			if ( ! $uid || empty( $reward ) ) {
				return $this->ret( false, 0, 0, __( '缺少接收人或奖励数据', 'zhiji' ) );
			}
			if ( ! function_exists( 'zhiji_notify' ) ) {
				return $this->ret( false, 0, 0, __( '通知中心未就绪', 'zhiji' ) );
			}

			$src_text = zhiji_reward_notify_source_text( $source );
			$prefix   = $src_text ? '【' . $src_text . '】' : '';
			$has_code = ! empty( $reward['code'] );

			// 渠道：调用方显式指定优先（已含 RewardNotify 的两个开关过滤结果）
			$channels = (array) $payload['channels'];
			if ( empty( $channels ) ) {
				$channels = $uid ? array( 'msg', 'mail' ) : array( 'mail' );
			}

			// 邮件正文：只有真的要走 mail 渠道时才渲染（渲染有成本）
			$body_html = '';
			if ( in_array( 'mail', $channels, true ) ) {
				$user = get_userdata( $uid );
				if ( $user && is_email( $user->user_email ) && stristr( $user->user_email, '@no' ) === false ) {
					$body_html = $this->mail_body( $user, $reward, $source );
				}
			}

			$args = array(
				'user_id'    => $uid,
				'title'      => $prefix . ( $has_code
					? __( '您的奖励优惠券已到账', 'zhiji' )
					: __( '您获得的奖励已到账', 'zhiji' ) ),
				'content'    => $this->msg_body( $reward, $src_text ),
				'data'       => $has_code
					? array(
						__( '优惠内容', 'zhiji' ) => isset( $reward['desc'] ) ? $reward['desc'] : '',
						__( '优惠码', 'zhiji' )     => (string) $reward['code'],
					)
					: array( __( '奖励', 'zhiji' ) => zhiji_reward_notify_desc_text( $reward ) ),
				'send_user'  => 'admin',   // 官方通知：显示为管理员而非匿名系统
				'dedupe_key' => (string) $payload['dedupe_key'],
			);
			if ( $body_html ) {
				$args['body_html'] = $body_html;   // Mail 渠道据此直通，不套通用票据
			}

			$result = zhiji_notify( (string) $payload['event'], $args );

			$sent = 0;
			foreach ( (array) $result['sent'] as $per_user ) {
				$sent += count( array_filter( (array) $per_user ) );
			}

			return $this->ret(
				$sent > 0,
				$sent,
				count( (array) $result['skipped'] ),
				$sent > 0 ? __( '投递成功', 'zhiji' ) : __( '全部渠道未成功', 'zhiji' )
			);
		}

		/**
		 * 某渠道当前是否可用
		 *
		 * @param string $channel msg / mail
		 * @return bool
		 */
		public function channel_enabled( $channel ) {
			$channel = (string) $channel;
			if ( 'msg' === $channel ) {
				return (bool) zhiji_get_option( 'reward_notify_msg_enabled', 1 ) && class_exists( 'ZibMsg' );
			}
			if ( 'mail' === $channel ) {
				return (bool) zhiji_get_option( 'reward_notify_mail_enabled', 1 ) && zhiji_contract_available( 'Template' );
			}
			return false;
		}

		/**
		 * 站内信正文（逐字保留改造前的文案与「【】」高亮标记契约）
		 *
		 * 【】是本模块前端 JS（zhiji_reward_notify_frontend_highlight）的高亮锚点：
		 * ZibMsg 会过滤 HTML，故正文里用【】标记、由前端替换成高亮 span —— 不可改。
		 *
		 * @param array  $reward
		 * @param string $src_text 已归一的中文来源名
		 * @return string
		 */
		private function msg_body( array $reward, $src_text = '' ) {
			if ( ! empty( $reward['code'] ) && $reward['code'] ) {
				$content = sprintf(
					__( '恭喜！您获得了一份%s：【%s】，请在有效期内使用。', 'zhiji' ),
					isset( $reward['desc'] ) ? $reward['desc'] : __( '奖励', 'zhiji' ),
					esc_html( $reward['code'] )
				);
			} else {
				$content = sprintf(
					__( '恭喜！您获得了一份奖励：【%s】。', 'zhiji' ),
					zhiji_reward_notify_desc_text( $reward )
				);
			}
			if ( $src_text ) {
				$content .= '（来自：' . $src_text . '）';
			}
			return $content;
		}

		/**
		 * 邮件正文（复用本模块既有的品牌模板，逐字保留）
		 *
		 * 保留原因：奖励邮件的票据排版（奖励数值大字 / 优惠码等宽字体 /
		 * 到期提示 / 来源副文案）比通知中心的通用票据更适合「到账通知」场景，
		 * 换掉会丢失这些细节。
		 *
		 * @param \WP_User $user
		 * @param array    $reward
		 * @param string   $source
		 * @return string
		 */
		private function mail_body( $user, array $reward, $source = '' ) {
			return (string) zhiji_reward_notify_mail_template(
				get_bloginfo( 'name' ),
				$user->display_name ? $user->display_name : $user->user_login,
				$reward,
				$source
			);
		}

		/**
		 * 统一返回结构
		 *
		 * @param bool   $ok
		 * @param int    $sent
		 * @param int    $skip
		 * @param string $message
		 * @return array
		 */
		private function ret( $ok, $sent, $skip, $message ) {
			return array(
				'ok'      => (bool) $ok,
				'sent'    => (int) $sent,
				'skip'    => (int) $skip,
				'message' => (string) $message,
			);
		}
	}

endif;

/**
 * 契约工厂：供 ContractRegistry 解析（工厂名规则 zhiji_contract_implementor_{模块key}）
 *
 * @return Zhiji_Contract_Dispatcher
 */
function zhiji_contract_implementor_reward_notify() {
	static $impl = null;
	if ( null === $impl ) {
		$impl = new Zhiji_Dispatcher_RewardNotify();
	}
	return $impl;
}
