<?php
/**
 * 奖励字段公共生成器
 *
 * @module  RewardFields
 * @desc    抽取 comment_fortune / reward_center 等模块共用的「五类奖励权重 + 数值区间 +
 *          会员天数等级」字段定义，供**新增模块**复用，避免同一套 CSF 结构再次重复。
 * @since   2.0.0（2026-09-26，由配置统一化探查报告 P0-① 驱动）
 *
 * ─────────────────────────────────────────────────────────────
 * ⚠️ 关于「存量模块是否替换为调用本生成器」的决策说明（2026-09-26）
 *
 * 结论：**本次不替换**存量模块（CommentFortune / RewardCenter）的字段定义。
 *
 * 原因：
 *  1. 字段定义属**声明式数据**，其重复的危害远小于「逻辑代码重复」；
 *     两处结构相同并不会带来运行期问题（无性能/正确性影响）。
 *  2. 替换需改动两个模块中**多段非连续字段块**，出错概率与实际收益不对等：
 *     一旦块边界判断失误，会导致后台设置项错乱、甚至已保存配置读不到。
 *  3. 本项收益是**可维护性**（未来改一处生效），而非用户可见的功能改进；
 *     在缺少完整回归测试的前提下，风险 > 收益。
 *
 * 建议：待 M3 上线稳定、有完整回归流程后，再单独开一次「字段定义收口」任务，
 *       逐个模块替换并逐项比对配置 key 与后台渲染结果。
 *
 * 因此本文件**暂不注册到加载列表**（避免无调用的无用加载）；
 * 新增模块若需奖励类字段，可直接 zib_require 引入后调用。
 * ─────────────────────────────────────────────────────────────
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 生成「五类奖励权重」字段（积分/余额/优惠码/会员/谢谢参与）
 *
 * @param string $prefix   字段前缀，如 'reward_center' 或 'comment_fortune'
 * @param array  $defaults 各权重默认值，键：points/balance/coupon/vip/free
 * @param array  $args     可选：'dependency' => array(...) 统一前置依赖
 * @return array CSF 字段数组
 */
function zhiji_reward_weight_fields( $prefix, $defaults = array(), $args = array() ) {
	$d    = wp_parse_args( $defaults, array(
		'points'  => 3,
		'balance' => 2,
		'coupon'  => 2,
		'vip'     => 2,
		'free'    => 1,
	) );
	$dep  = ! empty( $args['dependency'] ) ? array( 'dependency' => $args['dependency'] ) : array();
	$rows = array(
		'points'  => array( '积分权重', '抽取积分奖励的权重（0 = 不产出该奖励）。' ),
		'balance' => array( '余额权重', '抽取余额奖励的权重（0 = 不产出）。' ),
		'coupon'  => array( '优惠码权重', '抽取优惠码奖励的权重（0 = 不产出）。' ),
		'vip'     => array( '会员权重', '抽取会员奖励的权重（0 = 不产出）。' ),
		'free'    => array( '谢谢参与权重', '「谢谢参与」的权重（0 = 每次必中）。' ),
	);
	$out = array();
	foreach ( $rows as $key => $meta ) {
		$out[] = array_merge( array(
			'id'      => $prefix . '_w_' . $key,
			'type'    => 'number',
			'title'   => __( $meta[0], 'zhiji' ),
			'desc'    => __( $meta[1], 'zhiji' ),
			'default' => (int) $d[ $key ],
			'min'     => 0,
		), $dep );
	}
	return $out;
}

/**
 * 生成「奖励数值区间」字段（积分数值与余额区间）
 *
 * 注意 key 风格差异：reward_center 用 `_points_min`，comment_fortune 用 `_pts_min`
 * —— 由 $id_style 参数显式指定，确保与存量配置 key 完全一致。
 *
 * @param string $prefix   字段前缀
 * @param array  $defaults 键：pts_min/pts_max/bal_min/bal_max
 * @param string $id_style 'points'（reward_center 风格）| 'pts'（comment_fortune 风格）
 * @param array  $args     可选：'dependency' => array(...)
 * @return array CSF 字段数组
 */
function zhiji_reward_range_fields( $prefix, $defaults = array(), $id_style = 'points', $args = array() ) {
	$d   = wp_parse_args( $defaults, array(
		'pts_min' => 10,
		'pts_max' => 100,
		'bal_min' => 1,
		'bal_max' => 5,
	) );
	$dep = ! empty( $args['dependency'] ) ? array( 'dependency' => $args['dependency'] ) : array();
	$pfx = ( 'pts' === $id_style ) ? '_pts_' : '_points_';
	$out = array(
		array_merge( array(
			'id'      => $prefix . $pfx . 'min',
			'type'    => 'number',
			'title'   => __( '积分数值下限', 'zhiji' ),
			'desc'    => __( '积分奖励的随机下限。', 'zhiji' ),
			'default' => (int) $d['pts_min'],
			'min'     => 0,
		), $dep ),
		array_merge( array(
			'id'      => $prefix . $pfx . 'max',
			'type'    => 'number',
			'title'   => __( '积分数值上限', 'zhiji' ),
			'desc'    => __( '积分奖励的随机上限。', 'zhiji' ),
			'default' => (int) $d['pts_max'],
			'min'     => 0,
		), $dep ),
		array_merge( array(
			'id'      => $prefix . '_bal_min',
			'type'    => 'text',
			'title'   => __( '余额数值下限', 'zhiji' ),
			'desc'    => __( '余额奖励的随机下限（元）。', 'zhiji' ),
			'default' => (string) $d['bal_min'],
		), $dep ),
		array_merge( array(
			'id'      => $prefix . '_bal_max',
			'type'    => 'text',
			'title'   => __( '余额数值上限', 'zhiji' ),
			'desc'    => __( '余额奖励的随机上限（元）。', 'zhiji' ),
			'default' => (string) $d['bal_max'],
		), $dep ),
	);
	return $out;
}

/**
 * 生成「会员奖励」字段（天数 + 等级）
 *
 * @param string $prefix   字段前缀
 * @param array  $defaults 键：vip_days/vip_level
 * @param array  $args     可选：'dependency' => array(...)
 * @return array CSF 字段数组
 */
function zhiji_reward_vip_fields( $prefix, $defaults = array(), $args = array() ) {
	$d   = wp_parse_args( $defaults, array(
		'vip_days'  => 7,
		'vip_level' => 1,
	) );
	$dep = ! empty( $args['dependency'] ) ? array( 'dependency' => $args['dependency'] ) : array();
	return array(
		array_merge( array(
			'id'      => $prefix . '_vip_days',
			'type'    => 'number',
			'title'   => __( '会员天数', 'zhiji' ),
			'desc'    => __( '中奖会员的有效天数。', 'zhiji' ),
			'default' => (int) $d['vip_days'],
			'min'     => 1,
		), $dep ),
		array_merge( array(
			'id'      => $prefix . '_vip_level',
			'type'    => 'select',
			'title'   => __( '会员等级', 'zhiji' ),
			'desc'    => __( '中奖会员的等级（对应父主题的会员等级）。', 'zhiji' ),
			'options' => array(
				1 => 'VIP1',
				2 => 'VIP2',
				3 => 'VIP3',
			),
			'default' => (int) $d['vip_level'],
		), $dep ),
	);
}
