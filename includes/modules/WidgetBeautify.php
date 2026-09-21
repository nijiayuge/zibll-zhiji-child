<?php
/**
 * @module  WidgetBeautify
 * @desc    侧栏小工具统一样式美化
 * @option  widgets_beautify_enabled  总开关
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/WidgetBeautify.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('widgets_beautify', array(
    'title'    => '小工具美化',
    'parent'   => 'zhiji_beautify',
    'priority' => 40,
    'option'   => 'widgets_beautify_enabled',
));



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
 * 后台 CSF 设置：美化效果 → 小工具美化
 * ============================================================ */
add_action( 'after_setup_theme', function () {
	
	Zhiji_Registry::csf_section_for_legacy( 'widgets_beautify', array(
		'title'  => '小工具美化',
		'icon'   => 'fa fa-th-large',
		'parent' => 'zhiji_beautify',
		'priority' => 40,
		'fields' => array(
			array(
				'id'      => 'widgets_beautify_enabled',
				'type'    => 'switcher',
				'title'   => '启用小工具美化库',
				'default' => false,
				'desc'    => '开启后在 外观→小工具 中可用 8 个装饰性小工具。',
			),
			array(
				'id'         => 'widgets_list',
				'type'       => 'submessage',
				'style'      => 'success',
				'content'    => '可用小工具：滚动播报、简单模块、IP标签、在线征稿、跑马灯公告、引导卡片、侧边统计。底部统计请用「知集 - 站点统计看板」小工具。请到 外观→小工具 中拖入对应区域。',
				'dependency' => array( 'widgets_beautify_enabled', '==', '1' ),
			),
		),
	) );
}, 20 );

/**
 * 判断小工具美化库是否启用。
 *
 * @return bool
 */
function zhiji_widgets_beautify_is_enabled() {
	return filter_var( zhiji_get_option( 'widgets_beautify_enabled', false ), FILTER_VALIDATE_BOOLEAN );
}

/* ============================================================
 * 小工具类定义
 * ============================================================ */

	/* ========== #1 滚动播报小工具 ========== */
	if ( ! class_exists( 'zhiji_Widget_ScrollBroadcast' ) ) {
		class zhiji_Widget_ScrollBroadcast extends WP_Widget {
			function __construct() {
				parent::__construct( 'zhiji_scroll_broadcast', 'zhiji·滚动播报', array( 'classname' => 'widget_zhiji_sb', 'description' => '垂直翻滚的欢迎播报' ) );
			}
			function form( $instance ) {
				$lines = isset( $instance['lines'] ) ? $instance['lines'] : "坚持每天来逛逛，会让你\n生活也美好了！\n心情也舒畅了！\n走路也有劲了！\n腿也不痛了！\n腰也不酸了！";
				?><p><label>每行一条（共 6 行）：</label><br><textarea class="widefat" name="<?php echo $this->get_field_name( 'lines' ); ?>" rows="7"><?php echo esc_textarea( $lines ); ?></textarea></p><?php
			}
			function update( $new, $old ) {
				$instance = $old;
				$instance['lines'] = strip_tags( $new['lines'] );
				return $instance;
			}
			function widget( $args, $instance ) {
				$lines = explode( "\n", str_replace( "\r", '', $instance['lines'] ?: '' ) );
				$lines = array_slice( array_filter( $lines, 'trim' ), 0, 6 );
				$n = $this->number;
				echo $args['before_widget'];
				?>
				<div class="zhiji-deco-item">
				<style>
				#zhiji-sb-box-<?php echo $n; ?>{color:#526372;text-transform:uppercase;width:100%;font-size:16px;line-height:50px;text-align:center}
				#zhiji-sb-flip-<?php echo $n; ?>{overflow:hidden;height:50px}
				#zhiji-sb-flip-<?php echo $n; ?> div{height:50px}
				#zhiji-sb-flip-<?php echo $n; ?> > div > div{color:#fff;display:inline-block;text-align:center;height:50px;width:100%}
				#zhiji-sb-flip-<?php echo $n; ?> div:first-child{animation:zhiji-sb-show-<?php echo $n; ?> 8s linear infinite}
				.zhiji-sb-c1{background-color:#FF7E40}.zhiji-sb-c2{background-color:#C166FF}.zhiji-sb-c3{background-color:#737373}.zhiji-sb-c4{background-color:#4ec7f3}.zhiji-sb-c5{background-color:#42c58a}.zhiji-sb-c6{background-color:#F1617D}
				@keyframes zhiji-sb-show-<?php echo $n; ?>{0%{margin-top:-300px}5%{margin-top:-250px}16.666%{margin-top:-250px}21.666%{margin-top:-200px}33.332%{margin-top:-200px}38.332%{margin-top:-150px}49.998%{margin-top:-150px}54.998%{margin-top:-100px}66.664%{margin-top:-100px}71.664%{margin-top:-50px}83.33%{margin-top:-50px}88.33%{margin-top:0px}99.996%{margin-top:0px}100%{margin-top:300px}}
				</style>
				<div id="zhiji-sb-box-<?php echo $n; ?>">
				<div id="zhiji-sb-flip-<?php echo $n; ?>">
				<?php foreach ( $lines as $i => $t ): $cls = 'zhiji-sb-c' . ( ( $i % 6 ) + 1 ); ?>
					<div><div class="<?php echo $cls; ?>"><?php echo esc_html( trim( $t ) ); ?></div></div>
				<?php endforeach; ?>
				</div></div></div>
				<?php
				echo $args['after_widget'];
			}
		}
	}

	/* ========== #2 侧边栏简单小模块 ========== */
	if ( ! class_exists( 'zhiji_Widget_SimpleModule' ) ) {
		class zhiji_Widget_SimpleModule extends WP_Widget {
			function __construct() {
				parent::__construct( 'zhiji_simple_module', 'zhiji·简单模块', array( 'classname' => 'widget_zhiji_sm', 'description' => '醒目入口卡片' ) );
			}
			function form( $instance ) {
				$title = $instance['title'] ?? '知集小工具';
				$sub   = $instance['sub'] ?? '知集推荐，安全有保障';
				$url   = $instance['url'] ?? '#';
				$btn   = $instance['btn'] ?? '立即进入';
				?>
				<p><label>标题：</label><input class="widefat" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo esc_attr( $title ); ?>"></p>
				<p><label>副标题：</label><input class="widefat" name="<?php echo $this->get_field_name( 'sub' ); ?>" value="<?php echo esc_attr( $sub ); ?>"></p>
				<p><label>跳转链接：</label><input class="widefat" name="<?php echo $this->get_field_name( 'url' ); ?>" value="<?php echo esc_attr( $url ); ?>"></p>
				<p><label>按钮文字：</label><input class="widefat" name="<?php echo $this->get_field_name( 'btn' ); ?>" value="<?php echo esc_attr( $btn ); ?>"></p>
				<?php
			}
			function update( $new, $old ) {
				$instance = $old;
				$instance['title'] = strip_tags( $new['title'] );
				$instance['sub']   = strip_tags( $new['sub'] );
				$instance['url']   = esc_url_raw( $new['url'] );
				$instance['btn']   = strip_tags( $new['btn'] );
				return $instance;
			}
			function widget( $args, $instance ) {
				$title = $instance['title'] ?? '知集小工具';
				$sub   = $instance['sub'] ?? '知集推荐';
				$url   = $instance['url'] ?? '#';
				$btn   = $instance['btn'] ?? '立即进入';
				echo $args['before_widget'];
				?>
				<div class="zhiji-deco-item">
				<a class="zhiji-simple-ads" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" style="border-radius:5px;">
				  <h4><?php echo esc_html( $title ); ?></h4>
				  <h5><?php echo esc_html( $sub ); ?></h5>
				  <span class="zhiji-simple-btn"><?php echo esc_html( $btn ); ?></span>
				</a>
				<style>
				.zhiji-simple-ads{display:block;padding:40px 15px;text-align:center;color:#fff!important;background:linear-gradient(135deg,var(--zhiji-brand, #2e7cf6),var(--zhiji-raw-blue-deep, #1a5fd0))}
				.zhiji-simple-ads h4{margin:0;font-size:22px;font-weight:bold}
				.zhiji-simple-ads h5{margin:10px 0 0;font-size:14px;font-weight:bold;opacity:.9}
				.zhiji-simple-btn{display:inline-block;font-weight:bold;margin-top:20px;color:#fff;background:transparent;border:1px solid #fff;border-radius:10px;padding:0 36px;line-height:38px;font-size:14px;transition:all .3s}
				.zhiji-simple-ads:hover .zhiji-simple-btn{color:#343a3c;background:#fff}
				</style>
				</div>
				<?php
				echo $args['after_widget'];
			}
		}
	}

	/* ========== #3 侧边 IP 标签小工具 ========== */
	if ( ! class_exists( 'zhiji_Widget_IpLabel' ) ) {
		class zhiji_Widget_IpLabel extends WP_Widget {
			function __construct() {
				parent::__construct( 'zhiji_ip_label', 'zhiji·IP标签', array( 'classname' => 'widget_zhiji_ip', 'description' => 'IP签名档图片（请换成自己的图片）' ) );
			}
			function form( $instance ) {
				$img = $instance['img'] ?? '';
				?><p><label>签名档图片地址：</label><input class="widefat" name="<?php echo $this->get_field_name( 'img' ); ?>" value="<?php echo esc_url( $img ); ?>"></p><?php
			}
			function update( $new, $old ) {
				$instance = $old;
				$instance['img'] = esc_url_raw( $new['img'] );
				return $instance;
			}
			function widget( $args, $instance ) {
				$img = $instance['img'] ?? '';
				if ( ! $img ) { return; }
				echo $args['before_widget'];
				?>
				<div class="zhiji-deco-item" id="zhiji-ip-<?php echo $this->number; ?>">
				  <img src="<?php echo esc_url( $img ); ?>" alt="IP签名档" style="border-radius:var(--main-radius);box-shadow:0 0 10px var(--main-shadow);width:100%;">
				</div>
				<?php
				echo $args['after_widget'];
			}
		}
	}

	/* ========== #4 侧边在线征稿小工具 ========== */
	if ( ! class_exists( 'zhiji_Widget_Contribute' ) ) {
		class zhiji_Widget_Contribute extends WP_Widget {
			function __construct() {
				parent::__construct( 'zhiji_contribute', 'zhiji·在线征稿', array( 'classname' => 'widget_zhiji_ct', 'description' => '投稿/邮箱/友链入口' ) );
			}
			function form( $instance ) {
				$email = $instance['email'] ?? '';
				$post  = $instance['post'] ?? '#';
				$about = $instance['about'] ?? '#';
				$edit  = $instance['edit'] ?? '#';
				?>
				<p><label>投稿邮箱：</label><input class="widefat" name="<?php echo $this->get_field_name( 'email' ); ?>" value="<?php echo esc_attr( $email ); ?>"></p>
				<p><label>投稿页面：</label><input class="widefat" name="<?php echo $this->get_field_name( 'post' ); ?>" value="<?php echo esc_attr( $post ); ?>"></p>
				<p><label>关于我们：</label><input class="widefat" name="<?php echo $this->get_field_name( 'about' ); ?>" value="<?php echo esc_attr( $about ); ?>"></p>
				<p><label>在线投稿：</label><input class="widefat" name="<?php echo $this->get_field_name( 'edit' ); ?>" value="<?php echo esc_attr( $edit ); ?>"></p>
				<?php
			}
			function update( $new, $old ) {
				$instance = $old;
				$instance['email'] = strip_tags( $new['email'] );
				$instance['post']  = esc_url_raw( $new['post'] );
				$instance['about'] = esc_url_raw( $new['about'] );
				$instance['edit']  = esc_url_raw( $new['edit'] );
				return $instance;
			}
			function widget( $args, $instance ) {
				$email = $instance['email'] ?? '';
				$post  = $instance['post'] ?? '#';
				$about = $instance['about'] ?? '#';
				$edit  = $instance['edit'] ?? '#';
				echo $args['before_widget'];
				?>
				<div class="zhiji-deco-item">
				<style>#zhiji-ct a{width:30%;height:35px;border-radius:3px;text-align:center;line-height:35px;font-size:9pt;color:#fff;font-weight:700;float:left;margin-right:5%}
				#zhiji-ct .blog_link{background-color:#2ba9fa}#zhiji-ct .cms_link{background-color:#ff6969}#zhiji-ct .grid_link{background-color:#70c041}</style>
				<div id="zhiji-ct">
				<a class="blog_link" href="mailto:<?php echo esc_attr( $email ); ?>" rel="noopener">发送邮件</a>
				<a class="cms_link" href="<?php echo esc_url( $post ); ?>" target="_blank" rel="noopener">点击投稿</a>
				<a class="grid_link" href="<?php echo esc_url( $about ); ?>" target="_blank" rel="noopener">关于我们</a>
				<div style="clear:both"></div>
				</div>
				<hr>
				<a href="<?php echo esc_url( $edit ); ?>" target="_blank" rel="noopener">点击在线投稿</a><br>
				投稿邮箱：<b><?php echo esc_html( $email ); ?></b>
				</div>
				<?php
				echo $args['after_widget'];
			}
		}
	}

	/* ========== #5 跑马灯公告小工具 ========== */
	if ( ! class_exists( 'zhiji_Widget_Marquee' ) ) {
		class zhiji_Widget_Marquee extends WP_Widget {
			function __construct() {
				parent::__construct( 'zhiji_marquee', 'zhiji·跑马灯公告', array( 'classname' => 'widget_zhiji_mq', 'description' => '彩色跑马灯公告' ) );
			}
			function form( $instance ) {
				$text = $instance['text'] ?? '欢迎来到知集 - 子比主题子主题功能库。';
				?><p><label>公告文字：</label><input class="widefat" name="<?php echo $this->get_field_name( 'text' ); ?>" value="<?php echo esc_attr( $text ); ?>"></p><?php
			}
			function update( $new, $old ) {
				$instance = $old;
				$instance['text'] = strip_tags( $new['text'] );
				return $instance;
			}
			function widget( $args, $instance ) {
				$text = $instance['text'] ?? '欢迎来到知集';
				echo $args['before_widget'];
				?>
				<div class="zhiji-deco-item">
				<style>
				#zhiji-mq-nr{font-size:20px;margin:0;background:-webkit-linear-gradient(left,#fff,#ff0000 6.25%,#ff7d00 12.5%,#ffff00 18.75%,#00ff00 25%,#00ffff 31.25%,#0000ff 37.5%,#ff00ff 43.75%,#ffff00 50%,#ff0000 56.25%,#ff7d00 62.5%,#ffff00 68.75%,#00ff00 75%,#00ffff 81.25%,#0000ff 87.5%,#ff00ff 93.75%,#ffff00 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-size:200% 100%;animation:zhiji-mq-mask 2s infinite linear}
				@keyframes zhiji-mq-mask{0%{background-position:0 0}100%{background-position:-100% 0}}
				</style>
				<div style="background-color:#333;border-radius:25px;box-shadow:0 0 5px var(--zhiji-brand, #2e7cf6);padding:5px;margin-bottom:0;">
					<marquee><b id="zhiji-mq-nr"><?php echo esc_html( $text ); ?></b></marquee>
				</div>
				</div>
				<?php
				echo $args['after_widget'];
			}
		}
	}

	/* ========== #6 底部炫酷引导卡片 ========== */
	if ( ! class_exists( 'zhiji_Widget_GuideCard' ) ) {
		class zhiji_Widget_GuideCard extends WP_Widget {
			function __construct() {
				parent::__construct( 'zhiji_guide_card', 'zhiji·引导卡片', array( 'classname' => 'widget_zhiji_gc', 'description' => '底部炫酷引导卡片（联系站长/友链）' ) );
			}
			function form( $instance ) {
				$site = $instance['site'] ?? '知集';
				$qq   = $instance['qq'] ?? '';
				$link = $instance['link'] ?? '#';
				?>
				<p><label>站点名：</label><input class="widefat" name="<?php echo $this->get_field_name( 'site' ); ?>" value="<?php echo esc_attr( $site ); ?>"></p>
				<p><label>站长 QQ：</label><input class="widefat" name="<?php echo $this->get_field_name( 'qq' ); ?>" value="<?php echo esc_attr( $qq ); ?>"></p>
				<p><label>友链通道：</label><input class="widefat" name="<?php echo $this->get_field_name( 'link' ); ?>" value="<?php echo esc_attr( $link ); ?>"></p>
				<?php
			}
			function update( $new, $old ) {
				$instance = $old;
				$instance['site'] = strip_tags( $new['site'] );
				$instance['qq']   = strip_tags( $new['qq'] );
				$instance['link'] = esc_url_raw( $new['link'] );
				return $instance;
			}
			function widget( $args, $instance ) {
				$site = $instance['site'] ?? '知集';
				$qq   = $instance['qq'] ?? '';
				$link = $instance['link'] ?? '#';
				echo $args['before_widget'];
				?>
				<div class="zhiji-deco-item" id="zhiji-gc-<?php echo $this->number; ?>" style="box-shadow:0 0 10px var(--main-shadow);">
				<section class="zhiji-buy-container">
				  <div class="zhiji-buy-box">
					<div class="zhiji-slogan"><h3><?php echo esc_html( $site ); ?></h3><p>欢迎光临寒舍！</p></div>
					<ul class="zhiji-actions">
					  <?php if ( $qq ): ?><li><a href="http://wpa.qq.com/msgrd?v=3&uin=<?php echo esc_attr( $qq ); ?>&site=qq&menu=yes" target="_blank" class="zhiji-buy-button primary" rel="noopener noreferrer">联系站长</a></li><?php endif; ?>
					  <li><a href="<?php echo esc_url( $link ); ?>" target="_blank" class="zhiji-demo-button" rel="noopener noreferrer">友链通道</a></li>
					</ul>
				  </div>
				  <span class="zhiji-tips"><div>更多精彩文章，按<span>Ctrl</span>+<span>D</span>收藏本站！</div></span>
				</section>
				<style>
				.zhiji-buy-container{color:#ccc;padding:60px 40px 50px;margin:0 auto;background:linear-gradient(to right,var(--zhiji-brand, #2e7cf6) 0%,var(--zhiji-raw-blue-deep, #1a5fd0) 100%);border-radius:var(--main-radius)}
				.zhiji-buy-box{display:flex;justify-content:space-between;align-items:center;max-width:900px;margin:0 auto}
				@media screen and (max-width:700px){.zhiji-buy-box{display:block;text-align:center}.zhiji-buy-box .zhiji-slogan{margin-bottom:30px}}
				.zhiji-buy-box .zhiji-slogan h3{color:#fff;font-size:26px;margin:0 0 10px}
				.zhiji-buy-box .zhiji-slogan p{color:#fff;font-size:14px;font-weight:bold;margin:10px 0}
				.zhiji-buy-box .zhiji-actions{display:flex;align-items:center;list-style:none;margin:0;padding:0}
				@media screen and (max-width:700px){.zhiji-buy-box .zhiji-actions{justify-content:center}}
				.zhiji-buy-box .zhiji-actions li{margin:0}
				.zhiji-buy-box .zhiji-actions li:last-child{margin-left:10px}
				.zhiji-buy-box .zhiji-actions li a{position:relative;color:#fff!important;font-size:14px;font-weight:bold;line-height:1;text-decoration:none;padding:12px 28px;border-radius:6px;background:#fff;color:#333!important;transition:all .3s}
				.zhiji-buy-box .zhiji-actions li a.primary{background:linear-gradient(90deg,#006eff,#13adff);color:#fff!important}
				.zhiji-buy-box .zhiji-actions li a:hover{opacity:.85}
				.zhiji-tips{display:block;text-align:center;color:#fff;margin-top:20px;font-size:14px}
				</style>
				</div>
				<?php
				echo $args['after_widget'];
			}
		}
	}

	/* ========== #8 侧边信息统计 ========== */
	if ( ! class_exists( 'zhiji_Widget_SiteStat' ) ) {
		class zhiji_Widget_SiteStat extends WP_Widget {
			function __construct() {
				parent::__construct( 'zhiji_site_stat', 'zhiji·侧边统计', array( 'classname' => 'widget_zhiji_ss', 'description' => '侧边栏整站统计（彩色渐变卡片）' ) );
			}
			function form( $instance ) {
				$title = $instance['title'] ?? '网站信息统计';
				$date  = $instance['date'] ?? '2024-01-01';
				?>
				<p><label>标题：</label><input class="widefat" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo esc_attr( $title ); ?>"></p>
				<p><label>建站时间（Y-m-d）：</label><input class="widefat" name="<?php echo $this->get_field_name( 'date' ); ?>" value="<?php echo esc_attr( $date ); ?>"></p>
				<?php
			}
			function update( $new, $old ) {
				$instance = $old;
				$instance['title'] = strip_tags( $new['title'] );
				$instance['date']  = strip_tags( $new['date'] );
				return $instance;
			}
			function widget( $args, $instance ) {
				global $wpdb;
				$title = $instance['title'] ?? '网站信息统计';
				$date  = $instance['date'] ?? '2024-01-01';
				$count_posts = wp_count_posts();
				$published = $count_posts->publish;
				$comments = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->comments" );
				$time = floor( ( time() - strtotime( $date ) ) / 86400 );
				$time = $time > 0 ? $time : 0;
				$tags = wp_count_terms( 'post_tag' );
				$pages = wp_count_posts( 'page' );
				$pages_pub = $pages->publish;
				$links = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->links WHERE link_visible='Y'" );
				$users = $wpdb->get_var( "SELECT COUNT(ID) FROM $wpdb->users" );
				$last = $wpdb->get_results( "SELECT MAX(post_modified) AS M FROM $wpdb->posts WHERE (post_type='post' OR post_type='page') AND (post_status='publish' OR post_status='private')" );
				$last = $last ? date( 'Y-m-d', strtotime( $last[0]->M ) ) : '';
				$views = $wpdb->get_var( "SELECT SUM(meta_value+0) FROM $wpdb->postmeta WHERE meta_key='views'" );
				$views = $views ?: 0;
				$rows = array(
					'文章总数' => $published . ' 篇',
					'评论数目' => $comments . ' 条',
					'标签总数' => $tags . ' 个',
					'浏览次数' => $views . ' 次',
					'友链总数' => $links . ' 个',
					'用户总数' => $users . ' 个',
					'运行天数' => $time . ' 天',
					'建站时间' => $date,
					'最后更新' => $last,
				);
				echo $args['before_widget'];
				echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
				?>
				<div class="zhiji-site-stat">
				<ul>
				<?php $i = 1; foreach ( $rows as $k => $v ): ?>
					<li class="zhiji-ss-bg zhiji-ss-<?php echo $i; ?>"><div class="zhiji-ss-main"><div class="zhiji-ss-meat"><span><?php echo esc_html( $k ); ?>：</span><?php echo esc_html( $v ); ?></div></div></li>
				<?php $i++; endforeach; ?>
					<li class="zhiji-ss-bg zhiji-ss-10"><div class="zhiji-ss-main"><div class="zhiji-ss-meat"><span>数据查询：</span><?php echo (int) get_num_queries(); ?> 次</div></div></li>
					<li class="zhiji-ss-bg zhiji-ss-11"><div class="zhiji-ss-main"><div class="zhiji-ss-meat"><span>生成耗时：</span><?php echo timer_stop( 0, 5 ); ?>秒</div></div></li>
				</ul>
				<style>
				.zhiji-site-stat ul{margin:0;padding:0;list-style:none}
				.zhiji-site-stat .zhiji-ss-bg{margin:4px;border-radius:8px;cursor:pointer;transition:all .3s}
				.zhiji-site-stat .zhiji-ss-bg:hover{transform:translateX(-10px)}
				.zhiji-site-stat .zhiji-ss-main{display:flex;align-items:center;justify-content:space-around;padding:10px}
				.zhiji-site-stat .zhiji-ss-meat{color:#fff;font-weight:700;line-height:1.5}
				.zhiji-ss-1{background:var(--zhiji-gradient,linear-gradient(135deg,#667eea,#764ba2))}
				.zhiji-ss-2{background:linear-gradient(135deg,#f093fb,#f5576c)}
				.zhiji-ss-3{background:linear-gradient(135deg,#4facfe,#00f2fe)}
				.zhiji-ss-4{background:linear-gradient(135deg,#43e97b,#38f9d7)}
				.zhiji-ss-5{background:linear-gradient(135deg,#fa709a,#fee140)}
				.zhiji-ss-6{background:linear-gradient(135deg,#30cfd0,#330867)}
				.zhiji-ss-7{background:linear-gradient(135deg,#a8edea,#fed6e3)}
				.zhiji-ss-8{background:linear-gradient(135deg,#ff9a9e,#fecfef)}
				.zhiji-ss-9{background:linear-gradient(135deg,#fbc2eb,#a6c1ee)}
				.zhiji-ss-10{background:linear-gradient(135deg,#84fab0,#8fd3f4)}
				.zhiji-ss-11{background:linear-gradient(135deg,#fccb90,#d57eeb)}
				</style>
				</div>
				<?php
				echo $args['after_widget'];
			}
		}
	}

	/* 统一注册小工具（仅在启用时注册） */
	if ( ! function_exists( 'zhiji_register_transplant_widgets' ) ) {
		function zhiji_register_transplant_widgets() {
			if ( ! zhiji_widgets_beautify_is_enabled() ) {
				return;
			}
			register_widget( 'zhiji_Widget_ScrollBroadcast' );
			register_widget( 'zhiji_Widget_SimpleModule' );
			register_widget( 'zhiji_Widget_IpLabel' );
			register_widget( 'zhiji_Widget_Contribute' );
			register_widget( 'zhiji_Widget_Marquee' );
			register_widget( 'zhiji_Widget_GuideCard' );
			register_widget( 'zhiji_Widget_SiteStat' );
		}
		add_action( 'widgets_init', 'zhiji_register_transplant_widgets' );
	}
