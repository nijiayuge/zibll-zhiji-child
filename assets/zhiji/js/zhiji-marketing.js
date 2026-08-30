/**
 * 知集营销交互：多人拼团 + 砍价（邀好友 / 自助）
 * 依赖：jQuery、wp_localize_script 注入的 zhijiMarketing 全局对象。
 */
(function ($) {
	'use strict';

	if (typeof zhijiMarketing === 'undefined') {
		return;
	}

	var M = zhijiMarketing;

	function post(action, data) {
		data = data || {};
		data.action = action;
		data.nonce = M.nonce;
		return $.ajax({
			url: M.ajaxurl,
			method: 'POST',
			dataType: 'json',
			data: data
		});
	}

	function toast(msg, isError) {
		var box = $('<div class="zhiji-toast' + (isError ? ' err' : '') + '">' + msg + '</div>');
		$('body').append(box);
		setTimeout(function () { box.fadeOut(400, function () { box.remove(); }); }, 2600);
	}

	/* ── 通用弹窗系统（内联关键布局，避免被主题 CSS 覆盖导致错位）── */
	var zhijiModalActive = null;

	function zhijiCloseModal() {
		if (!zhijiModalActive) { return; }
		var m = zhijiModalActive;
		zhijiModalActive = null;
		m.classList.remove('show');
		setTimeout(function () { if (m && m.parentNode) { m.parentNode.removeChild(m); } }, 260);
	}

	/**
	 * 打开一个弹窗。innerHtml 为弹窗卡片内部 HTML。
	 * opts.closable: 是否允许点击遮罩/按钮关闭（加载弹窗传 false 防误关）。
	 * 关键：遮罩布局用内联样式写死，确保始终全屏居中，不受外部 CSS 影响。
	 */
	function zhijiOpenModal(innerHtml, opts) {
		zhijiCloseModal(); // 先关掉可能存在的弹窗，避免重复
		opts = opts || {};
		var mask = document.createElement('div');
		mask.className = 'zhiji-modal-mask';
		// 双保险：内联样式全部 !important，且挂到 documentElement（而非 body），
		// 规避 body/祖先的 transform/filter 让 fixed 参照系偏移导致弹窗跑到左上角（8-27 13:11 加固回归修复）
		mask.style.cssText =
			'position:fixed !important;top:0 !important;left:0 !important;right:0 !important;bottom:0 !important;' +
			'display:flex !important;align-items:center !important;justify-content:center !important;' +
			'width:100% !important;height:100% !important;' +
			'z-index:999999 !important;background:rgba(0,0,0,.5) !important;';
		mask.innerHTML = '<div class="zhiji-modal' + (opts.closable ? ' zhiji-modal-closable' : '') + '">' + innerHtml + '</div>';
		document.documentElement.appendChild(mask);
		// 强制重排后再加 show，确保过渡动画触发
		void mask.offsetWidth;
		mask.classList.add('show');
		zhijiModalActive = mask;
		if (opts.closable) {
			mask.addEventListener('click', function (e) { if (e.target === mask) { zhijiCloseModal(); } });
		}
		return mask;
	}

	/** 加载弹窗：转圈 + “兑换请稍后” */
	function showLoadingModal(text) {
		text = text || '兑换请稍后…';
		return zhijiOpenModal(
			'<div class="zhiji-modal-spinner"></div>' +
			'<div class="zhiji-modal-loading-text">' + text + '</div>',
			{ closable: false }
		);
	}

	/** 兑换成功价格弹窗 */
	function showSuccessModal(d) {
		d = d || {};
		var type = d.type || 'coupon';
		var amount = d.amount || 0;
		var scope = d.scope || '';
		var priceLabel;
		if (type === 'balance') {
			priceLabel = '余额 +' + amount + ' 元';
		} else if (type === 'download') {
			priceLabel = amount + ' 元资源券（' + scope + '）';
		} else {
			priceLabel = amount + ' 元优惠券（' + scope + '）';
		}
		var html =
			'<div class="zhiji-modal-icon">✓</div>' +
			'<div class="zhiji-modal-title">兑换成功</div>' +
			'<div class="zhiji-modal-row"><span>奖励</span><b>' + (d.title || '') + '</b></div>' +
			'<div class="zhiji-modal-row"><span>花费积分</span><b>' + (d.cost || 0) + '</b></div>' +
			'<div class="zhiji-modal-price">' + priceLabel + '</div>' +
			'<button class="zhiji-modal-btn but jb-blue">确定</button>';
		var mask = zhijiOpenModal(html, { closable: true });
		mask.querySelector('.zhiji-modal-btn').addEventListener('click', zhijiCloseModal);
		return mask;
	}

	$(function () {
		// ── 多人拼团 ──
		$(document).on('click', '.zhiji-gb-open', function (e) {
			e.preventDefault();
			var pid = $(this).data('product') || M.productId;
			var btn = $(this);
			btn.prop('disabled', true);
			post('zhiji_gb_open', { product_id: pid }).done(function (r) {
				if (r.success) {
					var url = r.data.share_url;
					var line = '<div class="zhiji-gb-share">分享链接（发给好友参团）：<br><code>' + url + '</code> ' +
						'<button class="zhiji-gb-copy but" data-share="' + url + '">复制</button></div>';
					btn.closest('.zhiji-gb-box').find('.zhiji-gb-result').html(line);
					toast(r.data.msg);
				} else {
					toast(r.data.msg || '操作失败', true);
				}
			}).fail(function () {
				toast('网络错误', true);
			}).always(function () { btn.prop('disabled', false); });
		});

		$(document).on('click', '.zhiji-gb-join', function (e) {
			e.preventDefault();
			var gid = $(this).data('group');
			var btn = $(this);
			btn.prop('disabled', true);
			post('zhiji_gb_join', { group_id: gid }).done(function (r) {
				if (r.success) {
					if (r.data.status === 'success') {
						var codes = r.data.coupons || {};
						var txt = '拼团成功！优惠码：' + Object.values(codes).join('、');
						btn.closest('.zhiji-gb-item').html('<span class="zhiji-gb-done">' + txt + '</span>');
					} else {
						btn.text('已参团（还差' + r.data.remaining + '人）');
					}
					toast(r.data.msg);
				} else {
					toast(r.data.msg || '操作失败', true);
				}
			}).fail(function () {
				toast('网络错误', true);
			}).always(function () { btn.prop('disabled', false); });
		});

		$(document).on('click', '.zhiji-gb-copy, .zhiji-bg-copy', function (e) {
			e.preventDefault();
			var url = $(this).data('share');
			if (navigator.clipboard) {
				navigator.clipboard.writeText(url).then(function () { toast(M.i18n.copyOk); });
			} else {
				toast(url);
			}
		});

		// ── 砍价 ──
		$(document).on('click', '.zhiji-bg-open', function (e) {
			e.preventDefault();
			var pid = $(this).data('product') || M.productId;
			var btn = $(this);
			btn.prop('disabled', true);
			post('zhiji_bg_open', { product_id: pid }).done(function (r) {
				if (r.success) {
					location.reload();
				} else {
					toast(r.data.msg || '操作失败', true);
				}
			}).fail(function () { toast('网络错误', true); })
				.always(function () { btn.prop('disabled', false); });
		});

		$(document).on('click', '.zhiji-bg-self', function (e) {
			e.preventDefault();
			var gid = $(this).data('bargain');
			var btn = $(this);
			btn.prop('disabled', true);
			post('zhiji_bg_self', { bargain_id: gid }).done(function (r) {
				if (r.success) {
					toast(r.data.msg);
					if (r.data.status === 'success') {
						btn.closest('.zhiji-bg-card').html('<div class="zhiji-bg-done">砍到底价！优惠码：' + r.data.coupon + '</div>');
					} else {
						btn.closest('.zhiji-bg-card').find('.zhiji-bg-cur').text('¥' + r.data.price);
						btn.prop('disabled', true).text('今日已砍，明天再来');
					}
				} else {
					toast(r.data.msg || '操作失败', true);
					btn.prop('disabled', false);
				}
			}).fail(function () { toast('网络错误', true); btn.prop('disabled', false); });
		});

		$(document).on('click', '.zhiji-bg-help', function (e) {
			e.preventDefault();
			var gid = $(this).data('bargain');
			var btn = $(this);
			btn.prop('disabled', true);
			post('zhiji_bg_help', { bargain_id: gid }).done(function (r) {
				if (r.success) {
					toast(r.data.msg);
					if (r.data.status === 'success') {
						btn.closest('.zhiji-bg-help-box').html('<div class="zhiji-bg-done">帮砍成功！发起人已获优惠码：' + r.data.coupon + '</div>');
					} else {
						btn.closest('.zhiji-bg-help-box').html('<div class="zhiji-bg-helped">你帮TA砍下了 ¥' + (r.data.price ? '' : '') + '，当前价 ¥' + r.data.price + '</div>');
					}
				} else {
					toast(r.data.msg || '操作失败', true);
					btn.prop('disabled', false);
				}
		}).fail(function () { toast('网络错误', true); btn.prop('disabled', false); });
	});

		// ── 积分商城兑换 ──
		$(document).on('click', '.zhiji-redeem', function (e) {
			e.preventDefault();
			var idx = $(this).data('idx');
			var btn = $(this);
			if (btn.prop('disabled')) {
				return;
			}
			btn.prop('disabled', true).text('兑换中…');
			// 先弹加载弹窗（转圈 + “兑换请稍后”）
			showLoadingModal('兑换请稍后…');
			post('zhiji_redeem', { idx: idx }).done(function (r) {
				if (r.success) {
					// 关闭加载弹窗，再展示成功弹窗（zhijiOpenModal 内部会先关旧弹窗）
					showSuccessModal(r.data || {});
					btn.closest('.zhiji-mall-card').addClass('zhiji-mall-done');
					btn.replaceWith('<span class="zhiji-mall-btn but jb-gray">已兑换</span>');
				} else {
					zhijiCloseModal(); // 关闭加载弹窗
					toast(r.data.msg || '兑换失败', true);
					btn.prop('disabled', false).text('立即兑换');
				}
			}).fail(function () {
				zhijiCloseModal();
				toast('网络错误', true);
				btn.prop('disabled', false).text('立即兑换');
			});
		});
	});
})(jQuery);
