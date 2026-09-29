/* 知集 · 文章 TTS 朗读（浏览器原生 SpeechSynthesis，零外部依赖）
 * 独立文件纪律：node --check 可校验；DOM 安全启动（DOMContentLoaded 兜底）。
 * 配置：window.ZHIJI_TTS_CFG = { rate: 1 }
 *
 * 设计要点：
 *  - 长文按句切分为 ≤160 字符的片段依序播报（规避 Chrome 超长 utterance 截断）；
 *  - 三态控制：播放（继续）/ 暂停 / 停止；
 *  - 离开页面自动停止（beforeunload），避免后台继续朗读。
 */
(function () {
	'use strict';

	function boot() {
		if (typeof window.speechSynthesis === 'undefined' || typeof window.SpeechSynthesisUtterance === 'undefined') {
			return; // 浏览器不支持：不注入按钮
		}
		var root = document.querySelector('.article-content');
		if (!root) { return; }

		var cfg = window.ZHIJI_TTS_CFG || {};
		var rate = parseFloat(cfg.rate) || 1;
		if (rate < 0.5) { rate = 0.5; }
		if (rate > 2) { rate = 2; }

		var chunks = [];
		var idx = 0;      // 当前播报片段下标
		var state = 'idle'; // idle | playing | paused

		/* 文本切分：按中英文句末标点断句，超长再按逗号/固定长度二段切 */
		function splitChunks(text) {
			var out = [];
			var sentences = text.replace(/\s+/g, ' ').split(/(?<=[。！？；!?;])/);
			// 兼容不支持 lookbehind 的旧浏览器：降级为整段切分
			if (sentences.length === 1 && text.length > 160) {
				sentences = text.split(/([。！？；!?;])/);
				var merged = [];
				for (var m = 0; m < sentences.length; m += 2) {
					merged.push((sentences[m] || '') + (sentences[m + 1] || ''));
				}
				sentences = merged;
			}
			var buf = '';
			for (var i = 0; i < sentences.length; i++) {
				var s = (sentences[i] || '').trim();
				if (!s) { continue; }
				if ((buf + s).length <= 160) {
					buf += s;
				} else {
					if (buf) { out.push(buf); buf = ''; }
					while (s.length > 160) {
						out.push(s.slice(0, 160));
						s = s.slice(160);
					}
					buf = s;
				}
			}
			if (buf) { out.push(buf); }
			return out;
		}

		function speakCurrent() {
			if (idx >= chunks.length) { stop(); return; }
			var u = new SpeechSynthesisUtterance(chunks[idx]);
			u.lang = 'zh-CN';
			u.rate = rate;
			u.onend = function () {
				if (state !== 'playing') { return; }
				idx++;
				speakCurrent();
			};
			u.onerror = function () { stop(); };
			window.speechSynthesis.speak(u);
		}

		function play() {
			if (state === 'paused') {
				window.speechSynthesis.resume();
				state = 'playing';
				render();
				return;
			}
			if (state === 'playing') { return; }
			// idle：从头开始（或已播完重开）
			window.speechSynthesis.cancel();
			if (!chunks.length) {
				chunks = splitChunks(root.innerText || '');
			}
			if (!chunks.length) { return; }
			if (idx >= chunks.length) { idx = 0; }
			state = 'playing';
			speakCurrent();
			render();
		}

		function pause() {
			if (state !== 'playing') { return; }
			window.speechSynthesis.pause();
			state = 'paused';
			render();
		}

		function stop() {
			state = 'idle';
			idx = 0;
			chunks = [];
			window.speechSynthesis.cancel();
			render();
		}

		/* UI：文章正文前浮动按钮条 */
		var bar = document.createElement('div');
		bar.className = 'zhiji-tts-bar';
		bar.innerHTML =
			'<button type="button" class="zhiji-tts-play" title="朗读本文">' +
			'<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="vertical-align:-3px;margin-right:4px"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3a4.5 4.5 0 0 0-2.5-4v8a4.5 4.5 0 0 0 2.5-4z"/></svg>' +
			'<span class="zhiji-tts-label">朗读本文</span>' +
			'</button>' +
			'<button type="button" class="zhiji-tts-pause" title="暂停" style="display:none">暂停</button>' +
			'<button type="button" class="zhiji-tts-stop" title="停止" style="display:none">停止</button>' +
			'<span class="zhiji-tts-status"></span>';

		var btnPlay = bar.querySelector('.zhiji-tts-play');
		var btnPause = bar.querySelector('.zhiji-tts-pause');
		var btnStop = bar.querySelector('.zhiji-tts-stop');
		var status = bar.querySelector('.zhiji-tts-status');

		btnPlay.addEventListener('click', function () {
			if (state === 'playing') { pause(); } else { play(); }
		});
		btnPause.addEventListener('click', pause);
		btnStop.addEventListener('click', stop);

		function render() {
			var label = bar.querySelector('.zhiji-tts-label');
			if (state === 'playing') {
				btnPlay.title = '暂停';
				if (label) { label.textContent = '正在朗读…（点击暂停）'; }
				btnPause.style.display = '';
				btnStop.style.display = '';
				status.textContent = (idx + 1) + '/' + chunks.length;
			} else if (state === 'paused') {
				btnPlay.title = '继续朗读';
				if (label) { label.textContent = '已暂停（点击继续）'; }
				btnPause.style.display = 'none';
				btnStop.style.display = '';
				status.textContent = (idx + 1) + '/' + chunks.length;
			} else {
				btnPlay.title = '朗读本文';
				if (label) { label.textContent = '朗读本文'; }
				btnPause.style.display = 'none';
				btnStop.style.display = 'none';
				status.textContent = '';
			}
		}

		root.parentNode.insertBefore(bar, root);
		bar.style.cssText = 'margin:0 0 12px;display:flex;align-items:center;gap:8px';
		btnPlay.style.cssText = 'cursor:pointer;border:1px solid #e8533f;color:#e8533f;background:#fff;border-radius:6px;padding:5px 14px;font-size:13px';
		btnPause.style.cssText = 'cursor:pointer;border:1px solid #ddd;background:#fff;border-radius:6px;padding:5px 10px;font-size:13px';
		btnStop.style.cssText = 'cursor:pointer;border:1px solid #ddd;background:#fff;border-radius:6px;padding:5px 10px;font-size:13px';
		status.style.cssText = 'font-size:12px;color:#999';

		window.addEventListener('beforeunload', function () {
			try { window.speechSynthesis.cancel(); } catch (e) { /* noop */ }
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
