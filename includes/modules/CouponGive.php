<?php
/**
 * @module  CouponGive
 * @desc    邮箱优惠码：游客填邮箱领随机优惠码、邀请好友再领、退出挽留弹窗联动、
 *          用户中心「我的优惠码」页签与消息区优惠码点击复制
 * @option  coupon_give_enabled      总开关
 *          coupon_give_discount_*   优惠码面额区间（减价/折扣，按身份区分）
 *          coupon_give_diff_enabled 未登录差异面额
 *          coupon_give_per_email    同一邮箱领取上限
 *          coupon_give_daily_limit  全站每日发放上限
 *          coupon_give_ref_*        邀请奖励
 * @hook    wp_ajax(_nopriv)_zhiji_coupon_give · 领取端点（self 域动作）
 *          zib_user_center_page_sidebar_button_1_args · 用户中心侧栏按钮（filter）
 *          main_user_tab_content_coupon · 用户中心「我的优惠码」页签内容（filter）
 *          wp_footer · 消息区优惠码点击复制脚本
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/CouponGive.php`（整体随迁；CSF 块转 csf_section_for；
 *          zib_ 调用收口 Adapter（svg/user_vip_level/coupon_discount_text）；
 *          依赖父主题 ZibCardPass 卡密类，缺失时自动降级）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('coupon_give', array(
    'title'    => '邮箱优惠码',
    'parent'   => 'zhiji_pay',
    'priority' => 10,
    'option'   => 'coupon_give_enabled',
));

add_action( 'after_setup_theme', function () {
	Zhiji_Registry::csf_section_for( 'coupon_give', array(
			array(
				'id'      => 'coupon_give_enabled',
				'type'    => 'switcher',
				'title'   => '启用邮箱领取优惠码',
				'default' => false,
				'desc'    => '访客输入邮箱领取一次性优惠码，邮件发送+个人中心「我的优惠码」Tab。',
			),
			array(
				'id'         => 'coupon_give_discount_type',
				'type'       => 'button_set',
				'title'      => '优惠方式',
				'desc'       => __( '优惠形式：立减固定金额，或按系数打折；随机模式则两者随机。', 'zhiji' ),
				'default'    => 'reduce',
				'options'    => array(
					'reduce'   => '立减',
					'multiply' => '折扣',
					'random'   => '随机',
				),
				'dependency' => array( 'coupon_give_enabled', '==', '1' ),
			),
			array(
				'id'         => 'coupon_give_reduce_min',
				'type'       => 'text',
				'title'      => '立减金额下限',
				'default'    => '1',
				'desc'       => '立减模式：优惠金额在此区间随机（元）。',
				'dependency' => array( 'coupon_give_enabled|coupon_give_discount_type', 'any|==', '1|reduce' ),
			),
			array(
				'id'         => 'coupon_give_reduce_max',
				'type'       => 'text',
				'title'      => '立减金额上限',
				'desc'       => __( '立减模式的优惠金额上限（元）。与下限共同构成随机区间。', 'zhiji' ),
				'default'    => '10',
				'dependency' => array( 'coupon_give_enabled|coupon_give_discount_type', 'any|==', '1|reduce' ),
			),
			array(
				'id'         => 'coupon_give_multiply_min',
				'type'       => 'text',
				'title'      => '折扣系数下限',
				'default'    => '0.7',
				'desc'       => '折扣模式：系数在此区间随机（如 0.8 = 8 折）。',
				'dependency' => array( 'coupon_give_enabled|coupon_give_discount_type', 'any|==', '1|multiply' ),
			),
			array(
				'id'         => 'coupon_give_multiply_max',
				'type'       => 'text',
				'title'      => '折扣系数上限',
				'desc'       => __( '折扣模式的系数上限（如 0.95 表示 9.5 折）。', 'zhiji' ),
				'default'    => '0.95',
				'dependency' => array( 'coupon_give_enabled|coupon_give_discount_type', 'any|==', '1|multiply' ),
			),
			array(
				'id'         => 'coupon_give_diff_enabled',
				'type'       => 'switcher',
				'title'      => '差异化面值',
				'default'    => false,
				'desc'       => '按身份给不同面值：VIP > 登录用户 > 游客。',
				'dependency' => array( 'coupon_give_enabled', '==', '1' ),
			),
			array(
				'id'         => 'coupon_give_login_reduce_min',
				'type'       => 'text',
				'title'      => '登录用户立减下限',
				'desc'       => __( '登录用户专属立减下限（元），通常比游客更优惠。', 'zhiji' ),
				'default'    => '2',
				'dependency' => array( 'coupon_give_enabled|coupon_give_diff_enabled', 'any|==', '1|1' ),
			),
			array(
				'id'         => 'coupon_give_login_reduce_max',
				'type'       => 'text',
				'title'      => '登录用户立减上限',
				'desc'       => __( '登录用户专属立减上限（元）。', 'zhiji' ),
				'default'    => '15',
				'dependency' => array( 'coupon_give_enabled|coupon_give_diff_enabled', 'any|==', '1|1' ),
			),
			array(
				'id'         => 'coupon_give_vip_reduce_min',
				'type'       => 'text',
				'title'      => 'VIP 立减下限',
				'desc'       => __( 'VIP 会员专属立减下限（元），最优惠档位。', 'zhiji' ),
				'default'    => '3',
				'dependency' => array( 'coupon_give_enabled|coupon_give_diff_enabled', 'any|==', '1|1' ),
			),
			array(
				'id'         => 'coupon_give_vip_reduce_max',
				'type'       => 'text',
				'title'      => 'VIP 立减上限',
				'desc'       => __( 'VIP 会员专属立减上限（元）。', 'zhiji' ),
				'default'    => '20',
				'dependency' => array( 'coupon_give_enabled|coupon_give_diff_enabled', 'any|==', '1|1' ),
			),
			array(
				'id'         => 'coupon_give_login_multiply_min',
				'type'       => 'text',
				'title'      => '登录用户折扣下限',
				'desc'       => __( '登录用户专属折扣下限（系数，越小越优惠）。', 'zhiji' ),
				'default'    => '0.65',
				'dependency' => array( 'coupon_give_enabled|coupon_give_diff_enabled', 'any|==', '1|1' ),
			),
			array(
				'id'         => 'coupon_give_login_multiply_max',
				'type'       => 'text',
				'title'      => '登录用户折扣上限',
				'desc'       => __( '登录用户专属折扣上限。', 'zhiji' ),
				'default'    => '0.9',
				'dependency' => array( 'coupon_give_enabled|coupon_give_diff_enabled', 'any|==', '1|1' ),
			),
			array(
				'id'         => 'coupon_give_vip_multiply_min',
				'type'       => 'text',
				'title'      => 'VIP 折扣下限',
				'desc'       => __( 'VIP 会员专属折扣下限（系数，最优惠档）。', 'zhiji' ),
				'default'    => '0.5',
				'dependency' => array( 'coupon_give_enabled|coupon_give_diff_enabled', 'any|==', '1|1' ),
			),
			array(
				'id'         => 'coupon_give_vip_multiply_max',
				'type'       => 'text',
				'title'      => 'VIP 折扣上限',
				'desc'       => __( 'VIP 会员专属折扣上限。', 'zhiji' ),
				'default'    => '0.85',
				'dependency' => array( 'coupon_give_enabled|coupon_give_diff_enabled', 'any|==', '1|1' ),
			),
			array(
				'id'         => 'coupon_give_daily_limit',
				'type'       => 'text',
				'title'      => '每日发放总量',
				'default'    => '0',
				'desc'       => '0 = 不限量；大于 0 时每日发放到该数量即停止。',
				'dependency' => array( 'coupon_give_enabled', '==', '1' ),
			),
			array(
				'id'         => 'coupon_give_limit_per',
				'type'       => 'text',
				'title'      => '每邮箱限领',
				'desc' => __( '同一邮箱最多可领取的优惠码张数。', 'zhiji' ),
				'default'    => '1',
				'dependency' => array( 'coupon_give_enabled', '==', '1' ),
			),
			array(
				'id'         => 'coupon_give_once_per_user',
				'type'       => 'switcher',
				'title'      => '每位用户仅限领取一次',
				'desc' => __( '开启后同一用户（账号或 IP）仅能领取一次。', 'zhiji' ),
				'default'    => true,
				'dependency' => array( 'coupon_give_enabled', '==', '1' ),
			),
			array(
				'id'         => 'coupon_give_expire_days',
				'type'       => 'text',
				'title'      => '优惠码有效天数',
				'desc' => __( '优惠码的有效天数，过期后不可使用。', 'zhiji' ),
				'default'    => '7',
				'dependency' => array( 'coupon_give_enabled', '==', '1' ),
			),
			array(
				'id'         => 'coupon_give_title',
				'type'       => 'text',
				'title'      => '优惠码标题',
				'desc' => __( '优惠码在「我的优惠码」等位置的显示名称。', 'zhiji' ),
				'default'    => '挽留弹窗专属优惠',
				'dependency' => array( 'coupon_give_enabled', '==', '1' ),
			),
			array(
				'id'         => 'coupon_give_post_id',
				'type'       => 'text',
				'title'      => '指定商品文章 ID',
				'default'    => '0',
				'desc'       => '0 = 全站通用；填写文章 ID 后优惠码仅限该商品使用。',
				'dependency' => array( 'coupon_give_enabled', '==', '1' ),
			),
			array(
				'id'         => 'coupon_give_ref_enabled',
				'type'       => 'switcher',
				'title'      => '分享裂变',
				'default'    => false,
				'desc'       => '好友通过邀请链接领码，邀请者各得一张奖励码。',
				'dependency' => array( 'coupon_give_enabled', '==', '1' ),
			),
			array(
				'id'         => 'coupon_give_ref_max',
				'type'       => 'text',
				'title'      => '裂变奖励上限',
				'desc' => __( '邀请裂变中，邀请者可获得的最大奖励张数。', 'zhiji' ),
				'default'    => '3',
				'dependency' => array( 'coupon_give_enabled|coupon_give_ref_enabled', 'any|==', '1|1' ),
			),
			array(
				'id'         => 'coupon_give_mail_title',
				'type'       => 'text',
				'title'      => '邮件标题',
				'placeholder' => '留空用默认',
				'desc'       => '支持 {discount} 占位符（显示实际优惠内容）。',
				'dependency' => array( 'coupon_give_enabled', '==', '1' ),
			),
			array(
				'id'         => 'coupon_give_mail_content',
				'type'       => 'textarea',
				'title'      => '邮件正文',
				'desc' => __( '发券邮件的正文内容；留空则使用内置默认模板。', 'zhiji' ),
				'placeholder' => '留空用默认模板',
				'dependency' => array( 'coupon_give_enabled', '==', '1' ),
			),
			array(
				'id'         => 'coupon_give_notice_enabled',
				'type'       => 'switcher',
				'title'      => '站内通知',
				'default'    => true,
				'desc'       => '领取成功后发送站内消息通知。',
				'dependency' => array( 'coupon_give_enabled', '==', '1' ),
			),
			array(
				'id'         => 'coupon_give_show_in_exit',
				'type'       => 'switcher',
				'title'      => '显示在退出挽留弹窗',
				'desc' => __( '是否在退出挽留弹窗中展示邮箱领券入口。', 'zhiji' ),
				'default'    => true,
				'dependency' => array( 'coupon_give_enabled', '==', '1' ),
			),
			array(
				'id'         => 'coupon_give_form_title',
				'type'       => 'text',
				'title'      => '领取表单标题',
				'desc' => __( '领取表单上方的标题文字。', 'zhiji' ),
				'default'    => '输入邮箱，领取专属优惠码',
				'dependency' => array( 'coupon_give_enabled', '==', '1' ),
			),
			array(
				'id'         => 'coupon_give_user_tab',
				'type'       => 'switcher',
				'title'      => '个人中心「我的优惠码」Tab',
				'desc' => __( '是否在个人中心增加「我的优惠码」页签。', 'zhiji' ),
				'default'    => true,
				'dependency' => array( 'coupon_give_enabled', '==', '1' ),
			),
) );
}, 20 );

/* =====================================================================
 * 一、工具函数
 * ===================================================================== */

/**
 * 在 [min,max] 范围内生成一个随机小数（保留两位小数）
 *
 * @param float $min 下限
 * @param float $max 上限
 * @return float
 */
function zhiji_coupon_give_rand_decimal( $min, $max ) {
	$i_min = (int) round( (float) $min * 100 );
	$i_max = (int) round( (float) $max * 100 );
	if ( $i_max <= $i_min ) {
		$i_max = $i_min + 1;
	}
	return wp_rand( $i_min, $i_max ) / 100;
}

/**
 * 生成优惠内容文本（如「立减5元」「8.8折」）
 *
 * 优先复用父主题 zibpay_get_coupon_discount_text()，缺失时兜底。
 *
 * @param array $discount discount 结构（type: reduce|multiply + val）
 * @return string
 */
function zhiji_coupon_give_discount_text( $discount ) {
	if ( ! is_array( $discount ) || empty( $discount['type'] ) || ! isset( $discount['val'] ) ) {
		return '';
	}
    $text = Zhiji_Adapter::coupon_discount_text( $discount );
    if ( is_string( $text ) && '' !== $text ) {
        return $text;
    }
    if ( 'multiply' === $discount['type'] ) {
		return ( $discount['val'] * 10 ) . __( '折', 'zhiji' );
	}
	/* translators: %s: 立减金额 */
	return sprintf( __( '立减%s元', 'zhiji' ), $discount['val'] );
}

/**
 * 当前领取者身份区间（差异化面值）
 *
 * - base  游客（基础区间）
 * - login 登录用户（登录区间）
 * - vip   VIP 用户（VIP 区间，父主题 zib_get_user_vip_level）
 *
 * @return string
 */
function zhiji_coupon_give_scope() {
	if ( ! zhiji_get_option( 'coupon_give_diff_enabled', 0 ) ) {
		return 'base';
	}
	if ( is_user_logged_in() && Zhiji_Adapter::user_vip_level( get_current_user_id() ) ) {
		return 'vip';
	}
	if ( is_user_logged_in() ) {
		return 'login';
	}
	return 'base';
}

/**
 * 获取指定身份/类型的面值区间（含校验与回退）
 *
 * @param string $scope base|login|vip
 * @param string $kind  reduce|multiply
 * @return array [min, max]
 */
function zhiji_coupon_give_range( $scope, $kind ) {
	$is_multi = ( 'multiply' === $kind );
	$base_min = $is_multi ? (float) zhiji_get_option( 'coupon_give_multiply_min', 0.7 ) : (float) zhiji_get_option( 'coupon_give_reduce_min', 1 );
	$base_max = $is_multi ? (float) zhiji_get_option( 'coupon_give_multiply_max', 0.95 ) : (float) zhiji_get_option( 'coupon_give_reduce_max', 10 );

	if ( ! in_array( $scope, array( 'login', 'vip' ), true ) ) {
		return array( $base_min, $base_max );
	}

	$min = (float) zhiji_get_option( "coupon_give_{$scope}_{$kind}_min", $base_min );
	$max = (float) zhiji_get_option( "coupon_give_{$scope}_{$kind}_max", $base_max );

	if ( $is_multi ) {
		if ( $min <= 0 || $min > 1 ) {
			$min = $base_min;
		}
		if ( $max <= 0 || $max > 1 || $max < $min ) {
			$max = $base_max;
		}
	} else {
		if ( $min < 0.01 ) {
			$min = $base_min;
		}
		if ( $max < $min ) {
			$max = $base_max;
		}
	}
	return array( $min, $max );
}

/**
 * 构建优惠码折扣 meta（依据后台配置）
 *
 * - 优惠方式 = 立减：立减金额在「下限~上限」间随机
 * - 优惠方式 = 折扣：折扣系数在「下限~上限」间随机
 * - 优惠方式 = 随机：先随机「立减/折扣」类型，再在对应区间内随机面值
 * - 差异化面值开启时按领取者身份（VIP/登录/游客）选择区间
 *
 * @param string $scope 指定身份区间（空=按当前用户自动判断；分享奖励传 'base'）
 * @return array 父主题 ZibCardPass meta 中的 discount 结构
 */
function zhiji_coupon_give_discount_meta( $scope = '' ) {
	$type = zhiji_get_option( 'coupon_give_discount_type', 'reduce' );

	// 随机模式：每次领取随机决定「立减」或「折扣」
	if ( 'random' === $type ) {
		$type = ( 1 === wp_rand( 1, 2 ) ) ? 'reduce' : 'multiply';
	}

	if ( ! $scope ) {
		$scope = zhiji_coupon_give_scope();
	}
	list( $min, $max ) = zhiji_coupon_give_range( $scope, $type );

	return array( 'type' => $type, 'val' => zhiji_coupon_give_rand_decimal( $min, $max ) );
}

/**
 * 获取访客真实 IP（含代理场景，仅用于频率限制）
 *
 * @return string
 */
function zhiji_coupon_give_client_ip() {
	$ip = '';
	if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
	} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
		$ip    = trim( (string) $parts[0] );
	} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
	return $ip;
}

/**
 * 统计某邮箱已领取的优惠码数量（用于限领）
 *
 * @param string $email 邮箱
 * @return int
 */
function zhiji_coupon_give_count_by_email( $email ) {
	if ( ! $email || ! class_exists( 'ZibCardPass' ) ) {
		return 0;
	}
	$count = 0;
	$rows  = ZibCardPass::get( array( 'type' => 'coupon' ), 'id', 0, 'all' );
	foreach ( $rows as $row ) {
		// 注意：ZibCardPass::get()（多行）不会反序列化 meta，需手动处理
		$meta = maybe_unserialize( $row->meta );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		if ( isset( $meta['email'] ) && $meta['email'] === $email ) {
			$count++;
		}
	}
	return $count;
}

/**
 * 判断当前用户（登录态按账号、未登录按 IP）是否已领取过挽留优惠码
 *
 * 「每位用户仅限领取一次」的核心判断：无论更换多少邮箱，
 * 只要该账号 / 该 IP 名下已存在本功能发放的优惠码（meta 带 email 标记）即判定已领取。
 *
 * @return bool true=已领取过
 */
function zhiji_coupon_give_has_received() {
	if ( ! class_exists( 'ZibCardPass' ) ) {
		return false;
	}
	$user_id = get_current_user_id();
	$ip      = zhiji_coupon_give_client_ip();

	$rows = ZibCardPass::get( array( 'type' => 'coupon' ), 'id', 0, 'all' );
	foreach ( $rows as $row ) {
		// 注意：ZibCardPass::get()（多行）不会反序列化 meta，需手动处理
		$meta = maybe_unserialize( $row->meta );
		if ( ! is_array( $meta ) ) {
			continue;
		}
		// 只统计本功能发放的码（meta 含 email 标记）
		if ( empty( $meta['email'] ) ) {
			continue;
		}
		// 已登录：同一账号
		if ( $user_id && ! empty( $meta['user_id'] ) && (int) $meta['user_id'] === (int) $user_id ) {
			return true;
		}
		// 未登录/兜底：同一 IP
		if ( $ip && ! empty( $meta['ip'] ) && $meta['ip'] === $ip ) {
			return true;
		}
	}
	return false;
}

/**
 * 当日已发放优惠码数量（每日限量用）
 *
 * @return int
 */
function zhiji_coupon_give_today_count() {
	if ( ! class_exists( 'ZibCardPass' ) ) {
		return 0;
	}
	$today = current_time( 'Y-m-d' );
	$count = 0;
	$rows  = ZibCardPass::get( array( 'type' => 'coupon' ), 'id', 0, 'all' );
	foreach ( $rows as $row ) {
		$meta = maybe_unserialize( $row->meta );
		if ( ! is_array( $meta ) || empty( $meta['email'] ) ) {
			continue;
		}
		if ( substr( (string) $row->create_time, 0, 10 ) === $today ) {
			$count++;
		}
	}
	return $count;
}

/**
 * 统计某邀请者邮箱已获得的裂变奖励码数量
 *
 * @param string $email 邀请者邮箱
 * @return int
 */
function zhiji_coupon_give_ref_count_by_email( $email ) {
	if ( ! $email || ! class_exists( 'ZibCardPass' ) ) {
		return 0;
	}
	$count = 0;
	$rows  = ZibCardPass::get( array( 'type' => 'coupon' ), 'id', 0, 'all' );
	foreach ( $rows as $row ) {
		$meta = maybe_unserialize( $row->meta );
		if ( ! is_array( $meta ) || empty( $meta['email'] ) ) {
			continue;
		}
		if ( ! empty( $meta['source'] ) && 'ref_bonus' === $meta['source'] && $meta['email'] === $email ) {
			$count++;
		}
	}
	return $count;
}

/**
 * 当前页面 URL（用于生成分享裂变链接）
 *
 * @return string
 */
function zhiji_coupon_give_current_url() {
	$scheme = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) ? 'https' : 'http';
	$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
	$uri    = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
	if ( ! $host ) {
		return home_url( '/' );
	}
	return $scheme . '://' . $host . $uri;
}

/* =====================================================================
 * 二、AJAX：邮箱领取优惠码（生成一张一次性优惠码 + 邮件发送）
 * ===================================================================== */

/**
 * AJAX 处理：输入邮箱 → 生成一次性优惠码 → 邮件发送
 *
 * 支持：每日限量、指定商品、差异化面值、分享裂变（ref 邀请码）
 */
function zhiji_coupon_give_ajax() {
	// 1. 权限与开关
	check_ajax_referer( 'zhiji_coupon_give', 'nonce' );
	if ( ! zhiji_get_option( 'coupon_give_enabled', 0 ) ) {
		wp_send_json_error( array( 'msg' => __( '该功能暂未开启', 'zhiji' ) ) );
	}

	// 2. 邮箱校验
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'msg' => __( '请输入有效的邮箱地址', 'zhiji' ) ) );
	}

	// 3. 依赖检查（父主题商城模块）
	// zhiji 修复（2026-09-23）：原条件误把父主题函数写成中文「折扣文案转发」，
	// function_exists 永远为 false → 邮箱领券永远返回「商城优惠码模块不可用」。
	// 折扣文案已由 Adapter::coupon_discount_text 容错，不作硬性依赖。
	if ( ! class_exists( 'ZibCardPass' ) ) {
		wp_send_json_error( array( 'msg' => __( '商城优惠码模块不可用', 'zhiji' ) ) );
	}

	// 4. 频率限制（同一 IP 一小时内最多 6 次，防刷）
	$ip      = zhiji_coupon_give_client_ip();
	$ip_key  = 'zhiji_cg_ip_' . md5( $ip );
	$ip_try  = (int) get_transient( $ip_key );
	if ( $ip_try >= 6 ) {
		wp_send_json_error( array( 'msg' => __( '领取过于频繁，请稍后再试', 'zhiji' ) ) );
	}
	set_transient( $ip_key, $ip_try + 1, HOUR_IN_SECONDS );

	// 4.5 每日限量（领完即止）
	$daily = (int) zhiji_get_option( 'coupon_give_daily_limit', 0 );
	if ( $daily > 0 && zhiji_coupon_give_today_count() >= $daily ) {
		wp_send_json_error( array( 'msg' => __( '今日优惠券已领完，明天再来吧', 'zhiji' ) ) );
	}

	// 5. 每邮箱限领数量
	$limit   = max( 1, (int) zhiji_get_option( 'coupon_give_limit_per', 1 ) );
	$already = zhiji_coupon_give_count_by_email( $email );
	if ( $already >= $limit ) {
		wp_send_json_error( array( 'msg' => __( '该邮箱已领取过优惠码，感谢支持', 'zhiji' ) ) );
	}

	// 5.5 每位用户仅限领取一次（同账号 / 同 IP，换邮箱也拦截）
	if ( zhiji_get_option( 'coupon_give_once_per_user', 1 ) && zhiji_coupon_give_has_received() ) {
		wp_send_json_error( array( 'msg' => __( '每位用户仅可领取一次挽留优惠，感谢支持', 'zhiji' ) ) );
	}

	// 6. 构建 meta（绑定邮箱/用户/IP，一次性使用 reuse=1，可带有效期）
	$user_id = get_current_user_id();
	$meta    = array(
		'discount' => zhiji_coupon_give_discount_meta(), // 差异化面值按当前身份
		'title'    => (string) zhiji_get_option( 'coupon_give_title', __( '挽留弹窗专属优惠', 'zhiji' ) ),
		'reuse'    => 1, // 一次性使用
		'email'    => $email,
		'user_id'  => $user_id ? (int) $user_id : 0,
		'ip'       => $ip, // 记录 IP：用于「每位用户仅限领取一次」的标记
		'source'   => 'direct', // 发放来源：direct 直接领取 / ref_bonus 分享奖励
	);
	$expire_days = (int) zhiji_get_option( 'coupon_give_expire_days', 7 );
	if ( $expire_days > 0 ) {
		$meta['expire_time'] = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $expire_days * DAY_IN_SECONDS );
	}

	// 7. 生成一张优惠码（12 位，查重防冲突；支持指定商品）
	$post_id = (int) zhiji_get_option( 'coupon_give_post_id', 0 );
	$code    = zhiji_coupon_give_create_one( $meta, $post_id );
	if ( ! $code ) {
		wp_send_json_error( array( 'msg' => __( '优惠码生成失败，请稍后重试', 'zhiji' ) ) );
	}

	// 8. 发送邮件（失败则回滚删除已生成码，避免「码未送达却占用名额」）
	$discount_text = zhiji_coupon_give_discount_text( $meta['discount'] );
	$expire_time   = isset( $meta['expire_time'] ) ? $meta['expire_time'] : '';
	$sent          = zhiji_coupon_give_send_mail( $email, $code, $discount_text, 'claim', $expire_time, $meta['title'] );
	if ( is_wp_error( $sent ) || ! $sent ) {
		ZibCardPass::delete( array( 'password' => $code ) );
		wp_send_json_error( array( 'msg' => __( '邮件发送失败，请检查站点邮件配置后重试', 'zhiji' ) ) );
	}

	// 8.3 站内通知联动：登录用户领取后发送系统通知（复用父主题 ZibMsg）
	zhiji_coupon_give_notify_user( $user_id, $code, $discount_text, $expire_time, 'direct' );

	// 8.5 分享裂变：若开启了裂变且好友通过邀请链接提交了 ref，给邀请者发奖励码
	if ( zhiji_get_option( 'coupon_give_ref_enabled', 0 ) && ! empty( $_POST['ref'] ) ) {
		$ref = sanitize_text_field( wp_unslash( $_POST['ref'] ) );
		if ( $ref && $ref !== $code ) {
			zhiji_coupon_give_ref_reward( $ref );
		}
	}

	// 9. 成功
	// 成功领取后清除该 IP 的频率计数：后续同一用户再来应命中「每位用户仅限领取一次」的
	// 明确提示，而不是被「领取过于频繁」误拦（限频仅用于防止刷量尝试）
	delete_transient( $ip_key );

	// 分享链接：当前页面 + ?zhiji_ref=CODE（裂变开启时返回给前端展示）
	$ref_url = '';
	if ( zhiji_get_option( 'coupon_give_ref_enabled', 0 ) ) {
		$ref_url = add_query_arg( 'zhiji_ref', $code, home_url( '/' ) );
	}

	wp_send_json_success(
		array(
			'msg'           => __( '优惠码已发送至您的邮箱，请注意查收', 'zhiji' ),
			'code'          => $code,
			'discount_text' => $discount_text, // 供看板娘播报/前端展示
			'ref_url'       => $ref_url,       // 分享裂变链接（空=未开启）
		)
	);
}
add_action( 'wp_ajax_zhiji_coupon_give', 'zhiji_coupon_give_ajax' );
add_action( 'wp_ajax_nopriv_zhiji_coupon_give', 'zhiji_coupon_give_ajax' );

/**
 * 分享裂变奖励：好友领取成功后，给邀请者邮箱发放一张奖励优惠码
 *
 * @param string $inviter_code 邀请者的优惠码（分享链接 ?zhiji_ref= 中的值）
 */
function zhiji_coupon_give_ref_reward( $inviter_code ) {
	if ( ! $inviter_code || ! class_exists( 'ZibCardPass' ) ) {
		return;
	}
	// 邀请者的码必须存在且为本功能发放（meta 带 email）
	$inviter = ZibCardPass::get_row( array( 'password' => $inviter_code, 'type' => 'coupon' ) );
	if ( ! $inviter ) {
		return;
	}
	$inv_meta = maybe_unserialize( $inviter->meta );
	if ( ! is_array( $inv_meta ) || empty( $inv_meta['email'] ) || ! is_email( $inv_meta['email'] ) ) {
		return;
	}
	$inv_email = $inv_meta['email'];
	$inv_uid   = ! empty( $inv_meta['user_id'] ) ? (int) $inv_meta['user_id'] : 0;

	// 奖励上限：防止邀请者无限分享刷奖励
	$ref_max = max( 1, (int) zhiji_get_option( 'coupon_give_ref_max', 3 ) );
	if ( zhiji_coupon_give_ref_count_by_email( $inv_email ) >= $ref_max ) {
		return;
	}

	// 构建奖励码 meta（统一用基础区间随机，避免重复差异化判断）
	$bonus_meta = array(
		'discount' => zhiji_coupon_give_discount_meta( 'base' ),
		'title'    => __( '分享奖励', 'zhiji' ),
		'reuse'    => 1,
		'email'    => $inv_email,
		'user_id'  => $inv_uid,
		'ip'       => zhiji_coupon_give_client_ip(),
		'source'   => 'ref_bonus',
	);
	$expire_days = (int) zhiji_get_option( 'coupon_give_expire_days', 7 );
	if ( $expire_days > 0 ) {
		$bonus_meta['expire_time'] = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $expire_days * DAY_IN_SECONDS );
	}

	$post_id    = (int) zhiji_get_option( 'coupon_give_post_id', 0 );
	$bonus_code = zhiji_coupon_give_create_one( $bonus_meta, $post_id );
	if ( ! $bonus_code ) {
		return;
	}

	// 发送奖励邮件（失败回滚删除，避免空占）
	$bonus_text = zhiji_coupon_give_discount_text( $bonus_meta['discount'] );
	$bonus_expire = isset( $bonus_meta['expire_time'] ) ? $bonus_meta['expire_time'] : '';
	$sent       = zhiji_coupon_give_send_mail( $inv_email, $bonus_code, $bonus_text, 'reward', $bonus_expire, __( '好友邀请', 'zhiji' ) );
	if ( is_wp_error( $sent ) || ! $sent ) {
		ZibCardPass::delete( array( 'password' => $bonus_code ) );
	}

	// 站内通知联动：邀请者（登录用户）获得分享奖励码
	zhiji_coupon_give_notify_user( $inv_uid, $bonus_code, $bonus_text, $bonus_expire, 'ref_bonus' );
}

/**
 * 创建一张优惠码，返回优惠码字符串（失败返回 false）
 *
 * @param array $meta    优惠码 meta
 * @param int   $post_id 限定商品 ID（0=全站通用）
 * @return string|false
 */
function zhiji_coupon_give_create_one( $meta, $post_id = 0 ) {
	$code = '';
	for ( $attempt = 0; $attempt < 6; $attempt++ ) {
		$candidate = ZibCardPass::rand_password( 12 );
		// 查重：已存在则换一个
		if ( ZibCardPass::get_row( array( 'password' => $candidate ) ) ) {
			continue;
		}
		$code = $candidate;
		break;
	}
	if ( ! $code ) {
		return false;
	}

	$row = ZibCardPass::add(
		array(
			'password' => $code,
			'type'     => 'coupon',
			'post_id'  => (int) $post_id, // 父主题原生限定商品：指定则仅该商品可用，0=全站
			'status'   => '0', // 0 = 正常未使用（父主题约定）
			'meta'     => $meta,
		)
	);

	return $row ? $code : false;
}

/**
 * 发送优惠码领取/奖励邮件
 *
 * @param string $email         收件邮箱
 * @param string $code          优惠码
 * @param string $discount_text 优惠内容文本（替换 {discount}）
 * @param string $type          claim=领取 | reward=裂变奖励（影响默认标题/正文）
 * @return bool|WP_Error
 */
function zhiji_coupon_give_send_mail( $email, $code, $discount_text = '', $type = 'claim', $expire_time = '', $source = '' ) {
	$site = get_bloginfo( 'name' );

	// 解析收件人用户名（display_name 优先，空则 user_login，兜底「用户」）
	$user = get_user_by( 'email', $email );
	$name = '';
	if ( $user ) {
		$name = $user->display_name ? $user->display_name : $user->user_login;
	}
	if ( '' === trim( $name ) ) {
		$name = __( '用户', 'zhiji' );
	}
	// 有效期文案：有到期时间则格式化，否则空（用于 {expire} 占位符）
	$expire_text = $expire_time ? date_i18n( 'Y年n月j日 H:i', strtotime( $expire_time ) ) : '';

	$subject = (string) zhiji_get_option( 'coupon_give_mail_title', '' );
	if ( '' === trim( $subject ) ) {
		if ( 'reward' === $type ) {
			/* translators: %s: 站点名称 */
			$subject = sprintf( __( '【%s】好友领取成功，您的分享奖励优惠码', 'zhiji' ), $site );
		} else {
			/* translators: %s: 站点名称 */
			$subject = sprintf( __( '【%s】您的专属优惠码', 'zhiji' ), $site );
		}
	}
	$subject = str_replace( array( '{site}', '{code}', '{discount}', '{name}', '{expire}' ), array( $site, $code, $discount_text, $name, $expire_text ), $subject );

	$custom_body = (string) zhiji_get_option( 'coupon_give_mail_content', '' );
	if ( '' !== trim( $custom_body ) ) {
		// 后台自定义了邮件内容：按纯文本 + 换行处理（兼容占位符）
		$body = str_replace( array( '{site}', '{code}', '{discount}', '{name}', '{expire}' ), array( $site, $code, $discount_text, $name, $expire_text ), $custom_body );
		$body = nl2br( esc_html( $body ) );
	} elseif ( function_exists( 'zhiji_mail_template_render' ) ) {
		// v1.9.4：统一调用 MailTemplate 邮件模板引擎（品牌票据风格）
		$headline = ( 'reward' === $type )
			? __( '好友领取成功，这是您的分享奖励！', 'zhiji' )
			: __( '您的专属优惠码已就绪', 'zhiji' );
		$subline = $source
			? sprintf( __( '您在「%s」中获得了以下奖励，奖励已发放至您的账户：', 'zhiji' ), $source )
			: ( ( 'reward' === $type )
				? __( '您的好友通过您的邀请领取了优惠码，作为奖励，我们为您准备了一张专属优惠码，可直接在结账时使用：', 'zhiji' )
				: __( '感谢您对本站的支持，这是为您准备的专属优惠码，请在有效期内使用：', 'zhiji' ) );

	// 邮件色值（邮件客户端不支持 CSS var，PHP 侧注入品牌令牌）
	$mail_brand        = zhiji_token_color( 'brand' );
	$mail_brand_light  = zhiji_token_color( 'brand_light' );
	$mail_surface_soft = zhiji_token_color( 'surface_soft' );
	$mail_border       = zhiji_token_color( 'border' );
		$body = zhiji_mail_template_render( array(
			'site'                 => $site,
			'name'                 => $name,
			'headline'             => $headline,
			'subline'              => $subline,
			'ticket_left_label'    => __( '优惠内容', 'zhiji' ),
			'ticket_left_content'  => '<div style="font-size:18px;font-weight:800;color:#111827;margin-top:8px;line-height:1.3;">' . esc_html( $discount_text ? $discount_text : __( '专属折扣', 'zhiji' ) ) . '</div>',
			'ticket_right_label'   => __( '优惠码 COUPON', 'zhiji' ),
			'ticket_right_content' => '<div style="font-size:22px;font-weight:800;color:' . zhiji_token_color( 'brand' ) . ';letter-spacing:2px;line-height:1.4;word-break:break-all;">' . esc_html( $code ) . '</div>',
			'coupon_expiry'        => $expire_text ? sprintf( __( '优惠码有效期至 %s，逾期自动失效', 'zhiji' ), $expire_text ) : __( '本优惠码长期有效', 'zhiji' ),
			'rule_line'            => __( '* 该优惠码仅可使用一次，结算时输入即可抵扣，逾期自动失效。', 'zhiji' ),
			'btn_text'             => __( '立即使用优惠码 &#8594;', 'zhiji' ),
			'btn_url'              => home_url( '/' ),
		) );
	} else {
		// 兜底：MailTemplate 未加载时使用旧版内置模板
		$body = zhiji_coupon_give_mail_html_template( $site, $name, $code, $discount_text, $type, $expire_time );
	}

	// v1.9.4：统一调用 zhiji_mail_send 发送（自动临时移除父主题 zib_get_mail_content 包装）
	if ( function_exists( 'zhiji_mail_send' ) ) {
		return zhiji_mail_send( $email, $subject, $body );
	}

	// 兜底：MailTemplate 未加载时手动处理父主题包装
	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	$zib_mail_priority = has_filter( 'wp_mail', 'zib_get_mail_content' );
	if ( false !== $zib_mail_priority ) {
		remove_filter( 'wp_mail', 'zib_get_mail_content', $zib_mail_priority );
	}
	$sent = wp_mail( $email, $subject, $body, $headers );
	if ( false !== $zib_mail_priority ) {
		add_filter( 'wp_mail', 'zib_get_mail_content', $zib_mail_priority );
	}
	return $sent;
}

/**
 * 领取/获得优惠码后发送站内系统通知（联动父主题 ZibMsg 通知系统）
 *
 * 仅当：后台开关开启 + 收件人为登录用户 + 父主题 ZibMsg 类存在。
 * 游客（无 user_id）仅收邮件、不发站内通知；通知类型 type=system（前台显示「系统」）。
 *
 * @param int    $user_id       接收通知的用户 ID（0=游客，跳过）
 * @param string $code          优惠码
 * @param string $discount_text 优惠内容（如「立减5元」）
 * @param string $expire_time   到期时间（Y-m-d H:i:s 或 ''）
 * @param string $type          direct 直接领取 / ref_bonus 分享奖励
 */
function zhiji_coupon_give_notify_user( $user_id, $code, $discount_text = '', $expire_time = '', $type = 'direct' ) {
	if ( ! $user_id ) {
		return;
	}
	if ( ! zhiji_get_option( 'coupon_give_notice_enabled', 1 ) ) {
		return;
	}
	if ( ! class_exists( 'ZibMsg' ) ) {
		return;
	}
	$title   = ( 'ref_bonus' === $type ) ? __( '您的分享奖励优惠码已到账', 'zhiji' ) : __( '您的专属优惠码已到账', 'zhiji' );
	$expire  = $expire_time ? date_i18n( 'Y年n月j日 H:i', strtotime( $expire_time ) ) : __( '长期有效', 'zhiji' );
	$content = sprintf(
		/* translators: 1: 优惠内容 2: 优惠码 3: 有效期 */
		__( '恭喜！您已成功获得一张%s优惠码：%s（%s）。请及时使用，逾期自动失效。', 'zhiji' ),
		$discount_text ? $discount_text : __( '专属', 'zhiji' ),
		$code,
		$expire
	);
	ZibMsg::add(
		array(
			'send_user'    => 'admin',
			'receive_user' => $user_id,
			'type'         => 'system',
			'title'        => $title,
			'content'      => $content,
			'meta'         => '',
			'other'        => '',
		)
	);
}

/**
 * 生成精美的优惠码邮件 HTML 模板
 * ---------------------------------------------------------------------
 * 全内联样式，兼容 QQ/163/Outlook 等主流邮件客户端；移动端自适应。
 * 支持 {site} / {code} / {discount} 占位符替换（均做转义）。
 *
 * @param string $site          站点名称
 * @param string $code          优惠码
 * @param string $discount_text 优惠内容文案（如「9.3折」「立减5元」）
 * @param string $type          claim 直接领取 / reward 分享奖励
 * @return string
 */
function zhiji_coupon_give_mail_html_template( $site, $name, $code, $discount_text, $type = 'claim', $expire_time = '' ) {
	$headline = ( 'reward' === $type )
		? __( '好友领取成功，这是您的分享奖励！', 'zhiji' )
		: __( '您的专属优惠码已就绪', 'zhiji' );
	$subline  = ( 'reward' === $type )
		? __( '您的好友通过您的邀请领取了优惠码，作为奖励，我们为您准备了一张专属优惠码，可直接在结账时使用：', 'zhiji' )
		: __( '感谢您对本站的支持，这是为您准备的专属优惠码，请在有效期内使用：', 'zhiji' );

	// 邮件色值（邮件客户端不支持 CSS var，PHP 侧注入品牌令牌）
	$mail_brand        = zhiji_token_color( 'brand' );
	$mail_brand_light  = zhiji_token_color( 'brand_light' );
	$mail_surface_soft = zhiji_token_color( 'surface_soft' );
	$mail_border       = zhiji_token_color( 'border' );

	$html = <<<'HTML'
<div style="margin:0;padding:0;background:#f3f4f6;">
	<!-- 主体（logo 已移入卡片顶部，整体一体） -->
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'PingFang SC','Microsoft YaHei','Segoe UI',sans-serif;">
		<tbody><tr>
			<td align="center" style="padding:36px 16px 20px;">
				<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;background:#ffffff;border-radius:16px;box-shadow:0 1px 2px rgba(17,24,39,.04),0 12px 32px rgba(17,24,39,.07);">
					<tbody><tr><td style="padding:38px 36px 0;text-align:center;">
						{logo_html}
					</td></tr>
					<tr><td style="padding:22px 36px 6px;text-align:center;">
						<div style="display:inline-block;padding:5px 14px;border-radius:999px;background:{mail_surface_soft};color:{mail_brand};font-size:12px;font-weight:600;letter-spacing:1px;">专属福利</div>
						<div style="font-size:22px;font-weight:800;color:#111827;line-height:1.5;margin-top:16px;">{headline}</div>
						<div style="font-size:15px;font-weight:600;color:#374151;line-height:1.7;margin-top:14px;">{greeting}</div>
						<div style="font-size:14px;color:#6b7280;line-height:1.9;margin-top:4px;">{subline}</div>
					</td></tr>

					<!-- 优惠券：左优惠码 / 右折扣，中缝虚线 -->
					<tr><td style="padding:30px 30px 6px;">
						<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-radius:14px;overflow:hidden;border:1px solid #d1d5db;box-shadow:0 3px 14px rgba(17,24,39,.08);">
							<tbody><tr>
								<td colspan="2" style="height:12px;line-height:12px;font-size:0;background-image:linear-gradient(135deg,#d1d5db 6px,transparent 6px),linear-gradient(225deg,#d1d5db 6px,transparent 6px);background-size:12px 12px;background-repeat:repeat-x;">&nbsp;</td>
							</tr>
							<tr>
								<td width="62%" align="center" style="background-color:{mail_surface_soft};background-image:radial-gradient(circle at 0 50%,{mail_surface_soft} 0 12px,{mail_brand_light} 12px,{mail_brand_light} 13px,transparent 13px);padding:26px 16px;border-right:2px dashed {mail_border};">
									<div style="font-size:11px;color:{mail_brand};letter-spacing:3px;margin-bottom:14px;">优惠码 COUPON</div>
									<div style="font-size:{code_fs};font-weight:800;color:{mail_brand};letter-spacing:{code_ls};word-break:break-all;font-family:Menlo,Consolas,Monaco,monospace;">{code}</div>
								</td>
								<td align="center" style="background-color:#ffffff;background-image:radial-gradient(circle at 100% 50%,#ffffff 0 12px,{mail_brand_light} 12px,{mail_brand_light} 13px,transparent 13px);padding:24px 12px;">
									<div style="font-size:10px;color:#9ca3af;letter-spacing:2px;">优惠内容</div>
									<div style="font-size:26px;font-weight:800;color:#111827;margin-top:8px;line-height:1.2;">{discount}</div>
									<div style="font-size:11px;color:#9ca3af;margin-top:6px;">专属折扣</div>
								</td>
							</tr></tbody>
						</table>
					</td></tr>

					<!-- 使用规则 -->
					<tr><td style="padding:24px 36px 4px;">
						<div style="font-size:12px;color:#9ca3af;line-height:1.9;text-align:center;">* 该优惠码仅可使用一次，{expire_line}，逾期自动失效。</div>
					</td></tr>

					<!-- CTA -->
					<tr><td style="padding:28px 36px 46px;text-align:center;">
						<a href="{home_url}" style="display:inline-block;padding:14px 46px;border-radius:999px;background:{mail_brand};color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;">立即使用优惠码 &#8594;</a>
					</td></tr>
				</table>
			</td>
		</tr></tbody>
	</table>

	<!-- 页脚 -->
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'PingFang SC','Microsoft YaHei','Segoe UI',sans-serif;">
		<tbody><tr>
			<td align="center" style="padding:18px 16px 42px;">
				<div style="width:56px;height:2px;background:#e5e7eb;margin:0 auto 16px;">&nbsp;</div>
				<div style="font-size:12px;color:#9ca3af;">本邮件由 {site} 自动发送，请勿直接回复。</div>
				<div style="font-size:12px;color:#d1d5db;margin-top:5px;">&copy; 2026 {site}</div>
			</td>
		</tr></tbody>
	</table>
</div>
HTML;

	// 统一替换模板占位符（均转义，防注入）
	// 优惠码按长度自适应字号与字距，避免窄屏/长码时换行
	$code_len = mb_strlen( $code );
	if ( $code_len > 16 ) {
		$code_fs = '17px';
		$code_ls = '1px';
	} elseif ( $code_len > 12 ) {
		$code_fs = '20px';
		$code_ls = '1px';
	} else {
		$code_fs = '23px';
		$code_ls = '2px';
	}

	// 有效期文案：有到期时间显示具体日期，否则「长期有效」
	$expire_line = $expire_time
		? sprintf( __( '有效期至 %s', 'zhiji' ), date_i18n( 'Y年n月j日 H:i', strtotime( $expire_time ) ) )
		: __( '长期有效', 'zhiji' );

	// 收件人称呼：{name}，您好！（无用户名时直接「您好！」）
	$greeting = ( '' === trim( $name ) || '用户' === $name ) ? __( '您好！', 'zhiji' ) : sprintf( __( '%s，您好！', 'zhiji' ), $name );

	// 网站 Logo：优先父主题 logo_src，无则回退站点名文字
	$logo_html = '';
	if ( function_exists( '_pz' ) && ( $logo_url = (string) _pz( 'logo_src' ) ) && 0 === strpos( $logo_url, 'http' ) ) {
		$logo_html = '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $site ) . '" style="max-width:200px;max-height:40px;width:auto;height:auto;">';
	}
	if ( '' === $logo_html ) {
		$logo_html = '<div style="font-size:17px;font-weight:700;color:' . $mail_brand . ';letter-spacing:1px;">' . esc_html( $site ) . '</div>';
	}

	$html = str_replace(
		array( '{site}', '{code}', '{discount}', '{headline}', '{subline}', '{greeting}', '{logo_html}', '{code_fs}', '{code_ls}', '{home_url}', '{expire_line}', '{mail_surface_soft}', '{mail_brand_light}', '{mail_border}', '{mail_brand}' ),
		array(
			esc_html( $site ),
			esc_html( $code ),
			esc_html( $discount_text ),
			esc_html( $headline ),
			esc_html( $subline ),
			esc_html( $greeting ),
			$logo_html,
			esc_html( $code_fs ),
			esc_html( $code_ls ),
			esc_url( home_url( '/' ) ),
			esc_html( $expire_line ),
			esc_html( $mail_surface_soft ),
			esc_html( $mail_brand_light ),
			esc_html( $mail_border ),
			esc_html( $mail_brand ),
		),
		$html
	);

	return $html;
}

/* =====================================================================
 * 三、挽留弹窗内的「输入邮箱领取优惠码」表单
 *    由 inc/Functions/ExitIntent.php 调用 zhiji_coupon_give_exit_block()
 * ===================================================================== */

/**
 * 输出挽留弹窗内的邮箱领取优惠码区块（HTML + JS）
 *
 * 前端增强（v1.3.0）：
 *   - 读取 URL ?zhiji_ref= 邀请码（分享裂变）并随提交发送
 *   - 领取成功：看板娘播报（zhiji_kanban_event 庆祝动画）+ 展示分享链接
 *
 * @return string
 */
function zhiji_coupon_give_exit_block() {
	if ( ! zhiji_get_option( 'coupon_give_enabled', 0 ) ) {
		return '';
	}
	if ( ! zhiji_get_option( 'coupon_give_show_in_exit', 1 ) ) {
		return '';
	}

	$ajax_url = admin_url( 'admin-ajax.php' );
	$nonce    = wp_create_nonce( 'zhiji_coupon_give' );
	$ref_open = (bool) zhiji_get_option( 'coupon_give_ref_enabled', 0 );

	ob_start();
	?>
	<div class="zhiji-exit-give">
		<div class="zhiji-exit-give-title"><?php echo esc_html( zhiji_get_option( 'coupon_give_form_title', __( '输入邮箱，领取专属优惠码', 'zhiji' ) ) ); ?></div>
		<div class="zhiji-exit-give-form">
			<input type="email" id="zhijiExitGiveEmail" placeholder="<?php echo esc_attr( __( '请输入邮箱地址', 'zhiji' ) ); ?>" autocomplete="email">
			<button type="button" id="zhijiExitGiveBtn"><?php echo esc_html( __( '领取', 'zhiji' ) ); ?></button>
		</div>
		<div class="zhiji-exit-give-msg" id="zhijiExitGiveMsg"></div>
		<?php if ( $ref_open ) : ?>
		<div class="zhiji-exit-give-share" id="zhijiExitGiveShare" style="display:none">
			<div class="zhiji-exit-give-share-tip"><?php echo esc_html__( '分享给好友，你和好友各得一张优惠券！', 'zhiji' ); ?></div>
			<div class="zhiji-exit-give-share-row">
				<input type="text" id="zhijiExitGiveShareUrl" readonly>
				<button type="button" id="zhijiExitGiveShareBtn"><?php echo esc_html__( '复制链接', 'zhiji' ); ?></button>
			</div>
		</div>
		<?php endif; ?>
	</div>
	<script>
	(function () {
		var btn = document.getElementById('zhijiExitGiveBtn');
		if (!btn) return;
		var input = document.getElementById('zhijiExitGiveEmail');
		var msg = document.getElementById('zhijiExitGiveMsg');
		var shareBox = document.getElementById('zhijiExitGiveShare');
		var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;
		var nonce = <?php echo wp_json_encode( $nonce ); ?>;
		var refEnabled = <?php echo $ref_open ? 'true' : 'false'; ?>;
		var busy = false;

		// 读取 URL ?zhiji_ref= 邀请码（好友通过分享链接进入），暂存到 localStorage
		var ref = '';
		try {
			var qs = new URLSearchParams(location.search);
			ref = qs.get('zhiji_ref') || '';
			if (ref) localStorage.setItem('zhiji_ref', ref);
			else ref = localStorage.getItem('zhiji_ref') || '';
		} catch (e) { ref = ''; }

		function setMsg(text, ok) {
			msg.textContent = text || '';
			msg.className = 'zhiji-exit-give-msg ' + (ok ? 'success' : 'error');
		}
		function submit() {
			if (busy) return;
			var email = (input.value || '').trim();
			if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
				setMsg('请输入有效的邮箱地址', false);
				return;
			}
			busy = true;
			btn.disabled = true;
			btn.textContent = '发送中...';
			setMsg('', true);
			var fd = new FormData();
			fd.append('action', 'zhiji_coupon_give');
			fd.append('nonce', nonce);
			fd.append('email', email);
			if (refEnabled && ref) fd.append('ref', ref);
			fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (res && res.success) {
						setMsg(res.data.msg, true);
						// Confetti 全屏彩带庆祝（zhiji.js 提供，纯 Canvas 轻量实现）
						if (typeof window.zhiji_confetti === 'function') {
							try { window.zhiji_confetti({ count: 140 }); } catch (e) {}
						}
						// 看板娘播报（领取成功庆祝动画；无看板娘则无监听器，无害）
						if (res.data.discount_text) {
							try {
								document.dispatchEvent(new CustomEvent('zhiji_kanban_event', {
									detail: { type: 'lottery', text: '恭喜！获得' + res.data.discount_text + '优惠券，快去下单吧～' }
								}));
							} catch (e) {}
						}
						// 展示分享裂变链接
						if (refEnabled && res.data.ref_url && shareBox) {
							document.getElementById('zhijiExitGiveShareUrl').value = res.data.ref_url;
							shareBox.style.display = 'block';
						}
					} else {
						setMsg(res && res.data && res.data.msg ? res.data.msg : '领取失败，请稍后重试', false);
					}
				})
				.catch(function () { setMsg('网络异常，请稍后重试', false); })
				.then(function () {
					busy = false;
					btn.disabled = false;
					btn.textContent = '领取';
				});
		}
		btn.addEventListener('click', submit);
		input.addEventListener('keydown', function (e) { if (e.key === 'Enter') submit(); });

		// 复制分享链接
		// 优先使用现代 Clipboard API（navigator.clipboard.writeText），
		// 仅在不可用（非安全上下文 http / 老浏览器）时降级 execCommand，
		// 并给出明确的成功/失败反馈，避免静默失败。
		var shareBtn = document.getElementById('zhijiExitGiveShareBtn');
		if (shareBtn) {
			shareBtn.addEventListener('click', function () {
				var urlInput = document.getElementById('zhijiExitGiveShareUrl');
				var url = urlInput.value || '';
				function showOk() {
					shareBtn.textContent = '已复制';
					setTimeout(function () { shareBtn.textContent = '复制链接'; }, 1500);
				};
				function showFail() {
					shareBtn.textContent = '复制失败，请手动选择复制';
					setTimeout(function () { shareBtn.textContent = '复制链接'; }, 2200);
				};
				function fallbackCopy() {
					urlInput.focus();
					urlInput.select();
					urlInput.setSelectionRange(0, urlInput.value.length);
					var ok = false;
					try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
					ok ? showOk() : showFail();
				};
				// Clipboard API 优先（直接尝试，失败自动降级）；兼容内网 http 环境
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(url).then(showOk).catch(fallbackCopy);
				} else {
					fallbackCopy();
				}
			});
		}
	})();
	</script>
	<style>
	.zhiji-exit-give{margin:0 0 16px;padding:14px 16px;background:var(--zhiji-surface-soft,#eaf2fe);border:1px dashed var(--zhiji-border,#dce6f5);border-radius:12px;text-align:left}
	.zhiji-exit-give-title{font-size:13px;font-weight:600;color:var(--zhiji-brand,#2e7cf6);margin-bottom:10px}
	.zhiji-exit-give-form{display:flex;gap:8px}
	.zhiji-exit-give-form input{flex:1;padding:9px 12px;border:1px solid var(--zhiji-border,#dce6f5);border-radius:20px;font-size:13px;outline:none;min-width:0;color:#333;background:#fff;caret-color:#333}
.zhiji-exit-give-form input::placeholder{color:var(--zhiji-text-muted,#5a6b85);opacity:1}
	.zhiji-exit-give-form input:focus{border-color:var(--zhiji-brand, #2e7cf6)}
	.zhiji-exit-give-form button{padding:9px 18px;border:none;border-radius:20px;background:var(--zhiji-brand, #2e7cf6);color:#fff;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap}
	.zhiji-exit-give-form button:disabled{opacity:.6;cursor:not-allowed}
	.zhiji-exit-give-msg{font-size:12px;margin-top:8px;min-height:16px}
	.zhiji-exit-give-msg.success{color:#52c41a}
	.zhiji-exit-give-msg.error{color:#ff4d4f}
	.zhiji-exit-give-share{margin-top:10px;padding:10px 12px;background:#fff;border:1px solid #e4dcff;border-radius:10px}
	.zhiji-exit-give-share-tip{font-size:12px;color:var(--zhiji-brand,#2e7cf6);font-weight:600;margin-bottom:8px}
	.zhiji-exit-give-share-row{display:flex;gap:6px}
	.zhiji-exit-give-share-row input{flex:1;padding:7px 10px;border:1px solid #e0d9f7;border-radius:8px;font-size:12px;color:#555;background:var(--zhiji-surface-soft,#eaf2fe);outline:none;min-width:0}
	.zhiji-exit-give-share-row button{padding:7px 14px;border:none;border-radius:8px;background:var(--zhiji-brand, #2e7cf6);color:#fff;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap}
	</style>
	<?php
	return ob_get_clean();
}

/* =====================================================================
 * 四、个人中心「我的优惠码」Tab
 * ===================================================================== */

/**
 * 注册个人中心 Tab：我的优惠码
 *
 * @param array $tabs_array 现有 tab 数组
 * @return array
 */
function zhiji_coupon_user_tab( $tabs_array ) {
	// 仅登录用户、且功能启用、且 Tab 开关打开时注册
	if ( ! is_user_logged_in() ) {
		return $tabs_array;
	}
	if ( ! zhiji_get_option( 'coupon_give_enabled', 0 ) ) {
		return $tabs_array;
	}
	if ( ! zhiji_get_option( 'coupon_give_user_tab', 1 ) ) {
		return $tabs_array;
	}

	// 注意：zibpay 已占用 vip/balance/income/rebate/order；父主题占用
	// level/auth/rewards/data/account，此处使用独立 key：coupon，避免冲突。
	$tabs_array['coupon'] = array(
		'title'    => __( '我的优惠码', 'zhiji' ),
		'nav_attr' => 'drawer-title="' . esc_attr( __( '我的优惠码', 'zhiji' ) ) . '"',
		'loader'   => '<div class="zib-widget"><p class="placeholder k1"></p><p class="placeholder t1"></p><p class="placeholder k1" style="height:80px;"></p><p class="placeholder k1"></p><p class="placeholder t1"></p></div>',
	);

	// 重排：将「我的优惠码」插入到父主题「官方认证」(auth) 之后，
	// 使用户中心图标区中优惠券入口位于「官方认证」右侧（红圈位置）。
	// 父主题 tab 顺序：level → auth → rewards → data → account。
	$coupon_tab = isset( $tabs_array['coupon'] ) ? $tabs_array['coupon'] : null;
	if ( null !== $coupon_tab ) {
		unset( $tabs_array['coupon'] );
		$reordered = array();
		foreach ( $tabs_array as $key => $val ) {
			$reordered[ $key ] = $val;
			if ( 'auth' === $key ) {
				$reordered['coupon'] = $coupon_tab;
			}
		}
		// 父主题未提供 auth（异常情况）时，追加到末尾兜底
		if ( ! isset( $reordered['coupon'] ) ) {
			$reordered['coupon'] = $coupon_tab;
		}
		$tabs_array = $reordered;
	}

	return $tabs_array;
}
add_filter( 'user_ctnter_main_tabs_array', 'zhiji_coupon_user_tab', 15 );
/**
 * 用户中心侧栏「我的服务」按钮组：在「官方认证」右侧插入「我的优惠码」入口
 * ---------------------------------------------------------------------
 * 父主题 zib_user_center_page_sidebar_button_1() 负责「我的服务」图标网格
 * （默认含 我的等级 / 官方认证），通过 zib_user_center_page_sidebar_button_1_args
 * filter 暴露扩展点。本函数把「我的优惠码」按钮插入到 auth（官方认证）之后，
 * 点击打开 #user-tab-coupon（与上面注册的 coupon Tab 联动）。
 *
 * @param array $buttons 侧栏按钮数组（含 html/icon/name/tab）
 * @return array
 */
function zhiji_coupon_sidebar_button_1( $buttons ) {
	// 与 Tab 注册保持一致：登录 + 功能开启 + Tab 开关开启
	if ( ! is_user_logged_in() ) {
		return $buttons;
	}
	if ( ! zhiji_get_option( 'coupon_give_enabled', 0 ) ) {
		return $buttons;
	}
	if ( ! zhiji_get_option( 'coupon_give_user_tab', 1 ) ) {
		return $buttons;
	}

	$coupon_btn = array(
		'icon' => Zhiji_Adapter::svg( 'tag-color' ),
		'name' => __( '我的优惠码', 'zhiji' ),
		'tab'  => 'coupon',
	);

	$new    = array();
	$insert = false;
	foreach ( $buttons as $but ) {
		$new[] = $but;
		// 在「官方认证」(auth) 之后插入优惠码入口
		if ( isset( $but['tab'] ) && 'auth' === $but['tab'] ) {
			$new[]  = $coupon_btn;
			$insert = true;
		}
	}
	// 未找到 auth（异常情况）时追加到末尾兜底
	if ( ! $insert ) {
		$new[] = $coupon_btn;
	}

	return $new;
}
add_filter( 'zib_user_center_page_sidebar_button_1_args', 'zhiji_coupon_sidebar_button_1', 20 );

/**
 * 输出个人中心「我的优惠码」Tab 内容
 *
 * @param string $con 现有内容
 * @param array  $opt tab 配置
 * @return string
 */
function zhiji_coupon_user_tab_content( $con, $opt ) {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return $con . '<div class="zib-widget"><div class="box-body center">' . esc_html__( '请先登录后查看', 'zhiji' ) . '</div></div>';
	}

	$coupons = zhiji_coupon_get_user_coupons( $user_id );

	if ( empty( $coupons ) ) {
		$html  = '<div class="ajax-item"><div class="zib-widget"><div class="box-body center muted-2-color">';
		$html .= esc_html__( '您还没有领取过优惠码', 'zhiji' );
		$html .= '</div></div></div><div class="ajax-pag hide"><div class="next-page ajax-next"><a href="#"></a></div></div>';
		return $con . $html;
	}

	$html  = '<div class="ajax-item"><div class="zib-widget">';
	$html .= '<div class="box-body notop"><div class="title-theme"><b>' . esc_html__( '我的优惠码', 'zhiji' ) . '</b></div></div>';
	$html .= '<div class="box-body">';
	$html .= '<div class="table-responsive"><table class="table table-hover zhiji-coupon-table">';
	$html .= '<thead><tr><th>' . esc_html__( '优惠码', 'zhiji' ) . '</th><th>' . esc_html__( '优惠内容', 'zhiji' ) . '</th><th>' . esc_html__( '来源', 'zhiji' ) . '</th><th>' . esc_html__( '状态', 'zhiji' ) . '</th><th>' . esc_html__( '领取时间', 'zhiji' ) . '</th></tr></thead><tbody>';

	foreach ( $coupons as $row ) {
		$meta          = is_array( $row->meta ) ? $row->meta : array();
		// 兼容两种 meta 结构：标准 discount 子键 / 直接 type+val（脚本发放等场景）
		$discount_meta = ! empty( $meta['discount'] ) ? $meta['discount'] : ( ( ! empty( $meta['type'] ) && isset( $meta['val'] ) ) ? array( 'type' => $meta['type'], 'val' => $meta['val'] ) : null );
		$discount_text = $discount_meta
			? Zhiji_Adapter::coupon_discount_text( $discount_meta )
			: '';
		// 状态分开显示：已使用 / 已过期 / 未使用
		if ( 'used' === $row->status ) {
			$status = __( '已使用', 'zhiji' );
		} elseif ( ! empty( $meta['expire_time'] ) && current_time( 'timestamp' ) > strtotime( (string) $meta['expire_time'] ) ) {
			$status = __( '已过期', 'zhiji' );
		} else {
			$status = __( '未使用', 'zhiji' );
		}

		// 来源：优先 meta.title（后台可配的来源描述），其次按 source 映射，兜底「其他」
		$source_text = '';
		if ( ! empty( $meta['title'] ) ) {
			$source_text = (string) $meta['title'];
		} elseif ( ! empty( $meta['source'] ) ) {
			$source_map = array(
				'direct'             => __( '邮箱领取', 'zhiji' ),
				'ref_bonus'          => __( '分享奖励', 'zhiji' ),
				'zhiji_lottery'      => __( '大转盘抽奖', 'zhiji' ),
				'reward_center'      => __( '奖励中心', 'zhiji' ),
				'reward_center_free' => __( '奖励中心免单', 'zhiji' ),
				'manual_test'        => __( '后台发放', 'zhiji' ),
			);
			$src_key     = (string) $meta['source'];
			$source_text = isset( $source_map[ $src_key ] ) ? $source_map[ $src_key ] : $src_key;
		} else {
			$source_text = __( '其他', 'zhiji' );
		}

		$html .= '<tr>';
		// 优惠码高亮样式与 CouponHighlight 的 .zhiji-cp 统一（2026-09-23）；
		// 用内联样式保证任何情况下都可见（不依赖其它模块是否输出 CSS）
		$html .= '<td><span class="zhiji-copy-code" data-code="' . esc_attr( $row->password ) . '" title="' . esc_attr__( '点击复制', 'zhiji' ) . '" style="display:inline-block;background:#fff6ec;border:1px dashed #ffb366;color:#e8590c;font-weight:600;border-radius:6px;padding:0 7px;letter-spacing:.5px;cursor:pointer;user-select:all;transition:all .15s ease">' . esc_html( $row->password ) . '</span></td>';
		$html .= '<td>' . esc_html( $discount_text ) . '</td>';
		$html .= '<td>' . esc_html( $source_text ) . '</td>';
		$html .= '<td>' . esc_html( $status ) . '</td>';
		$html .= '<td>' . esc_html( $row->create_time ) . '</td>';
		$html .= '</tr>';
	}

	$html .= '</tbody></table></div>';
	$html .= '</div></div></div>';
	// 隐藏分页容器：父主题 post_ajax 提取内容时会寻找分页元素，
	// 提供隐藏分页可避免加载后出现「没有更多内容」的空状态提示（与原生 rewards 结构一致）。
	$html .= '<div class="ajax-pag hide"><div class="next-page ajax-next"><a href="#"></a></div></div>';

	return $con . $html;
}
add_filter( 'main_user_tab_content_coupon', 'zhiji_coupon_user_tab_content', 10, 2 );

/**
 * 我的优惠码：点击复制（事件委托，兼容 Tab AJAX 加载内容）
 */
add_action( 'wp_footer', 'zhiji_coupon_copy_script', 99 );
function zhiji_coupon_copy_script() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<style id="zhiji-copy-code-css">
	/* 优惠码高亮（2026-09-23 强化版）
	   目标：无论父主题/暗色主题/浏览器 UA 样式如何，hover 都不出现白底。
	   手法：① 高特异性前缀 html body ② 覆盖 background / background-image /
	   background-color / color / border ③ 同时防御 :hover 与 :focus ④ 暗色用深底。 */
	html body .zhiji-copy-code{display:inline-block!important;background:#fff6ec!important;background-color:#fff6ec!important;background-image:none!important;border:1px dashed #ffb366!important;color:#e8590c!important;font-weight:600!important;border-radius:6px!important;padding:0 7px!important;letter-spacing:.5px;cursor:pointer;user-select:all;-webkit-user-select:all;transition:background-color .15s ease,color .15s ease}
	html body .zhiji-copy-code:hover,html body .zhiji-copy-code:focus,html body .zhiji-copy-code:active{background:#ffe3c7!important;background-color:#ffe3c7!important;background-image:none!important;color:#d9480f!important;border-color:#ff9f40!important;text-shadow:none!important}
	html body.dark-theme .zhiji-copy-code,html.dark-theme body .zhiji-copy-code{background:#3d2c14!important;background-color:#3d2c14!important;background-image:none!important;border-color:rgba(255,159,64,.55)!important;color:#ffc078!important}
	html body.dark-theme .zhiji-copy-code:hover,html body.dark-theme .zhiji-copy-code:focus,html.dark-theme body .zhiji-copy-code:hover{background:#4d3a1c!important;background-color:#4d3a1c!important;background-image:none!important;color:#ffd8a8!important;border-color:rgba(255,180,90,.8)!important}
	/* 关键：券码带 user-select:all，点击会整段选中，浏览器默认选中高亮呈白/蓝色 —— 看起来就是「鼠标放上去变白」。
	   这里自定义选中配色，选中态也保持暖色，视觉不再跳白。 */
	html body .zhiji-copy-code::selection,html body .zhiji-copy-code *::selection{background:#ffd8a8!important;color:#7a3d00!important}
	html body.dark-theme .zhiji-copy-code::selection,html.dark-theme body .zhiji-copy-code::selection{background:#7a5a2a!important;color:#ffe8cc!important}
	/* 2026-09-23：父主题 bootstrap.css 的
	   .table td,.table th{background-color:#fff!important} 与
	   .table-hover>tbody>tr:hover{background-color:#f5f5f5} 会让暗色主题下的
	   「我的优惠码」表格出现白色横条（用户截图反馈）。此处仅对本模块表格生效地改回透明，
	   让底色跟随主题变量，亮/暗色都正常。 */
	html body .zhiji-coupon-table>thead>tr>th,html body .zhiji-coupon-table>tbody>tr>td{background-color:transparent!important}
	html body .zhiji-coupon-table>tbody>tr:hover,html body .zhiji-coupon-table>tbody>tr:hover>td,
	html body .zhiji-coupon-table>tbody>tr:hover>th{background-color:transparent!important}
	html body.dark-theme .zhiji-coupon-table>tbody>tr:hover>td,html.dark-theme body .zhiji-coupon-table>tbody>tr:hover>td{background-color:transparent!important}
	</style>
	<script>
	(function(){
		// 角标浮层提示（2026-09-23 新增：与 CouponHighlight 的 .zhiji-cp-tip 体验统一；
		// 用内联样式实现，不依赖其它模块是否输出 CSS）
		var tip = null;
		function showTip(x, y, msg) {
			if (!tip) {
				tip = document.createElement('div');
				tip.style.cssText = 'position:fixed;z-index:99999;background:#333;color:#fff;font-size:12px;padding:5px 12px;border-radius:6px;pointer-events:none;opacity:0;transition:opacity .2s;box-shadow:0 4px 12px rgba(0,0,0,.2)';
				document.body.appendChild(tip);
			}
			tip.textContent = msg;
			tip.style.left = (x + 10) + 'px';
			tip.style.top = (y - 30) + 'px';
			tip.style.opacity = '1';
			clearTimeout(tip._t);
			tip._t = setTimeout(function () { tip.style.opacity = '0'; }, 1400);
		}
		document.addEventListener('click', function(e){
			var t = e.target && e.target.closest ? e.target.closest('.zhiji-copy-code') : null;
			if (!t) return;
			var code = t.getAttribute('data-code') || t.textContent.trim();
			var ori = t.textContent;
			function ok() {
				t.textContent = '已复制';
				showTip(e.clientX, e.clientY, '✅ 优惠码已复制：' + code);
				setTimeout(function(){ t.textContent = ori; }, 1200);
			}
			function fail() {
				t.textContent = '复制失败，请长按/双击手动选择';
				showTip(e.clientX, e.clientY, '复制失败，请长按/双击手动选择');
				setTimeout(function(){ t.textContent = ori; }, 2200);
			}
			function fallback() {
				var ta = document.createElement('textarea');
				ta.value = code;
				ta.style.position = 'fixed';
				ta.style.opacity = '0';
				document.body.appendChild(ta);
				ta.select();
				var okc = false;
				try { okc = document.execCommand('copy'); } catch(err) {}
				document.body.removeChild(ta);
				okc ? ok() : fail();
			}
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(code).then(ok, fallback);
			} else {
				fallback();
			}
		});
	})();
	</script>
	<?php
}

/**
 * 获取当前用户领取的优惠码列表
 *
 * @param int $user_id 用户 ID
 * @return array 优惠码对象数组（含 meta 反序列化）
 */
function zhiji_coupon_get_user_coupons( $user_id ) {
	$out   = array();
	$email = '';
	if ( ! $user_id || ! class_exists( 'ZibCardPass' ) ) {
		return $out;
	}
	$user  = get_userdata( $user_id );
	$email = $user ? $user->user_email : '';

	$rows = ZibCardPass::get( array( 'type' => 'coupon' ), 'id', 0, 'all' );
	foreach ( $rows as $row ) {
		// 注意：ZibCardPass::get()（多行）不会反序列化 meta，需手动处理
		$meta = maybe_unserialize( $row->meta );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		$match = ( ! empty( $meta['user_id'] ) && (int) $meta['user_id'] === (int) $user_id )
			|| ( $email && ! empty( $meta['email'] ) && $meta['email'] === $email );
		if ( $match ) {
			$row->meta = $meta; // 写回反序列化后的 meta，供下游直接使用
			$out[]     = $row;
		}
	}

	return $out;
}
