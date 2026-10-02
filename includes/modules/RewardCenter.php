<?php
/**
 * @module  RewardCenter
 * @desc    奖励中心总配置源（权重/额度，供福袋/优惠码/订阅/答题模块读取）
 * @option  reward_center_enabled  总开关
 * @hook    wp_footer · 余额来源文案修正
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/RewardCenter.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('reward_center', array(
    'title'    => '奖励中心',
    'parent'   => 'zhiji_user',
    'priority' => 20,
    'option'   => 'reward_center_enabled',
));



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
 * 1. 后台 CSF 分区：用户&互动 → 奖励中心（聚合 5 分节：积分规则/勋章/等级/兑换/日志）
 * ============================================================ */

/**
 * 取父主题已启用的会员等级选项（复用 zibll 会员体系，不自建等级）
 *
 * 父主题 VIP 等级用 vip_level meta（与奖励中心发放一致），等级名称/开关在
 * 子比设置 → 会员 中配置：_pz('pay_user_vip_'.$i.'_name') / _pz('pay_user_vip_'.$i.'_s')。
 * 此处只读已启用等级，保证奖励中心可选的会员等级与父主题实时同步（变量⑦）。
 *
 * @return array level(int) => 名称
 */
function zhiji_reward_center_vip_level_options() {
	if ( ! function_exists( '_pz' ) ) {
		// 父主题未加载时的兜底（理论上不会触发，子主题依赖 zibll）
		return array(
			1 => __( '月卡会员（LV1）', 'zhiji' ),
			2 => __( '年卡会员（LV2）', 'zhiji' ),
		);
	}
	$opts = array();
	// 父主题默认 2 个会员等级（options-module.php:1336 $vip_max=2），循环到 3 以兼容自定义扩展
	for ( $i = 1; $i <= 3; $i++ ) {
		if ( ! _pz( 'pay_user_vip_' . $i . '_s', $i === 1 ) ) {
			continue; // 该等级未启用
		}
		$name = _pz( 'pay_user_vip_' . $i . '_name', '' );
		$opts[ $i ] = $name ? $name : sprintf( __( 'VIP%d', 'zhiji' ), $i );
	}
	return $opts ? $opts : array( 1 => __( '月卡会员（LV1）', 'zhiji' ) );
}

/**
 * 渲染「已注册勋章」展示块（只读；勋章定义见 Ops.php user_medal_args）
 *
 * @return string HTML
 */
function zhiji_reward_center_medals_html() {
	$public = array(
		'兑换达人' => '累计兑换 10 次',
		'学神认证' => '答题满分 3 次',
	);
	$hidden = array( '夜猫子', '彩蛋猎人', '坚持之王' );
	$rows   = '';
	foreach ( $public as $name => $desc ) {
		$icon = function_exists( 'zhiji_medal_icon' ) ? zhiji_medal_icon( $name ) : '';
		$img  = $icon ? '<img src="' . esc_url( $icon ) . '" width="28" height="28" style="vertical-align:middle;margin-right:8px;border-radius:6px">' : '';
		$rows .= '<div style="padding:4px 0">' . $img . '<b>' . esc_html( $name ) . '</b> <span class="opacity7">— ' . esc_html( $desc ) . '</span></div>';
	}
	$hidden_line = '<div style="padding:4px 0" class="opacity8">隐藏成就（触发条件不对外公示，仅由事件授予）：' . esc_html( implode( ' / ', $hidden ) ) . '</div>';
	return '<div style="line-height:1.8">' . $rows . $hidden_line
		. '<div class="opacity7" style="margin-top:6px">勋章图标为子主题自绘（版权归知集），由 Ops.php 的 user_medal_args 注册、事件自动判定授予。</div></div>';
}

function zhiji_reward_center_register_options() {
	if ( ! class_exists( 'CSF' ) || ! is_admin() ) {
		return;
	}

	
	$vip_options = zhiji_reward_center_vip_level_options();

	Zhiji_Registry::register_options( 'reward_center', array(
				array(
					'id'      => 'reward_center_enabled',
					'type'    => 'switcher',
					'title'   => __( '启用奖励中心', 'zhiji' ),
					'label'   => __( '全站统一发奖闸门：关闭后各奖励渠道全部停发、迎新券/评论福袋/订阅奖励全部停发。', 'zhiji' ),
					'default' => true,
				),
				array(
					'type'    => 'content',
					'content' => zhiji_reward_center_overview_html(),
				),

				array(
					'type'    => 'subheading',
					'title'   => __( '① 积分规则 / 随机模式权重（数值越大越容易抽中，可为 0；全 0 时保底积分）', 'zhiji' ),
					'desc'    => __( '适用于评论福袋、抽奖等「随机抽一种奖励」的场景。', 'zhiji' ),
				),
				array(
					'id'      => 'reward_center_w_points',
					'type'    => 'number',
					'title'   => __( '积分权重', 'zhiji' ),
					'desc'       => __( '抽取积分奖励的权重（0 = 不产出该奖励）。', 'zhiji' ),
					'default' => 3,
					'min'     => 0,
				),
				array(
					'id'      => 'reward_center_w_balance',
					'type'    => 'number',
					'title'   => __( '余额权重', 'zhiji' ),
					'desc'       => __( '抽取余额奖励的权重（0 = 不产出）。', 'zhiji' ),
					'default' => 2,
					'min'     => 0,
				),
				array(
					'id'      => 'reward_center_w_coupon',
					'type'    => 'number',
					'title'   => __( '优惠码权重', 'zhiji' ),
					'desc'       => __( '抽取优惠码奖励的权重（0 = 不产出）。', 'zhiji' ),
					'default' => 2,
					'min'     => 0,
				),
				array(
					'id'      => 'reward_center_w_vip',
					'type'    => 'number',
					'title'   => __( '会员权益权重', 'zhiji' ),
					'desc'       => __( '抽取会员奖励的权重（0 = 不产出）。', 'zhiji' ),
					'default' => 2,
					'min'     => 0,
				),
				array(
					'id'      => 'reward_center_w_free',
					'type'    => 'number',
					'title'   => __( '免单券权重', 'zhiji' ),
					'desc'       => __( '产出「谢谢参与」的权重（0 = 永不落空）。', 'zhiji' ),
					'default' => 1,
					'min'     => 0,
				),

				array(
					'type'  => 'subheading',
					'title' => __( '① 积分规则 / 积分奖励参数', 'zhiji' ),
				),
				array(
					'id'      => 'reward_center_points_min',
					'type'    => 'number',
					'title'   => __( '积分最小值', 'zhiji' ),
					'desc'       => __( '积分奖励的随机下限。', 'zhiji' ),
					'default' => 10,
					'min'     => 1,
				),
				array(
					'id'      => 'reward_center_points_max',
					'type'    => 'number',
					'title'   => __( '积分最大值', 'zhiji' ),
					'desc'       => __( '积分奖励的随机上限。', 'zhiji' ),
					'default' => 100,
					'min'     => 1,
				),

				array(
					'type'  => 'subheading',
					'title' => __( '① 积分规则 / 余额奖励参数', 'zhiji' ),
				),
				array(
					'id'      => 'reward_center_balance_min',
					'type'    => 'number',
					'title'   => __( '余额最小值（元）', 'zhiji' ),
					'desc'       => __( '余额奖励的随机下限（元）。', 'zhiji' ),
					'default' => 1,
					'min'     => 0,
				),
				array(
					'id'      => 'reward_center_balance_max',
					'type'    => 'number',
					'title'   => __( '余额最大值（元）', 'zhiji' ),
					'desc'       => __( '余额奖励的随机上限（元）。', 'zhiji' ),
					'default' => 5,
					'min'     => 0,
				),

				// —— 分节二：勋章（只读展示）——
				array(
					'type'  => 'subheading',
					'title' => __( '② 勋章墙（当前已注册，由 Ops.php 事件自动授予）', 'zhiji' ),
					'desc'  => __( '勋章无需在此配置；以下为站点当前已注册勋章。', 'zhiji' ),
				),
				array(
					'type'    => 'content',
					'content' => zhiji_reward_center_medals_html(),
				),

				array(
					'type'  => 'subheading',
					'title' => __( '④ 兑换 / 优惠码奖励参数（复用 CouponGive 差异化面值体系）', 'zhiji' ),
				),
				array(
					'id'      => 'reward_center_coupon_scope',
					'type'    => 'select',
					'title'   => __( '面值区间', 'zhiji' ),
					'desc'       => __( '优惠码的适用范围（可指定商品 ID 或全站通用）。', 'zhiji' ),
					'options' => array(
						'login' => __( '登录用户区间（立减 1~10 / 折扣 0.7~0.95）', 'zhiji' ),
						'vip'   => __( 'VIP 用户区间（立减 2~20 / 折扣 0.5~0.9）', 'zhiji' ),
						'rand'  => __( '完全随机（立减 0.5~20 / 折扣 0.5~0.95）', 'zhiji' ),
					),
					'default' => 'login',
					'desc'    => __( '调用 CouponGive 的 zhiji_coupon_give_discount_meta 生成随机立减/折扣优惠码。', 'zhiji' ),
				),
				array(
					'id'      => 'reward_center_coupon_expire',
					'type'    => 'select',
					'title'   => __( '有效期规则', 'zhiji' ),
					'options' => array(
						'random' => __( '随机分配（7 天 / 30 天 / 永久 三档随机）', 'zhiji' ),
						'7'      => __( '统一 7 天', 'zhiji' ),
						'30'     => __( '统一 30 天', 'zhiji' ),
						'0'      => __( '永久有效', 'zhiji' ),
					),
					'default' => 'random',
					'desc'    => __( '2026-09-29 修复：此前奖励中心渠道发的券一律未写有效期（前台显示"永久有效"）。现按此规则写入 expire_time，覆盖注册迎新/评论福袋等全部奖励中心渠道；前台「我的优惠码 → 到期时间」即时生效。', 'zhiji' ),
				),

				// —— 分节三：等级（复用父主题）——
				array(
					'type'  => 'subheading',
					'title' => __( '③ 等级 / 会员权益奖励参数（复用父主题 zibll 会员体系）', 'zhiji' ),
					'desc'  => __( '会员等级名称与开关在「子比设置 → 会员」中配置；此处仅选择奖励发放的等级。', 'zhiji' ),
				),
				array(
					'id'      => 'reward_center_vip_days',
					'type'    => 'number',
					'title'   => __( '会员天数', 'zhiji' ),
					'desc'       => __( '会员奖励的天数。', 'zhiji' ),
					'default' => 7,
					'min'     => 1,
				),
				array(
					'id'      => 'reward_center_vip_level',
					'type'    => 'select',
					'title'   => __( '会员等级', 'zhiji' ),
					'desc'       => __( '会员奖励的等级（取自父主题已启用会员等级，复用 zibll 会员体系）。', 'zhiji' ),
					'options' => $vip_options,
					'default' => 1,
				),

				array(
					'type'  => 'subheading',
					'title' => __( '④ 兑换 / 全发模式（答题等达标后发放哪些奖励）', 'zhiji' ),
					'desc'  => __( '开启的奖励类型在「全发模式」下会全部发放；关闭则不发。随机模式不受此开关影响。', 'zhiji' ),
				),
				array(
					'id'      => 'reward_center_all_points',
					'type'    => 'switcher',
					'title'   => __( '发放积分', 'zhiji' ),
					'desc'       => __( '「全部奖励」模式下的积分数量。', 'zhiji' ),
					'default' => true,
				),
				array(
					'id'      => 'reward_center_all_balance',
					'type'    => 'switcher',
					'title'   => __( '发放余额', 'zhiji' ),
					'desc'       => __( '「全部奖励」模式下的余额金额（元）。', 'zhiji' ),
					'default' => false,
				),
				array(
					'id'      => 'reward_center_all_coupon',
					'type'    => 'switcher',
					'title'   => __( '发放优惠码', 'zhiji' ),
					'desc'       => __( '「全部奖励」模式下发放的优惠码张数。', 'zhiji' ),
					'default' => true,
				),
				array(
					'id'      => 'reward_center_all_vip',
					'type'    => 'switcher',
					'title'   => __( '发放会员权益', 'zhiji' ),
					'desc'       => __( '「全部奖励」模式下赠送的会员天数。', 'zhiji' ),
					'default' => false,
				),
				array(
					'id'      => 'reward_center_all_free',
					'type'    => 'switcher',
					'title'   => __( '发放免单券', 'zhiji' ),
					'default' => false,
					'desc'    => __( '免单券（multiply=0 全免）价值较高，建议谨慎开启。', 'zhiji' ),
				),

				// —— 分节五：日志 ——
				array(
					'type'  => 'subheading',
					'title' => __( '⑤ 发放记录与来源标签', 'zhiji' ),
				),
				array(
					'type'    => 'content',
					'content' => __( '奖励发放记录在用户中心「余额 / 积分明细」查看；来源标签由 zhiji_reward_source_label() 统一归一为中文（如「评论福袋」「大转盘抽奖」），历史英文标识已通过 zhiji_reward_records_migrate() 一次性迁移修正，不会再把内部码暴露给用户。', 'zhiji' ),
				),

			), 20 );
}
// 2026-09-26：改为 Registry 统一登记（P3-⑨），此处直接调用替代钩子
zhiji_reward_center_register_options();


/* ============================================================
 * 2. 统一发放入口
 * ============================================================ */

/**
 * 随机抽一种奖励发放（评论福袋/抽奖用）。
 *
 * @param int    $uid    用户 ID
 * @param string $source 来源标识（如 comment_fortune / lottery / quiz）
 * @param array  $overrides 覆盖参数（可选，如 array('points_min'=>5)）
 * @return array 奖励数组（单条），失败返回空数组
 */
function zhiji_reward_center_grant_random( $uid, $source = '', $overrides = array() ) {
	// 总开关（2026-09-26 补接线）：关闭时返回空数组，
	// 调用方 CommentFortune / EmailSubscribe 已内置空值兜底，不会中断业务。
	if ( ! zhiji_is_enabled( 'reward_center_enabled', true ) ) {
		return array();
	}
	if ( ! $uid ) {
		return array();
	}

	$w = array(
		'points'  => max( 0, (int) zhiji_get_option( 'reward_center_w_points', 3 ) ),
		'balance' => max( 0, (int) zhiji_get_option( 'reward_center_w_balance', 2 ) ),
		'coupon'  => max( 0, (int) zhiji_get_option( 'reward_center_w_coupon', 2 ) ),
		'vip'     => max( 0, (int) zhiji_get_option( 'reward_center_w_vip', 2 ) ),
		'free'    => max( 0, (int) zhiji_get_option( 'reward_center_w_free', 1 ) ),
	);
	$total = array_sum( $w );
	if ( $total <= 0 ) {
		$w['points'] = 1;
		$total        = 1;
	}

	$roll = wp_rand( 1, $total );
	$type = 'points';
	$acc  = 0;
	foreach ( $w as $t => $wt ) {
		$acc += $wt;
		if ( $roll <= $acc ) {
			$type = $t;
			break;
		}
	}

	return zhiji_reward_center_grant_one( $uid, $type, $source, $overrides );
}

/**
 * 全发模式：发放所有已启用的奖励（答题达标用）。
 *
 * @param int    $uid
 * @param string $source
 * @param array  $overrides
 * @return array 奖励数组（多条）
 */
function zhiji_reward_center_grant_all( $uid, $source = '', $overrides = array() ) {
	// 总开关（2026-09-26 补接线）：关闭时返回空数组，
	// 调用方 CommentFortune / EmailSubscribe 已内置空值兜底，不会中断业务。
	if ( ! zhiji_is_enabled( 'reward_center_enabled', true ) ) {
		return array();
	}
	if ( ! $uid ) {
		return array();
	}

	$types = array();
	if ( zhiji_get_option( 'reward_center_all_points', true ) )  $types[] = 'points';
	if ( zhiji_get_option( 'reward_center_all_balance', false ) ) $types[] = 'balance';
	if ( zhiji_get_option( 'reward_center_all_coupon', true ) )   $types[] = 'coupon';
	if ( zhiji_get_option( 'reward_center_all_vip', false ) )     $types[] = 'vip';
	if ( zhiji_get_option( 'reward_center_all_free', false ) )     $types[] = 'free';

	if ( empty( $types ) ) {
		return array();
	}

	$rewards = array();
	foreach ( $types as $type ) {
		$r = zhiji_reward_center_grant_one( $uid, $type, $source, $overrides );
		if ( ! empty( $r ) ) {
			$rewards[] = $r;
		}
	}
	return $rewards;
}

/**
 * 奖励来源标识 → 面向用户的展示名称（**唯一实现**，禁止在别处拼装）
 *
 * 语义：这是「这笔奖励由哪个业务/活动发放」，用于用户中心余额/积分记录的徽标与说明。
 *
 * 判定：映射表命中 → 已是中文（调用方直接传可读标签）原样 → 未识别的英文键回退「系统奖励」
 *      （**绝不把内部标识直接显示给用户** —— 曾因 desc 直接拼 $source，
 *        导致用户中心出现「来源：comment_fortune」这种英文码）
 *
 * @param string $source 业务来源标识
 * @return string
 */
function zhiji_reward_source_labels() {
	/**
	 * 奖励来源标签映射（新增业务来源时在此追加，或挂该 filter）
	 *
	 * @param array $labels
	 */
	return apply_filters( 'zhiji_reward_source_labels', array(
		// 评论福袋（CommentFortune）
		'comment_fortune'      => __( '评论福袋', 'zhiji' ),
		'comment_fortune_free' => __( '评论福袋', 'zhiji' ),
		// 优惠码体系（CouponGive）
		'direct'               => __( '挽留弹窗', 'zhiji' ),
		'ref_bonus'            => __( '分享奖励', 'zhiji' ),
		// 奖励中心自身
		'reward_center'        => __( '奖励中心', 'zhiji' ),
		'reward_center_free'   => __( '奖励中心', 'zhiji' ),
		// 其它业务（含 v1 遗留标识）
		'lottery'              => __( '大转盘抽奖', 'zhiji' ),
		'zhiji_lottery'        => __( '大转盘抽奖', 'zhiji' ),
		'email_subscribe'      => __( '邮件订阅', 'zhiji' ),
		'daily_task'           => __( '每日任务', 'zhiji' ),
		'credit_tasks'         => __( '知集任务', 'zhiji' ),
		'zhiji_credit_tasks'   => __( '知集任务', 'zhiji' ),
		'signin'               => __( '每日签到', 'zhiji' ),
		'checkin'              => __( '每日签到', 'zhiji' ),
		'bbs'                  => __( '社区互动', 'zhiji' ),
		'manual'               => __( '后台发放', 'zhiji' ),
		'ops_release'          => __( '运维放行', 'zhiji' ),
	) );
}

/**
 * 取来源展示名称
 *
 * @param string $source
 * @return string
 */
function zhiji_reward_source_label( $source ) {
	$source = trim( (string) $source );
	$labels = zhiji_reward_source_labels();

	if ( '' === $source ) {
		return __( '系统奖励', 'zhiji' );
	}
	if ( isset( $labels[ $source ] ) ) {
		return $labels[ $source ];
	}
	// 调用方直接传了中文标签（如 v1 的「邮件订阅奖励」）→ 原样展示
	if ( preg_match( '/[\x{4e00}-\x{9fa5}]/u', $source ) ) {
		return $source;
	}
	// 未识别的英文键：不把内部标识暴露给用户
	return __( '系统奖励', 'zhiji' );
}

/**
 * 奖励记录的「说明」文案（徽标右侧那行）
 *
 * 优先用调用方传入的 desc（如「热评锦鲤奖励」）；否则按奖励类型给一句可读说明。
 *
 * @param string $type      奖励类型 points/balance/vip/coupon/free
 * @param array  $overrides 调用方覆盖参数（可含 desc）
 * @return string
 */
function zhiji_reward_record_desc( $type, $overrides = array() ) {
	if ( is_array( $overrides ) && ! empty( $overrides['desc'] ) ) {
		return (string) $overrides['desc'];
	}
	switch ( (string) $type ) {
		case 'points':
			return __( '积分奖励', 'zhiji' );
		case 'balance':
			return __( '余额奖励', 'zhiji' );
		case 'vip':
			return __( '会员权益奖励', 'zhiji' );
		case 'coupon':
		case 'free':
			return __( '优惠码奖励', 'zhiji' );
		default:
			return __( '活动奖励', 'zhiji' );
	}
}

/**
 * 随机取一档优惠码有效期天数（7 / 30 / 0=永久）
 *
 * @return int
 */
/**
 * 奖励中心「渠道总览」面板（2026-09-30 配置体系审计 P2）
 *
 * 目的：奖励配置此前散落在 4 个分类 11 个模块，排查"奖为什么没发"要在分类间来回跳。
 * 本面板把三层语义与各发奖渠道的当前状态集中展示（只读，不改变任何开关归属）。
 *
 * @return string HTML
 */
function zhiji_reward_center_overview_html() {
	$channels = array(
		array( 'lottery',          '大转盘抽奖',   'lottery_enabled',          '奖品池 / 每日次数 / 积分加抽', 'lottery' ),
		array( 'comment_fortune',  '评论福袋',     'comment_fortune_enabled',  '触发间隔 / 文案（奖励参数走这里）', 'comment_fortune' ),
		array( 'member_guide',     '注册迎新',     'member_guide_enabled',     '迎新券开关 / 触达序列', 'member_guide' ),
		array( 'email_subscribe',  '邮件订阅奖励', 'email_sub_enabled',        '订阅奖励积分 / 勾选文案', 'email_subscribe' ),
		array( 'quiz',             '互动答题',     'quiz_enabled',             '题库 / 每日次数 / 得分上限', 'quiz' ),
	);

	$rows = '';
	foreach ( $channels as $c ) {
		list( $key, $name, $opt, $params, $scene ) = $c;
		$on    = zhiji_is_enabled( $opt, true );
		$badge = $on
			? '<span style="color:#16a34a;font-weight:600">● 已启用</span>'
			: '<span style="color:#9ca3af;font-weight:600">○ 已关闭</span>';
		$rows .= '<tr>'
			. '<td style="padding:6px 10px;border-bottom:1px solid #f0f0f0">' . esc_html( $name ) . '</td>'
			. '<td style="padding:6px 10px;border-bottom:1px solid #f0f0f0">' . $badge . '</td>'
			. '<td style="padding:6px 10px;border-bottom:1px solid #f0f0f0;color:#666">' . esc_html( $params ) . '</td>'
			. '<td style="padding:6px 10px;border-bottom:1px solid #f0f0f0"><code>' . esc_html( $opt ) . '</code></td>'
			. '</tr>';
	}

	return '<div style="margin:6px 0 18px">'
		. '<div style="font-weight:600;margin-bottom:6px">📊 发奖渠道总览（只读）</div>'
		. '<div style="color:#666;font-size:12px;line-height:1.9;margin-bottom:8px">'
		. '<b>三层配置语义</b>：① <b>全局层</b> = 本页上方总开关 + 发奖参数（积分/余额区间、优惠码面值与有效期规则）；'
		. '② <b>渠道层</b> = 下表中各渠道自身的开关与规则（在各自分类内）；'
		. '③ <b>活动层</b> = 兑换品 / 奖品池等具体条目。'
		. '</div>'
		. '<table style="width:100%;border-collapse:collapse;font-size:13px">'
		. '<thead><tr style="background:#f7f8fa;text-align:left">'
		. '<th style="padding:6px 10px">渠道</th><th style="padding:6px 10px">状态</th>'
		. '<th style="padding:6px 10px">渠道内参数</th><th style="padding:6px 10px">开关键</th></tr></thead>'
		. '<tbody>' . $rows . '</tbody></table>'
		. '<div style="color:#999;font-size:12px;margin-top:8px">'
		. '发放记录与明细请到「运维管理页面 → 场景」查看；渠道开关在各自分类内切换，本表随配置实时刷新。'
		. '</div></div>';
}

function zhiji_reward_coupon_rand_expire() {
	$pool = apply_filters( 'zhiji_reward_coupon_expire_pool', array( 7, 30, 0 ) );
	$pool = is_array( $pool ) && ! empty( $pool ) ? $pool : array( 7, 30, 0 );
	return (int) $pool[ array_rand( $pool, 1 ) ];
}

/**
 * 发放指定类型的一种奖励。
 *
 * @param int    $uid
 * @param string $type   points / balance / coupon / vip / free
 * @param string $source
 * @param array  $overrides
 * @return array 单条奖励数组，失败返回空数组
 */
function zhiji_reward_center_grant_one( $uid, $type, $source = '', $overrides = array() ) {
	// 兼容：调用方误把字符串当 overrides 传入时（曾有调用点参数错位），此处兜底为数组
	if ( ! is_array( $overrides ) ) {
		$overrides = array();
	}
	// 总开关（2026-09-26 补接线）：关闭时返回空数组，
	// 调用方 CommentFortune / EmailSubscribe 已内置空值兜底，不会中断业务。
	if ( ! zhiji_is_enabled( 'reward_center_enabled', true ) ) {
		return array();
	}
	if ( ! $uid || ! $type ) {
		return array();
	}

	switch ( $type ) {

		case 'points':
			$min = isset( $overrides['points_min'] ) ? (int) $overrides['points_min'] : max( 1, (int) zhiji_get_option( 'reward_center_points_min', 10 ) );
			$max = isset( $overrides['points_max'] ) ? (int) $overrides['points_max'] : max( $min, (int) zhiji_get_option( 'reward_center_points_max', 100 ) );
			$val = wp_rand( $min, $max );
			// 记录字段语义：type = 业务来源（徽标）、desc = 奖励说明
			// （禁止再把内部标识 $source 直接写进 desc —— 那是「来源：comment_fortune」的成因）
			Zhiji_Adapter::update_user_points( $uid, array(
				'value' => $val,
				'type'  => zhiji_reward_source_label( $source ),
				'desc'  => zhiji_reward_record_desc( 'points', $overrides ),
			) );
			return array( 'type' => 'points', 'name' => __( '积分', 'zhiji' ), 'val' => $val, 'desc' => sprintf( __( '+%d 积分', 'zhiji' ), $val ) );

		case 'balance':
			$min = isset( $overrides['balance_min'] ) ? (float) $overrides['balance_min'] : max( 0, (float) zhiji_get_option( 'reward_center_balance_min', 1 ) );
			$max = isset( $overrides['balance_max'] ) ? (float) $overrides['balance_max'] : max( $min, (float) zhiji_get_option( 'reward_center_balance_max', 5 ) );
			$val = round( $min + ( mt_rand() / mt_getrandmax() ) * ( $max - $min ), 2 );
			Zhiji_Adapter::update_user_balance( $uid, array(
				'value' => $val,
				'type'  => zhiji_reward_source_label( $source ),
				'desc'  => zhiji_reward_record_desc( 'balance', $overrides ),
			) );
			return array( 'type' => 'balance', 'name' => __( '余额', 'zhiji' ), 'val' => $val, 'desc' => sprintf( __( '+¥%s 余额', 'zhiji' ), number_format( $val, 2 ) ) );

		case 'coupon':
			if ( class_exists( 'ZibCardPass' ) && function_exists( 'zhiji_coupon_give_discount_meta' ) && function_exists( 'zhiji_coupon_give_create_one' ) ) {
				$scope    = isset( $overrides['coupon_scope'] ) ? $overrides['coupon_scope'] : zhiji_get_option( 'reward_center_coupon_scope', 'login' );
				$discount = zhiji_coupon_give_discount_meta( $scope );
				// 有效期（2026-09-29 修复"全部永久有效"）：默认 7 天/30 天/永久 三档随机，可在后台改规则
				$expire_rule = (string) zhiji_get_option( 'reward_center_coupon_expire', 'random' );
				$expire_days = ( 'random' === $expire_rule ) ? zhiji_reward_coupon_rand_expire() : (int) $expire_rule;
				$meta     = array(
					'discount' => $discount,
					'title'    => '奖励中心专属优惠码',
					'reuse'    => 1,
					'user_id'  => $uid,
					'source'   => $source ? $source : 'reward_center',
				);
				if ( $expire_days > 0 ) {
					$meta['expire_time'] = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $expire_days * DAY_IN_SECONDS );
				}
				$code = zhiji_coupon_give_create_one( $meta, 0 );
				if ( $code ) {
					$dt = ( 'multiply' === $discount['type'] )
					? ( ( (float) $discount['val'] <= 0 )
						? __( '免单', 'zhiji' ) // 免单特判：与 zhiji_coupon_give_discount_text() 口径一致（0折 → 免单）
						: ( $discount['val'] * 10 ) . __( ' 折', 'zhiji' ) )
					: __( '立减 ¥', 'zhiji' ) . number_format( $discount['val'], 2 );
					return array( 'type' => 'coupon', 'name' => __( '优惠码', 'zhiji' ), 'val' => $code, 'desc' => $dt, 'code' => $code );
				}
			}
			// 优惠码生成失败，保底发积分
			return zhiji_reward_center_grant_one( $uid, 'points', $source, array_merge( $overrides, array( 'points_min' => 10, 'points_max' => 30 ) ) );

		case 'vip':
			$days  = isset( $overrides['vip_days'] ) ? (int) $overrides['vip_days'] : max( 1, (int) zhiji_get_option( 'reward_center_vip_days', 7 ) );
			$level = isset( $overrides['vip_level'] ) ? (int) $overrides['vip_level'] : max( 1, min( 2, (int) zhiji_get_option( 'reward_center_vip_level', 1 ) ) );
			$exp   = get_user_meta( $uid, 'vip_exp_date', true );
			if ( $exp && 'Permanent' !== $exp && strtotime( $exp ) > current_time( 'timestamp' ) ) {
				$new_exp = date( 'Y-m-d 23:59:59', strtotime( '+' . (int) $days . ' day', strtotime( $exp ) ) );
			} else {
				$new_exp = date( 'Y-m-d 23:59:59', current_time( 'timestamp' ) + $days * DAY_IN_SECONDS );
			}
			update_user_meta( $uid, 'vip_level', $level );
			update_user_meta( $uid, 'vip_exp_date', $new_exp );
			return array( 'type' => 'vip', 'name' => __( '会员权益', 'zhiji' ), 'val' => $days, 'desc' => sprintf( __( '已开通/延长会员 %d 天（LV%d）', 'zhiji' ), $days, $level ) );

		case 'free':
			if ( class_exists( 'ZibCardPass' ) && function_exists( 'zhiji_coupon_give_create_one' ) ) {
				$meta = array(
					'discount' => array( 'type' => 'multiply', 'val' => 0 ),
					'title'    => '奖励中心免单券',
					'reuse'    => 1,
					'user_id'  => $uid,
					'source'   => ( $source ? $source : 'reward_center' ) . '_free',
				);
				$code = zhiji_coupon_give_create_one( $meta, 0 );
				if ( $code ) {
					return array( 'type' => 'free', 'name' => __( '免单券', 'zhiji' ), 'val' => $code, 'desc' => __( '下单直接免单', 'zhiji' ), 'code' => $code );
				}
			}
			// 免单券生成失败，保底发积分
			return zhiji_reward_center_grant_one( $uid, 'points', $source, array_merge( $overrides, array( 'points_min' => 20, 'points_max' => 50 ) ) );

		default:
			return array();
	}
}

/* ============================================================
 * ============================================================ */

/**
 *
 * @param array $reward 单条奖励数组
 * @return string
 */
function zhiji_reward_center_danmu_text( $reward ) {
	if ( ! is_array( $reward ) || empty( $reward['type'] ) ) {
		return '';
	}
	switch ( $reward['type'] ) {
		case 'vip':
			return sprintf( __( '%d 天会员权益！', 'zhiji' ), (int) $reward['val'] );
		case 'free':
			return __( '一张免单券！', 'zhiji' );
		case 'coupon':
			return __( '一张优惠码！', 'zhiji' );
		case 'balance':
			return sprintf( __( '¥%s 余额！', 'zhiji' ), number_format( (float) $reward['val'], 2 ) );
		default:
			return sprintf( __( '%d 积分！', 'zhiji' ), (int) $reward['val'] );
	}
}

/* ============================================================
 * 4. 用户中心余额/积分记录：来源标签文案修正
 * ------------------------------------------------------------
 * 父主题 zibpay 写入的余额明细 type 存在英文/歧义来源（lottery、
 * 知任务等），子主题不修改父主题、不改写历史数据，仅在前端
 * 用户中心容器内把来源标签替换为清晰中文。
 * ============================================================ */

/**
 * 用户中心余额/积分记录来源标签前端修正（兜底）
 *
 * 说明：数据层已有一次性迁移（zhiji_reward_records_migrate()）把历史记录的
 *       「奖励中心 + 来源：<英文码>」改写成可读文案；本脚本仅作**兜底**，
 *       把仍残留的来源标识替换为展示名称（覆盖迁移未触达的旧缓存/异步内容）。
 *
 * 2026-09-27：替换表改为**由 PHP 标签映射驱动**（原实现只硬编码了 lottery / 知任务）。
 */
// 2026-09-26：改走页脚统一调度（P3-⑧），原优先级 99 保持
zhiji_footer_add( 'balance-source-label', 'zhiji_balance_source_label_fix', 99 );
function zhiji_balance_source_label_fix() {
	if ( is_admin() || ! is_user_logged_in() ) {
		return;
	}
	// 只替换"代码感"的标识（含下划线或明确长名），避免误伤正常英文单词
	$map = array();
	foreach ( zhiji_reward_source_labels() as $key => $label ) {
		if ( '' === $key || $key === $label ) {
			continue;
		}
		if ( false !== strpos( $key, '_' ) || in_array( $key, array( 'lottery', 'signin', 'checkin', '知任务' ), true ) ) {
			$map[ $key ] = $label;
		}
	}
	if ( ! $map ) {
		return;
	}
	?>
	<script id="zhiji-balance-label-fix">
	(function(){
		var MAP = <?php echo wp_json_encode( $map, JSON_UNESCAPED_UNICODE ); ?>;
		var KEYS = Object.keys(MAP);
		function fix(node) {
			if (!node) return;
			if (node.nodeType === 3) {
				var t = node.nodeValue, nt = t;
				for (var i = 0; i < KEYS.length; i++) {
					if (nt.indexOf(KEYS[i]) !== -1) { nt = nt.split(KEYS[i]).join(MAP[KEYS[i]]); }
				}
				if (nt !== t) node.nodeValue = nt;
				return;
			}
			var cs = node.childNodes;
			for (var j = 0; j < cs.length; j++) { fix(cs[j]); }
		}
		function boot() {
			var root = document.querySelector('.user-center') || document.querySelector('.user-center-sidebar');
			if (root) { fix(root); }
		}
		if (document.readyState !== 'loading') { boot(); }
		else { document.addEventListener('DOMContentLoaded', boot); }
		// 用户中心 tab 内容为异步加载，间隔重扫确保替换到位（15 秒后停止）
		var t = setInterval(function(){
			if (!document.querySelector('.user-center')) { clearInterval(t); return; }
			fix(document.querySelector('.user-center'));
		}, 800);
		setTimeout(function(){ clearInterval(t); }, 15000);
	})();
	</script>
	<?php
}

/**
 * 一次性迁移：把历史余额/积分记录里的「奖励中心 + 来源：<内部标识>」改写为可读文案
 *
 * 背景：v2 把发奖收口到奖励中心时，记录字段写成 type='奖励中心'、desc='来源：comment_fortune'，
 *       徽标丢失业务语义、并把内部标识暴露给用户（用户中心显示「来源：comment_fortune」）。
 *
 * 迁移规则（仅处理匹配的记录，幂等）：
 *   desc 形如「来源：<标识>」→ type = 来源展示名、desc = 按记录类型（余额/积分）生成的可读说明
 *
 * @return int 改写的记录条数
 */
function zhiji_reward_records_migrate() {
	global $wpdb;

	// 只取"含有旧格式记录"的用户，避免全表扫描
	$user_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s LIMIT 200",
		'zib_other_data',
		'%' . $wpdb->esc_like( '来源：' ) . '%'
	) );
	if ( ! $user_ids ) {
		return 0;
	}

	$changed = 0;
	foreach ( $user_ids as $uid ) {
		$uid = (int) $uid;
		if ( $uid <= 0 ) {
			continue;
		}
		foreach ( array( 'balance_record' => 'balance', 'points_record' => 'points' ) as $meta_key => $reward_type ) {
			$records = Zhiji_Adapter::user_meta_get( $uid, $meta_key );
			if ( ! is_array( $records ) || ! $records ) {
				continue;
			}
			$dirty = false;
			foreach ( $records as $i => $rec ) {
				if ( ! is_array( $rec ) || empty( $rec['desc'] ) ) {
					continue;
				}
				if ( ! preg_match( '/^来源：(.+)$/u', (string) $rec['desc'], $m ) ) {
					continue;
				}
				$records[ $i ]['type'] = zhiji_reward_source_label( $m[1] );
				$records[ $i ]['desc'] = zhiji_reward_record_desc( $reward_type, array() );
				$dirty                 = true;
				$changed++;
			}
			if ( $dirty ) {
				Zhiji_Adapter::user_meta_update( $uid, $meta_key, $records );
			}
		}
	}

	update_option( 'zhiji_reward_labels_migrated', ZHIJI_VERSION, false );
	return $changed;
}

// 幂等执行：仅在版本不符时跑一次（option 走 autoload=false，无额外查询负担）
add_action( 'wp_loaded', function () {
	if ( get_option( 'zhiji_reward_labels_migrated' ) === ZHIJI_VERSION ) {
		return;
	}
	$n = zhiji_reward_records_migrate();
	if ( $n > 0 ) {
		zhiji_log( 'reward record labels migrated', array( 'changed' => $n ) );
	}
} );