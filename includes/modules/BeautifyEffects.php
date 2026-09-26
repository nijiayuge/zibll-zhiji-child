<?php
/**
 * @module  BeautifyEffects
 * @desc    下雪等视觉特效
 * @option  effects_enabled  总开关
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/BeautifyEffects.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('effects_beautify', array(
    'title'    => '视觉特效',
    'parent'   => 'zhiji_beautify',
    'priority' => 20,
    'option'   => 'effects_enabled',
));



defined( 'ABSPATH' ) || exit;

/* ============================================================
 * 后台 CSF 设置：美化效果 → 视觉特效
 * ============================================================ */
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('effects_beautify', array(
			array(
				'id'      => 'effects_enabled',
				'type'    => 'switcher',
				'title'   => '启用特效库',
				'default' => false,
				'desc'    => '总开关：关闭后下方全部特效不加载，页面最轻量。',
			),
			array(
				'id'         => 'effect_snow',
				'type'       => 'switcher',
				'title'      => '3D 雪花',
				'desc' => __( '开启页面 3D 雪花飘落效果（对性能有一定开销）。', 'zhiji' ),
				'default'    => false,
				'dependency' => array( 'effects_enabled', '==', '1' ),
			),
			array(
				'id'         => 'effect_sakura',
				'type'       => 'switcher',
				'title'      => '樱花飘落',
				'desc' => __( '开启樱花飘落效果。', 'zhiji' ),
				'default'    => false,
				'dependency' => array( 'effects_enabled', '==', '1' ),
			),
			array(
				'id'         => 'effect_particle',
				'type'       => 'switcher',
				'title'      => '点击爆炸粒子',
				'desc' => __( '点击页面时产生爆炸粒子效果。', 'zhiji' ),
				'default'    => false,
				'dependency' => array( 'effects_enabled', '==', '1' ),
			),
			array(
				'id'         => 'effect_cursor',
				'type'       => 'switcher',
				'title'      => '鼠标跟随光圈',
				'desc' => __( '鼠标跟随光圈效果。', 'zhiji' ),
				'default'    => false,
				'dependency' => array( 'effects_enabled', '==', '1' ),
			),
			array(
				'id'         => 'effect_color',
				'type'       => 'color',
				'title'      => '特效主色',
				'default'    => '#6a5cff',
				'desc'       => '鼠标光圈颜色。',
				'dependency' => array( 'effects_enabled', '==', '1' ),
			),
			array(
				'id'         => 'effect_coin',
				'type'       => 'switcher',
				'title'      => '点击金币',
				'default'    => false,
				'desc'       => '点击页面任意位置，浮现旋转金币 + 数字上飘。',
				'dependency' => array( 'effects_enabled', '==', '1' ),
			),
			array(
				'id'         => 'effect_coin_text',
				'type'       => 'text',
				'title'      => '金币数字文本',
				'default'    => '+1',
				'desc'       => '点击时金币旁显示的文字，例如 +1 / +10 / 金币+1。',
				'dependency' => array( 'effect_coin', '==', '1' ),
			),
		), 20);

/**
 * 输出特效容器与脚本（全部挂 wp_footer，延迟到页面底部加载）。
 */
function zhiji_beautify_effects_output() {
	if ( ! zhiji_get_option( 'effects_enabled', 0 ) ) {
		return;
	}

	$base = ZHIJI_URL . 'assets/zhiji/js/';
	$ver  = defined( 'ZHIJI_CHILD_VERSION' ) ? ZHIJI_CHILD_VERSION : '1.10.1';
	$html = '';

	// 雪花（3D，自运行）
	if ( zhiji_get_option( 'effect_snow', 0 ) ) {
		$html .= '<script src="' . esc_url( $base . 'snow3d.js' ) . '?v=' . esc_attr( $ver ) . '"></script>' . "\n";
	}

	// 樱花（canvas 飘落，图片内嵌）
	if ( zhiji_get_option( 'effect_sakura', 0 ) ) {
		$html .= '<script src="' . esc_url( $base . 'yinghua.js' ) . '?v=' . esc_attr( $ver ) . '"></script>' . "\n";
	}

	// 点击爆炸粒子（需要 canvas.baozha 容器）
	if ( zhiji_get_option( 'effect_particle', 0 ) ) {
		$html .= '<canvas class="baozha" style="position:fixed;left:0;top:0;z-index:999999;pointer-events:none;" aria-hidden="true"></canvas>' . "\n";
		$html .= '<script src="' . esc_url( $base . 'baozha.js' ) . '?v=' . esc_attr( $ver ) . '"></script>' . "\n";
	}

	// 鼠标跟随光圈（需要 .mouse-cursor 容器 + 样式）
	// 兼容旧框架「美化效果→鼠标特效」合并开关：新旧任一开启即生效
	$cursor_on = zhiji_get_option( 'effect_cursor', 0 ) || zhiji_get_option( 'zhiji_mouse_cursor', 0 );
	if ( $cursor_on ) {
		$color = (string) zhiji_get_option( 'effect_color', '' );
		if ( ! $color ) {
			$color = (string) zhiji_get_option( 'zhiji_cursor_color', '' );
		}
		if ( ! $color ) {
			// 旧三开关遗留值兼容：绿/粉曾开启 → 对应色
			if ( zhiji_get_option( 'zhiji_mouse_cursor2', 0 ) ) {
				$color = '#22b573';
			} elseif ( zhiji_get_option( 'zhiji_mouse_cursor3', 0 ) ) {
				$color = '#ff69b4';
			} else {
				$color = '#123eed';
			}
		}
		$html .= '<div class="mouse-cursor cursor-outer"></div><div class="mouse-cursor cursor-inner"></div>' . "\n";
		$html .= '<style>.mouse-cursor{position:fixed;left:0;top:0;pointer-events:none;border-radius:50%;-webkit-transform:translateZ(0);transform:translateZ(0);visibility:hidden;z-index:10000001}.cursor-inner{margin-left:-3px;margin-top:-3px;width:6px;height:6px;background:' . esc_attr( $color ) . ';-webkit-transition:width .3s ease-in-out,height .3s ease-in-out,margin .3s ease-in-out,opacity .3s ease-in-out;transition:width .3s ease-in-out,height .3s ease-in-out,margin .3s ease-in-out,opacity .3s ease-in-out}.cursor-inner.cursor-hover{margin-left:-20px;margin-top:-20px;width:40px;height:40px;opacity:.3}.cursor-outer{margin-left:-16px;margin-top:-16px;width:32px;height:32px;border:1px solid ' . esc_attr( $color ) . ';opacity:.5;-webkit-transition:all .2s ease-in-out;transition:all .2s ease-in-out}.cursor-outer.cursor-hover{opacity:.2}</style>' . "\n";
		$html .= '<script src="' . esc_url( $base . 'shubiao.js' ) . '?v=' . esc_attr( $ver ) . '"></script>' . "\n";
	}

	// 点击金币（点击页面任意位置，浮现金币 + 数字上飘）
	if ( zhiji_get_option( 'effect_coin', 0 ) ) {
		$coin_text = (string) zhiji_get_option( 'effect_coin_text', '+1' );
		$html .= '<style>.zhiji-coin{position:fixed;z-index:999999;pointer-events:none;display:flex;flex-direction:column;align-items:center;animation:zhijiCoinUp 1.6s ease-out forwards;-webkit-animation:zhijiCoinUp 1.6s ease-out forwards}.zhiji-coin svg{width:42px;height:42px;animation:zhijiCoinSpin .8s linear infinite;-webkit-animation:zhijiCoinSpin .8s linear infinite;filter:drop-shadow(0 2px 3px rgba(224,145,0,.4))}.zhiji-coin b{font-size:17px;line-height:1;color:#e09100;font-weight:800;margin-top:3px;text-shadow:0 0 3px #fff,0 1px 2px rgba(0,0,0,.2)}@keyframes zhijiCoinUp{0%{transform:translateY(0) scale(.8);opacity:1}60%{opacity:1}100%{transform:translateY(-90px) scale(1);opacity:0}}@keyframes zhijiCoinSpin{0%{transform:rotateY(0)}100%{transform:rotateY(180deg)}}</style>' . "\n";
	}

	if ( $html ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- 以上已逐段转义
	}
}
zhiji_footer_add( 'beautify-effects', 'zhiji_beautify_effects_output', 5 );

/**
 * 点击金币脚本（独立挂 wp_head，避免与主题 Pjax/其他脚本的点击拦截冲突）。
 */
function zhiji_beautify_effects_coin_script() {
	if ( ! zhiji_get_option( 'effects_enabled', 0 ) || ! zhiji_get_option( 'effect_coin', 0 ) ) {
		return;
	}
	$coin_text = (string) zhiji_get_option( 'effect_coin_text', '+1' );
	$js        = '(function(){var boot=function(){var bootAt=Date.now(),lastAt=0;var t=' . wp_json_encode( $coin_text, JSON_UNESCAPED_UNICODE ) . ';var s=\'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="11" fill="#ffd54a" stroke="#e09100" stroke-width="1.6"/><circle cx="12" cy="12" r="8" fill="none" stroke="#e8b000" stroke-width="1"/><text x="12" y="16.6" font-size="11" font-weight="800" text-anchor="middle" fill="#c77b00" font-family="Arial,Helvetica,sans-serif">\u00a5</text></svg>\';function mk(x,y){var n=document.createElement("span");n.className="zhiji-coin";n.style.left=(x-15)+"px";n.style.top=(y-22)+"px";n.innerHTML=s+"<b>"+t+"</b>";document.body.appendChild(n);setTimeout(function(){n.remove()},1500);}function hit(e){if(e.button!==0)return;if(Date.now()-bootAt<800)return;if(Date.now()-lastAt<300)return;if(e.clientX<10&&e.clientY<10)return;lastAt=Date.now();mk(e.clientX,e.clientY);}window.addEventListener("pointerdown",hit,true);window.addEventListener("click",hit,true);};if(document.body){boot();}else{document.addEventListener("DOMContentLoaded",boot);}})();';
	echo '<script>' . $js . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- 内部生成
}
add_action( 'wp_head', 'zhiji_beautify_effects_coin_script', 99 );
