<?php
/**
 * @module  BrandColor
 * @desc    品牌主色配置（全局&功能）—— 令牌色值的**配置入口**
 * @option  zhiji_brand_color  品牌主色
 * @since   2.0.8
 *
 * 2026-10-02（P3）新增。
 *
 * 【为什么需要这个模块】
 * 令牌色值本身（zhiji_token_color / zhiji_color_darken）与前端 CSS 变量输出
 * 已在 P3 下沉到 core/Helpers.php —— 那里是纯函数与纯输出，不该受开关控制
 * （关掉就会让 6 个模块的邮件渲染撞致命错误，见 Helpers 内的说明）。
 *
 * 但「后台改品牌主色」这个**配置入口**确实需要一个家：
 * 原属 ColorTokens 模块，该模块在 AF.20 已被移除，字段一并消失，
 * 于是存量站点 option 里还留着值（继续沿用），新站却无从配置。
 *
 * 故建此极简模块：只承载一个 color 字段，不含任何逻辑。
 * 将来若重做完整的配色模块，把这个字段迁过去、本模块删除即可，
 * core 里的色值函数与 CSS 输出**不需要任何改动**（它们只认 filter 与 option）。
 *
 * @migrate 字段源自已移除的 modules/ColorTokens.php
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('brand_color', array(
	'title'    => '品牌主色',
	'parent'   => 'zhiji_basic',
	'priority' => 5,
	'option'   => 'brand_color_enabled',
));

/**
 * 后台字段：品牌主色
 */
function zhiji_brand_color_register_options() {
	if ( ! class_exists( 'CSF' ) || ! is_admin() ) {
		return;
	}
	Zhiji_Registry::register_options( 'brand_color', array(
			array(
				'id'      => 'brand_color_enabled',
				'type'    => 'switcher',
				'title'   => __( '启用自定义品牌色', 'zhiji' ),
				'default' => true,
				'desc'    => __( '关闭后全站恢复默认蓝（#2e7cf6）。品牌色影响评论福袋/优惠码/抽奖/订阅邮件等处的按钮与高亮色。', 'zhiji' ),
			),
			array(
				'dependency' => array( 'brand_color_enabled', '==', '1' ),
				'id'         => 'zhiji_brand_color',
				'type'       => 'color',
				'title'      => __( '品牌主色', 'zhiji' ),
				'default'    => '#2e7cf6',
				'desc'       => __( '默认 #2e7cf6（蓝）。此处改动会同步到邮件模板与站内通知高亮（邮件客户端不支持 CSS 变量，故在 PHP 侧注入）。', 'zhiji' ),
			),
		), 5 );
}
zhiji_brand_color_register_options();

/**
 * 品牌主色来源：开关关闭时回落到默认色
 *
 * 挂 zhiji_brand_color filter，让 core 的 zhiji_brand_color() 能感知开关。
 * 选 filter 而非在 core 里判 option，是为了**开关逻辑留在配置层**、
 * core 只做「取值」，职责不混。
 */
add_filter( 'zhiji_brand_color', function ( $brand ) {
	if ( ! zhiji_get_option( 'brand_color_enabled', true ) ) {
		return '#2e7cf6';
	}
	return $brand;
}, 5 );
