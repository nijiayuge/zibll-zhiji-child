/**
 * 知集子主题 · 前台脚本（zhiji.js）
 * ---------------------------------------------------------------------
 * 由 inc/Functions/Assets.php 入队，依赖父主题已注册的本地 jquery，
 * 并在页脚加载。通过 wp_localize_script 注入的前端配置在 ZHIJI_CONFIG 中。
 *
 * 父主题（子比主题）已在 window._win 中提供大量全局 API
 * （如 zib_ajax、notyf、refresh_modal 等），二次开发时请优先复用，
 * 避免重复造轮子。
 */
(function ($) {
	'use strict';

	/**
	 * zhiji_confetti —— 轻量全屏彩带庆祝动效（Confetti）
	 * ---------------------------------------------------------------------
	 * 纯 Canvas 实现，无任何第三方依赖；挂载到 window 供全局调用。
	 * 由「邮箱优惠码」模块在领码成功时触发（也可在其他庆祝场景复用）。
	 *
	 * @param {Object} opts 可选参数：
	 *   - count    粒子数量（默认 120）
	 *   - duration 动画持续毫秒（默认 2600）
	 *   - colors   彩带颜色数组（默认取父主题主色系 + 明亮点缀色）
	 *   - origin   起始位置 { x: 0~1, y: 0~1 }，默认顶部居中
	 */
	window.zhiji_confetti = function (opts) {
		opts = opts || {};
		var count = opts.count || 120;
		var duration = opts.duration || (opts.cannons ? 3200 : 2600);
		// 双礼炮模式：左右底部炮口向中间上方喷射彩带（行业常见「礼炮」庆祝效果）
		var cannons = !!opts.cannons;
		var colors = opts.colors || [
			'#f04494', '#ffd166', '#06d6a0', '#118ab2', '#f77f2d', '#6a5cff'
		];
		var ox = (opts.origin && opts.origin.x) || 0.5;
		var oy = (opts.origin && opts.origin.y) || -0.08;

		var canvas = document.createElement('canvas');
		canvas.setAttribute('aria-hidden', 'true');
		canvas.style.cssText =
			'position:fixed;top:0;left:0;width:100%;height:100%;' +
			'pointer-events:none;z-index:999999;';
		document.body.appendChild(canvas);

		var ctx = canvas.getContext('2d');
		var W = canvas.width = window.innerWidth;
		var H = canvas.height = window.innerHeight;
		var cx = W * ox;
		var cy = Math.max(0, H * oy);

		var parts = [];
		var start = Date.now();

		// 双礼炮：喷射期（前 900ms）每帧从左右下方炮口向中间上方补发粒子，模拟礼炮对射
		function cannonBurst() {
			var origins = [
				{ x: W * 0.08, y: H * 0.85, angle: 60 },   // 左下炮口 → 向右上喷向中间
				{ x: W * 0.92, y: H * 0.85, angle: 120 }   // 右下炮口 → 向左上喷向中间
			];
			var per = Math.ceil(count / 2 / 20);
			for (var c = 0; c < origins.length; c++) {
				var o = origins[c];
				for (var k = 0; k < per; k++) {
					var ang = (o.angle + (Math.random() - 0.5) * 52) * Math.PI / 180;
					var speed = 11 + Math.random() * 9;
					parts.push({
						x: o.x + (Math.random() - 0.5) * 12,
						y: o.y + (Math.random() - 0.5) * 12,
						w: 5 + Math.random() * 6,
						h: 8 + Math.random() * 8,
						r: 3 + Math.random() * 3.5,
						c: colors[Math.floor(Math.random() * colors.length)],
						vx: Math.cos(ang) * speed,
						vy: -Math.sin(ang) * speed,   // 负值 = 向上（canvas y 轴向下）
						rot: Math.random() * Math.PI,
						vr: -0.25 + Math.random() * 0.5,
						circle: Math.random() > 0.5
					});
				}
			}
		}

		// 默认散花模式：顶部居中向下飘落（邮箱领取等场景复用）
		if (!cannons) {
			for (var i = 0; i < count; i++) {
				parts.push({
					x: cx + (Math.random() - 0.5) * W * 0.4,
					y: cy + Math.random() * 60,
					w: 5 + Math.random() * 7,       // 矩形宽（彩带）
					h: 8 + Math.random() * 8,       // 矩形高（彩带）
					r: 3 + Math.random() * 4,       // 圆形半径（纸屑）
					c: colors[i % colors.length],
					vy: 2.5 + Math.random() * 4.5,  // 下落速度
					vx: -3 + Math.random() * 6,     // 水平飘动
					rot: Math.random() * Math.PI,   // 初始旋转
					vr: -0.2 + Math.random() * 0.4, // 旋转速度
					circle: Math.random() > 0.55    // 形状：圆/矩形混合
				});
			}
		}

		function frame() {
			ctx.clearRect(0, 0, W, H);
			var alive = false;
			var elapsed = Date.now() - start;
			// 礼炮喷射期：前 900ms 持续补发粒子
			if (cannons && elapsed < 900) { cannonBurst(); }
			for (var j = 0; j < parts.length; j++) {
				var p = parts[j];
				p.x += p.vx;
				p.y += p.vy;
				p.vy += cannons ? 0.16 : 0.04;  // 重力（礼炮粒子更快回落）
				if (cannons) { p.vx *= 0.99; }  // 空气阻力，模拟喷射减速
				p.rot += p.vr;
				// 水平回弹，避免太快飘出屏幕
				if (p.x < -20 || p.x > W + 20) p.vx *= -0.9;
				if (p.y < H + 40) alive = true;

				ctx.save();
				ctx.translate(p.x, p.y);
				ctx.rotate(p.rot);
				ctx.fillStyle = p.c;
				if (p.circle) {
					ctx.beginPath();
					ctx.arc(0, 0, p.r, 0, Math.PI * 2);
					ctx.fill();
				} else {
					ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
				}
				ctx.restore();
			}
			if (alive && Date.now() - start < duration) {
				requestAnimationFrame(frame);
			} else {
				if (canvas.parentNode) canvas.parentNode.removeChild(canvas);
			}
		}
		requestAnimationFrame(frame);
	};

	$(function () {
		var $win = $(window);

		// 滚动超过 600px 时给 body 加 zhiji-scrolled 类
		$win.on('scroll', function () {
			if ($win.scrollTop() > 600) {
				$('body').addClass('zhiji-scrolled');
			} else {
				$('body').removeClass('zhiji-scrolled');
			}
		});
		// 头像懒加载兜底：zibll 懒加载将 src 置为默认占位图、真实头像在 data-src，
		// lazysizes 因 src 已有值而不替换，导致新上传头像不显示；这里主动补齐
		setTimeout(function () {
			$('img.avatar[data-src]').each(function () {
				var $i = $(this);
				var ds = $i.attr('data-src');
				if (ds && $i.attr('src') !== ds) {
					$i.attr('src', ds).addClass('ls-is-cached');
				}
			});
		}, 300);

		// 控制台欢迎信息（仅调试用）
		if (window.console && window.console.log) {
			console.log(
				'%cZhiji Child Theme %cloaded',
				'color:#ff5f33;font-weight:bold;',
				'color:#666;'
			);
		}
	});

	/**
	 * 刷新父主题消息角标（铃铛未读数）。
	 * 父主题只在页面加载 / 打开消息中心时更新角标，业务 AJAX 产生新站内消息后
	 * 不会自动刷新；这里通过子主题接口拉取未读数并更新全部 <badge[msg-cat]>。
	 * @param {string} nonce 抽奖 nonce（复用）
	 * @param {string} url   AJAX 地址（缺省用全局 ZHIJI_LOTTERY_AJAX）
	 */
	window.zhijiRefreshMsgBadge = function (nonce, url) {
		var ajaxUrl = url || (window.ZHIJI_AJAX || window.ZHIJI_LOTTERY_AJAX || '');
		if (!ajaxUrl) return;
		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: { action: 'zhiji_msg_counts', nonce: nonce || '' },
			dataType: 'json',
			success: function (counts) {
				if (!counts || typeof counts !== 'object') return;
				$.each(counts, function (cat, count) {
					var $badge = $('badge[msg-cat="' + cat + '"]');
					if (count > 0) {
						if ($badge.length) {
							$badge.html(count);
						} else if (cat === 'all') {
							// 父主题 count=0 时铃铛不渲染角标，这里为铃铛补建（同 class="top" 保证样式）
							var $bell = $('.msg-news-icon');
							if ($bell.length) {
								$bell.append('<badge class="top" msg-cat="all">' + count + '</badge>');
							}
						}
					} else {
						$badge.remove();
					}
				});
			}
		});
	};
})(jQuery);
