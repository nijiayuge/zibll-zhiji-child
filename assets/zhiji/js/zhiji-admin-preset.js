/**
 * 后台表单字段快速预设（通用）
 * 任意 CSF group / 重复字段里，带 class "zhiji-preset-fill" 的按钮，
 * 点击后读取 data-fill（JSON：字段名 => 值）自动填充「当前项」对应字段。
 * 用法（PHP 渲染按钮）：
 *   <button type="button" class="button button-small zhiji-preset-fill"
 *           data-fill='{"title":"示例","cost":50,"type":"coupon"}'>填充示例</button>
 */
(function($) {
	'use strict';

	function findRow($btn) {
		// Codestar Framework group 重复项容器类
		var $row = $btn.closest('.csf-cloneable-item');
		if (!$row.length) {
			$row = $btn.closest('.csf-accordion-item');
		}
		if (!$row.length) {
			$row = $btn.closest('.csf-group-item');
		}
		return $row;
	}

	function fillField($row, suffix, value) {
		var $el = $row.find('input[name*="[' + suffix + ']"], select[name*="[' + suffix + ']"], textarea[name*="[' + suffix + ']"]').first();
		if (!$el.length) {
			return;
		}
		if ($el.is('select')) {
			$el.val(value).trigger('change').trigger('csf.change');
		} else if ($el.is(':checkbox, :radio')) {
			$el.prop('checked', !!value).trigger('change');
		} else {
			$el.val(value).trigger('input').trigger('change');
		}
	}

	function applyFill($btn) {
		var raw = $btn.attr('data-fill');
		if (!raw) {
			return;
		}
		var map;
		try {
			map = JSON.parse(raw);
		} catch (e) {
			map = {};
		}
		if (!map || typeof map !== 'object') {
			return;
		}
		var $row = findRow($btn);
		if (!$row.length) {
			window.alert('未找到当前项容器，请先展开该项再点预设。');
			return;
		}
		$.each(map, function(key, value) {
			fillField($row, key, value);
		});
	}

	$(document).on('click', '.zhiji-preset-fill', function(e) {
		e.preventDefault();
		applyFill($(this));
	});

})(jQuery);
