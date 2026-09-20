<?php
/**
 * 微语时间线页面模板
 *
 * Template Name: 微语时间线页面
 * Template Post Type: page
 *
 * @package Zhiji_Child
 * @since   1.9.17
 */

get_header();
$header_style = function_exists( 'zib_get_page_header_style' ) ? zib_get_page_header_style() : 0;
$posts_per_page = (int) zhiji_get_option( 'weiyu_posts_per_page', 20 );
$show_avatar    = filter_var( zhiji_get_option( 'weiyu_show_avatar', true ), FILTER_VALIDATE_BOOLEAN );
$like_enabled   = filter_var( zhiji_get_option( 'weiyu_like_enabled', true ), FILTER_VALIDATE_BOOLEAN );
?>
<main class="container">
	<div class="content-wrap">
		<div class="content-layout">
			<?php while ( have_posts() ) : the_post(); ?>
				<?php if ( $header_style != 1 ) { echo zib_get_page_header(); } ?>
			<?php endwhile; ?>
			<div class="box-body theme-box radius8 main-bg main-shadow">
				<?php if ( $header_style == 1 ) { echo zib_get_page_header(); } ?>

				<!-- 微语时间线样式 -->
				<style type="text/css">
				#shuoshuo_content{padding:20px 10px;}
				body.theme-dark .cbp_tmtimeline::before{background:rgba(255,255,255,.06);}
				ul.cbp_tmtimeline{padding:0;list-style:none;margin:0;}
				.cbp_tmtimeline{margin:20px 0 0 0;padding:0;list-style:none;position:relative;}
				.cbp_tmtimeline:before{position:absolute;top:0;bottom:0;width:4px;background:rgba(0,0,0,.06);left:90px;margin-left:10px;border-radius:2px;}
				body.theme-dark .cbp_tmtimeline:before{background:rgba(255,255,255,.08);}
				.cbp_tmtimeline > li{position:relative;margin-bottom:30px;list-style:none;}
				.cbp_tmtimeline > li .cbp_tmtime{display:block;max-width:80px;position:absolute;left:0;top:0;}
				.cbp_tmtimeline > li .cbp_tmtime span{display:block;text-align:right;}
				.cbp_tmtimeline > li .cbp_tmtime span:first-child{font-size:.85em;color:#999;}
				.cbp_tmtimeline > li .cbp_tmtime span:last-child{font-size:1.1em;color:var(--zhiji-brand, #2e7cf6);font-weight:600;}
				.cbp_tmtimeline > li .cbp_tmlabel{margin:0 0 0 110px;background:var(--zhiji-brand, #2e7cf6);color:#fff;padding:1em 1.2em .6em 1.2em;font-weight:400;line-height:1.6;position:relative;border-radius:8px;transition:all .3s ease;box-shadow:0 2px 8px rgba(0,0,0,.1);cursor:pointer;display:block;}
				.cbp_tmtimeline > li .cbp_tmlabel:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.15);}
				.cbp_tmtimeline > li .cbp_tmlabel:after{right:100%;border:solid transparent;content:" ";height:0;width:0;position:absolute;pointer-events:none;border-right-color:var(--zhiji-brand, #2e7cf6);border-width:10px;top:15px;}
				.cbp_tmtimeline > li .cbp_tmlabel h4{margin:0 0 8px 0;font-size:16px;font-weight:600;}
				.cbp_tmtimeline > li .cbp_tmlabel p{margin-bottom:8px;}
				.cbp_tmtimeline > li .cbp_tmlabel .shuoshuo_meta{display:flex;align-items:center;justify-content:space-between;margin-top:10px;padding-top:8px;border-top:1px solid rgba(255,255,255,.2);font-size:12px;opacity:.9;}
				.cbp_tmtimeline > li .cbp_tmlabel .shuoshuo_time{display:flex;align-items:center;gap:4px;}
				.cbp_tmtimeline > li .cbp_tmlabel .shuoshuo_like{display:flex;align-items:center;gap:4px;cursor:pointer;transition:transform .2s;}
				.cbp_tmtimeline > li .cbp_tmlabel .shuoshuo_like:hover{transform:scale(1.1);}
				.shuoshuo_author_img{position:absolute;left:70px;top:0;z-index:2;}
				.shuoshuo_author_img img{border-radius:50%;width:44px;height:44px;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.15);transition:transform .5s ease;}
				body.theme-dark .shuoshuo_author_img img{border-color:#26272b;}
				.shuoshuo_author_img img:hover{transform:scale(1.15) rotate(360deg);}
				.weiyu-pagination{text-align:center;margin-top:30px;padding:20px 0;}
				.weiyu-pagination .page-numbers{display:inline-block;padding:8px 14px;margin:0 4px;border-radius:6px;background:var(--zhiji-brand, #2e7cf6);color:#fff;text-decoration:none;font-size:14px;transition:all .2s;}
				.weiyu-pagination .page-numbers:hover{opacity:.85;}
				.weiyu-pagination .page-numbers.current{background:rgba(0,0,0,.1);color:var(--zhiji-brand, #2e7cf6);font-weight:600;}
				body.theme-dark .weiyu-pagination .page-numbers.current{background:rgba(255,255,255,.1);}
				@media (max-width:640px){
					.cbp_tmtimeline:before{left:50px;}
					.cbp_tmtimeline > li .cbp_tmtime{max-width:45px;}
					.cbp_tmtimeline > li .cbp_tmtime span:last-child{font-size:.9em;}
					.cbp_tmtimeline > li .cbp_tmlabel{margin-left:65px;padding:.8em 1em .5em 1em;}
					.shuoshuo_author_img{left:38px;}
					.shuoshuo_author_img img{width:36px;height:36px;}
				}
				</style>

				<div id="primary" class="content-area">
					<main id="main" class="site-main" role="main">
						<div id="shuoshuo_content">
							<ul class="cbp_tmtimeline">
								<?php
								$paged = max( 1, get_query_var( 'paged' ) );
								$q     = new WP_Query(
									array(
										'post_type'      => 'shuoshuo',
										'post_status'    => 'publish',
										'posts_per_page' => $posts_per_page,
										'paged'          => $paged,
									)
								);
								if ( $q->have_posts() ) :
									while ( $q->have_posts() ) :
										$q->the_post();
										$like_count = get_post_meta( get_the_ID(), 'bigfa_ding', true );
										$like_count = $like_count ? (int) $like_count : 0;
										?>
								<li>
									<?php if ( $show_avatar ) : ?>
									<span class="shuoshuo_author_img">
										<img src="<?php echo esc_url( function_exists( 'zib_get_user_avatar_url' ) ? zib_get_user_avatar_url( get_the_author_meta( 'ID' ), 48 ) : get_avatar_url( get_the_author_meta( 'ID' ), array( 'size' => 48 ) ) ); ?>" class="avatar avatar-48" alt="<?php echo esc_attr( get_the_author() ); ?>">
									</span>
									<?php endif; ?>
									<div class="cbp_tmtime">
										<span><?php echo esc_html( get_the_time( 'm-d' ) ); ?></span>
										<span><?php echo esc_html( get_the_time( 'H:i' ) ); ?></span>
									</div>
									<div class="cbp_tmlabel">
										<h4><?php the_title(); ?></h4>
										<div class="cbp_tmcontent"><?php the_content(); ?></div>
										<div class="shuoshuo_meta">
											<span class="shuoshuo_time">
												<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
												<?php echo esc_html( get_the_time( 'Y年n月j日 G:i' ) ); ?>
											</span>
											<?php if ( $like_enabled ) : ?>
											<span class="shuoshuo_like" data-id="<?php the_ID(); ?>" onclick="zhiji_weiyu_like(this)">
												<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
												<span class="like-count"><?php echo esc_html( $like_count ); ?></span>
											</span>
											<?php endif; ?>
										</div>
									</div>
								</li>
										<?php
									endwhile;
									wp_reset_postdata();
								else :
									echo '<li style="text-align:center;padding:40px;color:#999;">暂无微语，快去发布第一条吧～</li>';
								endif;
								?>
							</ul>
							<?php if ( $q->max_num_pages > 1 ) : ?>
							<div class="weiyu-pagination">
								<?php
								echo paginate_links(
									array(
										'total'     => $q->max_num_pages,
										'current'   => $paged,
										'prev_text' => '上一页',
										'next_text' => '下一页',
									)
								);
								?>
							</div>
							<?php endif; ?>
						</div>
					</main>
				</div>
			</div>
			<?php comments_template( '/template/comments.php', true ); ?>
		</div>
		<?php get_sidebar(); ?>
	</div>
</main>

<?php if ( $like_enabled ) : ?>
<script type="text/javascript">
function zhiji_weiyu_like(el) {
	var id = el.getAttribute('data-id');
	var countEl = el.querySelector('.like-count');
	var xhr = new XMLHttpRequest();
	xhr.open('POST', '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', true);
	xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	xhr.onreadystatechange = function() {
		if (xhr.readyState === 4 && xhr.status === 200) {
			if (countEl) countEl.textContent = xhr.responseText;
			el.style.transform = 'scale(1.3)';
			setTimeout(function(){ el.style.transform = ''; }, 300);
		}
	};
	xhr.send('action=zhiji_weiyu_like&um_id=' + id + '&um_action=ding');
}
</script>
<?php endif; ?>

<?php get_footer(); ?>
