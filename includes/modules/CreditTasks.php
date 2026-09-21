<?php
/**
 * @module  CreditTasks
 * @desc    积分任务（邀请注册/分享/完善资料）
 * @option  credit_tasks_enabled  总开关
 * @hook    wp_ajax(_nopriv)_zhiji_share_reward · 分享奖励
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/CreditTasks.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('credit_tasks', array(
    'title'    => '积分任务',
    'parent'   => 'zhiji_user',
    'priority' => 140,
    'option'   => 'credit_tasks_enabled',
));



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
 * 后台 CSF 设置：用户&互动 → 成长积分任务
 * ============================================================ */
add_action( 'after_setup_theme', function () {
	
	$default_tasks = array(
		array( 'title' => '邀请好友注册', 'type' => 'invite', 'reward' => 'points', 'value' => '20', 'limit' => '0', 'enabled' => '1', 'desc' => '每成功邀请 1 人注册得 20 积分' ),
		array( 'title' => '分享内容', 'type' => 'share', 'reward' => 'points', 'value' => '1', 'limit' => '5', 'enabled' => '1', 'desc' => '每次分享得 1 积分（每日上限 5）' ),
		array( 'title' => '完善资料', 'type' => 'profile', 'reward' => 'points', 'value' => '5', 'limit' => '0', 'enabled' => '1', 'desc' => '完善头像/简介各得 5 积分（每项限一次）' ),
		array( 'title' => '连续活跃', 'type' => 'streak', 'reward' => 'points', 'value' => '15', 'limit' => '7', 'enabled' => '1', 'desc' => '连续活跃 7 天额外得 15 积分' ),
		array( 'title' => '发表评论', 'type' => 'comment', 'reward' => 'points', 'value' => '3', 'limit' => '5', 'enabled' => '1', 'desc' => '每次发表评论得 3 积分（每日上限 5）' ),
	);

	Zhiji_Registry::csf_section_for_legacy( 'credit_tasks', array(
		'title'  => '成长积分任务',
		'icon'   => 'fa fa-trophy',
		'parent' => 'zhiji_user',
		'priority' => 80,
		'fields' => array(
			array(
				'id'      => 'credit_tasks_enabled',
				'type'    => 'switcher',
				'title'   => '启用成长积分任务',
				'default' => false,
			),
			array(
				'type'    => 'submessage',
				'style'   => 'info',
				'content' => '补齐父主题积分体系缺失的运营型任务。积分/余额复用父主题 zibpay 发放。评论/分享奖励与「每日任务」互斥（P1-5）：每日任务启用对应任务时，此处自动跳过发放。连续签到由父主题原生签到覆盖，此处不重复。',
				'dependency' => array( 'credit_tasks_enabled', '==', '1' ),
			),
			array(
				'id'          => 'credit_tasks_list',
				'type'        => 'repeater',
				'title'       => '任务列表',
				'button_text' => '新增任务',
				'default'     => $default_tasks,
				'dependency'  => array( 'credit_tasks_enabled', '==', '1' ),
				'fields'      => array(
					array( 'id' => 'title', 'type' => 'text', 'title' => '任务名称' ),
					array(
						'id'      => 'type',
						'type'    => 'select',
						'title'   => '任务类型',
						'options' => array(
							'invite'  => '邀请注册',
							'share'   => '分享内容',
							'profile' => '完善资料',
							'streak'  => '连续活跃',
							'comment' => '发表评论',
							'custom'  => '自定义（仅展示，无自动触发）',
						),
						'default' => 'custom',
					),
					array(
						'id'      => 'reward',
						'type'    => 'select',
						'title'   => '奖励类型',
						'options' => array( 'points' => '积分', 'balance' => '余额' ),
						'default' => 'points',
					),
					array( 'id' => 'value', 'type' => 'number', 'title' => '奖励数值', 'default' => '10' ),
					array( 'id' => 'limit', 'type' => 'number', 'title' => '每日上限（0=不限）', 'default' => '0' ),
					array( 'id' => 'enabled', 'type' => 'switcher', 'title' => '启用该项', 'default' => '1' ),
					array( 'id' => 'desc', 'type' => 'text', 'title' => '任务说明' ),
				),
			),
		),
	) );
}, 20 );

/**
 * 判断成长积分任务是否启用。
 */
function zhiji_credit_tasks_is_enabled() {
	return filter_var( zhiji_get_option( 'credit_tasks_enabled', false ), FILTER_VALIDATE_BOOLEAN );
}

/**
 * 获取指定类型的启用任务列表。
 */
function zhiji_credit_tasks_of_type( $type ) {
	$tasks = zhiji_get_option( 'credit_tasks_list', array() );
	if ( ! is_array( $tasks ) ) {
		return array();
	}
	$result = array();
	foreach ( $tasks as $task ) {
		if ( ( $task['type'] ?? '' ) === $type && filter_var( $task['enabled'] ?? '1', FILTER_VALIDATE_BOOLEAN ) ) {
			$result[] = $task;
		}
	}
	return $result;
}

/**
 * 发放奖励（积分/余额）。
 */
/**
 * 发放奖励（积分/余额）。
 */
function zhiji_credit_grant_reward( $uid, $task ) {
	$value = (int) ( $task['value'] ?? 0 );
	$reward = $task['reward'] ?? 'points';
	$desc = $task['title'] ?? '成长任务';
	if ( $value <= 0 ) {
		return false;
	}
	if ( 'points' === $reward ) {
		Zhiji_Adapter::update_user_points( $uid, array( 'value' => $value, 'type' => '知集任务', 'desc' => $desc ) );
		zhiji_credit_reward_notify( $uid, $reward, $value, $desc );
		return true;
	}
	if ( 'balance' === $reward ) {
		Zhiji_Adapter::update_user_balance( $uid, array( 'value' => $value, 'type' => '知集任务', 'desc' => $desc ) );
		zhiji_credit_reward_notify( $uid, $reward, $value, $desc );
		return true;
	}
	return false;
}

/**
 * 统一奖励通知（系统通知 + 邮件），对齐 DailyTask 调用格式。
 */
function zhiji_credit_reward_notify( $uid, $reward_type, $value, $desc ) {
	if ( function_exists( 'zhiji_reward_notify' ) ) {
		zhiji_reward_notify( $uid, array(
			'source' => 'credit_task',
			'type'   => $reward_type,
			'value'  => $value,
			'desc'   => '完成成长任务「' . $desc . '」',
		) );
	}
}

/**
 * 每日计数（用户meta）。
 */
function zhiji_credit_daily_count( $uid, $type ) {
	$today = gmdate( 'Y-m-d' );
	$meta = get_user_meta( $uid, 'zhiji_credit_daily', true );
	if ( ! is_array( $meta ) ) {
		$meta = array();
	}
	if ( ( $meta['date'] ?? '' ) !== $today ) {
		return 0;
	}
	return (int) ( $meta[ $type ] ?? 0 );
}

function zhiji_credit_inc_daily_count( $uid, $type ) {
	$today = gmdate( 'Y-m-d' );
	$meta = get_user_meta( $uid, 'zhiji_credit_daily', true );
	if ( ! is_array( $meta ) || ( $meta['date'] ?? '' ) !== $today ) {
		$meta = array( 'date' => $today );
	}
	$meta[ $type ] = (int) ( $meta[ $type ] ?? 0 ) + 1;
	update_user_meta( $uid, 'zhiji_credit_daily', $meta );
}

/* ============================================================
 * 任务钩子
 * ============================================================ */
if ( zhiji_credit_tasks_is_enabled() ) {

	// 分享奖励（前端 AJAX 调用）
	add_action( 'wp_ajax_zhiji_share_reward', function () {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( '请先登录' );
		}
		$uid = get_current_user_id();
		// P1-5 互斥：分享积分由「每日任务」发放时（DailyTask share 启用），此处跳过避免双发
		if ( function_exists( 'zhiji_daily_task_get_enabled_tasks' ) && isset( zhiji_daily_task_get_enabled_tasks()['share'] ) ) {
			wp_send_json_error( '分享奖励已由「每日任务」发放' );
		}
		$granted = false;
		foreach ( zhiji_credit_tasks_of_type( 'share' ) as $task ) {
			$limit = (int) ( $task['limit'] ?? 0 );
			if ( $limit > 0 && zhiji_credit_daily_count( $uid, 'share' ) >= $limit ) {
				continue;
			}
			zhiji_credit_grant_reward( $uid, $task );
			zhiji_credit_inc_daily_count( $uid, 'share' );
			$granted = true;
		}
		if ( $granted ) {
			wp_send_json_success( array( 'msg' => '已发放分享奖励' ) );
		} else {
			wp_send_json_error( '今日分享奖励已达上限或未启用' );
		}
	} );
	add_action( 'wp_ajax_nopriv_zhiji_share_reward', function () {
		wp_send_json_error( '请先登录' );
	} );

	// 发表评论奖励
	add_action( 'comment_post', function ( $comment_id, $comment_approved, $commentdata ) {
		if ( $comment_approved !== 1 ) {
				return;
		}
		// P1-5 互斥：评论积分由「每日任务」发放时（DailyTask comment 启用），此处跳过避免双发
		if ( function_exists( 'zhiji_daily_task_get_enabled_tasks' ) && isset( zhiji_daily_task_get_enabled_tasks()['comment'] ) ) {
			return;
		}
		$uid = isset( $commentdata['user_id'] ) ? (int) $commentdata['user_id'] : 0;
		if ( ! $uid ) {
			return;
		}
		foreach ( zhiji_credit_tasks_of_type( 'comment' ) as $task ) {
			$limit = (int) ( $task['limit'] ?? 0 );
			if ( $limit > 0 && zhiji_credit_daily_count( $uid, 'comment' ) >= $limit ) {
				continue;
			}
			zhiji_credit_grant_reward( $uid, $task );
			zhiji_credit_inc_daily_count( $uid, 'comment' );
		}
	}, 20, 3 );

	// 连续活跃加成（每日登录）
	add_action( 'wp_login', function ( $user_login, $user ) {
		$uid = $user->ID;
		$need = 0;
		foreach ( zhiji_credit_tasks_of_type( 'streak' ) as $task ) {
			$need = max( $need, (int) ( $task['limit'] ?? 0 ) );
		}
		if ( $need < 1 ) {
			return;
		}
		$dates = (array) get_user_meta( $uid, 'zhiji_active_dates', true );
		$today = gmdate( 'Y-m-d' );
		if ( in_array( $today, $dates, true ) ) {
			return;
		}
		$dates[] = $today;
		$dates = array_slice( $dates, - ( $need + 2 ) );
		update_user_meta( $uid, 'zhiji_active_dates', $dates );

		$streak = 0;
		$cur = strtotime( $today );
		while ( in_array( gmdate( 'Y-m-d', $cur ), $dates, true ) ) {
			$streak++;
			$cur = strtotime( '-1 day', $cur );
		}
		$granted_today = get_user_meta( $uid, 'zhiji_streak_granted', true );
		if ( $streak >= $need && $granted_today !== $today ) {
			foreach ( zhiji_credit_tasks_of_type( 'streak' ) as $task ) {
				zhiji_credit_grant_reward( $uid, $task );
			}
			update_user_meta( $uid, 'zhiji_streak_granted', $today );
		}
	}, 20, 2 );

	// 邀请注册奖励（父主题邀请码钩子）
	add_action( 'zib_use_invit_code', function ( $code, $inviter_id ) {
		$inviter_id = (int) $inviter_id;
		if ( ! $inviter_id ) {
			return;
		}
		foreach ( zhiji_credit_tasks_of_type( 'invite' ) as $task ) {
			zhiji_credit_grant_reward( $inviter_id, $task );
		}
	}, 20, 2 );

	// 完善资料奖励
	add_action( 'profile_update', function ( $uid, $old_user_data ) {
		foreach ( zhiji_credit_tasks_of_type( 'profile' ) as $task ) {
			$done = (array) get_user_meta( $uid, 'zhiji_profile_tasks', true );
			$checks = array(
				'avatar' => (bool) get_user_meta( $uid, 'custom_avatar', true ),
				'desc'   => ! empty( get_userdata( $uid )->description ),
			);
			$changed = false;
			foreach ( $checks as $k => $ok ) {
				if ( $ok && empty( $done[ $k ] ) ) {
					zhiji_credit_grant_reward( $uid, $task );
					$done[ $k ] = 1;
					$changed = true;
				}
			}
			if ( $changed ) {
				update_user_meta( $uid, 'zhiji_profile_tasks', $done );
			}
		}
	}, 20, 2 );

	/* ============================================================
	 * 用户中心「成长任务」 tab
	 * ============================================================ */

	/**
	 * 渲染任务中心内容（供 tab content 字段直接调用）。
	 */
	function zhiji_credit_task_center_content() {
		$uid   = get_current_user_id();
		$tasks = zhiji_get_option( 'credit_tasks_list', array() );
		if ( ! is_array( $tasks ) ) {
			$tasks = array();
		}
		$brand = function_exists( 'zhiji_token_color' ) ? zhiji_token_color( 'brand' ) : '#2e7cf6';
		ob_start();
		?>
		<div class="zhiji-credit-tasks-box">
			<style>
			.zhiji-credit-tasks-box{padding:20px;}
			.zhiji-credit-tasks-box h3{margin:0 0 20px;font-size:18px;}
			.zhiji-task-item{display:flex;align-items:center;justify-content:space-between;padding:15px;margin-bottom:12px;background:var(--body-bg-color);border-radius:8px;border:1px solid rgba(0,0,0,.06);transition:all .3s;}
			.zhiji-task-item:hover{border-color:<?php echo esc_attr( $brand ); ?>;transform:translateX(4px);}
			.zhiji-task-info{flex:1;}
			.zhiji-task-title{font-size:15px;font-weight:600;margin-bottom:4px;}
			.zhiji-task-desc{font-size:12px;color:#999;}
			.zhiji-task-reward{text-align:right;min-width:100px;}
			.zhiji-task-reward .num{font-size:20px;font-weight:bold;color:<?php echo esc_attr( $brand ); ?>;}
			.zhiji-task-reward .type{font-size:12px;color:#999;}
			.zhiji-task-progress{font-size:12px;color:#999;margin-top:4px;}
			.zhiji-share-btn{display:inline-block;padding:8px 20px;background:<?php echo esc_attr( $brand ); ?>;color:#fff;border-radius:6px;font-size:13px;cursor:pointer;transition:all .3s;border:none;margin-top:6px;}
			.zhiji-share-btn:hover{opacity:.85;}
			.zhiji-share-btn[disabled]{background:#9ca3af;cursor:not-allowed;opacity:1;}
			</style>
			<h3>成长任务中心</h3>
			<?php if ( empty( $tasks ) ) : ?>
				<p style="color:#999;text-align:center;padding:40px;">暂无任务</p>
			<?php else : ?>
				<?php foreach ( $tasks as $task ) :
					$enabled = filter_var( $task['enabled'] ?? '1', FILTER_VALIDATE_BOOLEAN );
					if ( ! $enabled ) continue;
					$type = $task['type'] ?? 'custom';
					$limit = (int) ( $task['limit'] ?? 0 );
					$today_count = ( $limit > 0 && in_array( $type, array( 'share', 'comment' ), true ) ) ? zhiji_credit_daily_count( $uid, $type ) : 0;
					$reward_text = ( 'points' === ( $task['reward'] ?? 'points' ) ) ? '积分' : '余额';
				?>
				<div class="zhiji-task-item">
					<div class="zhiji-task-info">
						<div class="zhiji-task-title"><?php echo esc_html( $task['title'] ?? '未命名任务' ); ?></div>
						<div class="zhiji-task-desc"><?php echo esc_html( $task['desc'] ?? '' ); ?></div>
						<?php if ( $limit > 0 && in_array( $type, array( 'share', 'comment' ), true ) ) : ?>
							<div class="zhiji-task-progress">今日进度：<?php echo (int) $today_count; ?>/<?php echo (int) $limit; ?></div>
						<?php endif; ?>
					</div>
					<div class="zhiji-task-reward">
						<div class="num">+<?php echo (int) ( $task['value'] ?? 0 ); ?></div>
						<div class="type"><?php echo esc_html( $reward_text ); ?></div>
						<?php if ( 'share' === $type ) :
							$share_by_daily = function_exists( 'zhiji_daily_task_get_enabled_tasks' ) && isset( zhiji_daily_task_get_enabled_tasks()['share'] );
							$share_locked   = $limit > 0 && $today_count >= $limit;
							if ( $share_by_daily ) : ?>
								<div class="zhiji-task-progress">分享奖励由每日任务自动发放</div>
							<?php elseif ( $share_locked ) : ?>
								<button class="zhiji-share-btn" disabled>今日已达上限</button>
							<?php else : ?>
								<button class="zhiji-share-btn" onclick="zhiji_claim_share_reward(this)">领取</button>
							<?php endif;
						endif; ?>
					</div>
				</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<script>
		var zhiji_claim_share_reward = function(btn) {
			btn.disabled = true;
			btn.textContent = '领取中...';
			fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded'},
				body: 'action=zhiji_share_reward'
			}).then(r => r.json()).then(data => {
				if (data.success) {
					var item = btn.closest('.zhiji-task-item');
					var prog = item ? item.querySelector('.zhiji-task-progress') : null;
					var hitLimit = false;
					if (prog && prog.textContent.indexOf('今日进度') === 0) {
						var m = prog.textContent.match(/(\d+)\/(\d+)/);
						if (m) {
							var cur = parseInt(m[1], 10) + 1, lim = parseInt(m[2], 10);
							prog.textContent = '今日进度：' + cur + '/' + lim;
							if (cur >= lim) { hitLimit = true; }
						}
					}
					if (hitLimit) {
						btn.disabled = true;
						btn.textContent = '今日已达上限';
						btn.style.background = '#9ca3af';
						btn.style.cursor = 'not-allowed';
					} else {
						btn.disabled = false;
						btn.textContent = '领取';
						btn.style.background = '';
					}
				} else {
					btn.textContent = data.data || '领取失败';
					btn.style.background = '#dc3545';
					setTimeout(() => { btn.disabled = false; btn.textContent = '领取'; btn.style.background = ''; }, 2000);
				}
			}).catch(() => {
				btn.disabled = false;
				btn.textContent = '领取';
			});
		}
		</script>
		<?php
		return ob_get_clean();
	}

	add_filter( 'user_ctnter_main_tabs_array', function ( $tabs ) {
		if ( is_admin() || ! is_array( $tabs ) ) {
			return $tabs;
		}
		$task_tab = array(
			'title'    => '成长任务',
			'icon'     => 'fa fa-trophy',
			'nav_attr' => 'drawer-title="' . esc_attr( '成长任务' ) . '"',
			'content'  => zhiji_credit_task_center_content(),
		);
		// 置首位以保证默认激活并内联 content
		return array( 'zhijitask' => $task_tab ) + $tabs;
	}, 20 );
}
