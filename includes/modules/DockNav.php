<?php
/**
 * @module  DockNav
 * @desc    移动端底部 Dock 导航栏
 * @option  dock_nav_enabled  总开关
 * @hook    wp_footer · 输出 Dock 栏
 * @hook    zhiji_asset_url · 图标资源
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/DockNav.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('dock_nav', array(
    'title'    => '底部 Dock 导航',
    'parent'   => 'zhiji_page',
    'priority' => 40,
    'option'   => 'dock_nav_enabled',
));



// 安全门

/* ============================================================
 * 后台 CSF 设置：页面&显示 → 底部 Dock 导航
 * ============================================================ */
add_action( 'after_setup_theme', function () {
		Zhiji_Registry::csf_section_for_legacy( 'dock_nav', array(
		'title'  => '底部 Dock 导航',
		'icon'   => 'fa fa-anchor',
		'parent' => 'zhiji_page',
		'priority' => 40,
		'fields' => array(
			array(
				'id'      => 'dock_enabled',
				'type'    => 'switcher',
				'title'   => '启用底部 Dock 导航',
				'default' => false,
				'desc'    => '悬浮于页面底部的快捷导航条，支持自定义菜单项。',
			),
			array(
				'id'         => 'dock_position',
				'type'       => 'button_set',
				'title'      => '显示位置',
				'default'    => 'center',
				'options'    => array(
					'center' => '居中',
					'left'   => '左侧',
					'right'  => '右侧',
				),
				'dependency' => array( 'dock_enabled', '==', '1' ),
			),
			array(
				'id'         => 'dock_collapsible',
				'type'       => 'switcher',
				'title'      => '可折叠',
				'default'    => true,
				'desc'       => '显示折叠按钮，可收起/展开导航条。',
				'dependency' => array( 'dock_enabled', '==', '1' ),
			),
			array(
				'id'         => 'dock_blur',
				'type'       => 'switcher',
				'title'      => '毛玻璃背景',
				'default'    => true,
				'dependency' => array( 'dock_enabled', '==', '1' ),
			),
			array(
				'id'         => 'dock_items',
				'type'       => 'group',
				'title'      => '菜单项',
				'desc'       => '图标可用内置 SVG 键（home/hot/book/video/music/game/shop/star/heart/chat/user/search/phone/mail/gift/trophy/compass/menu）或 emoji。',
				'button_title' => '添加菜单项',
				'fields'     => array(
					array(
						'id'    => 'label',
						'type'  => 'text',
						'title' => '名称',
					),
					array(
						'id'    => 'icon',
						'type'  => 'text',
						'title' => '图标（SVG 键或 emoji）',
						'placeholder' => '如 home / 🔥',
					),
					array(
						'id'          => 'icon_url',
						'type'        => 'text',
						'title'       => '自定义图标图片 URL',
						'placeholder' => '留空则用上面的图标',
					),
					array(
						'id'      => 'action',
						'type'    => 'button_set',
						'title'   => '动作',
						'default' => 'link',
						'options' => array(
							'link'  => '跳转链接',
							'modal' => '二维码弹窗',
						),
					),
					array(
						'id'         => 'url',
						'type'       => 'text',
						'title'      => '链接地址',
						'placeholder' => 'http:// 或站内相对路径',
						'dependency' => array( 'action', '==', 'link' ),
					),
					array(
						'id'         => 'qr_image',
						'type'       => 'text',
						'title'      => '二维码图片 URL',
						'dependency' => array( 'action', '==', 'modal' ),
					),
					array(
						'id'         => 'modal_title',
						'type'       => 'text',
						'title'      => '弹窗标题',
						'placeholder' => '如「扫码加群」',
						'dependency' => array( 'action', '==', 'modal' ),
					),
				),
				'dependency' => array( 'dock_enabled', '==', '1' ),
			),
		),
	) );
}, 20 );


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 渲染单个 dock 项的图标：
 *  - icon_url 为图片 URL（http(s):// 或图片扩展名结尾）→ <img>（优先）
 *  - icon 命中内置 SVG 图标库（key 或 emoji）→ 内联 SVG（遵守铁律：不用 emoji 渲染）
 *  - 否则输出原始字符（兼容自定义字符）
 *
 * @param string $icon     图标（内置 SVG key / emoji / 任意字符）
 * @param string $icon_url 自定义图片 URL
 * @return string HTML
 */
function zhiji_dock_icon_html( $icon, $icon_url = '' ) {
	$icon     = trim( (string) $icon );
	$icon_url = trim( (string) $icon_url );
	if ( '' !== $icon_url
		&& ( preg_match( '#^https?://#i', $icon_url ) || preg_match( '#\.(png|jpe?g|gif|webp|svg)$#i', $icon_url ) ) ) {
		return '<img src="' . esc_url( $icon_url ) . '" alt="" class="zhiji-dock-img" loading="lazy">';
	}
	$svg = zhiji_dock_svg_html( $icon );
	if ( '' !== $svg ) {
		return $svg;
	}
	return '<span class="zhiji-dock-emoji">' . esc_html( $icon ) . '</span>';
}

/**
 * 内置 SVG 图标库：icon 为 SVG key（home/hot/...）或存量 emoji 时输出对应内联 SVG。
 *
 * @param string $icon 图标 key 或 emoji
 * @return string HTML（未命中返回空串）
 */
function zhiji_dock_svg_html( $icon ) {
	// emoji → key 兼容映射（存量配置自动升级为 SVG）
	static $emoji_map = array(
		'🏠' => 'home', '🔥' => 'hot', '📚' => 'book', '🎬' => 'video',
		'🎵' => 'music', '🎮' => 'game', '🛒' => 'shop', '⭐' => 'star',
		'❤️' => 'heart', '💬' => 'chat', '👤' => 'user', '🔍' => 'search',
		'📞' => 'phone', '✉️' => 'mail', '🎁' => 'gift', '🏆' => 'trophy',
		'🧭' => 'compass', '☰' => 'menu',
	);
	if ( isset( $emoji_map[ $icon ] ) ) {
		$icon = $emoji_map[ $icon ];
	}
	static $svgs = array(
		'home'  => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/></svg>',
		'hot'   => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3c1 4.5-4.5 6.5-4.5 11a4.5 4.5 0 0 0 9 0c0-2.2-1.1-3.4-2.3-4.6C13 10.3 12 9 12 7c-1 2-2 3.5-2.5 4.5"/></svg>',
		'book'  => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5a2 2 0 0 1 2-2h14v16H6a2 2 0 0 0-2 2z"/><path d="M4 19a2 2 0 0 1 2-2h14"/></svg>',
		'video' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-2v8l-5-2"/></svg>',
		'music' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l10-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="16" cy="16" r="3"/></svg>',
		'game'  => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="7" width="20" height="10" rx="4"/><path d="M7 11v4M5 13h4M16 12h.01M19 14h.01"/></svg>',
		'shop'  => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h16l-1.2 12H5.2z"/><path d="M8 8a4 4 0 0 1 8 0"/></svg>',
		'star'  => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 2.7 5.5 6 .9-4.3 4.2 1 6-5.4-2.8-5.4 2.8 1-6L3.3 9.4l6-.9z"/></svg>',
		'heart' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20s-7-4.5-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.5-9 9-9 9z"/></svg>',
		'chat'  => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16v11H8l-4 4z"/></svg>',
		'user'  => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>',
		'search' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>',
		'phone' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>',
		'mail' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
		'gift' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="8" width="18" height="12" rx="2"/><path d="M3 13h18M12 8v12M12 8s-2-4-4.5-4a2.5 2.5 0 0 0 0 5zM12 8s2-4 4.5-4a2.5 2.5 0 0 1 0 5z"/></svg>',
		'trophy' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 4h10v6a5 5 0 0 1-10 0z"/><path d="M7 6H4a2 2 0 0 0 2 4h2M17 6h3a2 2 0 0 1-2 4h-2M12 15v5M8 20h8"/></svg>',
		'compass' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/></svg>',
		'menu' => '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>',
	);
	if ( isset( $svgs[ $icon ] ) ) {
		return '<span class="zhiji-dock-svg">' . $svgs[ $icon ] . '</span>';
	}
	return '';
}

/**
 * 输出底部 Dock 导航（wp_footer，含内联样式与交互）
 */
function zhiji_dock_nav_output() {
	if ( ! zhiji_get_option( 'dock_enabled', 0 ) ) {
		return;
	}
	$items = zhiji_get_option( 'dock_items', array() );
	if ( empty( $items ) || ! is_array( $items ) ) {
		return;
	}

	$position    = (string) zhiji_get_option( 'dock_position', 'center' );
	$collapsible = (bool) zhiji_get_option( 'dock_collapsible', true );
	$blur        = (bool) zhiji_get_option( 'dock_blur', true );

	$classes = array( 'zhiji-dock' );
	if ( 'left' === $position ) {
		$classes[] = 'zhiji-dock-left';
	} elseif ( 'right' === $position ) {
		$classes[] = 'zhiji-dock-right';
	}
	if ( $blur ) {
		$classes[] = 'zhiji-dock-blur';
	}

	echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" id="zhiji-dock" role="navigation" aria-label="快捷导航">';

	$has_modal = false;
	foreach ( $items as $item ) {
		$item  = (array) $item;
		$label = isset( $item['label'] ) ? trim( (string) $item['label'] ) : '';
		$url   = isset( $item['url'] ) ? trim( (string) $item['url'] ) : '';
		$icon  = isset( $item['icon'] ) ? trim( (string) $item['icon'] ) : '';
		$action      = isset( $item['action'] ) ? trim( (string) $item['action'] ) : 'link';
		$qr          = isset( $item['qr_image'] ) ? trim( (string) $item['qr_image'] ) : '';
		$modal_title = isset( $item['modal_title'] ) ? trim( (string) $item['modal_title'] ) : '';
		if ( '' === $url && '' === $label && '' === $icon ) {
			continue;
		}
		$icon_html = zhiji_dock_icon_html( $icon, isset( $item['icon_url'] ) ? $item['icon_url'] : '' );

		if ( 'modal' === $action && '' !== $qr ) {
			$has_modal = true;
			echo '<button type="button" class="zhiji-dock-item zhiji-dock-modal-trigger"'
				. ' data-qr="' . esc_attr( $qr ) . '"'
				. ' data-title="' . esc_attr( $modal_title ) . '">';
			echo '<span class="zhiji-dock-icon">' . $icon_html . '</span>';
			if ( '' !== $label ) {
				echo '<span class="zhiji-dock-label">' . esc_html( $label ) . '</span>';
			}
			echo '</button>';
		} else {
			$attr = '';
			if ( '' !== $url ) {
				$attr = ' href="' . esc_url( $url ) . '"'
					. ( preg_match( '#^https?://#i', $url ) ? ' target="_blank" rel="noopener noreferrer"' : '' );
			}
			echo '<a class="zhiji-dock-item"' . $attr . '>';
			echo '<span class="zhiji-dock-icon">' . $icon_html . '</span>';
			if ( '' !== $label ) {
				echo '<span class="zhiji-dock-label">' . esc_html( $label ) . '</span>';
			}
			echo '</a>';
		}
	}

	if ( $collapsible ) {
		echo '<button type="button" class="zhiji-dock-toggle" aria-label="折叠/展开导航" aria-expanded="true">'
			. '<span class="zhiji-dock-caret">&#8964;</span></button>';
	}

	echo '</div>';

	if ( $has_modal ) {
		echo '<div class="zhiji-dock-modal" id="zhiji-dock-modal" hidden>';
		echo '<div class="zhiji-dock-modal-mask"></div>';
		echo '<div class="zhiji-dock-modal-box" role="dialog" aria-modal="true">';
		echo '<button type="button" class="zhiji-dock-modal-close" aria-label="关闭">&#215;</button>';
		echo '<div class="zhiji-dock-modal-title"></div>';
		echo '<img class="zhiji-dock-modal-img" alt="" loading="lazy">';
		echo '</div></div>';
	}
	?>
	<style id="zhiji-dock-style">
	.zhiji-dock{position:fixed;bottom:22px;left:50%;transform:translateX(-50%);display:flex;align-items:flex-end;gap:6px;padding:8px 12px;border-radius:20px;background:rgba(255,255,255,.72);box-shadow:0 10px 34px rgba(0,0,0,.14);z-index:9999;max-width:94vw;overflow-x:auto;scrollbar-width:none}
	.zhiji-dock::-webkit-scrollbar{display:none}
	.zhiji-dock.zhiji-dock-blur{backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)}
	.zhiji-dock.zhiji-dock-left{left:22px;transform:none}
	.zhiji-dock.zhiji-dock-right{left:auto;right:22px;transform:none}
	.zhiji-dock-item{display:flex;flex-direction:column;align-items:center;text-decoration:none;width:50px;color:inherit;transition:transform .2s cubic-bezier(.34,1.56,.64,1);flex:0 0 auto}
	.zhiji-dock-item .zhiji-dock-icon{width:42px;height:42px;border-radius:13px;display:flex;align-items:center;justify-content:center;background:#eef0f3;overflow:hidden;font-size:22px;line-height:1}
	.zhiji-dock-item .zhiji-dock-emoji{font-size:22px;line-height:1}
	.zhiji-dock-item .zhiji-dock-svg{display:flex;width:22px;height:22px;color:inherit}
	.zhiji-dock-item .zhiji-dock-svg svg{width:22px;height:22px;display:block}
	.zhiji-dock-item .zhiji-dock-img{width:100%;height:100%;object-fit:cover}
	.zhiji-dock-item:hover{transform:translateY(-14px) scale(1.18)}
	.zhiji-dock-item .zhiji-dock-label{font-size:11px;margin-top:5px;opacity:0;transition:opacity .2s;white-space:nowrap;max-width:80px;overflow:hidden;text-overflow:ellipsis}
	.zhiji-dock-item:hover .zhiji-dock-label{opacity:1}
	.zhiji-dock-toggle{display:flex;align-items:center;justify-content:center;width:30px;height:42px;border:0;background:transparent;cursor:pointer;color:#888;flex:0 0 auto}
	.zhiji-dock-caret{font-size:20px;line-height:1;transition:transform .2s}
	.zhiji-dock.collapsed .zhiji-dock-item{display:none}
	.zhiji-dock.collapsed{transform:translateX(-50%) scale(.96);opacity:.85}
	.zhiji-dock.collapsed.zhiji-dock-left,.zhiji-dock.collapsed.zhiji-dock-right{transform:none}
	@media (prefers-color-scheme:dark){.zhiji-dock{background:rgba(30,32,36,.7)}.zhiji-dock-item .zhiji-dock-icon{background:#2a2d33}.zhiji-dock-item{color:#e6e6e6}.zhiji-dock-toggle{color:#aaa}}
	@media (max-width:600px){.zhiji-dock-item{width:44px}.zhiji-dock-item .zhiji-dock-icon{width:38px;height:38px;font-size:20px}}
	.zhiji-dock-modal{position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center}
	.zhiji-dock-modal[hidden]{display:none}
	.zhiji-dock-modal-mask{position:absolute;inset:0;background:rgba(0,0,0,.5);-webkit-backdrop-filter:blur(2px);backdrop-filter:blur(2px)}
	.zhiji-dock-modal-box{position:relative;background:#fff;border-radius:16px;padding:22px 22px 18px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,.3);text-align:center}
	.zhiji-dock-modal-title{font-size:15px;font-weight:600;margin-bottom:12px;color:#222}
	.zhiji-dock-modal-img{max-width:240px;max-height:240px;width:auto;height:auto;border-radius:8px}
	.zhiji-dock-modal-close{position:absolute;top:6px;right:12px;border:0;background:transparent;font-size:22px;line-height:1;color:#999;cursor:pointer}
	@media (prefers-color-scheme:dark){.zhiji-dock-modal-box{background:#23262b;color:#eee}.zhiji-dock-modal-title{color:#eee}}
	</style>
	<script>(function(){var d=document.getElementById('zhiji-dock');if(!d)return;var t=d.querySelector('.zhiji-dock-toggle');if(t){t.addEventListener('click',function(){var c=d.classList.toggle('collapsed');t.setAttribute('aria-expanded',c?'false':'true');var car=t.querySelector('.zhiji-dock-caret');if(car){car.style.transform='rotate(180deg)';}});}})();</script>
	<script>(function(){var m=document.getElementById('zhiji-dock-modal');if(!m)return;var img=m.querySelector('.zhiji-dock-modal-img'),tt=m.querySelector('.zhiji-dock-modal-title');var open = function(qr,title) {img.src=qr;tt.textContent=title||'';m.hidden=false;}var close = function() {m.hidden=true;}m.querySelector('.zhiji-dock-modal-mask').addEventListener('click',close);m.querySelector('.zhiji-dock-modal-close').addEventListener('click',close);document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});var tr=document.querySelectorAll('.zhiji-dock-modal-trigger');tr.forEach(function(b){b.addEventListener('click',function(){open(b.getAttribute('data-qr'),b.getAttribute('data-title'));});});})();</script>
	<?php
}
add_action( 'wp_footer', 'zhiji_dock_nav_output', 20 );
