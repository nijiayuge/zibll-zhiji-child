<?php
/**
 * @module  RewardCenter
 * @desc    发奖规则 —— 评论福袋等场景「发什么、发多少」的统一参数源
 * @option  reward_center_enabled  总开关
 * @hook    wp_footer · 余额来源文案修正
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/RewardCenter.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 *
 * 2026-10-03 用户反馈「奖励中心太乱也看不懂」→ 定位为**按场景重组**（原按奖励类型分组）。
 *
 * 【它到底是什么，2026-10-03 实测结论】
 * 名字叫「中心」容易让人以为是大总管，实际它只是**发奖引擎 + 一份共享参数**。
 * 全站仅 3 个调用方，且各自依赖程度不同：
 *   · 评论福袋   grant_random() —— **完全依赖本模块的权重表与数值区间**（唯一真正依赖它的）
 *   · 注册迎新   grant_one(…,'coupon') —— 只借发券引擎，面值走自己的 member_guide_coupon_scope
 *   · 邮件订阅   grant_one(…,'points') —— 只借记账引擎，积分数由自己的 email_sub_reward_points 传入
 *   · 全发模式   grant_all() —— **零调用方**（保留为能力预留，见页面内说明）
 * 所以页面的组织方式应是「**谁在用 → 配什么**」，而不是「积分/余额/券」这种奖励类型维度。
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('reward_center', array(
    'title'    => '发奖规则',
    'parent'   => 'zhiji_user',
    'priority' => 20,
    'option'   => 'reward_center_enabled',
));



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
 * 1. 后台 CSF 分区：用户&互动 → 发奖规则
 * ------------------------------------------------------------
 * 结构：① 谁在用（只读速览）→ ② 各场景配置 → ③ 通用参数
 * 依据：2026-10-03 按用户反馈重排，字段 id 一个都没改（改键名会读不到用户已存的值）。
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

	// ⚠️ 字段 id 一个都不能改 —— 改了会读不到用户已保存的值（改键名必须配「旧键→新键」映射）。
	//    2026-10-03 重排：按「场景」组织（原按奖励类型），并修掉 4 个具体问题：
	//    ① 编号错乱（此前 ① 出现 3 次、④ 出现 2 次、③ 夹在两个 ④ 中间）
	//    ② 开发者文案（内部函数名 / 内部 option 键名列表）混在用户可见的 desc 里
	//    ③ reward_center_coupon_scope 的 desc 写了两次（后者覆盖前者）
	//    ④ 勋章墙（只读）夹在配置流中间
	Zhiji_Registry::register_options( 'reward_center', array(
				array(
					'id'      => 'reward_center_enabled',
					'type'    => 'switcher',
					'title'   => __( '启用发奖规则', 'zhiji' ),
					'label'   => __( '总闸门：关闭后评论福袋、注册迎新券、邮件订阅奖励<b>全部停发</b>。'
						. '各业务自己的开关不受影响。', 'zhiji' ),
					'default' => true,
				),
				array(
					'type'    => 'content',
					'content' => zhiji_reward_center_overview_html(),
				),

				/* ============================================================
				 * 场景一：评论福袋（唯一真正依赖本模块参数的场景）
				 * —— CommentFortune.php:234 调 grant_random()，不传 overrides，
				 *    完全按这里的权重表与数值区间发放
				 * ============================================================ */
				array(
					'id'       => 'zhiji_accordion_1',
					       'type'       => 'accordion',
					'accordions' => array(
						array(
							'title'  => __( '① 评论福袋 · 抽哪种', 'zhiji' ),
							'icon'   => 'fas fa-dice',
							'fields' => array(
								array(
									'type' => 'submessage',
									'style' => 'info',
									'content' => __( '评论福袋每次触发时，从下表<b>随机抽一种</b>奖励。'
										. '权重越大越容易被抽中；权重全为 0 时保底发积分；'
										. '<b>免单券权重</b>决定「谢谢参与」（没抽中）的概率。', 'zhiji' ),
								),
								array(
									'id'      => 'reward_center_w_points',
									'type'    => 'number',
									'title'   => __( '积分权重', 'zhiji' ),
									'desc'    => __( '抽中积分的概率权重。0 = 不产出积分。', 'zhiji' ),
									'default' => 3,
									'min'     => 0,
								),
								array(
									'id'      => 'reward_center_w_balance',
									'type'    => 'number',
									'title'   => __( '余额权重', 'zhiji' ),
									'desc'    => __( '抽中余额的概率权重。0 = 不产出余额。', 'zhiji' ),
									'default' => 2,
									'min'     => 0,
								),
								array(
									'id'      => 'reward_center_w_coupon',
									'type'    => 'number',
									'title'   => __( '优惠码权重', 'zhiji' ),
									'desc'    => __( '抽中优惠码的概率权重。0 = 不发券。', 'zhiji' ),
									'default' => 2,
									'min'     => 0,
								),
								array(
									'id'      => 'reward_center_w_vip',
									'type'    => 'number',
									'title'   => __( '会员权益权重', 'zhiji' ),
									'desc'    => __( '抽中会员天数的概率权重。0 = 不发会员。', 'zhiji' ),
									'default' => 2,
									'min'     => 0,
								),
								array(
									'id'      => 'reward_center_w_free',
									'type'    => 'number',
									'title'   => __( '免单券权重', 'zhiji' ),
									'desc'    => __( '抽中「谢谢参与」（未中奖）的权重。0 = 永不落空。', 'zhiji' ),
									'default' => 1,
									'min'     => 0,
								),
							),
						),
						array(
							'title'  => __( '② 评论福袋 · 抽中给多少', 'zhiji' ),
							'icon'   => 'fas fa-coins',
							'fields' => array(
								array(
									'type' => 'submessage',
									'style' => 'info',
									'content' => __( '上一步决定<b>抽不抽中</b>，这一步决定<b>给多少</b>。'
										. '实际发放值在「最小值 ~ 最大值」之间随机。', 'zhiji' ),
								),
								array(
									'id'      => 'reward_center_points_min',
									'type'    => 'number',
									'title'   => __( '积分 · 最小值', 'zhiji' ),
									'desc'    => __( '抽中积分时的随机下限。', 'zhiji' ),
									'default' => 10,
									'min'     => 1,
								),
								array(
									'id'      => 'reward_center_points_max',
									'type'    => 'number',
									'title'   => __( '积分 · 最大值', 'zhiji' ),
									'desc'    => __( '抽中积分时的随机上限。', 'zhiji' ),
									'default' => 100,
									'min'     => 1,
								),
								array(
									'id'      => 'reward_center_balance_min',
									'type'    => 'number',
									'title'   => __( '余额 · 最小值（元）', 'zhiji' ),
									'desc'    => __( '抽中余额时的随机下限。', 'zhiji' ),
									'default' => 1,
									'min'     => 0,
								),
								array(
									'id'      => 'reward_center_balance_max',
									'type'    => 'number',
									'title'   => __( '余额 · 最大值（元）', 'zhiji' ),
									'desc'    => __( '抽中余额时的随机上限。', 'zhiji' ),
									'default' => 5,
									'min'     => 0,
								),
								array(
									'id'      => 'reward_center_vip_days',
									'type'    => 'number',
									'title'   => __( '会员 · 天数', 'zhiji' ),
									'desc'    => __( '抽中会员权益时延长的天数。', 'zhiji' ),
									'default' => 7,
									'min'     => 1,
								),
								array(
									'id'      => 'reward_center_vip_level',
									'type'    => 'select',
									'title'   => __( '会员 · 等级', 'zhiji' ),
									'desc'    => __( '发放到的会员等级，取自「子比设置 → 会员」中已启用的等级。', 'zhiji' ),
									'options' => $vip_options,
									'default' => 1,
								),
							),
						),
					),
				),

				/* ============================================================
				 * 场景二：优惠码面值（评论福袋抽中券时用；注册迎新走自己的配置）
				 * —— 面值区间复用 CouponGive 的差异化体系，本模块只选区间 + 定有效期
				 * ============================================================ */
				array(
					'id'       => 'zhiji_accordion_2',
					       'type'       => 'accordion',
					'accordions' => array(
						array(
							'title'  => __( '③ 优惠码 · 面值与有效期（评论福袋抽中券时用）', 'zhiji' ),
							'icon'   => 'fas fa-ticket-alt',
							'fields' => array(
								array(
									'type' => 'submessage',
									'style' => 'info',
									'content' => __( '评论福袋抽中「优惠码」时按此规则发券：'
										. '在所选区间内随机出立减/折扣金额，各区间的上下限在'
										. '<b>「用户&amp;互动 → 优惠码」</b>中配置。'
										. '<br>注：<b>注册迎新</b>的迎新券面值走它自己的配置（该模块内的面值区间），不适用这里。', 'zhiji' ),
								),
								array(
									'id'      => 'reward_center_coupon_scope',
									'type'    => 'select',
									'title'   => __( '面值区间', 'zhiji' ),
									'desc'    => __( '决定发券时的立减/折扣随机范围。', 'zhiji' ),
									'options' => array(
										'login' => __( '登录用户区间（立减 1~10 / 折扣 0.7~0.95）', 'zhiji' ),
										'vip'   => __( 'VIP 用户区间（立减 2~20 / 折扣 0.5~0.9）', 'zhiji' ),
										'rand'  => __( '完全随机（立减 0.5~20 / 折扣 0.5~0.95）', 'zhiji' ),
									),
									'default' => 'login',
								),
								array(
									'id'      => 'reward_center_coupon_expire',
									'type'    => 'select',
									'title'   => __( '有效期规则', 'zhiji' ),
									'desc'    => __( '决定用户「我的优惠码」里显示的到期时间。', 'zhiji' ),
									'options' => array(
										'random' => __( '随机分配（7 天 / 30 天 / 永久 三档随机）', 'zhiji' ),
										'7'      => __( '统一 7 天', 'zhiji' ),
										'30'     => __( '统一 30 天', 'zhiji' ),
										'0'      => __( '永久有效', 'zhiji' ),
									),
									'default' => 'random',
								),
							),
						),
					),
				),

				/* ============================================================
				 * 预留能力：全发模式（一次性发全部奖励）
				 * —— grant_all() 实测**零调用方**，明确标注避免误解
				 * ============================================================ */
				array(
					'id'       => 'zhiji_accordion_3',
					       'type'       => 'accordion',
					'accordions' => array(
						array(
							'title'  => __( '④ 全发模式（预留 · 当前无业务使用）', 'zhiji' ),
							'icon'   => 'fas fa-gift',
							'fields' => array(
								array(
									'type' => 'submessage',
									'style' => 'warning',
									'content' => __( '「全发模式」会一次性发放下列<b>全部</b>奖励，而非随机抽一种。'
										. '实测：目前<b>没有任何业务在调用</b>该模式 —— 评论福袋用的是「随机抽一种」，'
										. '注册迎新与邮件订阅各发固定奖励。此处仅作为能力预留保留，'
										. '将来接入新场景时开启对应项即可。', 'zhiji' ),
								),
								array(
									'id'      => 'reward_center_all_points',
									'type'    => 'switcher',
									'title'   => __( '发放积分', 'zhiji' ),
									'default' => true,
								),
								array(
									'id'      => 'reward_center_all_balance',
									'type'    => 'switcher',
									'title'   => __( '发放余额', 'zhiji' ),
									'default' => false,
								),
								array(
									'id'      => 'reward_center_all_coupon',
									'type'    => 'switcher',
									'title'   => __( '发放优惠码', 'zhiji' ),
									'default' => true,
								),
								array(
									'id'      => 'reward_center_all_vip',
									'type'    => 'switcher',
									'title'   => __( '发放会员权益', 'zhiji' ),
									'default' => false,
								),
								array(
									'id'      => 'reward_center_all_free',
									'type'    => 'switcher',
									'title'   => __( '发放免单券', 'zhiji' ),
									'desc'    => __( '免单券（0 折全免）价值较高，建议谨慎开启。', 'zhiji' ),
									'default' => false,
								),
							),
						),
					),
				),

				/* ============================================================
				 * 只读信息（勋章墙 + 发放记录说明）
				 * —— 都不是配置项，单独成节并标注「只读」，避免用户在里面找可填的东西
				 * ============================================================ */
				array(
					'id'       => 'zhiji_accordion_4',
					       'type'       => 'accordion',
					'accordions' => array(
						array(
							'title'  => __( '附：勋章墙（只读 · 达成即自动授予）', 'zhiji' ),
							'icon'   => 'fas fa-award',
							'fields' => array(
								array(
									'type'    => 'content',
									'content' => zhiji_reward_center_medals_html(),
								),
							),
						),
						array(
							'title'  => __( '附：发放记录（只读 · 在用户中心查看）', 'zhiji' ),
							'icon'   => 'fas fa-list-alt',
							'fields' => array(
								array(
									'type'    => 'content',
									'content' => __( '奖励发放记录在用户中心「余额 / 积分明细」查看。'
										. '来源标签会统一显示为中文（如「评论福袋」「大转盘抽奖」），不会把内部英文码暴露给用户。', 'zhiji' ),
								),
							),
						),
					),
				),

			), 20 );
}
// 2026-09-26：改为 Registry 统一登记（P3-⑨），此处直接调用替代钩子
zhiji_reward_center_register_options();


/* ============================================================
 * 2. 统一发放入口
 * ============================================================ */

/**
 * 随机抽一种奖励发放。
 *
 * 2026-10-03 实测：**唯一调用方是评论福袋**（CommentFortune.php:234）。
 * 抽奖（大转盘）有自己的发奖实现（zhiji_lottery_grant_prize），
 * 不走本函数 —— 此前注释写「抽奖用」是错的，一并更正。
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
 * 奖励中心「谁会用到这里的配置」面板
 *
 * 目的：奖励配置散落在多个分类多个模块，排查「奖为什么没发」要来回跳。
 * 本面板列出各业务与本页的真实关系（只读，不改变任何开关归属）。
 *
 * @return string HTML
 */
function zhiji_reward_center_overview_html() {
	/**
	 * 跳转链接
	 *
	 * ⚠️ 这里连踩两个坑，都必须靠**实测**而非推断：
	 *
	 * ① tab id **不是** `register_module` 的 `parent`（如 zhiji_comment），
	 *    而是**分类标题**经 `sanitize_title()` 的结果 —— 线上实测：
	 *      '评论&互动' → '%e8%af%84%e8%ae%ba%e4%ba%92%e5%8a%a8'
	 *      子节 = 父分类 id + '/' + sanitize_title(子节标题)
	 *    用 parent key 拼出来的链接，点了必然无效。
	 *
	 * ② 子节标题**也不是**模块 key 或我以为的名字：member_guide 的分区叫
	 *    「会员引导」（不是「注册迎新」）、lottery 叫「抽奖大转盘」（不是「大转盘」）。
	 *    → 所以这里**从 Registry 动态取真实 title**，不硬编码。
	 *    父分类标题取自 includes/options/admin-options.php 的 $cats。
	 *
	 * @param string $parent_title 父分类标题（如 '用户&互动'）
	 * @param string $module_key   模块 key（用于取真实分区名）
	 * @return string
	 */
	$link = function ( $parent_title, $module_key ) {
		$mods    = Zhiji_Registry::modules();
		$sub     = isset( $mods[ $module_key ]['title'] ) ? (string) $mods[ $module_key ]['title'] : $module_key;
		$id      = sanitize_title( $parent_title ) . '/' . sanitize_title( $sub );
		return sprintf(
			'<a href="#tab=%1$s" data-zhiji-goto="%1$s" class="zhiji-goto-section" title="去「%2$s → %3$s」设置">去设置 →</a>',
			esc_attr( $id ),
			esc_attr( $parent_title ),
			esc_attr( $sub )
		);
	};

	// 分区名一律走 Registry 动态取（见上方 $link 的注释：子节标题不是模块 key，
	// 实测 member_guide=「会员引导」、lottery=「抽奖大转盘」）。
	// 第 4 列是**模块 key**，第 6 列 $how 是说明文案。
	$channels = array(
		array( '评论福袋',     true,  'comment_fortune_enabled', '按本页权重随机抽一种',     '评论&互动', 'comment_fortune', '随机抽一种（用本页权重+区间）' ),
		array( '注册迎新',     false, 'member_guide_enabled',    '固定发一张迎新券',       '用户&互动', 'member_guide',    '固定发券（面值走本模块配置）' ),
		array( '邮件订阅奖励', false, 'email_sub_enabled',       '按本模块配的积分数发放', '用户&互动', 'email_subscribe', '固定发积分（数量走本模块配置）' ),
		array( '大转盘抽奖',   false, 'lottery_enabled',         '奖品池独立配置',         '用户&互动', 'lottery',         '自己发奖（奖品池独立）' ),
		array( '互动答题',     false, 'quiz_enabled',            '题库独立配置',           '互动&趣味', 'quiz',            '自己发奖（题库独立）' ),
	);

	$rows = '';
	foreach ( $channels as $c ) {
		list( $name, $uses, $opt, $params, $parent, $sub, $how ) = $c;
		$on    = zhiji_is_enabled( $opt, true );
		$badge = $on
			? '<span style="color:#16a34a;font-weight:600">● 已启用</span>'
			: '<span style="color:#9ca3af;font-weight:600">○ 已关闭</span>';
		$uses_badge = $uses
			? '<span style="color:#2563eb">✓ 用本页配置</span>'
			: '<span style="color:#9ca3af">仅借发奖引擎</span>';
		// ⚠️ 此前这一列直接显示内部 option 名（salary_enabled / member_guide_enabled…），
		//    对用户毫无意义 —— 改为跳转到对应设置分区。
		$goto = $link( $parent, $sub );
		$rows .= '<tr>'
			. '<td style="padding:6px 10px;border-bottom:1px solid #f0f0f0">' . esc_html( $name ) . '</td>'
			. '<td style="padding:6px 10px;border-bottom:1px solid #f0f0f0">' . $uses_badge . '</td>'
			. '<td style="padding:6px 10px;border-bottom:1px solid #f0f0f0;color:#666">' . esc_html( $how ) . '</td>'
			. '<td style="padding:6px 10px;border-bottom:1px solid #f0f0f0">' . $badge . '</td>'
			. '<td style="padding:6px 10px;border-bottom:1px solid #f0f0f0">' . $goto . '</td>'
			. '</tr>';
	}

	return '<div style="margin:6px 0 18px">'
		. '<div style="font-weight:600;margin-bottom:6px">📊 谁会用到这里的配置</div>'
		. '<div style="color:#666;font-size:12px;line-height:1.9;margin-bottom:8px">'
		. '只有<b>评论福袋</b>真正使用下面的权重与数值区间。'
		. '注册迎新与邮件订阅只是借用这里的<b>发奖引擎</b>（负责记账/发券），'
		. '它们发什么、发多少在各自模块内配置。'
		. '</div>'
		. '<table style="width:100%;border-collapse:collapse;font-size:13px">'
		. '<thead><tr style="background:#f7f8fa;text-align:left">'
		. '<th style="padding:6px 10px">业务</th><th style="padding:6px 10px">用这里的配置吗</th>'
		. '<th style="padding:6px 10px">它实际怎么发</th><th style="padding:6px 10px">渠道状态</th>'
		. '<th style="padding:6px 10px">跳转</th></tr></thead>'
		. '<tbody>' . $rows . '</tbody></table>'
		. '<div style="color:#999;font-size:12px;margin-top:8px">'
		. '发放记录在用户中心「余额 / 积分明细」查看；运维明细到「扩展&amp;增强 → 运维管理」查。'
		. '</div></div>'
		. zhiji_reward_center_goto_js();
}

/**
 * 「去设置」跳转的 JS 兜底
 *
 * ⚠️ 为什么需要它（实测得出，不能想当然）：
 *   ① CSF 的 tab id **不是** register_module 的 parent（如 zhiji_comment），
 *      而是**分类标题**经 sanitize_title() 的结果（线上实测 '评论&互动'
 *      → '%e8%af%84%e8%ae%ba%e4%ba%92%e5%8a%a8'），子节 = 父 + '/' + sanitize_title(子节标题)。
 *      用 parent key 拼出来的链接，点了必然无效。
 *   ② CSF 的切换由**它自己绑在侧边栏 `<a data-tab-id>` 上的事件**驱动，
 *      **不监听 location.hash** —— 光有正确的 `href="#tab=…"` 也不会切换。
 *      线上实测其内容区是 `div.csf-section.hidden[data-section-id]`，
 *      侧边栏是 `li.csf-tab-item > a[data-tab-id]`，页面里**没有** ui-tabs。
 *
 * 做法：点「去设置」时，转而在侧边栏找到对应 `a[data-tab-id="…"]` 并 `.trigger('click')`，
 * 把切换交还给 CSF 自己的逻辑 —— 不重复实现它的切换动画与状态管理。
 * 若找不到（分类被改名等），回退到直接显示目标分区，至少让用户看得到内容。
 */
function zhiji_reward_center_goto_js() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	return <<<'HTML'
<script id="zhiji-rc-goto">
(function(){
  function go(anchor){
    var id = anchor.getAttribute('data-zhiji-goto');
    if(!id) return;
    var target = document.querySelector('.csf-nav-options a[data-tab-id="' + id + '"]');
    if(target){ target.click(); return; }
    // 侧边栏没有该分区（可能被改名/移除）：直接把目标分区显示出来，避免点了没反应
    var sec = document.querySelector('.csf-section[data-section-id="' + id + '"]');
    if(sec){
      var secs = document.querySelectorAll('.csf-section');
      for(var i=0;i<secs.length;i++){ secs[i].classList.add('hidden'); }
      sec.classList.remove('hidden');
      sec.scrollIntoView({behavior:'smooth', block:'start'});
    }
  }
  document.addEventListener('click', function(e){
    var a = e.target.closest ? e.target.closest('a[data-zhiji-goto]') : null;
    if(!a) return;
    e.preventDefault();
    go(a);
  });
})();
</script>
HTML;
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
			// P2：改走 CouponIssuer 契约，不再直调 coupon_give 的内部函数。
			// ⚠️ notify 显式传 false —— 原实现不发站内通知（抽奖才发），保持行为一致。
			// ⚠️ desc 文案仍在本处自算：奖励中心口径是「立减 ¥8.84 / 8.8 折」，
			//    与 coupon_give 的「立减8.84元」不同，改用契约返回值会变更用户可见文案。
			$issuer = zhiji_contract( 'CouponIssuer' );
			if ( $issuer ) {
				$scope    = isset( $overrides['coupon_scope'] ) ? $overrides['coupon_scope'] : zhiji_get_option( 'reward_center_coupon_scope', 'login' );
				$discount = $issuer->discount_meta( $scope );
				// 有效期（2026-09-29 修复"全部永久有效"）：默认 7 天/30 天/永久 三档随机，可在后台改规则
				$expire_rule = (string) zhiji_get_option( 'reward_center_coupon_expire', 'random' );
				$expire_days = ( 'random' === $expire_rule ) ? zhiji_coupon_expire_rand_days() : (int) $expire_rule;
				$res = $issuer->issue( array(
					'discount'    => $discount,
					'title'       => '奖励中心专属优惠码',
					'expire_days' => $expire_days,
					'user_id'     => $uid,
					'source'      => $source ? $source : 'reward_center',
					'notify'      => false,
				) );
				if ( ! empty( $res['ok'] ) ) {
					$code = $res['code'];
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
			// P2：改走 CouponIssuer 契约。免单券 = multiply×0（订单全免），沿用原口径。
			$issuer = zhiji_contract( 'CouponIssuer' );
			if ( $issuer ) {
				$res = $issuer->issue( array(
					'discount'    => array( 'type' => 'multiply', 'val' => 0 ),
					'title'       => '奖励中心免单券',
					'expire_days' => 0,   // 原实现未设 expire_time = 永久有效
					'user_id'     => $uid,
					'source'      => ( $source ? $source : 'reward_center' ) . '_free',
					'notify'      => false,
				) );
				if ( ! empty( $res['ok'] ) ) {
					return array( 'type' => 'free', 'name' => __( '免单券', 'zhiji' ), 'val' => $res['code'], 'desc' => __( '下单直接免单', 'zhiji' ), 'code' => $res['code'] );
				}
			}
			// 免单券生成失败，保底发积分
			return zhiji_reward_center_grant_one( $uid, 'points', $source, array_merge( $overrides, array( 'points_min' => 20, 'points_max' => 50 ) ) );

		default:
			return array();
	}
}

/* ============================================================
 * 3. 弹幕文案
 * ============================================================ */

/**
 * 把单条奖励转成弹幕/通知文案
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
 * 4. Ledger 契约实现（P2 架构重构）
 * ------------------------------------------------------------
 * 把「积分 / 余额的增减」收敛成唯一入口，其余模块只依赖契约、不再
 * 直接调 zhiji_reward_center_grant_one()。
 *
 * 【与既有函数的关系，一句话说清】
 *   zhiji_reward_center_grant_one()  = 「抽一种奖励」（带随机权重/随机金额/保底降级）
 *   本类                            = 「账本记账」（给定类型与数量，精确增减）
 * 二者不是替代关系：grant_one 内部可以调本类记账，但本类**不含任何随机逻辑**。
 * 这样拆分的原因：抽奖/福袋/答题要的是「随机发奖」，而商城扣积分、兑换扣减要的是
 * 「精确记账 + 余额校验 + 幂等」。混在一起会让抽奖逻辑被扣减场景污染。
 *
 * 【为何要幂等键】
 * 抽奖有「每日限量 + 网络重试 + 用户连点」，同一笔奖励被发两次是真实现象
 * （旧代码靠 ClaimLog 事后统计，无法阻止重复发放）。has_key() 让发奖方能
 * 在发奖**前**判重。
 * ============================================================ */

if ( ! class_exists( 'Zhiji_Ledger_RewardCenter' ) ) :

	/**
	 * 积分 / 余额账本（实现 Zhiji_Contract_Ledger）
	 */
	class Zhiji_Ledger_RewardCenter implements Zhiji_Contract_Ledger {

		/**
		 * 支持的账本类型 → 父主题 zibpay 的账本名
		 * @var array<string,string>
		 */
		private $types = array(
			'points'  => 'points',
			'balance' => 'balance',
		);

		/**
		 * 账本类型是否受支持
		 *
		 * @param string $type
		 * @return bool
		 */
		public function supports( $type ) {
			return isset( $this->types[ $type ] );
		}

		/**
		 * 发放（增加余额/积分）
		 *
		 * @param int    $uid
		 * @param string $type   points / balance
		 * @param int    $amount 正数
		 * @param array  $meta   source / desc / idem_key
		 * @return array{ok:bool,balance:float|int,message:string}
		 */
		public function grant( $uid, $type, $amount, array $meta = array() ) {
			$uid = (int) $uid;
			$type = (string) $type;
			$is_balance = ( 'balance' === $type );

			if ( ! $uid || ! $this->supports( $type ) ) {
				return $this->fail( __( '账本类型不支持', 'zhiji' ) );
			}
			$amount = $is_balance ? (float) $amount : (int) $amount;
			if ( $amount <= 0 ) {
				return $this->fail( __( '发放数量必须为正', 'zhiji' ) );
			}
			// 幂等：同一 key 已发过则直接返回当前余额，不重复发放
			$key = isset( $meta['idem_key'] ) ? (string) $meta['idem_key'] : '';
			if ( $key && $this->has_key( $key ) ) {
				return array(
					'ok'      => false,
					'balance' => $this->balance( $uid, $type ),
					'message' => __( '该奖励已发放过', 'zhiji' ),
				);
			}

			$args = array(
				'value' => $amount,
				'type'  => isset( $meta['source'] ) && '' !== $meta['source']
					? zhiji_reward_source_label( $meta['source'] )
					: __( '系统奖励', 'zhiji' ),
				'desc'  => isset( $meta['desc'] ) ? (string) $meta['desc'] : '',
			);

			$ok = $is_balance
				? Zhiji_Adapter::update_user_balance( $uid, $args )
				: Zhiji_Adapter::update_user_points( $uid, $args );

			if ( ! $ok ) {
				return $this->fail( __( '账本写入失败（父主题 zibpay 未就绪？）', 'zhiji' ) );
			}

			if ( $key ) {
				$this->mark_key( $key );
			}

			return array(
				'ok'      => true,
				'balance' => $this->balance( $uid, $type ),
				'message' => __( '发放成功', 'zhiji' ),
			);
		}

		/**
		 * 扣减（减少余额/积分）
		 *
		 * 余额充足性由父主题的**条件更新 SQL** 保证原子：
		 *   UPDATE … SET meta_value = CAST(meta_value AS SIGNED) - N
		 *    WHERE … AND CAST(meta_value AS SIGNED) >= N
		 * 即「读-判断-写」被压成单条语句，并发下不会扣成负数。
		 * （见父主题 zibpay/functions/zibpay-balance.php:149 zibpay_user_balance_or_points_save_db）
		 *
		 * @param int    $uid
		 * @param string $type
		 * @param int    $amount 正数，内部取负
		 * @param array  $meta
		 * @return array{ok:bool,balance:float|int,message:string}
		 */
		public function spend( $uid, $type, $amount, array $meta = array() ) {
			$uid = (int) $uid;
			$type = (string) $type;
			$is_balance = ( 'balance' === $type );

			if ( ! $uid || ! $this->supports( $type ) ) {
				return $this->fail( __( '账本类型不支持', 'zhiji' ) );
			}
			$amount = $is_balance ? (float) $amount : (int) $amount;
			if ( $amount <= 0 ) {
				return $this->fail( __( '扣减数量必须为正', 'zhiji' ) );
			}

			$key = isset( $meta['idem_key'] ) ? (string) $meta['idem_key'] : '';
			if ( $key && $this->has_key( $key ) ) {
				return array(
					'ok'      => false,
					'balance' => $this->balance( $uid, $type ),
					'message' => __( '该笔已扣减过', 'zhiji' ),
				);
			}

			// 前置余额校验（仅用于给出准确文案；真正的原子性在 SQL 里）
			$before = $this->balance( $uid, $type );
			if ( ( $is_balance ? (float) $before : (int) $before ) < $amount ) {
				return array(
					'ok'      => false,
					'balance' => $before,
					'message' => $is_balance
						? __( '余额不足', 'zhiji' )
						: __( '积分不足', 'zhiji' ),
				);
			}

			$args = array(
				'value' => -1 * $amount,
				'type'  => isset( $meta['source'] ) && '' !== $meta['source']
					? zhiji_reward_source_label( $meta['source'] )
					: __( '系统消费', 'zhiji' ),
				'desc'  => isset( $meta['desc'] ) ? (string) $meta['desc'] : '',
			);

			$ok = $is_balance
				? Zhiji_Adapter::update_user_balance( $uid, $args )
				: Zhiji_Adapter::update_user_points( $uid, $args );

			if ( ! $ok ) {
				return $this->fail( __( '账本写入失败（余额不足或父主题未就绪）', 'zhiji' ) );
			}

			if ( $key ) {
				$this->mark_key( $key );
			}

			return array(
				'ok'      => true,
				'balance' => $this->balance( $uid, $type ),
				'message' => __( '扣减成功', 'zhiji' ),
			);
		}

		/**
		 * 查询余额
		 *
		 * @param int    $uid
		 * @param string $type
		 * @return int|float
		 */
		public function balance( $uid, $type ) {
			$uid = (int) $uid;
			$type = (string) $type;
			if ( ! $uid || ! $this->supports( $type ) ) {
				return 0;
			}
			return ( 'balance' === $type )
				? (float) Zhiji_Adapter::get_user_balance( $uid )
				: (int) Zhiji_Adapter::get_user_points( $uid );
		}

		/**
		 * 幂等键是否已被占用
		 *
		 * 存 user meta（非全局 option）—— 键天然带用户维度，跨用户不会互相干扰；
		 * 容量固定在 100 条内，超出按先进先出淘汰，避免无限制增长。
		 *
		 * @param string $key
		 * @return bool
		 */
		public function has_key( $key ) {
			$key = (string) $key;
			if ( '' === $key ) {
				return false;
			}
			$uid = (int) $this->key_owner( $key );
			if ( ! $uid ) {
				return false;
			}
			$keys = (array) get_user_meta( $uid, 'zhiji_ledger_idem_keys', true );
			return in_array( $key, $keys, true );
		}

		/**
		 * 占用一个幂等键
		 *
		 * @param string $key
		 * @return void
		 */
		private function mark_key( $key ) {
			$uid = (int) $this->key_owner( $key );
			if ( ! $uid ) {
				return;
			}
			$keys = (array) get_user_meta( $uid, 'zhiji_ledger_idem_keys', true );
			if ( in_array( $key, $keys, true ) ) {
				return;
			}
			$keys[] = $key;
			if ( count( $keys ) > 100 ) {
				$keys = array_slice( $keys, -100 );
			}
			update_user_meta( $uid, 'zhiji_ledger_idem_keys', $keys );
		}

		/**
		 * 从幂等键反解出用户 ID
		 *
		 * 键格式约定：`<uid>:<场景>:<业务ref>`，首段即用户 ID。
		 * 这样契约层不必为 has_key 再单独传 uid（接口签名是 has_key($key)）。
		 *
		 * @param string $key
		 * @return int 0 = 解析不出
		 */
		private function key_owner( $key ) {
			$parts = explode( ':', (string) $key );
			return isset( $parts[0] ) ? (int) $parts[0] : 0;
		}

		/**
		 * 统一失败返回
		 *
		 * @param string $message
		 * @return array
		 */
		private function fail( $message ) {
			return array(
				'ok'      => false,
				'balance' => 0,
				'message' => $message,
			);
		}
	}

endif;

/**
 * 契约工厂：供 ContractRegistry 解析（工厂名规则 zhiji_contract_implementor_{模块key}）
 *
 * @return Zhiji_Contract_Ledger
 */
function zhiji_contract_implementor_reward_center() {
	static $impl = null;
	if ( null === $impl ) {
		$impl = new Zhiji_Ledger_RewardCenter();
	}
	return $impl;
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