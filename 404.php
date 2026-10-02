<?php
/**
 * 子主题 404 模板（覆盖父主题 template/content-404 的展示层）
 *
 * 硬约束：本文件属子主题覆盖层，不修改父主题任何文件；
 * 展示与父主题一致的 404 图 + 搜索框。
 */

get_header();
?>
<main class="main-min-height">
	<div class="f404">
		<img src="<?php echo esc_url(get_template_directory_uri() . '/img/404.svg'); ?>" alt="404">
	</div>
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
