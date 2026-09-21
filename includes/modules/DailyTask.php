<?php
/**
 * @module  DailyTask
 * @desc    每日任务与宝箱奖励（积分）
 * @option  daily_task_enabled  总开关
 * @hook    init · 任务判定
 * @hook    user_center 页签 · 任务面板
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/DailyTask.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('daily_task', array(
    'title'    => '每日任务',
    'parent'   => 'zhiji_user',
    'priority' => 120,
    'option'   => 'daily_task_enabled',
));



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 获取今日日期 key
 */
function zhiji_daily_task_date_key() {
	return 'zhiji_daily_tasks_' . current_time( 'Y-m-d' );
}

/**
 * 获取用户今日任务完成状态
 */
function zhiji_daily_task_get_status( $user_id ) {
	$key    = zhiji_daily_task_date_key();
	$status = get_user_meta( $user_id, $key, true );
	if ( ! is_array( $status ) ) {
		$status = array();
	}
	return $status;
}

/**
 * 更新用户今日任务完成状态
 */
function zhiji_daily_task_update_status( $user_id, $task_id, $completed = true ) {
	$status = zhiji_daily_task_get_status( $user_id );
	if ( $completed ) {
		$status[ $task_id ] = array(
			'completed_at' => current_time( 'mysql' ),
			'rewarded'     => false,
		);
	} else {
		unset( $status[ $task_id ] );
	}
	update_user_meta( $user_id, zhiji_daily_task_date_key(), $status );
	return $status;
}

/**
 * 检查任务是否已完成
 */
function zhiji_daily_task_is_completed( $user_id, $task_id ) {
	$status = zhiji_daily_task_get_status( $user_id );
	return isset( $status[ $task_id ] );
}

/**
 * 获取任务配置列表
 */
function zhiji_daily_task_get_tasks() {
	$tasks = array(
		'comment' => array(
			'id'          => 'comment',
			'name'        => '发表评论',
			'desc'        => '发表一条评论',
			'icon'        => '💬',
			'default_on'  => true,
			'default_reward' => 5,
		),
		'share'   => array(
			'id'          => 'share',
			'name'        => '分享文章',
			'desc'        => '分享一篇文章给好友',
			'icon'        => '🔗',
			'default_on'  => true,
			'default_reward' => 8,
		),
		'browse'  => array(
			'id'          => 'browse',
			'name'        => '浏览文章',
			'desc'        => '浏览3篇文章',
			'icon'        => '📖',
			'default_on'  => true,
			'default_reward' => 5,
			'target'      => 3,
		),
	);

	// 从后台配置读取开关和奖励
	foreach ( $tasks as $id => &$task ) {
		$task['enabled'] = (bool) zhiji_get_option( 'task_' . $id . '_enabled', $task['default_on'] );
		$task['reward']  = (int) zhiji_get_option( 'task_' . $id . '_reward', $task['default_reward'] );
	}
	unset( $task );

	return $tasks;
}

/**
 * 获取已启用的任务
 */
function zhiji_daily_task_get_enabled_tasks() {
	$tasks = zhiji_daily_task_get_tasks();
	return array_filter( $tasks, function ( $t ) {
		return ! empty( $t['enabled'] );
	} );
}

/**
 * 完成任务并发放奖励
 */
function zhiji_daily_task_complete( $user_id, $task_id ) {
	if ( ! $user_id || ! $task_id ) {
		return false;
	}

	$tasks = zhiji_daily_task_get_enabled_tasks();
	if ( ! isset( $tasks[ $task_id ] ) ) {
		return false;
	}

	if ( zhiji_daily_task_is_completed( $user_id, $task_id ) ) {
		return false; // 已完成
	}

	$task = $tasks[ $task_id ];
	$reward = max( 0, (int) $task['reward'] );

	// 更新完成状态
	zhiji_daily_task_update_status( $user_id, $task_id, true );

	// 发放积分奖励
	if ( $reward > 0 ) {
		Zhiji_Adapter::update_user_points( $user_id, array(
			'value' => $reward,
			'type'  => 'increase',
			'desc'  => '每日任务-' . $task['name'],
		) );
	}

	// 发送通知
	if ( function_exists( 'zhiji_reward_notify' ) ) {
		zhiji_reward_notify( $user_id, array(
			'source'  => 'daily_task',
			'type'    => 'points',
			'value'   => $reward,
			'desc'    => '完成每日任务「' . $task['name'] . '」',
		) );
	}

	// 检查是否全部完成，发放宝箱奖励
	zhiji_daily_task_check_all_completed( $user_id );

	return true;
}

/**
 * 检查是否全部任务完成，发放宝箱奖励
 */
function zhiji_daily_task_check_all_completed( $user_id ) {
	$tasks   = zhiji_daily_task_get_enabled_tasks();
	$status  = zhiji_daily_task_get_status( $user_id );
	$all_done = true;

	foreach ( $tasks as $id => $task ) {
		if ( ! isset( $status[ $id ] ) ) {
			$all_done = false;
			break;
		}
	}

	if ( ! $all_done ) {
		return;
	}

	// 检查宝箱是否已领取
	$chest_key = 'zhiji_daily_chest_' . current_time( 'Y-m-d' );
	if ( get_user_meta( $user_id, $chest_key, true ) ) {
		return; // 已领取
	}

	$chest_reward = (int) zhiji_get_option( 'task_chest_reward', 20 );
	if ( $chest_reward > 0 ) {
		Zhiji_Adapter::update_user_points( $user_id, array(
			'value' => $chest_reward,
			'type'  => 'increase',
			'desc'  => '每日任务-全部完成宝箱',
		) );
	}

	update_user_meta( $user_id, $chest_key, true );

	// 发送通知
	if ( function_exists( 'zhiji_reward_notify' ) ) {
		zhiji_reward_notify( $user_id, array(
			'source'  => 'daily_task',
			'type'    => 'points',
			'value'   => $chest_reward,
			'desc'    => '完成全部每日任务，获得宝箱奖励',
		) );
	}
}

/**
 * 评论发布时触发任务
 */
function zhiji_daily_task_on_comment( $comment_id, $comment_approved, $commentdata ) {
	if ( $comment_approved !== 1 ) {
		return; // 只处理已批准的评论
	}
	$user_id = isset( $commentdata['user_id'] ) ? $commentdata['user_id'] : 0;
	if ( $user_id ) {
		zhiji_daily_task_complete( $user_id, 'comment' );
	}
}
add_action( 'comment_post', 'zhiji_daily_task_on_comment', 10, 3 );

/**
 * 文章浏览时触发任务（记录浏览次数）
 */
function zhiji_daily_task_on_browse() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return;
	}
	if ( zhiji_daily_task_is_completed( $user_id, 'browse' ) ) {
		return;
	}

	// 记录今日浏览的文章ID（去重）
	$browse_key = 'zhiji_daily_browse_' . current_time( 'Y-m-d' );
	$browsed    = get_user_meta( $user_id, $browse_key, true );
	if ( ! is_array( $browsed ) ) {
		$browsed = array();
	}

	$post_id = get_the_ID();
	if ( ! in_array( $post_id, $browsed, true ) ) {
		$browsed[] = $post_id;
		update_user_meta( $user_id, $browse_key, $browsed );
	}

	$tasks  = zhiji_daily_task_get_enabled_tasks();
	$target = isset( $tasks['browse']['target'] ) ? (int) $tasks['browse']['target'] : 3;

	if ( count( $browsed ) >= $target ) {
		zhiji_daily_task_complete( $user_id, 'browse' );
	}
}
add_action( 'wp_head', 'zhiji_daily_task_on_browse' );

/**
 * 分享文章触发任务（通过 URL 参数 ?zhiji_shared=1）
 */
function zhiji_daily_task_on_share() {
	if ( ! isset( $_GET['zhiji_shared'] ) || $_GET['zhiji_shared'] != 1 ) {
		return;
	}
	$user_id = get_current_user_id();
	if ( $user_id ) {
		zhiji_daily_task_complete( $user_id, 'share' );
	}
}
add_action( 'init', 'zhiji_daily_task_on_share' );

/**
 * 个人中心侧边栏「我的服务」增加「每日任务」按钮
 */
function zhiji_daily_task_sidebar_button( $buttons ) {
	$buttons[] = array(
		'html' => '',
		'icon' => '<svg class="icon" aria-hidden="true" width="1em" height="1em" viewBox="0 0 1024 1024"><path d="M832 128H192c-35.2 0-64 28.8-64 64v640c0 35.2 28.8 64 64 64h640c35.2 0 64-28.8 64-64V192c0-35.2-28.8-64-64-64z m-41.6 256L460.8 640c-12.8 12.8-32 12.8-44.8 0L288 512c-12.8-12.8-12.8-32 0-44.8s32-12.8 44.8 0l89.6 89.6 281.6-281.6c12.8-12.8 32-12.8 44.8 0s12.8 32 0 44.8z" fill="currentColor"/></svg>',
		'name' => '每日任务',
		'tab'  => 'daily_task',
	);
	return $buttons;
}
add_filter( 'zib_user_center_page_sidebar_button_1_args', 'zhiji_daily_task_sidebar_button' );

/**
 * 个人中心增加「每日任务」tab（子比主题过滤器 user_ctnter_main_tabs_array）
 */
function zhiji_daily_task_user_tab( $tabs ) {
	$tabs['daily_task'] = array(
		'title'    => '每日任务',
		'nav_attr' => 'drawer-title="每日任务"',
		'loader'   => '<div class="zib-widget"><div class="mt10"><div class="placeholder k1 mb10"></div><div class="placeholder k1 mb10"></div><div class="placeholder s1"></div></div><p class="placeholder k1 mb30"></p><div class="placeholder t1 mb30"></p><p class="placeholder k1 mb30"></p><p style="height: 120px;" class="placeholder t1"></p></div>',
	);
	return $tabs;
}
add_filter( 'user_ctnter_main_tabs_array', 'zhiji_daily_task_user_tab' );

/**
 * 每日任务 tab 内容（通过 main_user_tab_content_daily_task 过滤器输出）
 */
function zhiji_daily_task_user_tab_content() {
	ob_start();
	$user_id = get_current_user_id();
	$tasks   = zhiji_daily_task_get_enabled_tasks();
	$status  = zhiji_daily_task_get_status( $user_id );
	$total   = count( $tasks );
	$done    = 0;
	foreach ( $tasks as $id => $task ) {
		if ( isset( $status[ $id ] ) ) {
			$done++;
		}
	}
	$chest_reward = (int) zhiji_get_option( 'task_chest_reward', 20 );
	$chest_done   = (bool) get_user_meta( $user_id, 'zhiji_daily_chest_' . current_time( 'Y-m-d' ), true );
	$progress     = $total > 0 ? round( $done / $total * 100 ) : 0;
	?>
	<div class="zhiji-daily-task-wrap">
		<div class="zhiji-daily-task-header">
			<div class="zhiji-daily-task-title">每日任务</div>
			<div class="zhiji-daily-task-progress">
				<div class="zhiji-daily-task-progress-bar">
					<div class="zhiji-daily-task-progress-fill" style="width: <?php echo esc_attr( $progress ); ?>%;"></div>
				</div>
				<span class="zhiji-daily-task-progress-text"><?php echo esc_html( $done ); ?>/<?php echo esc_html( $total ); ?></span>
			</div>
		</div>

		<div class="zhiji-daily-task-list">
			<?php foreach ( $tasks as $id => $task ) :
				$is_done = isset( $status[ $id ] );
				$browse_count = 0;
				if ( 'browse' === $id ) {
					$browse_key = 'zhiji_daily_browse_' . current_time( 'Y-m-d' );
					$browsed    = get_user_meta( $user_id, $browse_key, true );
					$browse_count = is_array( $browsed ) ? count( $browsed ) : 0;
				}
				?>
				<div class="zhiji-daily-task-item <?php echo $is_done ? 'is-done' : ''; ?>">
					<div class="zhiji-daily-task-icon"><?php echo esc_html( $task['icon'] ); ?></div>
					<div class="zhiji-daily-task-info">
						<div class="zhiji-daily-task-name"><?php echo esc_html( $task['name'] ); ?></div>
						<div class="zhiji-daily-task-desc">
							<?php echo esc_html( $task['desc'] ); ?>
							<?php if ( 'browse' === $id && ! $is_done ) : ?>
								（<?php echo esc_html( $browse_count ); ?>/<?php echo esc_attr( isset( $task['target'] ) ? $task['target'] : 3 ); ?>）
							<?php endif; ?>
						</div>
					</div>
					<div class="zhiji-daily-task-reward">+<?php echo esc_html( $task['reward'] ); ?> 积分</div>
					<div class="zhiji-daily-task-action">
						<?php if ( $is_done ) : ?>
							<span class="zhiji-daily-task-done">已完成</span>
						<?php else : ?>
							<span class="zhiji-daily-task-go">去完成</span>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="zhiji-daily-task-chest <?php echo $chest_done ? 'is-done' : ( $done >= $total ? 'can-open' : '' ); ?>">
			<div class="zhiji-daily-task-chest-icon">🎁</div>
			<div class="zhiji-daily-task-chest-info">
				<div class="zhiji-daily-task-chest-title">全部完成宝箱</div>
				<div class="zhiji-daily-task-chest-desc">完成全部任务可额外获得 <?php echo esc_html( $chest_reward ); ?> 积分</div>
			</div>
			<div class="zhiji-daily-task-chest-status">
				<?php if ( $chest_done ) : ?>
					<span class="zhiji-daily-task-done">已领取</span>
				<?php elseif ( $done >= $total ) : ?>
					<span class="zhiji-daily-task-can-open">已发放</span>
				<?php else : ?>
					<span class="zhiji-daily-task-locked">还差 <?php echo esc_html( $total - $done ); ?> 个任务</span>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<style>
	.zhiji-daily-task-wrap { padding: 16px; }
	.zhiji-daily-task-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
	.zhiji-daily-task-title { font-size: 18px; font-weight: 700; color: #333; }
	.zhiji-daily-task-progress { display: flex; align-items: center; gap: 10px; flex: 1; max-width: 200px; margin-left: 20px; }
	.zhiji-daily-task-progress-bar { flex: 1; height: 8px; background: #eee; border-radius: 4px; overflow: hidden; }
	.zhiji-daily-task-progress-fill { height: 100%; background: linear-gradient(90deg, var(--zhiji-brand, #2e7cf6), var(--zhiji-raw-blue-deep, #1a5fd0)); border-radius: 4px; transition: width .3s; }
	.zhiji-daily-task-progress-text { font-size: 13px; color: #666; white-space: nowrap; }
	.zhiji-daily-task-list { display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px; }
	.zhiji-daily-task-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: #f9f9f9; border-radius: 10px; transition: all .2s; }
	.zhiji-daily-task-item.is-done { background: #f0fff4; opacity: .8; }
	.zhiji-daily-task-icon { font-size: 24px; width: 40px; text-align: center; }
	.zhiji-daily-task-info { flex: 1; }
	.zhiji-daily-task-name { font-size: 15px; font-weight: 600; color: #333; margin-bottom: 2px; }
	.zhiji-daily-task-desc { font-size: 12px; color: #999; }
	.zhiji-daily-task-reward { font-size: 13px; font-weight: 600; color: var(--zhiji-brand, #2e7cf6); white-space: nowrap; }
	.zhiji-daily-task-action { min-width: 70px; text-align: center; }
	.zhiji-daily-task-btn { padding: 6px 16px; border: none; border-radius: 16px; background: linear-gradient(135deg, var(--zhiji-brand, #2e7cf6), var(--zhiji-raw-blue-deep, #1a5fd0)); color: #fff; font-size: 12px; cursor: pointer; transition: all .2s; }
	.zhiji-daily-task-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(240,68,148,.3); }
	.zhiji-daily-task-done { font-size: 12px; color: #5cb85c; font-weight: 600; }
	.zhiji-daily-task-go { font-size: 12px; color: #999; }
	.zhiji-daily-task-can-open { font-size: 12px; color: var(--zhiji-brand, #2e7cf6); font-weight: 600; }
	.zhiji-daily-task-locked { font-size: 12px; color: #ccc; }
	.zhiji-daily-task-chest { display: flex; align-items: center; gap: 12px; padding: 16px; background: linear-gradient(135deg, #fff8e1, #ffecb3); border-radius: 12px; border: 1px solid #ffe082; }
	.zhiji-daily-task-chest.is-done { opacity: .6; }
	.zhiji-daily-task-chest-icon { font-size: 32px; }
	.zhiji-daily-task-chest-info { flex: 1; }
	.zhiji-daily-task-chest-title { font-size: 15px; font-weight: 700; color: #f57c00; margin-bottom: 2px; }
	.zhiji-daily-task-chest-desc { font-size: 12px; color: #f9a825; }
	.zhiji-daily-task-chest-status { min-width: 70px; text-align: center; }
	</style>
	<?php
	return Zhiji_Adapter::ajaxpager_one_centent( ob_get_clean() );
}
add_filter( 'main_user_tab_content_daily_task', 'zhiji_daily_task_user_tab_content' );

/**
 * 后台配置：每日任务分区
 */
add_action( 'after_setup_theme', function () {
	
	$fields = array(
		array(
			'id'      => 'task_chest_reward',
			'type'    => 'number',
			'title'   => '全部完成宝箱奖励（积分）',
			'desc'    => '完成全部每日任务后额外发放的积分奖励',
			'default' => 20,
			'min'     => 0,
		),
	);

	$tasks = zhiji_daily_task_get_tasks();
	foreach ( $tasks as $id => $task ) {
		$fields[] = array(
			'id'      => 'task_' . $id . '_enabled',
			'type'    => 'switcher',
			'title'   => $task['name'] . ' - 启用',
			'desc'    => $task['desc'],
			'default' => $task['default_on'],
		);
		$fields[] = array(
			'id'      => 'task_' . $id . '_reward',
			'type'    => 'number',
			'title'   => $task['name'] . ' - 奖励积分',
			'desc'    => '完成该任务发放的积分数量',
			'default' => $task['default_reward'],
			'min'     => 0,
			'dependency' => array( 'task_' . $id . '_enabled', '==', true ),
		);
	}

	Zhiji_Registry::csf_section_for_legacy( 'daily_task', array(
		'title'  => '每日任务',
		'icon'   => 'fa fa-tasks',
		'parent' => 'zhiji_user',
		'priority' => 90,
		'fields' => $fields,
	) );
}, 20 );

/* ============================================================
 * 路由兼容：父主题用户中心 rewrite 规则为 user/([A-Za-z]+)$，
 * 不匹配含下划线的 tab（daily_task、zhijitask 等），导致 404。
 * 子主题补注册更宽规则；必须用 add_rewrite_rule('top') 前插，
 * 追加到末尾会被 WP 默认 catch-all 规则（pagename）抢先匹配。
 * ============================================================ */
add_action( 'init', function () {
	$slug = trim( function_exists( '_pz' ) ? _pz( 'user_center_rewrite_slug', 'user' ) : 'user' );
	$slug = $slug ? $slug : 'user';
	add_rewrite_rule( $slug . '/([A-Za-z_]+)$', 'index.php?user_center=$matches[1]', 'top' );
}, 10 );

// 首次启用时刷新重写规则（v2 标记，重新触发一次 flush）
add_action( 'init', function () {
	if ( get_option( 'zhiji_usercenter_rewrite_flushed_v2' ) ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'zhiji_usercenter_rewrite_flushed_v2', 1 );
}, 20 );
