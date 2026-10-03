<?php
/**
 * @module  CommentFortune
 * @desc    热评锦鲤：评论抽福袋奖励积分
 * @option  comment_fortune_enabled  总开关
 * @hook    comment_post · 评论后判定
 * @hook    wp_ajax_zhiji_comment_fortune_check · 签到式领取
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/CommentFortune.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('comment_fortune', array(
    'title'    => '评论福袋',
    'parent'   => 'zhiji_comment',
    'priority' => 110,
    'option'   => 'comment_fortune_enabled',
));

/** 领取记录表（ClaimLog）中本功能使用的场景标识（用户维度） */
if ( ! defined( 'ZHIJI_COMMENT_FORTUNE_CLAIM_SCENE' ) ) {
	define( 'ZHIJI_COMMENT_FORTUNE_CLAIM_SCENE', 'comment_fortune' );
}



// 安全门
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ===================== 钩子注册 ===================== */

add_action( 'comment_post', 'zhiji_comment_fortune_on_comment', 10, 3 );
zhiji_footer_add( 'comment-fortune', 'zhiji_comment_fortune_footer', 10 );
// 2026-09-26：注册到网关（P2-⑥），旧端点保留为转发入口
// 2026-09-27：补齐旧端点转发入口 —— 前端 JS 直调 action=zhiji_comment_fortune_check，
//             缺此注册时 admin-ajax 找不到处理器会返回 **HTTP 400**（控制台报错、福袋查询静默失效）。
//             public=true：处理器自身对游客返回 {fortune:false}（只读本人标记，无数据暴露），
//             这样游客也不会看到 401/400 的控制台错误。
zhiji_api_register( 'zhiji_comment_fortune_check', 'zhiji_comment_fortune_ajax_check', true, '' );
add_action( 'wp_ajax_zhiji_comment_fortune_check', 'zhiji_api_legacy_forward' );
add_action( 'wp_ajax_nopriv_zhiji_comment_fortune_check', 'zhiji_api_legacy_forward' );

/**
 * 评论落库后：若命中福袋位且作者已登录，随机发放奖励并写入一次性标记。
 *
 * @param int        $comment_id       评论 id
 * @param int|string $comment_approved 审核状态
 * @param array      $commentdata      评论数据
 */
function zhiji_comment_fortune_on_comment( $comment_id, $comment_approved, $commentdata ) {
	if ( ! zhiji_get_option( 'comment_fortune_enabled', false ) ) {
		return;
	}
	$uid = get_current_user_id();
	if ( ! $uid ) {
		return; // 仅登录用户可参与福袋
	}

	$every = max( 2, min( 100, (int) zhiji_get_option( 'comment_fortune_every', 20 ) ) );

	$count = zhiji_comment_fortune_today_count();
	if ( $count < 1 || 0 !== ( $count % $every ) ) {
		return;
	}

	zhiji_comment_fortune_dispatch( $uid, $count );
}

/**
 * 发放一个福袋：持久化「待领取」记录 + 奖励通知
 *
 * 2026-09-27：从 on_comment 抽出为独立函数，便于直接调用与联调（不必真发评论）。
 *
 * @param int $uid   用户 id
 * @param int $count 当日评论序位（第 N 条命中福袋位）
 * @return bool 是否发放成功
 */
function zhiji_comment_fortune_dispatch( $uid, $count ) {
	$uid   = (int) $uid;
	$count = (int) $count;
	if ( $uid <= 0 ) {
		return false;
	}

	$reward = zhiji_comment_fortune_grant( $uid );
	if ( ! is_array( $reward ) ) {
		return false;
	}

	$text = zhiji_comment_fortune_pick_text();

	set_transient(
		'zhiji_comment_fortune_' . $uid,
		array(
			'n'      => $count,
			'reward' => $reward,
			'text'   => $text,
		),
		2 * HOUR_IN_SECONDS
	);

	// 持久化「待领取」记录：transient 只活 2 小时，过期后用户就再也看不到中奖弹窗；
	// 落库后运维台可查询、可补发（详见 core/ClaimLog.php 与 admin/scenes/CommentFortune.php）
	zhiji_comment_fortune_log_claim( $uid, $count, $reward, $text );

	// 统一奖励通知：站内系统通知 + 邮件（RewardNotify 模块，可后台开关）
	if ( function_exists( 'zhiji_reward_notify' ) ) {
		$notify_source = ( 'free' === $reward['type'] ) ? 'comment_fortune_free' : 'comment_fortune';
		zhiji_reward_notify( $uid, $reward, $notify_source );
	}


	return true;
}

/**
 * 消费用户的福袋标记（用户打开页面领到弹窗时调用）
 *
 * 2026-09-27：从 ajax_check 抽出，便于直测；同时把领取动作落到记录表（active → 已领取）。
 *
 * @param int $uid 用户 id
 * @return array|null 命中时返回标记数据（n / reward / text），否则 null
 */
function zhiji_comment_fortune_consume( $uid ) {
	$uid = (int) $uid;
	if ( $uid <= 0 ) {
		return null;
	}
	$flag = get_transient( 'zhiji_comment_fortune_' . $uid );
	if ( ! is_array( $flag ) || empty( $flag['n'] ) ) {
		return null;
	}
	delete_transient( 'zhiji_comment_fortune_' . $uid );

	zhiji_comment_fortune_log_consumed( $uid );
	return $flag;
}

/**
 * 福袋记录落库（scene=comment_fortune，**用户维度**而非邮箱维度）
 *
 * @param int    $uid    用户 id
 * @param int    $count  当日评论序位
 * @param array  $reward 奖励数据
 * @param string $text   文案
 * @return int|false 记录 ID
 */
function zhiji_comment_fortune_log_claim( $uid, $count, $reward, $text ) {
	// 2026-09-28：原 `comment_fortune_log_enabled` 开关已按「开关评估 A6」移除，**恒记录**。
	// 理由：中奖弹窗标记原本只活在 2 小时 transient 里 —— 用户那段时间没打开页面，
	// 弹窗就永久丢失（奖励已到账但用户无感知），运维侧也查不到、无法补发。
	// 该持久化正是"中奖可查、可补发"能力的唯一基础，不应留下一个能把它关掉的开关。
	if ( ! function_exists( 'zhiji_claim_log_add' ) ) {
		return false;
	}
	return zhiji_claim_log_add( array(
		'scene'     => ZHIJI_COMMENT_FORTUNE_CLAIM_SCENE,
		'user_id'   => (int) $uid,
		'object_id' => isset( $reward['code'] ) ? (string) $reward['code'] : '',
		'source'    => isset( $reward['type'] ) ? (string) $reward['type'] : '',
		'note'      => sprintf( '第 %d 位锦鲤', (int) $count ),
		'meta'      => array(
			'n'      => (int) $count,
			'reward' => $reward,
			'text'   => $text,
		),
	) );
}

/**
 * 把该用户最新一条「待领取」记录标记为已领取（active → cleared，保留审计）
 *
 * @param int $uid 用户 id
 * @return int 影响行数
 */
function zhiji_comment_fortune_log_consumed( $uid ) {
	if ( ! function_exists( 'zhiji_claim_log_query' ) ) {
		return 0;
	}
	$q = zhiji_claim_log_query( array(
		'scene'    => ZHIJI_COMMENT_FORTUNE_CLAIM_SCENE,
		'user_id'  => (int) $uid,
		'status'   => 'active',
		'per_page' => 1,
	) );
	if ( empty( $q['rows'] ) ) {
		return 0;
	}
	$ret = zhiji_claim_log_clear( array(
		'ids'  => array( (int) $q['rows'][0]->id ),
		'mode' => 'reset',
		'note' => '用户已领取弹窗',
		'by'   => 0,
	) );
	return (int) $ret['affected'];
}

/**
 * 当日已批准评论总数（统计口径与父主题评论体系一致）。
 *
 * @return int
 */
function zhiji_comment_fortune_today_count() {
	global $wpdb;
	$midnight = strtotime( 'today midnight' );
	if ( false === $midnight ) {
		return 0;
	}
	$sql = $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = '1' AND comment_date_gmt >= %s",
		gmdate( 'Y-m-d H:i:s', $midnight )
	);
	return (int) $wpdb->get_var( $sql );
}

/**
 * 按后台权重随机发放奖励。
 *
 * @param int $uid 用户 id
 * @return array|null {type, name, val, desc, code?}
 */
function zhiji_comment_fortune_grant( $uid ) {
	/**
	 * v1.9.4 迁移：统一调用 RewardCenter 奖励中心发放随机奖励。
	 * 奖励类型/权重/参数均在「用户&互动 → 奖励中心」统一配置，
	 * 本函数不再各自维护积分区间/余额区间/优惠码面值等配置。
	 * 返回格式保持兼容：{type,name,val,desc,code?}
	 */
	if ( function_exists( 'zhiji_reward_center_grant_random' ) ) {
		$reward = zhiji_reward_center_grant_random( $uid, 'comment_fortune', array(
			// 2026-09-27：传 desc，使用户中心余额/积分记录的「说明」显示业务文案
			//（v1 原为「热评锦鲤奖励」，v2 收口奖励中心时丢失，只剩「来源：comment_fortune」）
			'desc' => __( '热评锦鲤奖励', 'zhiji' ),
		) );
		if ( $reward && is_array( $reward ) ) {
			return $reward;
		}
	}
	// 兜底：RewardCenter 不可用时保底发积分（锦鲤福袋必有奖励，无「谢谢参与」）
	$val = wp_rand( 10, 50 );
	Zhiji_Adapter::update_user_points( $uid, array( 'value' => $val, 'type' => '评论福袋', 'desc' => '热评锦鲤奖励' ) );
	return array(
		'type' => 'points',
		'name' => '积分',
		'val'  => $val,
		'desc' => '已发放至账户积分',
	);
}

function zhiji_comment_fortune_pick_text() {
	$custom = zhiji_get_option( 'comment_fortune_texts', '' );
	if ( is_string( $custom ) && '' !== trim( $custom ) ) {
		$lines = array_filter( array_map( 'trim', explode( "\n", $custom ) ), 'strlen' );
		if ( ! empty( $lines ) ) {
			return $lines[ array_rand( $lines ) ];
		}
	}
	$defaults = array(
		'锦鲤护体，好运常伴～',
		'今天的手气也太好了吧！',
		'评论区欧皇就是你！',
		'好运开场，快去逛逛今天的资源～',
		'这条锦鲤告诉你：好资源都在知集等你！',
	);
	return $defaults[ array_rand( $defaults ) ];
}

/**
 * admin-ajax：查询当前登录用户的福袋标记（只读本人，消费后删除）。
 */
function zhiji_comment_fortune_ajax_check() {
	// 0. 应急开关（2026-09-29）：一键暂停前台互动 —— 置于一切校验之前
	if ( function_exists( 'zhiji_ops_kill_active' ) && zhiji_ops_kill_active() ) {
		wp_send_json_error( array( 'msg' => __( '应急模式已开启，互动功能暂停', 'zhiji' ) ), 503 );
	}
	if ( ! is_user_logged_in() ) {
		wp_send_json_success( array( 'fortune' => false ) );
	}
	$uid  = get_current_user_id();
	$flag = zhiji_comment_fortune_consume( $uid );
	if ( is_array( $flag ) && ! empty( $flag['n'] ) ) {
		wp_send_json_success(
			array(
				'fortune' => true,
				'n'       => (int) $flag['n'],
				'reward'  => isset( $flag['reward'] ) ? $flag['reward'] : array( 'type' => 'points', 'name' => '积分', 'val' => 0, 'desc' => '' ),
				'text'    => isset( $flag['text'] ) ? esc_html( $flag['text'] ) : '',
			)
		);
	}
	wp_send_json_success( array( 'fortune' => false ) );
}

/**
 * 前台输出福袋检查脚本（仅登录用户；开关关闭不输出）。
 */
function zhiji_comment_fortune_footer() {
	if ( ! zhiji_get_option( 'comment_fortune_enabled', false ) ) {
		return;
	}
	if ( ! is_user_logged_in() ) {
		return;
	}
	$ajax_url = admin_url( 'admin-ajax.php' );
	$nonce    = wp_create_nonce( 'zhiji_comment_fortune' );
	?>
	<style>
	.zhiji-cf-mask{position:fixed;inset:0;z-index:999999;background:rgba(20,10,40,.55);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:20px}
	.zhiji-cf-box{position:relative;max-width:420px;width:100%;background:linear-gradient(160deg,#fff,#f4f0ff);border:1px solid #e5dcff;border-radius:22px;padding:34px 28px 26px;text-align:center;box-shadow:0 20px 60px rgba(109,94,252,.35);animation:zhijiCfIn .35s ease;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"PingFang SC","Microsoft YaHei",sans-serif}
	@keyframes zhijiCfIn{from{opacity:0;transform:translateY(14px) scale(.96)}to{opacity:1;transform:none}}
	.zhiji-cf-emoji{font-size:52px;line-height:1}
	.zhiji-cf-title{font-size:19px;font-weight:700;color:var(--zhiji-brand, #2e7cf6);margin-top:12px}
	.zhiji-cf-desc{margin-top:8px;font-size:13.5px;color:#6b7280;line-height:1.7}
	.zhiji-cf-reward{margin-top:16px;background:#fff;border:1px solid #efe9ff;border-radius:14px;padding:16px 14px;font-size:15px;color:#333;line-height:1.8}
	.zhiji-cf-reward b{color:#f5a623}
	.zhiji-cf-code{margin-top:8px;display:inline-block;background:var(--zhiji-surface-soft,#eaf2fe);border:1px dashed var(--zhiji-border,#dce6f5);color:var(--zhiji-brand, #2e7cf6);font-weight:700;font-family:ui-monospace,Consolas,monospace;font-size:16px;letter-spacing:1px;padding:8px 16px;border-radius:10px;cursor:pointer;user-select:all}
	.zhiji-cf-code:hover{background:#ece4ff}
	.zhiji-cf-tip{font-size:11.5px;color:#a5a0b8;margin-top:6px}
	.zhiji-cf-text{margin-top:12px;font-size:14px;color:#333;background:var(--zhiji-surface-soft,#eaf2fe);border-radius:10px;padding:10px 14px;line-height:1.7}
	.zhiji-cf-btn{margin-top:18px;display:inline-block;padding:9px 26px;border:none;border-radius:999px;background:var(--zhiji-brand, #2e7cf6);color:#fff;font-size:14px;font-weight:600;cursor:pointer;box-shadow:0 8px 20px rgba(0,0,0,.15)}
	.zhiji-cf-btn:hover{filter:brightness(1.06)}
	</style>
	<script>
	(function(){
		if (!window.fetch) return;
		fetch('<?php echo esc_url_raw( $ajax_url ); ?>', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'action=zhiji_comment_fortune_check&nonce=' + encodeURIComponent('<?php echo esc_js( $nonce ); ?>')
		}).then(function(r){ return r.json(); }).then(function(res){
			if (!res || !res.success || !res.data || !res.data.fortune) return;
			var d = res.data, rw = d.reward || {};
			var mask = document.createElement('div');
			mask.className = 'zhiji-cf-mask';
			var box = document.createElement('div');
			box.className = 'zhiji-cf-box';
			var emoji = document.createElement('div');
			emoji.className = 'zhiji-cf-emoji';
			emoji.textContent = rw.type === 'coupon' ? '\u{1F3AB}' : (rw.type === 'vip' ? '\u{1F451}' : (rw.type === 'free' ? '\u{1F3AB}' : '\u{1F389}'));
			var t = document.createElement('div');
			t.className = 'zhiji-cf-title';
			t.textContent = '恭喜你成为今日第 ' + d.n + ' 位锦鲤！';
			var desc = document.createElement('div');
			desc.className = 'zhiji-cf-desc';
			desc.textContent = '你的评论恰好命中了福袋彩蛋，奖励已到账～';
			var rwBox = document.createElement('div');
			rwBox.className = 'zhiji-cf-reward';
			if (rw.type === 'points') {
				rwBox.innerHTML = '获得 <b>' + Number(rw.val) + ' ' + (rw.name || '积分') + '</b><br><span style="font-size:12px;color:#9a9aa8">' + (rw.desc || '') + '</span>';
			} else if (rw.type === 'balance') {
				rwBox.innerHTML = '获得 <b>¥' + Number(rw.val).toFixed(2) + ' ' + (rw.name || '余额') + '</b><br><span style="font-size:12px;color:#9a9aa8">' + (rw.desc || '') + '</span>';
			} else if (rw.type === 'vip') {
				rwBox.innerHTML = '获得 <b>' + Number(rw.val) + ' 天' + (rw.name || '会员权益') + '</b><br><span style="font-size:12px;color:#9a9aa8">' + (rw.desc || '') + '</span>';
			} else if (rw.type === 'coupon' || rw.type === 'free') {
				var isFree = rw.type === 'free';
				// 文案口语化（2026-09-27）：说人话、给下一步动作，少用书面语
				rwBox.innerHTML = (isFree ? '运气爆棚！抽到 <b>' : '抽到 <b>') + (rw.name || '优惠码') + '</b> 啦'
					+ '<span style="font-size:12px;color:#9a9aa8">（点一下券码就能复制）</span>';
				var code = document.createElement('div');
				code.className = 'zhiji-cf-code';
				code.textContent = rw.code || rw.val;
				// 复制统一走 core/CopyToast 模块（点击即复制 + 明确提示）
				code.setAttribute('data-zhiji-copy', String(rw.code || rw.val));
				code.setAttribute('data-zhiji-copy-msg', isFree ? '免单券已复制，去结算时粘贴即可免单' : '券码已复制，结算时粘贴即可抵扣');
				code.setAttribute('title', '点一下即可复制');
				rwBox.appendChild(code);
				var tip = document.createElement('div');
				tip.className = 'zhiji-cf-tip';
				tip.textContent = isFree ? '下单结算时把券码粘进去，这单就不用付钱啦' : '下单结算时粘贴券码，直接减钱';
				rwBox.appendChild(tip);
			} else {
				rwBox.innerHTML = '获得 <b>' + (rw.val || '') + ' ' + (rw.name || '奖励') + '</b>';
			}
			var txt = document.createElement('div');
			txt.className = 'zhiji-cf-text';
			txt.textContent = d.text || '';
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'zhiji-cf-btn';
			btn.textContent = '收下好运';
			btn.addEventListener('click', function(){ mask.parentNode && mask.parentNode.removeChild(mask); });
			box.appendChild(emoji); box.appendChild(t); box.appendChild(desc); box.appendChild(rwBox); box.appendChild(txt); box.appendChild(btn);
			mask.appendChild(box);
			document.body.appendChild(mask);
			mask.addEventListener('click', function(e){ if (e.target === mask) { mask.parentNode && mask.parentNode.removeChild(mask); } });
			// Confetti 联动：锦鲤必有奖励，全屏彩带庆祝
			if (typeof window.zhiji_confetti === 'function') {
				try { window.zhiji_confetti({ count: 110 }); } catch (e) {}
			}
		}).catch(function(){});
	})();
	</script>
	<?php
}

/**
 * 后台 CSF 设置：用户&互动 → 评论福袋
 */
function zhiji_comment_fortune_register_options() {
	if ( ! class_exists( 'CSF' ) || ! is_admin() ) {
		return;
	}
	Zhiji_Registry::register_options( 'comment_fortune', array(
				array(
					'id'      => 'comment_fortune_enabled',
					'type'    => 'switcher',
					'title'   => __( '评论福袋（热评锦鲤）', 'zhiji' ),
					'default' => false,
					'desc'    => __( '当日评论总数每满 N 条，命中福袋位的登录用户下次打开页面时收到「今日第 X 位锦鲤」彩蛋弹窗，并随机获得积分 / 余额 / 优惠码奖励，给评论互动加惊喜。', 'zhiji' ),
				),
				array(
				    'type'  => 'subheading',
				    'title' => __( '① 触发规则', 'zhiji' ),
				    'dependency' => array('comment_fortune_enabled', '==', '1'),
				),
				array(
					'dependency' => array( 'comment_fortune_enabled', '==', '1' ),
					'id'         => 'comment_fortune_every',
					'type'       => 'number',
					'title'      => __( '福袋位间隔（条）', 'zhiji' ),
					'default'    => 20,
					'min'        => 2,
					'max'        => 100,
					'desc'       => __( '当日评论数第 20 / 40 / 60 … 条命中福袋位。', 'zhiji' ),
				),
				// 2026-09-30 配置体系审计（P1）：奖励类型/权重/数值区间已收口到「奖励中心」，
				// 原 11 个旧字段（w_*/pts_*/bal_*/vip_*）已删除：留着会造成「后台改了不生效」的配置幻觉。
				array(
					'type'    => 'submessage',
					'style'   => 'info',
					'content' => __( '奖励类型、权重与数值区间已统一到「用户&互动 → 奖励中心」配置，本页保留福袋的触发规则与文案。', 'zhiji' ),
				),
				array(
				    'type'  => 'subheading',
				    'title' => __( '② 福袋文案', 'zhiji' ),
				    'dependency' => array('comment_fortune_enabled', '==', '1'),
				),
				array(
					'dependency' => array( 'comment_fortune_enabled', '==', '1' ),
					'id'         => 'comment_fortune_texts',
					'type'       => 'textarea',
					'title'      => __( '俏皮文案（一行一条）', 'zhiji' ),
					'rows'       => 5,
					'placeholder' => '锦鲤护体，好运常伴～',
					'sanitize'   => false,
					'desc'       => __( '命中后随机展示一句。留空使用内置 5 句默认文案。', 'zhiji' ),
				),
				// 2026-09-28：「记录福袋领取日志」开关已按「开关评估 A6」移除，改为常驻 + 说明。
				// 关掉它会让中奖弹窗只活在 2h transient → 错过即永久丢失且无法补发。
				zhiji_notice(
					__( '福袋发放记录 <strong>始终持久化</strong>（2026-09-28 起不再可配置）。'
						. '该记录是后台「知集运维 → 评论福袋待领取」查询与补发中奖弹窗的唯一依据；'
						. '关闭它会导致用户错过弹窗后无法找回，故开关已移除。', 'zhiji' ),
					'info',
					array( 'comment_fortune_enabled', '==', '1' )
				),
				array(
					'type'    => 'content',
					'content' => __( '说明：计数含全部评论，但发奖与弹窗仅对「登录用户」生效；标记 2 小时有效，展示一次即消失；锦鲤必有奖励（无「谢谢参与」，权重全 0 时保底积分）；优惠码/免单券走 CouponGive 体系（来源标记 comment_fortune / comment_fortune_free）；发奖后自动发站内系统通知 + 邮件。', 'zhiji' ),
				),
			), 110 );
}
// 2026-09-26：改为 Registry 统一登记（P3-⑨），此处直接调用替代钩子
zhiji_comment_fortune_register_options();

