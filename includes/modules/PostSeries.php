<?php
/**
 * @module  PostSeries
 * @desc    文章系列连载导航
 * @option  series_enabled  总开关
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/PostSeries.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('post_series', array(
    'title'    => '文章系列',
    'parent'   => 'zhiji_post',
    'priority' => 30,
    'option'   => 'series_enabled',
));



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 注册「系列」自定义分类法
 */
function zhiji_series_register_taxonomy() {
	$labels = array(
		'name'              => _x( '系列', 'taxonomy general name', 'zhiji' ),
		'singular_name'     => _x( '系列', 'taxonomy singular name', 'zhiji' ),
		'search_items'      => __( '搜索系列', 'zhiji' ),
		'all_items'         => __( '所有系列', 'zhiji' ),
		'parent_item'       => __( '父系列', 'zhiji' ),
		'parent_item_colon' => __( '父系列：', 'zhiji' ),
		'edit_item'         => __( '编辑系列', 'zhiji' ),
		'update_item'       => __( '更新系列', 'zhiji' ),
		'add_new_item'      => __( '添加新系列', 'zhiji' ),
		'new_item_name'     => __( '新系列名称', 'zhiji' ),
		'menu_name'         => __( '系列', 'zhiji' ),
	);

	$args = array(
		'hierarchical'      => true,
		'labels'            => $labels,
		'show_ui'           => true,
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => array( 'slug' => 'series' ),
		'show_in_rest'      => true,
	);

	register_taxonomy( 'series', array( 'post' ), $args );
}
add_action( 'init', 'zhiji_series_register_taxonomy', 0 );

/**
 * 系列分类法添加封面图字段
 */
function zhiji_series_add_cover_field() {
	?>
	<div class="form-field">
		<label for="zhiji_series_cover"><?php _e( '系列封面图', 'zhiji' ); ?></label>
		<input type="text" name="zhiji_series_cover" id="zhiji_series_cover" value="" placeholder="<?php esc_attr_e( '输入图片URL或点击上传', 'zhiji' ); ?>" style="width:70%;">
		<button type="button" class="button zhiji-upload-cover" style="margin-left:5px;"><?php _e( '上传', 'zhiji' ); ?></button>
		<p class="description"><?php _e( '用于系列归档页面和文章内系列卡片的封面展示', 'zhiji' ); ?></p>
	</div>
	<?php
}
add_action( 'series_add_form_fields', 'zhiji_series_add_cover_field', 10, 2 );

/**
 * 系列编辑页面封面图字段
 */
function zhiji_series_edit_cover_field( $term ) {
	$cover = get_term_meta( $term->term_id, 'zhiji_series_cover', true );
	?>
	<tr class="form-field">
		<th scope="row" valign="top"><label for="zhiji_series_cover"><?php _e( '系列封面图', 'zhiji' ); ?></label></th>
		<td>
			<input type="text" name="zhiji_series_cover" id="zhiji_series_cover" value="<?php echo esc_attr( $cover ); ?>" style="width:70%;">
			<button type="button" class="button zhiji-upload-cover" style="margin-left:5px;"><?php _e( '上传', 'zhiji' ); ?></button>
			<p class="description"><?php _e( '用于系列归档页面和文章内系列卡片的封面展示', 'zhiji' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'series_edit_form_fields', 'zhiji_series_edit_cover_field', 10, 2 );

/**
 * 保存系列封面图
 */
function zhiji_series_save_cover( $term_id ) {
	if ( isset( $_POST['zhiji_series_cover'] ) ) {
		update_term_meta( $term_id, 'zhiji_series_cover', esc_url_raw( $_POST['zhiji_series_cover'] ) );
	}
}
add_action( 'edited_series', 'zhiji_series_save_cover', 10, 2 );
add_action( 'create_series', 'zhiji_series_save_cover', 10, 2 );

/**
 * 文章内显示系列导航卡片（在文章内容前/后）
 */
function zhiji_series_render_card( $content ) {
	if ( ! is_singular( 'post' ) ) {
		return $content;
	}
	if ( ! (bool) zhiji_get_option( 'series_enabled', 1 ) ) {
		return $content;
	}

	$terms = get_the_terms( get_the_ID(), 'series' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return $content;
	}

	$term = $terms[0];
	$cover = get_term_meta( $term->term_id, 'zhiji_series_cover', true );
	$term_link = get_term_link( $term );

	// 获取同系列文章列表
	$series_posts = get_posts( array(
		'post_type'      => 'post',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'ASC',
		'tax_query'      => array(
			array(
				'taxonomy' => 'series',
				'field'    => 'term_id',
				'terms'    => $term->term_id,
			),
		),
	) );

	$current_index = 0;
	$total = count( $series_posts );
	foreach ( $series_posts as $i => $p ) {
		if ( $p->ID === get_the_ID() ) {
			$current_index = $i;
			break;
		}
	}

	$prev_post = ( $current_index > 0 ) ? $series_posts[ $current_index - 1 ] : null;
	$next_post = ( $current_index < $total - 1 ) ? $series_posts[ $current_index + 1 ] : null;

	ob_start();
	?>
	<div class="zhiji-series-card">
		<div class="zhiji-series-header">
			<?php if ( $cover ) : ?>
				<div class="zhiji-series-cover">
					<img src="<?php echo esc_url( $cover ); ?>" alt="<?php echo esc_attr( $term->name ); ?>">
				</div>
			<?php endif; ?>
			<div class="zhiji-series-info">
				<div class="zhiji-series-label"><?php _e( '系列连载', 'zhiji' ); ?></div>
				<h3 class="zhiji-series-name">
					<a href="<?php echo esc_url( $term_link ); ?>"><?php echo esc_html( $term->name ); ?></a>
				</h3>
				<?php if ( $term->description ) : ?>
					<p class="zhiji-series-desc"><?php echo esc_html( $term->description ); ?></p>
				<?php endif; ?>
				<div class="zhiji-series-progress"><?php printf( __( '第 %d / %d 篇', 'zhiji' ), $current_index + 1, $total ); ?></div>
			</div>
		</div>

		<?php if ( $total > 1 ) : ?>
		<div class="zhiji-series-nav">
			<?php if ( $prev_post ) : ?>
				<a href="<?php echo esc_url( get_permalink( $prev_post->ID ) ); ?>" class="zhiji-series-prev">
					<span class="zhiji-series-nav-label"><?php _e( '← 上一篇', 'zhiji' ); ?></span>
					<span class="zhiji-series-nav-title"><?php echo esc_html( $prev_post->post_title ); ?></span>
				</a>
			<?php else : ?>
				<span class="zhiji-series-nav-disabled"><?php _e( '已是第一篇', 'zhiji' ); ?></span>
			<?php endif; ?>

			<a href="<?php echo esc_url( $term_link ); ?>" class="zhiji-series-toc"><?php _e( '系列目录', 'zhiji' ); ?></a>

			<?php if ( $next_post ) : ?>
				<a href="<?php echo esc_url( get_permalink( $next_post->ID ) ); ?>" class="zhiji-series-next">
					<span class="zhiji-series-nav-label"><?php _e( '下一篇 →', 'zhiji' ); ?></span>
					<span class="zhiji-series-nav-title"><?php echo esc_html( $next_post->post_title ); ?></span>
				</a>
			<?php else : ?>
				<span class="zhiji-series-nav-disabled"><?php _e( '已是最新篇', 'zhiji' ); ?></span>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
	<?php
	$card = ob_get_clean();

	// 显示位置：文章内容前
	$position = zhiji_get_option( 'series_position', 'before' );
	if ( 'after' === $position ) {
		return $content . $card;
	}
	return $card . $content;
}
add_filter( 'the_content', 'zhiji_series_render_card', 20 );

/**
 * 系列卡片样式与脚本
 */
function zhiji_series_assets() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	if ( ! (bool) zhiji_get_option( 'series_enabled', 1 ) ) {
		return;
	}
	?>
	<style>
	.zhiji-series-card {
		background: #fff;
		border-radius: 12px;
		padding: 20px;
		margin: 20px 0;
		box-shadow: 0 2px 12px rgba(0,0,0,.06);
		border: 1px solid #f0f0f0;
	}
	.zhiji-series-header {
		display: flex;
		gap: 16px;
		align-items: flex-start;
	}
	.zhiji-series-cover {
		flex: 0 0 120px;
		width: 120px;
		height: 120px;
		border-radius: 8px;
		overflow: hidden;
	}
	.zhiji-series-cover img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}
	.zhiji-series-info {
		flex: 1;
		min-width: 0;
	}
	.zhiji-series-label {
		display: inline-block;
		font-size: 11px;
		color: #fff;
		background: var(--zhiji-brand, #2e7cf6);
		padding: 2px 8px;
		border-radius: 4px;
		margin-bottom: 6px;
		font-weight: 600;
	}
	.zhiji-series-name {
		margin: 0 0 6px;
		font-size: 18px;
		font-weight: 700;
	}
	.zhiji-series-name a {
		color: #333;
		text-decoration: none;
	}
	.zhiji-series-name a:hover {
		color: var(--zhiji-brand, #2e7cf6);
	}
	.zhiji-series-desc {
		margin: 0 0 8px;
		font-size: 13px;
		color: #888;
		line-height: 1.6;
	}
	.zhiji-series-progress {
		font-size: 12px;
		color: var(--zhiji-brand, #2e7cf6);
		font-weight: 600;
	}
	.zhiji-series-nav {
		display: flex;
		gap: 12px;
		margin-top: 16px;
		padding-top: 16px;
		border-top: 1px solid #f0f0f0;
		align-items: center;
	}
	.zhiji-series-prev,
	.zhiji-series-next {
		flex: 1;
		display: block;
		padding: 10px 14px;
		background: #f8f9fa;
		border-radius: 8px;
		text-decoration: none;
		transition: background .2s;
		overflow: hidden;
	}
	.zhiji-series-prev:hover,
	.zhiji-series-next:hover {
		background: #f0f0f0;
	}
	.zhiji-series-prev {
		text-align: left;
	}
	.zhiji-series-next {
		text-align: right;
	}
	.zhiji-series-nav-label {
		display: block;
		font-size: 11px;
		color: #999;
		margin-bottom: 2px;
	}
	.zhiji-series-nav-title {
		display: block;
		font-size: 13px;
		color: #333;
		font-weight: 500;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.zhiji-series-toc {
		flex: 0 0 auto;
		padding: 8px 16px;
		background: var(--zhiji-brand, #2e7cf6);
		color: #fff;
		border-radius: 20px;
		text-decoration: none;
		font-size: 13px;
		font-weight: 600;
		white-space: nowrap;
	}
	.zhiji-series-toc:hover {
		opacity: .85;
		color: #fff;
	}
	.zhiji-series-nav-disabled {
		flex: 1;
		text-align: center;
		font-size: 12px;
		color: #ccc;
		padding: 10px;
	}
	@media (max-width: 768px) {
		.zhiji-series-header {
			flex-direction: column;
		}
		.zhiji-series-cover {
			flex: 0 0 auto;
			width: 100%;
			height: 160px;
		}
		.zhiji-series-nav {
			flex-wrap: wrap;
		}
		.zhiji-series-prev,
		.zhiji-series-next {
			flex: 1 1 100%;
		}
		.zhiji-series-toc {
			flex: 1 1 100%;
			text-align: center;
		}
	}
	</style>
	<script>
	// 系列封面图上传
	jQuery(document).ready(function($) {
		$(document).on('click', '.zhiji-upload-cover', function(e) {
			e.preventDefault();
			var button = $(this);
			var input = button.siblings('input#zhiji_series_cover');
			var customUploader = wp.media({
				title: '选择系列封面图',
				button: { text: '使用此图片' },
				multiple: false
			});
			customUploader.on('select', function() {
				var attachment = customUploader.state().get('selection').first().toJSON();
				input.val(attachment.url);
			});
			customUploader.open();
		});
	});
	</script>
	<?php
}
zhiji_footer_add( 'post-series', 'zhiji_series_assets', 10 );

/**
 * 后台配置：文章系列分区
 */
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('post_series', array(
			array(
				'id'      => 'series_enabled',
				'type'    => 'switcher',
				'title'   => '启用文章系列',
				'desc'    => '为文章添加「系列」分类法，支持系列封面、上一篇/下一篇导航、系列目录',
				'default' => true,
			),
			array(
				'id'      => 'series_position',
				'type'    => 'button_set',
				'title'   => '系列卡片显示位置',
				'desc' => __( '文章系列卡片在页面中的显示位置。', 'zhiji' ),
				'options' => array(
					'before' => '文章内容前',
					'after'  => '文章内容后',
				),
				'default' => 'before',
			),
			array(
				'type'    => 'submessage',
				'style'   => 'info',
				'content' => '系列分类法地址：<code>/series/系列别名</code>。在后台「文章 → 系列」中添加系列并设置封面图，编辑文章时勾选所属系列即可。',
			),
		), 20);
