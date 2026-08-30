/* Zibll Zhiji 美化脚本
 * 职责：给 body 添加唯一标记类 .zhiji-ui（幂等），供 zhiji.css 作用域命中。
 * 不依赖主题 main.js，jQuery ready 后即可执行。
 */
(function ($) {
	'use strict';

	function applyMarker() {
		if (!document.body) {
			return;
		}
		if (!document.body.classList.contains('zhiji-ui')) {
			document.body.classList.add('zhiji-ui');
		}
	}

	$(function () {
		applyMarker();
		// 兜底：极端情况 body 尚未就绪时延时补一次
		setTimeout(applyMarker, 500);
	});
})(jQuery);
