/**
 * 在线工具箱前端逻辑（纯前端，无后端请求）
 */
(function ($) {
	'use strict';

	function utfbytes(str) {
		// 统计 UTF-8 字节数
		var bytes = 0;
		for (var i = 0; i < str.length; i++) {
			var c = str.charCodeAt(i);
			if (c < 0x80) bytes += 1;
			else if (c < 0x800) bytes += 2;
			else if (c >= 0xd800 && c <= 0xdbff) { bytes += 4; i++; }
			else bytes += 3;
		}
		return bytes;
	}

	$(function () {
		var $box = $('.zhiji-toolbox');
		if (!$box.length) return;

		// tab 切换
		$box.on('click', '.zhiji-tb-tab', function () {
			var tool = $(this).data('tool');
			$box.find('.zhiji-tb-tab').removeClass('active');
			$(this).addClass('active');
			$box.find('.zhiji-tb-pane').hide();
			$box.find('.zhiji-tb-pane[data-pane="' + tool + '"]').show();
		});
		$box.find('.zhiji-tb-pane').hide();
		$box.find('.zhiji-tb-pane').first().show();

		function out(pane, text) {
			pane.find('.zhiji-tb-out').text(text);
		}

		$box.on('click', '.zhiji-tb-actions button', function () {
			var $btn = $(this);
			var act = $btn.data('act');
			var $pane = $btn.closest('.zhiji-tb-pane');
			var val = $.trim($pane.find('.zhiji-tb-input').val());

			try {
				if (act === 'json-format') {
					out($pane, JSON.stringify(JSON.parse(val), null, 2));
				} else if (act === 'json-min') {
					out($pane, JSON.stringify(JSON.parse(val)));
				} else if (act === 'b64-en') {
					out($pane, btoa(unescape(encodeURIComponent(val))));
				} else if (act === 'b64-de') {
					out($pane, decodeURIComponent(escape(atob(val))));
				} else if (act === 'url-en') {
					out($pane, encodeURIComponent(val));
				} else if (act === 'url-de') {
					out($pane, decodeURIComponent(val));
				} else if (act === 'ts-to-date') {
					var ts = parseInt(val, 10);
					if (isNaN(ts)) { out($pane, '时间戳无效'); return; }
					out($pane, new Date(ts * 1000).toLocaleString());
				} else if (act === 'date-to-ts') {
					var t = Date.parse(val.replace(/-/g, '/'));
					if (isNaN(t)) { out($pane, '日期无效'); return; }
					out($pane, String(Math.floor(t / 1000)));
				} else if (act === 'count') {
					var chars = val.length;
					var bytes = utfbytes(val);
					var noSpace = val.replace(/\s/g, '').length;
					var lines = val ? val.split(/\r\n|\r|\n/).length : 0;
					out($pane, '字符数(含空白): ' + chars + '\n不含空白: ' + noSpace + '\nUTF-8 字节: ' + bytes + '\n行数: ' + lines);
				}
			} catch (e) {
				out($pane, '处理出错：' + e.message);
			}
		});

		// 粘贴自动去尾随空格提示
		$box.on('input', '.zhiji-tb-input', function () {
			$(this).closest('.zhiji-tb-pane').find('.zhiji-tb-out').text('');
		});
	});
})(jQuery);
