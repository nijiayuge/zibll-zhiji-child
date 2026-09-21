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

add_action( 'after_setup_theme', 'zhiji_reward_notify_register_options', 20 );

/**
 * 统一奖励通知入口。
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
	$ok = false;

	// 1. 站内系统通知（仅登录用户）
	if ( zhiji_get_option( 'reward_notify_msg_enabled', 1 ) && $uid ) {
		$ok = zhiji_reward_notify_msg( $uid, $reward, $source ) || $ok;
	}

	// 2. 邮件通知（需有效邮箱）
	if ( zhiji_get_option( 'reward_notify_mail_enabled', 1 ) ) {
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
	$type = isset( $reward['type'] ) ? $reward['type'] : '';
	$val  = isset( $reward['val'] ) ? $reward['val'] : '';
	$name = isset( $reward['name'] ) ? $reward['name'] : '';
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
			return sprintf( __( '免单券：%s（本单全额免费）', 'zhiji' ), isset( $reward['code'] ) ? $reward['code'] : $val );
		case 'experience':
			if ( '' === $val || null === $val ) {
				return __( '经验奖励', 'zhiji' );
			}
			return sprintf( __( '经验 +%s', 'zhiji' ), $val );
		default:
			return sprintf( __( '%s：%s', 'zhiji' ), $name, $val );
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
	$mail_brand = zhiji_token_color( 'brand' ); // 邮件品牌色（PHP 注入）
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
		'bargain'              => __( '砍价成功奖励', 'zhiji' ),
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
	if ( function_exists( 'zhiji_mail_send' ) ) {
		return zhiji_mail_send( $email, $subject, $body );
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
	if ( function_exists( 'zhiji_mail_template_render' ) ) {
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
			$rule_line    = __( '* 该优惠码仅可使用一次，结算时输入即可抵扣，逾期自动失效。', 'zhiji' );
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

		return zhiji_mail_template_render( array(
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
 * 后台 CSF 设置：用户&互动 → 奖励通知
 */
function zhiji_reward_notify_register_options() {
	if ( ! class_exists( 'CSF' ) || ! is_admin() ) {
		return;
	}
	Zhiji_Registry::csf_section_for_legacy( 'reward_notify',
		array(
			'parent' => 'zhiji_user',
			'priority' => 30,
			'title'  => __( '奖励通知', 'zhiji' ),
			'icon'   => 'fa fa-fw fa-bell',
			'fields' => array(
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
					'content' => __( '说明：本模块为全站奖励通知的统一入口；评论福袋、砍价等已接入；抽奖、挽留弹窗/裂变奖励沿用各自已有的站内通知+邮件。', 'zhiji' ),
				),
			),
		)
	);
}

/**
 * 前端：消息中心奖励数值高亮（ZibMsg 过滤 HTML，故用 JS 把【...】替换成高亮 span）。
 */
add_action( 'wp_footer', 'zhiji_reward_notify_frontend_highlight' );
function zhiji_reward_notify_frontend_highlight() {
	if ( ! zhiji_get_option( 'reward_notify_enabled', 1 ) ) {
		return;
	}
	?>
	<script>
	(function(){
		var HL_COLOR = (getComputedStyle(document.documentElement).getPropertyValue('--zhiji-brand') || '#2e7cf6').trim();
		var HL_RE = /【([^】]{1,50})】/g;
		var makeSpan=(text){
			var s = document.createElement('span');
			s.style.cssText = 'display:inline-block;padding:3px 12px;margin:0 3px;border-radius:8px;background:#f0f7ff;color:'+HL_COLOR+';font-weight:700;border:1px dashed #c9a;font-size:14px;';
			s.textContent = text;
			return s;
		}
		var highlightTextNode=(node){
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
		var doHighlight=(root){
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
		var highlightRewards=(){ doHighlight(document); }
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
