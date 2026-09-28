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
 * 1. 后台 CSF 分区：用户&互动 → 奖励中心
 * ============================================================ */

function zhiji_reward_center_register_options() {
	if ( ! class_exists( 'CSF' ) || ! is_admin() ) {
		return;
	}

	Zhiji_Registry::register_options( 'reward_center', array(

				array(
					'type'    => 'subheading',
					'title'   => __( '随机模式权重（数值越大越容易抽中，可为 0；全 0 时保底积分）', 'zhiji' ),
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
					'title' => __( '积分奖励参数', 'zhiji' ),
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
					'title' => __( '余额奖励参数', 'zhiji' ),
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

				array(
					'type'  => 'subheading',
					'title' => __( '优惠码奖励参数（复用 CouponGive 差异化面值体系）', 'zhiji' ),
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
					'type'  => 'subheading',
					'title' => __( '会员权益奖励参数', 'zhiji' ),
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
					'desc'       => __( '会员奖励的等级。', 'zhiji' ),
					'options' => array(
						1 => __( '月卡会员（LV1）', 'zhiji' ),
						2 => __( '年卡会员（LV2）', 'zhiji' ),
					),
					'default' => 1,
				),

				array(
					'type'  => 'subheading',
					'title' => __( '全发模式（答题/砍价等达标后发放哪些奖励）', 'zhiji' ),
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
 * 全发模式：发放所有已启用的奖励（答题/砍价达标用）。
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
				$meta     = array(
					'discount' => $discount,
					'title'    => '奖励中心专属优惠码',
					'reuse'    => 1,
					'user_id'  => $uid,
					'source'   => $source ? $source : 'reward_center',
				);
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
 * 3. 工具函数：奖励文本摘要（供弹幕/通知用）
 * ============================================================ */

/**
 * 将奖励数组转为简短文本（弹幕用）。
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