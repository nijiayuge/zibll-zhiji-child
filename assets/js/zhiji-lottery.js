/* ============================================================
 * 知集·大抽奖 前端交互层（jQuery 适配，IIFE 包裹避免命名污染）
 * 依赖：jQuery、wp_localize_script 注入的 ZHIJI_LOTTERY_AJAX（admin-ajax.php 地址）
 *
 * 关键修复（对应「点击抽奖弹窗闪烁/抖动」）：
 *  1) 弹窗 DOM 在短码中被一次性渲染（opacity:0 + visibility:hidden），
 *     DOMready 时统一 append 到 body 一次（脱离可能被 transform 的祖先容器，
 *     确保 position:fixed 相对视口，消除「抖动」），之后「打开」只切换 .is-open 类，
 *     不再每次点击都移动节点（旧实现 appendTo 每次点击触发重排 = 闪烁根因）。
 *  2) 打开带锁（.is-open 守卫）防重复触发；抽奖带加载锁（.is-drawing + data 锁）防并发 Ajax。
 *  3) 结果区用 opacity/visibility 过渡，不切换 display，避免重排闪烁。
 *  4) ESC / 遮罩 / 关闭按钮 三种关闭；打开时锁定 body 滚动。
 * ============================================================ */
(function ($) {
	'use strict';

	/* 初始绘制静态转盘（读取 canvas 上的 data-* 配置） */
	function drawWheel(cv) {
		var sz = parseInt(cv.getAttribute('data-zhiji-size'), 10) || 240;
		var prizes, colors;
		try { prizes = JSON.parse(cv.getAttribute('data-zhiji-prizes') || '[]'); } catch (e) { prizes = []; }
		try { colors = JSON.parse(cv.getAttribute('data-zhiji-colors') || '[]'); } catch (e) { colors = []; }
		var n = prizes.length;
		if (!n) { return; }
		var seg = Math.PI * 2 / n;
		var ctx = cv.getContext('2d');

		function draw(sa) {
			var x = ctx, S = sz, CX = sz / 2, CY = sz / 2, R = sz / 2 - 4, P = prizes, CL = colors, N = n, SG = seg;
			x.clearRect(0, 0, S, S);
			for (var i = 0; i < N; i++) {
				var a1 = sa + i * SG, a2 = a1 + SG;
				x.beginPath();
				x.moveTo(CX, CY);
				x.arc(CX, CY, R, a1, a2);
				x.closePath();
				x.fillStyle = CL[i % CL.length];
				x.fill();
				x.strokeStyle = 'rgba(255,255,255,.4)';
				x.lineWidth = 1.5;
				x.stroke();
				// 文字：标准 translate(圆心) → rotate(中线角) → fillText
				x.save();
				x.translate(CX, CY);
				x.rotate(a1 + SG / 2);
				x.fillStyle = '#fff';
				x.font = "bold 13px 'Microsoft YaHei', sans-serif";
				x.textAlign = 'right';
				x.textBaseline = 'middle';
				x.shadowColor = 'rgba(0,0,0,.35)';
				x.shadowBlur = 2;
				x.shadowOffsetX = 1;
				x.shadowOffsetY = 1;
				var t = P[i];
				x.fillText(t.length > 5 ? t.slice(0, 5) + '…' : t, R - 18, 0);
				x.shadowColor = 'transparent';
				x.restore();
			}
			// 中心圆
			x.beginPath();
			x.arc(CX, CY, R * 0.22, 0, Math.PI * 2);
			x.fillStyle = '#fff';
			x.fill();
			x.strokeStyle = '#e0e0e0';
			x.lineWidth = 1;
			x.stroke();
		}

		cv._zhiji = { draw: draw, n: n, seg: seg };
		draw(-Math.PI / 2);
	}

	/* 打开 / 关闭（带锁防抖） */
	function openModal($m) {
		if ($m.hasClass('is-open')) { return; }       // 已开则忽略，防重复触发
		$m.addClass('is-open');
		$('body').addClass('zhiji-lottery-noscroll'); // 锁定背景滚动
		var cv = $m.find('canvas')[0];
		if (cv && cv._zhiji) { cv._zhiji.draw(-Math.PI / 2); } // 重绘静态盘，清掉残留旋转角
	}

	function closeModal($m) {
		if (!$m.length) { return; }
		$m.removeClass('is-open');
		$('body').removeClass('zhiji-lottery-noscroll');
	}

	/* 开奖动画（requestAnimationFrame 逐帧重绘，避开 CSS transform-origin 歧义） */
	function spin($m, res) {
		var $res = $m.find('.zhiji-lottery-result');
		var $btn = $m.find('.zhiji-lottery-draw-btn');
		var cv = $m.find('canvas')[0];
		if (!cv || !cv._zhiji) { showResult($m, res, $res, $btn); return; }
		var z = cv._zhiji, SG = z.seg;
		var targetIdx = res.index;
		var targetMid = -Math.PI / 2 - (targetIdx * SG + SG / 2);
		var spins = Math.PI * 2 * (5 + Math.random());
		var startA = -Math.PI / 2, endA = targetMid - spins, dur = 4500, t0 = null;
		function frame(t) {
			if (!t0) { t0 = t; }
			var p = Math.min((t - t0) / dur, 1), ease = 1 - Math.pow(1 - p, 3);
			z.draw(startA + (endA - startA) * ease);
		if (p < 1) { requestAnimationFrame(frame); }
		else { z.draw(targetMid); showResult($m, res); }
		}
		requestAnimationFrame(frame);
	}

	/* 依据后端返回的最新状态重建按钮区（积分兑换修复核心：免费→兑换→今日已抽完，免刷新） */
	function renderActions($m, st) {
		var nonce = $m.data('nonce') || '';
		var html = '';
		if (!st.done) {
			html += '<a href="javascript:;" class="zhiji-lottery-draw-btn zhiji-btn zhiji-btn-blue" data-extra="0" data-nonce="' + nonce + '">🎲 开始抽奖</a>';
		} else if (st.can_extra) {
			html += '<a href="javascript:;" class="zhiji-lottery-draw-btn zhiji-btn zhiji-btn-yellow" data-extra="1" data-nonce="' + nonce + '">💰 积分兑换（' + st.extra_cost + '/次·剩' + st.extra_left + '次）</a>';
		}
		if (st.can_share) {
			html += '<a href="javascript:;" class="zhiji-lottery-share-btn zhiji-btn zhiji-btn-green" data-nonce="' + nonce + '">🔗 分享得次数（剩' + st.share_left + '次）</a>';
		}
		$m.find('.zhiji-lottery-actions').html(html);
	}

	function showResult($m, res) {
		var $res = $m.find('.zhiji-lottery-result');
		var $msg = $m.find('.zhiji-lottery-msg');
		$res.html(
			'<div class="zhiji-lottery-result-inner">' +
				'<div class="zhiji-lottery-result-emoji">🎉</div>' +
				'<div class="zhiji-lottery-result-title">' + (res.msg || '') + '</div>' +
				'<div class="zhiji-lottery-result-sub">累计 ' + res.total + ' 次 / 中奖 ' + res.wins + ' 次</div>' +
			'</div>'
		).addClass('show');
		// 动态重建按钮区：免费抽完立即出现积分兑换；兑换后仍可继续兑换（均无需刷新页面）
		if (res.state) {
			renderActions($m, res.state);
			if (res.state.done && res.state.can_extra) {
				$msg.text('今日免费次数已用完 · 可积分兑换再抽').removeClass('is-error');
			} else if (res.state.done) {
				$msg.text('今日已抽完，明天再来吧').removeClass('is-error');
			} else {
				$msg.text('今日免费剩 ' + res.state.free_left + ' 次').removeClass('is-error');
			}
		}
		// 刷新「最近抽奖记录」看板（全局记录，抽奖后无需刷新页面即时更新）
		if (typeof res.log_html !== 'undefined') {
			var $log = $m.find('.zhiji-lottery-log');
			if (res.log_html) {
				if ($log.length) { $log.replaceWith(res.log_html); }
				else { $m.append(res.log_html); }
			} else {
				$log.remove();
			}
		}
		$m.removeData('drawing');
	}

	$(function () {
		// 初始化：绘制静态盘 + 一次性移入 body（脱离 transform 祖先，固定相对视口）
		$('.zhiji-lottery-modal').each(function () {
			var $m = $(this);
			var cv = $m.find('canvas')[0];
			if (cv && !cv._zhiji) { drawWheel(cv); }
			if (!$m.data('zhijiMoved')) {
				$m.appendTo(document.body);
				$m.data('zhijiMoved', 1);
			}
		});

		// 首页转盘触发器：同样绘制 Canvas 转盘（点击沿用 .zhiji-lottery-open-btn 打开弹窗）
		$('.zhiji-lottery-trigger-wheel').each(function () {
			var cv = this;
			if (!cv._zhiji) { drawWheel(cv); }
		});

		// 打开弹窗（防抖）
		$(document).on('click', '.zhiji-lottery-open-btn', function (e) {
			e.preventDefault();
			var id = $(this).attr('data-zhiji-modal');
			var $m = id ? $('#' + id) : $('.zhiji-lottery-modal').first();
			if (!$m.length) { return; }
			openModal($m);
		});

		// 关闭：按钮 / 遮罩 / ESC
		$(document).on('click', '.zhiji-lottery-close', function (e) {
			e.preventDefault();
			closeModal($(this).closest('.zhiji-lottery-modal'));
		});
		$(document).on('click', '.zhiji-lottery-modal', function (e) {
			if (e.target === this) { closeModal($(this)); }
		});
		$(document).on('keydown', function (e) {
			if (e.key === 'Escape' || e.keyCode === 27) {
				var $o = $('.zhiji-lottery-modal.is-open');
				if ($o.length) { closeModal($o); }
			}
		});

	// 抽奖（加载锁 + 防并发 Ajax）
	$(document).on('click', '.zhiji-lottery-draw-btn', function () {
		var $btn = $(this);
		if ($btn.hasClass('is-drawing')) { return; }            // 加载锁
		var $m = $btn.closest('.zhiji-lottery-modal');
		if ($m.data('drawing')) { return; }                    // 二次保险
		$btn.addClass('is-drawing');
		$m.data('drawing', 1);
		// 注意：data-extra 是字符串，"0" 在 JS 里为 truthy，必须用 parseInt 取数值
		// 否则免费抽会被误判为积分兑换，导致免费次数不减、且兑换分支报错使记录不刷新。
		var extra = parseInt($btn.attr('data-extra'), 10) || 0;
		var $msg = $m.find('.zhiji-lottery-msg');
		var $res = $m.find('.zhiji-lottery-result');
		$res.removeClass('show');
		$.ajax({
			url: ZHIJI_LOTTERY_AJAX,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'zhiji_lottery_draw',
				nonce: $btn.attr('data-nonce'),
				extra: extra ? 1 : 0
			}
		}).done(function (res) {
				if (!res || res.error) {
					$msg.text(res ? res.msg : '请求失败').addClass('is-error');
					$btn.removeClass('is-drawing');
					$m.removeData('drawing');
					return;
				}
				$msg.text(res.msg).removeClass('is-error');
				spin($m, res);
			}).fail(function () {
				$msg.text('网络异常，请重试').addClass('is-error');
				$btn.removeClass('is-drawing');
				$m.removeData('drawing');
			});
		});

		// 分享得次数
		$(document).on('click', '.zhiji-lottery-share-btn', function (e) {
			e.preventDefault();
			var $btn = $(this);
			if ($btn.hasClass('is-drawing')) { return; }
			$btn.addClass('is-drawing');
			var $m = $btn.closest('.zhiji-lottery-modal');
			$.ajax({
				url: ZHIJI_LOTTERY_AJAX,
				type: 'POST',
				dataType: 'json',
				data: { action: 'zhiji_lottery_share', nonce: $btn.attr('data-nonce') }
			}).done(function (r) {
				$m.find('.zhiji-lottery-msg').text(r && r.msg ? r.msg : '分享成功');
				if (!r || r.error) { $btn.removeClass('is-drawing'); return; }
				setTimeout(function () { location.reload(); }, 800);
			}).fail(function () {
				$m.find('.zhiji-lottery-msg').text('网络异常，请重试').addClass('is-error');
				$btn.removeClass('is-drawing');
			});
		});
	});
})(jQuery);
