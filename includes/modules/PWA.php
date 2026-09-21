<?php
/**
 * @module  PWA
 * @desc    PWA 渐进式应用（manifest + Service Worker）
 * @option  pwa_enabled  总开关
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/PWA.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('pwa', array(
    'title'    => 'PWA 应用',
    'parent'   => 'zhiji_over',
    'priority' => 40,
    'option'   => 'pwa_enabled',
));



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 检查是否启用 PWA
 */
function zhiji_pwa_enabled() {
	return (bool) zhiji_get_option( 'pwa_enabled', 0 );
}

/**
 * 输出 manifest 链接
 */
function zhiji_pwa_manifest_link() {
	if ( ! zhiji_pwa_enabled() ) {
		return;
	}
	echo '<link rel="manifest" href="' . esc_url( home_url( '/?zhiji_pwa_manifest=1' ) ) . '">' . "\n";
	echo '<meta name="theme-color" content="' . esc_attr( zhiji_get_option( 'pwa_theme_color', '#2e7cf6' ) ) . '">' . "\n";
	echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
	echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr( zhiji_get_option( 'pwa_app_name', get_bloginfo( 'name' ) ) ) . '">' . "\n";
}
add_action( 'wp_head', 'zhiji_pwa_manifest_link', 5 );

/**
 * 动态生成 manifest.json
 */
function zhiji_pwa_generate_manifest() {
	if ( ! isset( $_GET['zhiji_pwa_manifest'] ) || $_GET['zhiji_pwa_manifest'] != 1 ) {
		return;
	}
	if ( ! zhiji_pwa_enabled() ) {
		return;
	}

	$app_name        = zhiji_get_option( 'pwa_app_name', get_bloginfo( 'name' ) );
	$app_short_name  = zhiji_get_option( 'pwa_short_name', get_bloginfo( 'name' ) );
	$theme_color     = zhiji_get_option( 'pwa_theme_color', '#2e7cf6' );
	$background_color = zhiji_get_option( 'pwa_background_color', '#ffffff' );
	$icon            = zhiji_get_option( 'pwa_icon', '' );
	// CSF media字段返回数组（含url/id/width/height），取url字段
	if ( is_array( $icon ) ) {
		$icon = isset( $icon['url'] ) ? $icon['url'] : '';
	}
	$start_url       = home_url( '/' );

	$manifest = array(
		'name'             => $app_name,
		'short_name'       => $app_short_name,
		'description'      => get_bloginfo( 'description' ),
		'start_url'        => $start_url,
		'display'          => 'standalone',
		'orientation'      => 'portrait-primary',
		'theme_color'      => $theme_color,
		'background_color' => $background_color,
		'lang'             => get_bloginfo( 'language' ),
		'icons'            => array(),
	);

	// 添加图标
	if ( $icon ) {
		$sizes = array( 192, 512 );
		foreach ( $sizes as $size ) {
			$manifest['icons'][] = array(
				'src'     => esc_url( $icon ),
				'sizes'   => $size . 'x' . $size,
				'type'    => 'image/png',
				'purpose' => 'any maskable',
			);
		}
	} else {
		// 默认图标（使用 site icon 或占位）
		$site_icon = get_site_icon_url( 512 );
		if ( $site_icon ) {
			$manifest['icons'][] = array(
				'src'     => esc_url( $site_icon ),
				'sizes'   => '512x512',
				'type'    => 'image/png',
				'purpose' => 'any maskable',
			);
		}
	}

	header( 'Content-Type: application/manifest+json; charset=utf-8' );
	header( 'Cache-Control: public, max-age=86400' );
	echo wp_json_encode( $manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	exit;
}
add_action( 'init', 'zhiji_pwa_generate_manifest' );

/**
 * 注册 Service Worker
 */
function zhiji_pwa_register_sw() {
	if ( ! zhiji_pwa_enabled() ) {
		return;
	}
	if ( is_admin() ) {
		return;
	}
	?>
	<script>
	(function() {
		if ('serviceWorker' in navigator) {
			window.addEventListener('load', function() {
				navigator.serviceWorker.register('<?php echo esc_url( home_url( '/?zhiji_pwa_sw=1' ) ); ?>', { updateViaCache: 'none' })
					.then(function(registration) {
						if (registration.update) { registration.update(); }
						console.log('PWA ServiceWorker registered:', registration.scope);
					})
					.catch(function(error) {
						console.log('PWA ServiceWorker registration failed:', error);
					});
			});
		}
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'zhiji_pwa_register_sw', 100 );

/**
 * 清理旧版（?zhiji_sw=1）残留 Service Worker，避免其拦截后台保存等请求
 */
function zhiji_pwa_cleanup_legacy_sw() {
	if ( is_admin() ) {
		return;
	}
	?>
	<script>
	(function() {
		if ('serviceWorker' in navigator) {
			navigator.serviceWorker.getRegistrations().then(function(regs) {
				regs.forEach(function(reg) {
					if (reg.active && reg.active.scriptURL.indexOf('zhiji_sw') !== -1) {
						reg.unregister();
					}
				});
			});
		}
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'zhiji_pwa_cleanup_legacy_sw', 99 );

/**
 * 动态生成 Service Worker JS
 * 兼容旧版 ?zhiji_sw=1 注册：PWA 未启用时返回自毁 SW，清除旧注册与缓存
 */
function zhiji_pwa_generate_sw() {
	$is_legacy = isset( $_GET['zhiji_sw'] ) && $_GET['zhiji_sw'] == 1;
	if ( ! ( isset( $_GET['zhiji_pwa_sw'] ) && $_GET['zhiji_pwa_sw'] == 1 ) && ! $is_legacy ) {
		return;
	}
	if ( ! zhiji_pwa_enabled() ) {
		// 未启用 PWA：返回自毁 SW（unregister + 清缓存），防止旧版 SW 拦截后台请求
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		echo "// zhiji PWA disabled: self-destruct legacy service worker\n";
		echo "self.addEventListener('install', function() { self.skipWaiting(); });\n";
		echo "self.addEventListener('activate', function(event) {\n";
		echo "\tevent.waitUntil(\n";
		echo "\t\tcaches.keys().then(function(names) {\n";
		echo "\t\t\treturn Promise.all(names.map(function(n) { return caches.delete(n); }));\n";
		echo "\t\t}).then(function() { return self.registration.unregister(); })\n";
		echo "\t);\n";
		echo "});\n";
		echo "self.addEventListener('fetch', function() {});\n";
		exit;
	}

	$cache_name = 'zhiji-pwa-' . md5( home_url() ) . '-' . date( 'Ymd' );
	$offline_page = home_url( '/' );

	header( 'Content-Type: application/javascript; charset=utf-8' );
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	?>
const CACHE_NAME = '<?php echo esc_js( $cache_name ); ?>';
const OFFLINE_URL = '<?php echo esc_url( $offline_page ); ?>';

// 安装：预缓存核心资源
self.addEventListener('install', function(event) {
	event.waitUntil(
		caches.open(CACHE_NAME).then(function(cache) {
			return cache.addAll([OFFLINE_URL]);
		}).then(function() {
			return self.skipWaiting();
		})
	);
});

// 激活：清理旧缓存
self.addEventListener('activate', function(event) {
	event.waitUntil(
		caches.keys().then(function(cacheNames) {
			return Promise.all(
				cacheNames.filter(function(name) {
					return name !== CACHE_NAME;
				}).map(function(name) {
					return caches.delete(name);
				})
			);
		}).then(function() {
			return self.clients.claim();
		})
	);
});

// 拦截请求：仅 GET；后台/登录/接口请求一律放行，避免破坏后台保存与上传
self.addEventListener('fetch', function(event) {
	if (event.request.method !== 'GET') return;

	var requestUrl = new URL(event.request.url);
	if (requestUrl.pathname.indexOf('/wp-admin') === 0 ||
		requestUrl.pathname.indexOf('/wp-login') === 0 ||
		requestUrl.pathname.indexOf('admin-ajax') !== -1 ||
		requestUrl.search.indexOf('zhiji_pwa') !== -1) {
		return;
	}

	event.respondWith(
		fetch(event.request).then(function(response) {
			// 缓存成功的静态响应
			if (response && response.status === 200 && response.type === 'basic') {
				const responseClone = response.clone();
				caches.open(CACHE_NAME).then(function(cache) {
					cache.put(event.request, responseClone);
				});
			}
			return response;
		}).catch(function() {
			// 网络失败，尝试缓存；无缓存时兜底回退网络，绝不返回 undefined
			return caches.match(event.request).then(function(cached) {
				if (cached) return cached;
				return fetch(event.request);
			});
		})
	);
});
	<?php
	exit;
}
add_action( 'init', 'zhiji_pwa_generate_sw' );

/**
 * PWA 安装提示按钮（移动端底部）
 */
function zhiji_pwa_install_prompt() {
	if ( ! zhiji_pwa_enabled() ) {
		return;
	}
	if ( is_admin() ) {
		return;
	}
	if ( ! (bool) zhiji_get_option( 'pwa_install_prompt', 1 ) ) {
		return;
	}
	?>
	<div id="zhiji-pwa-install" class="zhiji-pwa-install" style="display:none;">
		<div class="zhiji-pwa-install-icon">
			<img src="<?php echo esc_url( get_site_icon_url( 192 ) ?: '' ); ?>" alt="App Icon" onerror="this.style.display='none'">
		</div>
		<div class="zhiji-pwa-install-info">
			<div class="zhiji-pwa-install-title">安装到桌面</div>
			<div class="zhiji-pwa-install-desc">像 App 一样快速访问</div>
		</div>
		<button class="zhiji-pwa-install-btn" id="zhiji-pwa-install-btn">安装</button>
		<button class="zhiji-pwa-install-close" id="zhiji-pwa-install-close">&times;</button>
	</div>

	<style>
	.zhiji-pwa-install {
		position: fixed;
		bottom: 20px;
		left: 50%;
		transform: translateX(-50%);
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 12px 16px;
		background: #fff;
		border-radius: 12px;
		box-shadow: 0 4px 20px rgba(0,0,0,.15);
		z-index: 999998;
		max-width: 340px;
		width: calc(100% - 40px);
		animation: zhijiPwaSlideUp .3s ease;
	}
	@keyframes zhijiPwaSlideUp {
		from { transform: translateX(-50%) translateY(100px); opacity: 0; }
		to { transform: translateX(-50%) translateY(0); opacity: 1; }
	}
	.zhiji-pwa-install-icon img {
		width: 40px;
		height: 40px;
		border-radius: 8px;
	}
	.zhiji-pwa-install-info {
		flex: 1;
	}
	.zhiji-pwa-install-title {
		font-size: 14px;
		font-weight: 600;
		color: #333;
	}
	.zhiji-pwa-install-desc {
		font-size: 12px;
		color: #999;
	}
	.zhiji-pwa-install-btn {
		padding: 8px 16px;
		border: none;
		border-radius: 20px;
		background: linear-gradient(135deg, var(--zhiji-brand, #2e7cf6), var(--zhiji-raw-blue-deep, #1a5fd0));
		color: #fff;
		font-size: 13px;
		font-weight: 600;
		cursor: pointer;
		white-space: nowrap;
	}
	.zhiji-pwa-install-close {
		position: absolute;
		top: -8px;
		right: -8px;
		width: 24px;
		height: 24px;
		border: none;
		border-radius: 50%;
		background: #666;
		color: #fff;
		font-size: 14px;
		cursor: pointer;
		display: flex;
		align-items: center;
		justify-content: center;
	}
	@media (min-width: 768px) {
		.zhiji-pwa-install { display: none !important; }
	}
	</style>

	<script>
	(function() {
		let deferredPrompt = null;
		const installBox = document.getElementById('zhiji-pwa-install');
		const installBtn = document.getElementById('zhiji-pwa-install-btn');
		const closeBtn = document.getElementById('zhiji-pwa-install-close');

		// 检查是否已安装
		if (window.matchMedia('(display-mode: standalone)').matches) {
			return;
		}

		// 监听 beforeinstallprompt
		window.addEventListener('beforeinstallprompt', function(e) {
			e.preventDefault();
			deferredPrompt = e;
			if (installBox) installBox.style.display = 'flex';
		});

		// 点击安装
		if (installBtn) {
			installBtn.addEventListener('click', function() {
				if (deferredPrompt) {
					deferredPrompt.prompt();
					deferredPrompt.userChoice.then(function() {
						deferredPrompt = null;
						if (installBox) installBox.style.display = 'none';
					});
				}
			});
		}

		// 关闭
		if (closeBtn) {
			closeBtn.addEventListener('click', function() {
				if (installBox) installBox.style.display = 'none';
				localStorage.setItem('zhiji_pwa_install_dismissed', '1');
			});
		}

		// 已关闭过则不显示
		if (localStorage.getItem('zhiji_pwa_install_dismissed') === '1') {
			if (installBox) installBox.style.display = 'none';
		}
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'zhiji_pwa_install_prompt', 99 );

/**
 * 后台配置：PWA 分区
 */
add_action( 'after_setup_theme', function () {
	
	Zhiji_Registry::csf_section_for_legacy( 'pwa', array(
		'title'  => 'PWA 应用',
		'icon'   => 'fa fa-mobile',
		'parent' => 'zhiji_over',
		'priority' => 40,
		'fields' => array(
			array(
				'id'      => 'pwa_enabled',
				'type'    => 'switcher',
				'title'   => '启用 PWA',
				'desc'    => '让网站像原生应用一样安装到手机桌面，支持离线访问',
				'default' => false,
			),
			array(
				'id'      => 'pwa_app_name',
				'type'    => 'text',
				'title'   => '应用名称',
				'desc'    => '安装到桌面后显示的名称',
				'default' => get_bloginfo( 'name' ),
				'dependency' => array( 'pwa_enabled', '==', true ),
			),
			array(
				'id'      => 'pwa_short_name',
				'type'    => 'text',
				'title'   => '短名称',
				'desc'    => '桌面图标下方显示的短名称（建议不超过12字）',
				'default' => get_bloginfo( 'name' ),
				'dependency' => array( 'pwa_enabled', '==', true ),
			),
			array(
				'id'      => 'pwa_theme_color',
				'type'    => 'color',
				'title'   => '主题色',
				'desc'    => '浏览器地址栏和启动画面的颜色',
				'default' => '#2e7cf6',
				'dependency' => array( 'pwa_enabled', '==', true ),
			),
			array(
				'id'      => 'pwa_background_color',
				'type'    => 'color',
				'title'   => '背景色',
				'desc'    => '启动画面的背景色',
				'default' => '#ffffff',
				'dependency' => array( 'pwa_enabled', '==', true ),
			),
			array(
				'id'      => 'pwa_icon',
				'type'    => 'media',
				'title'   => '应用图标',
				'desc'    => '建议 512x512 PNG，留空使用网站图标',
				'dependency' => array( 'pwa_enabled', '==', true ),
			),
			array(
				'id'      => 'pwa_install_prompt',
				'type'    => 'switcher',
				'title'   => '显示安装提示',
				'desc'    => '移动端底部显示「安装到桌面」提示按钮',
				'default' => true,
				'dependency' => array( 'pwa_enabled', '==', true ),
			),
			array(
				'type'    => 'submessage',
				'style'   => 'info',
				'content' => 'PWA（渐进式 Web 应用）让用户可以将网站安装到手机桌面，像原生 App 一样启动，并支持离线访问。启用后需 HTTPS 环境才能正常使用 Service Worker。',
				'dependency' => array( 'pwa_enabled', '==', true ),
			),
		),
	) );
}, 20 );
