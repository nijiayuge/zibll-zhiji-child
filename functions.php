<?php
/**
 * 知集子主题 functions.php
 *
 * 子主题功能入口，用于在父主题(zibll)基础上添加自定义功能。
 * 主要通过覆盖 zibpay/page/ 目录下的模板文件实现商城后台页面定制。
 *
 * @package Zibll_Zhiji_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 子主题版本号
 */
define( 'ZIBLL_ZHIJI_CHILD_VERSION', '1.0.0' );

/**
 * 子主题目录路径
 */
define( 'ZIBLL_ZHIJI_CHILD_PATH', get_stylesheet_directory() . '/' );

/**
 * 子主题目录 URL
 */
define( 'ZIBLL_ZHIJI_CHILD_URL', get_stylesheet_directory_uri() . '/' );

/**
 * 加载子主题样式
 * 父主题样式通过 zibll 的 wp_enqueue_style 自动加载，子主题只需加载自身样式
 */
function zibll_zhiji_child_enqueue_styles() {
	wp_enqueue_style(
		'zibll-zhiji-child-style',
		ZIBLL_ZHIJI_CHILD_URL . 'style.css',
		array(),
		ZIBLL_ZHIJI_CHILD_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'zibll_zhiji_child_enqueue_styles', 20 );

/**
 * 覆盖父主题 zibpay 商城后台页面模板
 *
 * 子主题 zibpay/page/ 目录下的文件会自动覆盖父主题对应模板，
 * 包括：优惠码(coupon)、订单(order)、商品(product)、收入(income)、
 *       提现(withdraw)、充值卡(charge-card)、商城(shop)、分销(rebate)等。
 *
 * 模板加载逻辑由父主题 zibpay 模块处理，此处无需额外代码。
 */
