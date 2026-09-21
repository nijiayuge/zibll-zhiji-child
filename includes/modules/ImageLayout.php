<?php
/**
 * @module  ImageLayout
 * @desc    文章图片宽度与排版控制
 * @option  image_layout_enabled  总开关
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/ImageLayout.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('image_layout', array(
    'title'    => '图片宽度排版',
    'parent'   => 'zhiji_page',
    'priority' => 30,
    'option'   => 'image_layout_enabled',
));



// 安全门
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 文章正文图片排版过滤器
 *
 * 挂 the_content（优先级 20：在父主题图片懒加载/灯箱处理后执行，
 * 避免与父主题对 img 的处理冲突），仅对单篇/单页正文生效。
 *
 * @param string $content 文章内容。
 * @return string
 */
function zhiji_image_layout_filter( $content ) {
	// 仅前台单页正文；后台预览无需处理
	if ( is_admin() || ! is_singular() ) {
		return $content;
	}
	// 总开关
	if ( ! zhiji_get_option( 'image_layout_enabled', 1 ) ) {
		return $content;
	}

	// ① 匹配完整 <img ... alt="..." ...> 标签，追加 fig-* 排版 class
	$content = preg_replace_callback(
		'#<img\b[^>]*alt\s*=\s*"[^"]*"[^>]*>#i',
		'zhiji_image_layout_cb',
		$content
	);

	// ② 把"仅含排版图、无文字"的段落包成 flex 画廊容器（zhiji-gallery）
	//    核心修复：float 布局下同一行图高度参差会导致下一行图插入空隙错位，
	//    flex 多行天然对齐、绝不交错，彻底解决多图墙参差错乱。
	//    含文字的段落（图+文字环绕）不包裹，仍走 float 方案。
	//    注意：捕获组必须包住整段 img 序列（外层），不能放在 (?:...)+ 内部，
	//    否则 $m[1] 只会保留序列最后一张图，其余被吞。
	$content = preg_replace_callback(
		'#<p(?:\s[^>]*)?>\s*((?:<img\b[^>]*class\s*=\s*"[^"]*figure[^"]*"[^>]*>\s*)+)</p>#i',
		function ( $m ) {
			return '<div class="zhiji-gallery">' . $m[1] . '</div>';
		},
		$content
	);

	return $content;
}
add_filter( 'the_content', 'zhiji_image_layout_filter', 20 );

/**
 * 单张图片排版回调：解析 alt 结尾的 "-宽度 [left|right]" 并追加 fig-* class
 *
 * @param array $m preg_replace_callback 匹配数组（$m[0] 为完整 img 标签）。
 * @return string 处理后的 img 标签。
 */
function zhiji_image_layout_cb( $m ) {
	$tag = $m[0];

	// 提取 alt 值
	if ( ! preg_match( '#alt\s*=\s*"([^"]*)"#i', $tag, $am ) ) {
		return $tag;
	}
	$alt = trim( $am[1] );

	// 解析结尾 "-宽度 [left|right]"（如 "配图-50 right"）
	if ( ! preg_match( '#^(.*?)-(\d{2,3})(?:\s+(left|right))?$#', $alt, $wm ) ) {
		return $tag;
	}
	$width = (int) $wm[2];
	$pos   = ( ! empty( $wm[3] ) ) ? $wm[3] : '';

	// 仅支持预设档位，其余原样返回
	if ( ! in_array( $width, array( 20, 25, 33, 50, 75, 100 ), true ) ) {
		return $tag;
	}

	// 追加 class：figure fig-XX [left|right]
	$add = 'figure fig-' . $width . ( $pos ? ' ' . $pos : '' );

	// 合并已有 class（保留父主题懒加载 lazyload 等），避免破坏原结构
	if ( preg_match( '#class\s*=\s*"([^"]*)"#i', $tag, $cm ) && '' !== trim( $cm[1] ) ) {
		$tag = preg_replace( '#class\s*=\s*"([^"]*)"#i', 'class="' . esc_attr( trim( $cm[1] ) ) . ' ' . $add . '"', $tag, 1 );
	} else {
		$tag = preg_replace( '#<img\b#i', '<img class="' . esc_attr( $add ) . '"', $tag, 1 );
	}

	// alt 去后缀（保留描述文字，宽度信息已转入 class）
	$new_alt = esc_attr( $wm[1] );
	$tag     = preg_replace( '#alt\s*=\s*"([^"]*)"#i', 'alt="' . $new_alt . '"', $tag, 1 );

	return $tag;
}

/**
 * 图片排版样式（wp_head 内联输出）
 *
 * 直接在头部输出，不依赖 enqueue_css 开关，确保排版始终生效。
 */
function zhiji_image_layout_css() {
	if ( is_admin() ) {
		return;
	}
	if ( ! zhiji_get_option( 'image_layout_enabled', 1 ) ) {
		return;
	}
	echo '<style id="zhiji-image-layout">'
		/* —— 画廊容器（纯图段落自动包裹）——
		   行内 flex 天然等高对齐，多行互不交错，彻底消除 float 错位 */
		. '.article-content .zhiji-gallery{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;}'
		. '.article-content .zhiji-gallery img.figure{float:none;display:block;height:auto;margin:0;box-sizing:border-box;}'
		. '.article-content .zhiji-gallery img.figure.fig-20{flex:0 0 calc(20% - 8px);max-width:calc(20% - 8px);}'
		. '.article-content .zhiji-gallery img.figure.fig-25{flex:0 0 calc(25% - 6px);max-width:calc(25% - 6px);}'
		. '.article-content .zhiji-gallery img.figure.fig-33{flex:0 0 calc(33.3333% - 8px);max-width:calc(33.3333% - 8px);}'
		. '.article-content .zhiji-gallery img.figure.fig-50{flex:0 0 calc(50% - 8px);max-width:calc(50% - 8px);}'
		. '.article-content .zhiji-gallery img.figure.fig-75{flex:0 0 calc(75% - 8px);max-width:calc(75% - 8px);}'
		. '.article-content .zhiji-gallery img.figure.fig-100{flex:0 0 100%;max-width:100%;}'
		/* —— 图文混排段落（单图+文字）：保留 float 环绕 —— */
		. '.article-content img.figure,.article-content a img.figure{display:inline-block;vertical-align:top;height:auto;max-width:100%;}'
		. '.article-content img.figure.fig-20{width:20%;}'
		. '.article-content img.figure.fig-25{width:25%;}'
		. '.article-content img.figure.fig-33{width:33.3333%;}'
		. '.article-content img.figure.fig-50{width:50%;}'
		. '.article-content img.figure.fig-75{width:75%;}'
		. '.article-content img.figure.fig-100{width:100%;}'
		. '.article-content img.figure.left{float:left;margin:0 12px 8px 0;}'
		. '.article-content img.figure.right{float:right;margin:0 0 8px 12px;}'
		. '.article-content:after{content:"";display:table;clear:both;}'
		/* 移动端：画廊保持并排，间距收窄让图更大更密 */
		. '@media (max-width:767px){'
		. '.article-content .zhiji-gallery{gap:6px;margin-bottom:12px;}'
		. '.article-content .zhiji-gallery img.figure.fig-20{flex-basis:calc(20% - 5px);max-width:calc(20% - 5px);}'
		. '.article-content .zhiji-gallery img.figure.fig-25{flex-basis:calc(25% - 5px);max-width:calc(25% - 5px);}'
		. '.article-content .zhiji-gallery img.figure.fig-33{flex-basis:calc(33.3333% - 4px);max-width:calc(33.3333% - 4px);}'
		. '.article-content .zhiji-gallery img.figure.fig-50{flex-basis:calc(50% - 3px);max-width:calc(50% - 3px);}'
		. '.article-content .zhiji-gallery img.figure.fig-75{flex-basis:calc(75% - 6px);max-width:calc(75% - 6px);}'
		. '}'
		. '</style>' . "\n";
}
add_action( 'wp_head', 'zhiji_image_layout_css', 99 );

// ===== 后台开关：文章图片宽度排版（密集墙） =====
add_action( 'after_setup_theme', function () {
	if ( ! class_exists( 'CSF' ) || ! defined( 'ZHIJI_OPTION_KEY' ) ) {
		return;
	}
	Zhiji_Registry::csf_section_for_legacy( 'image_layout', array(
		'parent' => 'zhiji_page',
		'priority' => 30,
		'title'  => '图片宽度排版',
		'icon'   => 'fa fa-columns',
		'fields' => array(
			array(
				'id'      => 'image_layout_enabled',
				'type'    => 'switcher',
				'title'   => '启用图片宽度排版（密集墙）',
				'desc'    => '文章内纯图片段落自动排成密集墙：行内等高、行间宽度错落、多图成墙不溢出。',
				'default' => true,
			),
			array(
				'type'    => 'submessage',
				'style'   => 'success',
				'content' => '图片 alt 后缀控制单图宽度：-20 / -25 / -33 / -50 / -75（例如 alt="壁纸-50" 占 50%% 宽）。',
			),
		),
	) );
} );
