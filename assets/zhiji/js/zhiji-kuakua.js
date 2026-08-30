/**
 * 知集 · 夸一夸弹窗 (Kuakua) 控制脚本
 * 依赖：页面已输出 #zhijiKuakuaBtn / #zhijiKuakuaBox，并注入
 *   window.zhijiKuakuaPhrases (string[]) 与 window.zhijiKuakuaAuto (bool)
 * 暴露：window.zhijiKuakua.open() / .close() / .show()
 */
(function () {
	'use strict';

	var btn, box, overlay, titleEl, textEl, closeBtn, againBtn;
	var phrases = Array.isArray(window.zhijiKuakuaPhrases) && window.zhijiKuakuaPhrases.length
		? window.zhijiKuakuaPhrases
		: ['你今天也超棒的！'];

	function pick() {
		return phrases[Math.floor(Math.random() * phrases.length)];
	}

	function open() {
		if (!box) { return; }
		if (textEl) { textEl.textContent = pick(); }
		box.classList.add('show');
		box.setAttribute('aria-hidden', 'false');
		document.body.classList.add('zhiji-kuakua-open');
	}

	function close() {
		if (!box) { return; }
		box.classList.remove('show');
		box.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('zhiji-kuakua-open');
	}

	function bind() {
		btn = document.getElementById('zhijiKuakuaBtn');
		box = document.getElementById('zhijiKuakuaBox');
		if (!btn || !box) { return; }
		titleEl = box.querySelector('.zhiji-kuakua-title');
		textEl = box.querySelector('.zhiji-kuakua-text');
		closeBtn = box.querySelector('.zhiji-kuakua-close');
		againBtn = box.querySelector('.zhiji-kuakua-again');
		overlay = box.querySelector('.zhiji-kuakua-overlay');

		btn.addEventListener('click', open);
		if (closeBtn) { closeBtn.addEventListener('click', close); }
		if (againBtn) { againBtn.addEventListener('click', open); }
		if (overlay) { overlay.addEventListener('click', close); }
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') { close(); }
		});

		// 首访自动弹一次（localStorage 去重）
		if (window.zhijiKuakuaAuto && !localStorage.getItem('zhiji_kuakua_done')) {
			localStorage.setItem('zhiji_kuakua_done', '1');
			setTimeout(open, 1200);
		}
	}

	window.zhijiKuakua = { open: open, close: close, show: open };

	if (document.readyState !== 'loading') {
		bind();
	} else {
		document.addEventListener('DOMContentLoaded', bind);
	}
})();
