<?php
/**
 * 子主题 404 模板（覆盖父主题 template/content-404 的展示层）
 *
 * 2026-09-29：新增 404 小游戏（贪吃蛇，知集模块 NotFoundGame 控制）。
 * 硬约束：本文件属子主题覆盖层，不修改父主题任何文件；
 * 未启用游戏模块时展示与父主题一致的 404 图 + 搜索框。
 */

get_header();
?>
<main class="main-min-height">
	<div class="f404">
		<img src="<?php echo esc_url(get_template_directory_uri() . '/img/404.svg'); ?>" alt="404">
	</div>
	<?php if (function_exists('zhiji_is_enabled') && zhiji_is_enabled('notfound_game_enabled', false)) : ?>
	<div class="theme-box box-body" style="text-align:center">
		<div style="padding:16px">
			<div style="font-weight:600;margin-bottom:6px">🎮 趁页面找不到，来局贪吃蛇吧</div>
			<div style="font-size:12px;color:#999;margin-bottom:10px">方向键 / WASD 控制 · 空格暂停 · 吃到食物得 1 分</div>
			<canvas id="zhiji-404-snake" width="400" height="300" style="max-width:100%;border:1px solid #eee;border-radius:8px;background:#fafafa;display:inline-block"></canvas>
			<div style="margin-top:10px">
				<span id="zhiji-404-score" style="font-weight:600">得分：0</span>
				<button type="button" id="zhiji-404-restart" class="button" style="margin-left:12px">重新开始</button>
			</div>
		</div>
	</div>
	<?php endif; ?>
	<div class="theme-box box-body main-search">
		<?php
		if (function_exists('_pz') && _pz('404_search_s', true) && class_exists('Zhiji_Adapter')) {
			echo Zhiji_Adapter::main_search(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
	</div>
</main>
<?php
get_footer();
