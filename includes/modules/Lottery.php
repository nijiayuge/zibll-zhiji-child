<?php
/**
 * @module  Lottery
 * @desc    抽奖大转盘（积分/余额消耗、多类型奖品、中奖通知）
 * @option  lottery_enabled  总开关
 * @hook    wp_ajax(_nopriv)_zhiji_lottery_draw · 抽奖
 * @hook    shortcode zhiji_lottery
 * @hook    cron zhiji_lottery_send_win_mail
 * @since   2.0.0
 * @migrate 自 v1 `inc/Functions/Lottery.php`
 *          （v2 迁移：CSF 块转 csf_section_for_legacy、常量与命名规范对齐）
 */

defined('ABSPATH') || exit;

Zhiji_Registry::register_module('lottery', array(
    'title'    => '抽奖大转盘',
    'parent'   => 'zhiji_user',
    'priority' => 60,
    'option'   => 'lottery_enabled',
));



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
 * 后台 CSF 设置：用户&互动 → 抽奖大转盘
 * ============================================================ */
    // 2026-09-26：改为 Registry 统一登记（P3-⑨），钩子由核心统一挂载
    Zhiji_Registry::register_options('lottery', array(
			array(
				'id'      => 'lottery_enabled',
				'type'    => 'switcher',
				'title'   => '启用抽奖大转盘',
				'default' => false,
				'desc'    => '开启后页面右侧显示抽奖悬浮按钮，点击弹出大转盘抽奖。',
			),
			array(
				'id'         => 'lottery_daily_free',
				'type'       => 'number',
				'title'      => '每日免费抽奖次数',
				'desc'       => __( '每天可免费抽奖的次数（0 = 需消耗积分才能抽）。', 'zhiji' ),
				'default'    => '1',
				'dependency' => array( 'lottery_enabled', '==', '1' ),
			),
			array(
				'id'         => 'lottery_prob_preset',
				'type'       => 'select',
				'title'      => '奖品池预设',
				'desc'       => __( '奖品池概率预设：影响各档奖品的中奖率分布。', 'zhiji' ),
				'default'    => 'lead',
				'options'    => array(
					'lead'     => '引导型（谢谢参与概率高）',
					'generous' => '慷慨型（中奖概率高）',
					'custom'   => '自定义（使用下方奖品池）',
				),
				'dependency' => array( 'lottery_enabled', '==', '1' ),
			),
			array(
				'id'         => 'lottery_prizes',
				'type'       => 'textarea',
				'title'      => '自定义奖品池',
				'default'    => "谢谢参与|none|0|40\n积分 20|points|20|25\n积分 50|points|50|15\n经验 100|level|100|10\n余额 1 元|balance|1|8\n优惠券 5 元|coupon|5|2\n会员 1 天|vip_day|1|1\n免单券|free|0|1",
				'desc'       => '每行一个奖品，格式：名称|类型|数值|权重。类型：none(谢谢参与)/points(积分)/balance(余额)/coupon(优惠码)/vip_day(会员天数)/vip_month(会员月数)/free(免单券)/level(经验值)',
				'dependency' => array( 'lottery_prob_preset', '==', 'custom' ),
			),
			array(
				'id'         => 'lottery_extra_cost',
				'type'       => 'number',
				'title'      => '积分兑换单价（0=关闭）',
				'desc'       => __( '额外抽奖需消耗的积分单价（0 = 关闭积分兑换）。', 'zhiji' ),
				'default'    => '0',
				'dependency' => array( 'lottery_enabled', '==', '1' ),
			),
			array(
				'id'         => 'lottery_extra_max',
				'type'       => 'number',
				'title'      => '每日积分兑换次数上限',
				'desc'       => __( '每天使用积分兑换抽奖的次数上限（0 = 不限）。', 'zhiji' ),
				'default'    => '5',
				'dependency' => array( 'lottery_enabled', '==', '1' ),
			),
			array(
				'id'         => 'lottery_share_daily',
				'type'       => 'number',
				'title'      => '每日分享得次数（0=关闭）',
				'desc'       => __( '每天分享可获得的额外抽奖次数（0 = 关闭）。', 'zhiji' ),
				'default'    => '1',
				'dependency' => array( 'lottery_enabled', '==', '1' ),
			),
			array(
				'id'         => 'lottery_daily_total',
				'type'       => 'number',
				'title'      => '每日总抽奖次数上限（0=不限）',
				'desc'       => __( '每天总抽奖次数上限，含免费与兑换（0 = 不限）。', 'zhiji' ),
				'default'    => '0',
				'dependency' => array( 'lottery_enabled', '==', '1' ),
			),
			array(
				'id'         => 'lottery_min_level',
				'type'       => 'number',
				'title'      => '最低参与等级（0=不限）',
				'desc'       => __( '参与抽奖的最低用户等级（0 = 不限）。', 'zhiji' ),
				'default'    => '0',
				'dependency' => array( 'lottery_enabled', '==', '1' ),
			),
			array(
				'id'         => 'lottery_size',
				'type'       => 'number',
				'title'      => '转盘直径（px）',
				'desc'       => __( '转盘的显示直径（像素），过小会影响移动端操作。', 'zhiji' ),
				'default'    => '240',
				'dependency' => array( 'lottery_enabled', '==', '1' ),
			),
			array(
				'id'         => 'lottery_show_stats',
				'type'       => 'switcher',
				'title'      => '显示累计/中奖统计',
				'desc'       => __( '是否在转盘下方显示中奖统计（人数/奖品）。', 'zhiji' ),
				'default'    => true,
				'dependency' => array( 'lottery_enabled', '==', '1' ),
			),
			array(
				'id'         => 'lottery_float_btn',
				'type'       => 'switcher',
				'title'      => '右侧悬浮按钮显示抽奖入口',
				'desc' => __( '是否在页面右侧悬浮按钮中增加抽奖入口。', 'zhiji' ),
				'default'    => true,
				'dependency' => array( 'lottery_enabled', '==', '1' ),
			),
			array(
				'id'         => 'lottery_coupon_days',
				'type'       => 'number',
				'title'      => '中奖优惠码有效期（天）',
				'desc' => __( '抽中优惠码奖品时的有效天数。', 'zhiji' ),
				'default'    => '30',
				'dependency' => array( 'lottery_enabled', '==', '1' ),
			),
			array(
				'id'         => 'lottery_coupon_title',
				'type'       => 'text',
				'title'      => '中奖优惠码名称来源标注',
				'default'    => '抽奖中奖',
				'desc'       => '后台优惠码列表「名称」列自动追加实际优惠金额',
				'dependency' => array( 'lottery_enabled', '==', '1' ),
			),
			array(
				'id'         => 'lottery_vip_level',
				'type'       => 'select',
				'title'      => '中奖会员等级',
				'desc' => __( '抽中会员奖品时赠送的等级。', 'zhiji' ),
				'default'    => '1',
				'options'    => array(
					'1' => '普通会员',
					'2' => '高级会员',
				),
				'dependency' => array( 'lottery_enabled', '==', '1' ),
			),
			array(
				'id'         => 'lottery_mail_enabled',
				'type'       => 'switcher',
				'title'      => '中奖邮件通知',
				'default'    => true,
				'dependency' => array( 'lottery_enabled', '==', '1' ),
			),
			array(
				'id'         => 'lottery_notice_enabled',
				'type'       => 'switcher',
				'title'      => '中奖站内通知',
				'desc'       => '抽中积分/经验/余额/会员时发送站内系统消息；优惠码/免单券类已自动附带优惠码通知，不受此开关影响',
				'default'    => true,
				'dependency' => array( 'lottery_enabled', '==', '1' ),
			),
			array(
				'id'         => 'lottery_mail_title',
				'type'       => 'text',
				'title'      => '中奖邮件标题',
				'default'    => '',
				'desc'       => '占位符：{site}{name}{prize}{value}，留空用默认',
				'dependency' => array( 'lottery_mail_enabled', '==', '1' ),
			),
			array(
				'id'         => 'lottery_mail_content',
				'type'       => 'textarea',
				'title'      => '中奖邮件正文',
				'default'    => '',
				'desc'       => '留空用内置精美模板',
				'dependency' => array( 'lottery_mail_enabled', '==', '1' ),
			),
		), 20);


/* -------------------------------------------------------------------------
 * 元数据 / 选项键（集中定义，避免散落字符串）
 * ---------------------------------------------------------------------- */
define( 'ZHIJI_LOTTERY_META_TODAY',     'zhiji_lottery_today' );     // 今日免费已用：日期|次数
define( 'ZHIJI_LOTTERY_META_TOTAL',     'zhiji_lottery_total' );     // 累计抽奖次数
define( 'ZHIJI_LOTTERY_META_WINS',      'zhiji_lottery_wins' );      // 累计中奖次数
define( 'ZHIJI_LOTTERY_META_EXTRA_DATE', 'zhiji_lottery_extra_date' ); // 积分兑换日期
define( 'ZHIJI_LOTTERY_META_EXTRA_CNT', 'zhiji_lottery_extra_count' ); // 积分兑换次数
define( 'ZHIJI_LOTTERY_META_SHARE_DATE', 'zhiji_lottery_share_date' ); // 分享日期
define( 'ZHIJI_LOTTERY_META_SHARE_CNT', 'zhiji_lottery_share_count' ); // 分享次数
define( 'ZHIJI_LOTTERY_META_DAILY_TOTAL', 'zhiji_lottery_daily_total' ); // 每日总抽次
define( 'ZHIJI_LOTTERY_LOG_OPTION',     'zhiji_lottery_log' );       // 全局抽奖日志

/**
 * 统一事件广播（联动扩展点）。
 *
 * 各业务模块在关键动作后调用，展示类模块（弹幕 / 实时动态 / 灵动岛等）
 * 通过 add_action 订阅即可实现跨功能联动，互不耦合。
 *
 * @param string $event 事件名（如 lottery_win / coupon_claimed）
 * @param array  $data  事件数据（uid / name / type / value 等）
 */
function zhiji_event_fire( $event, $data = array() ) {
	/**
	 * 通用事件（所有事件统一入口）
	 *
	 * @param string $event
	 * @param array  $data
	 */
	do_action( 'zhiji_event_fire', $event, $data );

	/** 细分事件（如 zhiji_event_lottery_win） */
	do_action( 'zhiji_event_' . $event, $data );
}

/**
 * 模块初始化：注册所有钩子（仅后台总开关开启时生效）。
 */
function zhiji_lottery_init() {
	if ( ! zhiji_get_option( 'lottery_enabled', 0 ) ) {
		return;
	}
	// 短代码 [zhiji_lottery] 输出触发按钮 + 预渲染弹窗
	add_shortcode( 'zhiji_lottery', 'zhiji_lottery_shortcode' );
	// 抽奖 / 分享 AJAX（未登录拒绝）
	// 2026-09-26：注册到网关（P2-⑥），旧端点保留为转发入口，前端调用点不变
	zhiji_api_register( 'zhiji_lottery_draw', 'zhiji_lottery_ajax_draw', false, '' );
	add_action( 'wp_ajax_nopriv_zhiji_lottery_draw', 'zhiji_lottery_ajax_require_login' );
	// 2026-09-26：注册到网关（P2-⑥），旧端点保留为转发入口，前端调用点不变
	zhiji_api_register( 'zhiji_lottery_share', 'zhiji_lottery_ajax_share', false, '' );
	add_action( 'wp_ajax_nopriv_zhiji_lottery_share', 'zhiji_lottery_ajax_require_login' );
	// 中奖邮件即时发送（前端特效播完后触发，保证送达不依赖下次访问；见 zhiji_lottery_ajax_send_mail）
	// 2026-09-26：注册到网关（P2-⑥），旧端点保留为转发入口，前端调用点不变
	zhiji_api_register( 'zhiji_lottery_send_mail', 'zhiji_lottery_ajax_send_mail', false, '' );
	add_action( 'wp_ajax_nopriv_zhiji_lottery_send_mail', 'zhiji_lottery_ajax_require_login' );
	// 消息角标刷新：业务 AJAX 产生站内消息后由前端主动拉取未读数（父主题无全局刷新接口）
	// 2026-09-26：注册到网关（P2-⑥），旧端点保留为转发入口，前端调用点不变
	zhiji_api_register( 'zhiji_msg_counts', 'zhiji_lottery_ajax_msg_counts', false, '' );
	add_action( 'wp_ajax_nopriv_zhiji_msg_counts', 'zhiji_lottery_ajax_require_login' );
	// 前台资源
	add_action( 'wp_enqueue_scripts', 'zhiji_lottery_enqueue' );
	// 全局弹窗：页面无短码时 wp_footer 自动挂载，保证悬浮按钮可弹出
	add_action( 'wp_footer', 'zhiji_lottery_global_modal' );
	// 融入父主题右侧悬浮按钮栏（zibll 的 zib_float_right 过滤器）
	if ( zhiji_get_option( 'lottery_float_btn', 1 ) ) {
		add_filter( 'zib_float_right', 'zhiji_lottery_float_right_btn' );
	}
	// 后台预设联动 JS（下拉预设一键填充奖品池）
	if ( is_admin() ) {
		add_action( 'admin_footer', 'zhiji_lottery_admin_preset_js' );
	}
}

/**
 * 前台资源入队（CSS / JS + AJAX 地址本地化）。
 */
function zhiji_lottery_enqueue() {
	if ( is_admin() ) {
		return;
	}
	// 本站点实测：wp_enqueue_scripts(head) 内联输出必然生效；wp_footer 在线上被环境干扰（脚本丢失/500）。
	// 因此 CSS / JS 全部在 head 内联输出（nowdoc，完全自包含，不依赖任何外部资源文件）。
	$lottery_css = <<<'ZHIJI_LOTTERY_CSS'
/* ============================================================
 * 知集·大抽奖 前端弹窗样式（交互层）
 * ------------------------------------------------------------
 * 设计要点（对应「点击抽奖弹窗闪烁/抖动」BUG 修复）：
 *  1) 弹窗用 opacity + visibility 控制显隐，绝不切换 display，避免重绘闪烁；
 *  2) 初始即预渲染 DOM（visibility:hidden），点击只加 .is-open 类做过渡；
 *  3) 隐藏时 pointer-events:none，避免隐形遮罩误挡页面点击；
 *  4) 卡片 transform:translateZ(0) 独立合成层，隔离父主题可能存在的 transform 上下文；
 *  5) z-index 拉高（100000）并以 .zhiji-lottery- 前缀命名，避免与 Zibll 弹窗/主题样式冲突。
 * ============================================================ */

/* 悬浮抽奖按钮：礼盒图标 —— 白色圆底 + 深橙金大图标，确保清晰可见 */
.zhiji-lottery-open-btn .fa-gift {
	color: #e8850c !important;
	font-size: 28px !important;
	line-height: 46px !important;
	width: 46px !important;
	height: 46px !important;
	text-align: center !important;
	border-radius: 50% !important;
	background: rgba(255,255,255,.95) !important;
	box-shadow: 0 2px 6px rgba(0,0,0,.15) !important;
	text-shadow: none !important;
	-webkit-text-stroke: 0 !important;
}
/* 悬停时礼盒图标外圈呼吸感（橙色实线圆环扩散脉冲，视觉明显） */
.zhiji-lottery-open-btn.float-btn .fa-gift {
	position: relative !important;
}
.zhiji-lottery-open-btn.float-btn .fa-gift::after {
	content: '';
	position: absolute;
	inset: -4px;
	border-radius: 50%;
	border: 5px solid rgba(232,133,12,.9);
	box-shadow: 0 0 0 7px rgba(255,107,53,.4);
	opacity: 0;
	pointer-events: none;
}
.zhiji-lottery-open-btn.float-btn:hover .fa-gift::after {
	animation: zhiji-gift-breathe 1.3s ease-out infinite;
}
@keyframes zhiji-gift-breathe {
	0% { transform: scale(1); opacity: 1; }
	100% { transform: scale(1.8); opacity: 0; }
}
/* 悬浮抽奖按钮：圆形悬浮球 + 底部胶囊文字标签（替代原瘦长条） */
.zhiji-lottery-open-btn.float-btn {
	width: 58px !important;
	height: 58px !important;
	border-radius: 50% !important;
	padding: 0 !important;
	background: linear-gradient(135deg, #ff9d7a 0%, #ff6f91 100%) !important;
	border: 2px solid rgba(255,255,255,.85) !important;
	box-shadow: 0 4px 16px rgba(255,111,145,.45), inset 0 1px 0 rgba(255,255,255,.4) !important;
	display: flex !important;
	align-items: center !important;
	justify-content: center !important;
	position: relative !important;
	transition: transform .2s ease, box-shadow .2s ease !important;
}
.zhiji-lottery-open-btn.float-btn .fa-gift {
	margin: 0 !important;
}
.zhiji-lottery-open-btn.float-btn span {
	position: absolute !important;
	top: calc(100% + 7px) !important;
	left: 50%;
	transform: translateX(-50%);
	z-index: 2;
	padding: 2px 9px;
	border-radius: 12px;
	background: rgba(255,255,255,.94);
	color: #ff5f85 !important;
	font-size: 12px !important;
	font-weight: 700 !important;
	line-height: 1.5 !important;
	white-space: nowrap;
	box-shadow: 0 2px 8px rgba(0,0,0,.1);
}
.zhiji-lottery-open-btn.float-btn:hover {
	transform: scale(1.06) !important;
	box-shadow: 0 6px 20px rgba(255,111,145,.55), inset 0 1px 0 rgba(255,255,255,.4) !important;
}

/* 弹窗遮罩层 */
.zhiji-lottery-modal {
	position: fixed;
	inset: 0;
	z-index: 100000;
	display: flex;
	align-items: center;
	justify-content: center;
	background: rgba(0, 0, 0, .6);
	opacity: 0;
	visibility: hidden;
	pointer-events: none;
	transition: opacity .25s ease;
	-webkit-backface-visibility: hidden;
	backface-visibility: hidden;
	will-change: opacity;
}

/* 打开态：仅切换类，平滑显现（无 display 切换、无节点移动） */
.zhiji-lottery-modal.is-open {
	opacity: 1;
	visibility: visible;
	pointer-events: auto;
}

.zhiji-lottery-modal * {
	box-sizing: border-box;
}

/* 弹窗卡片（白底 + 金色碎屑装饰，参考图风格：径向渐变多点撒金粉） */
.zhiji-lottery-card {
	position: relative;
	width: 94%;
	max-width: 720px;
	max-height: 92vh;
	overflow-y: auto;
	background-color: #fff;
	background-image:
		radial-gradient(circle, rgba(255, 196, 60, .5) 1.5px, transparent 2px),
		radial-gradient(circle, rgba(255, 214, 90, .45) 2px, transparent 2.8px),
		radial-gradient(circle, rgba(255, 182, 30, .4) 1.8px, transparent 2.5px),
		radial-gradient(circle, rgba(255, 224, 130, .4) 1.2px, transparent 1.8px),
		radial-gradient(circle, rgba(255, 205, 70, .45) 1.6px, transparent 2.2px),
		radial-gradient(circle, rgba(255, 190, 50, .38) 2.2px, transparent 3px);
	background-size: 180px 150px, 240px 200px, 130px 160px, 300px 220px, 200px 180px, 260px 140px;
	background-position: 0 0, 40px 30px, 80px 10px, 20px 60px, 110px 40px, 60px 90px;
	border-radius: 16px;
	padding: 24px 20px 20px;
	box-shadow: 0 20px 60px rgba(0, 0, 0, .3);
	transform: translateZ(0); /* 独立合成层，避免与祖先 transform 互相干扰 */
}

/* 关闭按钮 */
.zhiji-lottery-close {
	position: absolute;
	top: 8px;
	right: 14px;
	font-size: 28px;
	line-height: 1;
	color: #bbb;
	text-decoration: none;
	font-weight: 300;
	cursor: pointer;
	transition: color .15s;
}

.zhiji-lottery-close:hover {
	color: #888;
}

.zhiji-lottery-title {
	margin: 0 0 14px;
	text-align: center;
	font-size: 20px;
	font-weight: 800;
	letter-spacing: 2px;
	color: #fff;
	background: linear-gradient(135deg, #ffa94d, #ff7a2f 55%, #f2571c);
	border-radius: 40px;
	padding: 10px 18px;
	box-shadow: 0 4px 14px rgba(255, 122, 47, .35);
}

/* 转盘容器与指针 */
.zhiji-lottery-wheel {
	position: relative;
	display: inline-block;
	width: var(--zhiji-lottery-size, 240px);
	max-width: 100%;
}

.zhiji-lottery-pointer {
	position: absolute;
	top: -10px;
	left: 50%;
	transform: translateX(-50%);
	z-index: 3;
	width: 0;
	height: 0;
	border-left: 12px solid transparent;
	border-right: 12px solid transparent;
	border-top: 24px solid var(--zhiji-danger, #e24b4a);
	filter: drop-shadow(0 2px 4px rgba(0, 0, 0, .25));
}

.zhiji-lottery-canvas {
	display: block;
	margin: 0 auto;
	max-width: 100%;
	height: auto;
	border-radius: 50%;
	box-shadow: 0 6px 22px rgba(0, 0, 0, .16);
}

/* ============ 九宫格抽奖样式（参考图：3×3 格子 + 中心按钮 + 右侧账户栏） ============ */
.zhiji-lottery-body {
	display: flex;
	gap: 16px;
	align-items: flex-start;
	justify-content: center;
	margin-top: 8px;
}

/* 九宫格容器：白底微暖渐变 + 蓝色边框（参考图风格） */
.zhiji-lottery-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 9px;
	width: min(calc(var(--zhiji-lottery-size, 240px) * 1.6), 400px);
	flex: 0 0 auto;
	padding: 13px;
	background: linear-gradient(180deg, #fffefb, #faf6ff);
	border: 2px solid #b8d3ff;
	border-radius: 18px;
	box-shadow: 0 6px 18px rgba(90, 140, 255, .12);
}

/* 奖品格：柔和彩底 + emoji 图标 + 深色文字，丰富不刺眼 */
.zhiji-grid-cell {
	aspect-ratio: 1/1;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 3px;
	text-align: center;
	font-size: 12px;
	font-weight: 700;
	line-height: 1.25;
	color: #3a3a4d;
	background: var(--cg, #f4f7ff);
	border: 1px solid rgba(0, 0, 0, .05);
	border-radius: 11px;
	box-shadow: 0 2px 5px rgba(0, 0, 0, .05);
	transition: transform .12s ease, box-shadow .12s ease;
	padding: 3px;
	word-break: break-word;
	overflow: hidden;
}

.zhiji-grid-cell .zgc-ic {
	font-size: 19px;
	line-height: 1;
}

.zhiji-grid-cell .zgc-ic svg {
	display: block;
	margin: 0 auto;
}

.zhiji-grid-cell .zgc-tx {
	font-size: 12px;
	font-weight: 700;
	padding: 0 2px;
}

.zhiji-grid-cell.is-blank {
	background: #f4f5f7;
	color: #aab0b8;
	font-weight: 500;
	box-shadow: none;
}

.zhiji-grid-cell.is-active {
	transform: scale(1.07);
	box-shadow: 0 0 0 3px rgba(255, 190, 40, .6), 0 6px 16px rgba(255, 170, 40, .28);
	z-index: 2;
}

.zhiji-grid-cell.is-win {
	animation: zhijiGridWin .6s ease infinite alternate;
}

@keyframes zhijiGridWin {
	from { transform: scale(1.03); box-shadow: 0 0 0 3px rgba(255, 190, 40, .5); }
	to { transform: scale(1.1); box-shadow: 0 0 0 5px rgba(255, 110, 40, .5); }
}

/* 中心圆形按钮（橙色） */
.zhiji-grid-btn {
	aspect-ratio: 1/1;
	border-radius: 50%;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 2px;
	background: radial-gradient(circle at 32% 26%, #ffb25e, #ff7a2f 58%, #f2571c);
	color: #fff;
	font-weight: 800;
	font-size: 14px;
	text-decoration: none;
	box-shadow: 0 8px 22px rgba(255, 107, 53, .45), inset 0 1px 0 rgba(255, 255, 255, .35);
	transition: transform .12s ease;
	animation: zhijiBtnPulse 2s ease-in-out infinite;
	text-align: center;
	padding: 6px;
}

@keyframes zhijiBtnPulse {
	0%, 100% { box-shadow: 0 8px 22px rgba(255, 107, 53, .45), inset 0 1px 0 rgba(255, 255, 255, .35); }
	50% { box-shadow: 0 8px 30px rgba(255, 140, 60, .7), inset 0 1px 0 rgba(255, 255, 255, .35); }
}

.zhiji-grid-btn:hover { color: #fff; transform: scale(1.04); }
.zhiji-grid-btn:active { transform: scale(.95); }
.zhiji-grid-btn.is-drawing, .zhiji-grid-btn.is-disabled { opacity: .65; pointer-events: none; }
.zhiji-grid-btn .zgb-sub { font-size: 10px; font-weight: 600; opacity: .95; line-height: 1.2; }

/* 右侧账户/记录栏 */
.zhiji-lottery-side {
	flex: 1 1 236px;
	min-width: 0;
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.zhiji-lottery-account {
	background: #fff;
	border: 1px solid #eef0f4;
	border-radius: 12px;
	padding: 12px 14px;
}

.zhiji-lottery-account .zla-title {
	font-size: 13px;
	font-weight: 800;
	color: #3c3c4d;
	margin-bottom: 10px;
}

/* 两列统计网格（参考图：余额/积分并排，label 上 value 下） */
.zhiji-lottery-account .zla-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 8px;
	margin-bottom: 8px;
}

.zhiji-lottery-account .zla-item {
	text-align: center;
	background: rgba(127, 127, 127, .05); /* fallback（不支持 color-mix 时） */
	background: color-mix(in srgb, var(--zhiji-brand, #2e7cf6) 8%, #fff);
	border: 1px solid rgba(127, 127, 127, .2); /* fallback */
	border-color: color-mix(in srgb, var(--zhiji-brand, #2e7cf6) 30%, transparent);
	border-radius: 10px;
	padding: 9px 4px 8px;
}

.zhiji-lottery-account .zla-item-full { width: 100%; }

.zhiji-lottery-account .zla-label {
	font-size: 11px;
	color: #9a9aa8;
	margin-bottom: 4px;
}

.zhiji-lottery-account .zla-label svg {
	vertical-align: -2px;
	margin-right: 2px;
}

.zhiji-lottery-account .zla-num {
	font-weight: 800;
	color: #2e7cf6; /* fallback */
	color: var(--zhiji-brand, #2e7cf6);
	font-size: 16px;
}

.zhiji-lottery-side .zhiji-lottery-log {
	margin: 0;
	background: #fff;
	border: 1px solid #eef0f4;
	border-radius: 12px;
	padding: 10px 12px;
	max-height: 264px;
	overflow: auto;
}

@media (max-width: 720px) {
	.zhiji-lottery-body { flex-direction: column; align-items: stretch; }
	.zhiji-lottery-grid { width: 100%; max-width: 400px; margin: 0 auto; }
	.zhiji-lottery-side { width: 100%; }
}

/* ============ 九宫格样式结束 ============ */

/* 消息 / 统计文本 */
.zhiji-lottery-msg {
	text-align: center;
	min-height: 22px;
	margin: 10px 0;
	font-size: 12px;
	color: #666;
}

.zhiji-lottery-msg.is-error {
	color: var(--zhiji-danger, #e24b4a);
}

/* 结果区：用 opacity/visibility 控制，禁止 display:none 以免重排闪烁 */
.zhiji-lottery-result {
	opacity: 0;
	visibility: hidden;
	transition: opacity .3s ease;
}

.zhiji-lottery-result.show {
	opacity: 1;
	visibility: visible;
}

.zhiji-lottery-result-inner {
	text-align: center;
	padding: 16px 8px;
}

.zhiji-lottery-result-emoji {
	font-size: 36px;
	margin-bottom: 8px;
}

.zhiji-lottery-result-title {
	font-size: 17px;
	font-weight: 600;
	color: #333;
}

.zhiji-lottery-result-sub {
	font-size: 13px;
	color: #888;
	margin-top: 6px;
}

/* 按钮区 */
.zhiji-lottery-actions {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
	justify-content: center;
	margin-top: 6px;
}

.zhiji-btn {
	display: inline-block;
	padding: 9px 16px;
	border-radius: 8px;
	font-size: 14px;
	text-decoration: none;
	cursor: pointer;
	color: #fff;
	border: none;
	line-height: 1.4;
	transition: filter .15s;
	user-select: none;
}

.zhiji-btn:hover {
	filter: brightness(1.08);
}

.zhiji-btn:active {
	filter: brightness(.95);
}

/* 加载锁视觉态：抽奖进行中按钮变灰且不可点 */
.zhiji-btn.is-drawing {
	opacity: .6;
	pointer-events: none;
}

.zhiji-btn-blue {
	background: #378add;
}

.zhiji-btn-yellow {
	background: #ef9f27;
}

.zhiji-btn-green {
	background: #1d9e75;
}

.zhiji-lottery-stats {
	color: #999;
	font-size: 12px;
	margin-top: 10px;
}

/* 看板记录 */
.zhiji-lottery-log {
	margin-top: 16px;
	text-align: left;
}

.zhiji-lottery-log-title {
	font-size: 12px;
	color: #999;
	font-weight: 600;
	margin-bottom: 6px;
}

.zhiji-lottery-log table {
	width: 100%;
	border-collapse: collapse;
}

.zhiji-lottery-log th,
.zhiji-lottery-log td {
	padding: 5px 8px;
	font-size: 11px;
	text-align: left;
}

/* 背景滚动锁（打开弹窗时加在 body 上） */
.zhiji-lottery-noscroll {
	overflow: hidden;
}

/* 移动端响应式 */
@media (max-width: 480px) {
	.zhiji-lottery-card {
		padding: 20px 14px 16px;
		border-radius: 14px;
	}

	.zhiji-lottery-title {
		font-size: 16px;
	}

	.zhiji-lottery-actions {
		flex-direction: column;
	}

	.zhiji-lottery-actions .zhiji-btn {
		width: 100%;
		text-align: center;
	}
}

/* ============================================================
 * 首页转盘触发器（trigger="wheel"）：可见的 Canvas 转盘，
 * 点击直接弹窗抽奖，全程不离开首页。
 * ============================================================ */
.zhiji-lottery-trigger-wrap {
	display: inline-block;
	text-align: center;
	line-height: 1.4;
}

.zhiji-lottery-trigger-wheel {
	display: block;
	margin: 0 auto;
	border-radius: 50%;
	cursor: pointer;
	box-shadow: 0 8px 22px rgba(91, 106, 240, .28);
	transition: transform .2s ease, box-shadow .2s ease;
	-webkit-backface-visibility: hidden;
	backface-visibility: hidden;
}

.zhiji-lottery-trigger-wheel:hover {
	transform: scale(1.04) rotate(8deg);
	box-shadow: 0 12px 30px rgba(91, 106, 240, .42);
}

.zhiji-lottery-trigger-wheel.is-disabled {
	cursor: not-allowed;
}

.zhiji-lottery-trigger-hint {
	margin-top: 12px;
	font-weight: 600;
	font-size: 15px;
	color: #5b6af0;
}

/* 暗夜模式适配：弹窗卡片/转盘/账户记录栏深色化 */
body.dark-theme .zhiji-lottery-card {
	background: #26282b;
	box-shadow: 0 20px 60px rgba(0, 0, 0, .55);
}
body.dark-theme .zhiji-lottery-title,
body.dark-theme .zhiji-lottery-result-title {
	color: #e5eef7;
}
body.dark-theme .zhiji-lottery-msg,
body.dark-theme .zhiji-lottery-result-sub,
body.dark-theme .zhiji-lottery-stats,
body.dark-theme .zhiji-lottery-log-title {
	color: #9aa3ad;
}
body.dark-theme .zhiji-lottery-close {
	color: #7a828c;
}
body.dark-theme .zhiji-lottery-close:hover {
	color: #aab2bc;
}
body.dark-theme .zhiji-grid-cell {
	background: #33353a !important;
	color: #d8dce2;
	border-color: rgba(255, 255, 255, .08);
	box-shadow: none;
}
body.dark-theme .zhiji-grid-cell.is-blank {
	background: #2c2e32 !important;
	color: #7a828c;
}
body.dark-theme .zhiji-lottery-account {
	background: #33353a;
	border-color: #3a3d42;
}
body.dark-theme .zhiji-lottery-account .zla-title {
	color: #e5eef7;
}
body.dark-theme .zhiji-lottery-account .zla-num,
body.dark-theme .zhiji-lottery-log td {
	color: #c3cad2;
}
body.dark-theme .zhiji-lottery-log th {
	color: #8a929c;
}
body.dark-theme .zhiji-lottery-side .zhiji-lottery-log {
	background: #2f3135;
	border-color: #3a3d42;
}
body.dark-theme .zhiji-lottery-grid {
	background: #2b2d31;
	border-color: #4a5568;
	box-shadow: none;
}

ZHIJI_LOTTERY_CSS;
	// 2026-09-26：改走统一内联资源服务（Assets.php，head 内联策略不变）
	zhiji_asset_add_css( 'lottery', $lottery_css );

	// AJAX 地址全局变量 + 完整 JS（jQuery 由父主题在 head 加载，先于本脚本执行）
	zhiji_asset_add_js( 'lottery-ajax', 'window.ZHIJI_LOTTERY_AJAX=' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ';' );
	$lottery_js = <<<'ZHIJI_LOTTERY_JS'
/* ============================================================
 * 知集·大抽奖 前端交互层（jQuery 适配，IIFE 包裹避免命名污染）
 * 依赖：jQuery、wp_localize_script 注入的 ZHIJI_LOTTERY_AJAX（admin-ajax.php 地址）
 *
 * 关键修复（对应「点击抽奖弹窗闪烁/抖动」）：
 *  1) 弹窗 DOM 在短码中被一次性渲染（opacity:0 + visibility:hidden），
 *     DOMready 时统一 append 到 body 一次（脱离可能被 transform 的祖先容器，
 *     确保 position:fixed 相对视口，消除「抖动」），之后「打开」只切换 .is-open 类，
 *     不再每次点击都移动节点（旧实现 appendTo 每次点击触发重排 = 闪烁根因）。
 *  2) 打开带锁（.is-open 守卫）防重复触发；抽奖带加载锁（.is-drawing + data 锁）防并发 Ajax。
 *  3) 结果区用 opacity/visibility 过渡，不切换 display，避免重排闪烁。
 *  4) ESC / 遮罩 / 关闭按钮 三种关闭；打开时锁定 body 滚动。
 * ============================================================ */
(function ($) {
	'use strict';

	/* 初始绘制静态转盘（读取 canvas 上的 data-* 配置） */
	function drawWheel(cv) {
		var sz = parseInt(cv.getAttribute('data-zhiji-size'), 10) || 240;
		var prizes, colors;
		try { prizes = JSON.parse(cv.getAttribute('data-zhiji-prizes') || '[]'); } catch (e) { prizes = []; }
		try { colors = JSON.parse(cv.getAttribute('data-zhiji-colors') || '[]'); } catch (e) { colors = []; }
		var n = prizes.length;
		if (!n) { return; }
		var seg = Math.PI * 2 / n;
		var ctx = cv.getContext('2d');

		function draw(sa) {
			var x = ctx, S = sz, CX = sz / 2, CY = sz / 2, R = sz / 2 - 4, P = prizes, CL = colors, N = n, SG = seg;
			x.clearRect(0, 0, S, S);
			for (var i = 0; i < N; i++) {
				var a1 = sa + i * SG, a2 = a1 + SG;
				x.beginPath();
				x.moveTo(CX, CY);
				x.arc(CX, CY, R, a1, a2);
				x.closePath();
				x.fillStyle = CL[i % CL.length];
				x.fill();
				x.strokeStyle = 'rgba(255,255,255,.4)';
				x.lineWidth = 1.5;
				x.stroke();
				// 文字：标准 translate(圆心) → rotate(中线角) → fillText
				x.save();
				x.translate(CX, CY);
				x.rotate(a1 + SG / 2);
				x.fillStyle = '#fff';
				x.font = "bold 13px 'Microsoft YaHei', sans-serif";
				x.textAlign = 'right';
				x.textBaseline = 'middle';
				x.shadowColor = 'rgba(0,0,0,.35)';
				x.shadowBlur = 2;
				x.shadowOffsetX = 1;
				x.shadowOffsetY = 1;
				var t = P[i];
				x.fillText(t.length > 5 ? t.slice(0, 5) + '…' : t, R - 18, 0);
				x.shadowColor = 'transparent';
				x.restore();
			}
			// 中心圆
			x.beginPath();
			x.arc(CX, CY, R * 0.22, 0, Math.PI * 2);
			x.fillStyle = '#fff';
			x.fill();
			x.strokeStyle = '#e0e0e0';
			x.lineWidth = 1;
			x.stroke();
		}

		cv._zhiji = { draw: draw, n: n, seg: seg };
		draw(-Math.PI / 2);
	}

	/* 打开 / 关闭（带锁防抖） */
	function openModal($m) {
		if ($m.hasClass('is-open')) { return; }       // 已开则忽略，防重复触发
		$m.addClass('is-open');
		$('body').addClass('zhiji-lottery-noscroll'); // 锁定背景滚动
		// 九宫格：打开时重置所有格子高亮
		$m.find('.zhiji-grid-cell').removeClass('is-active is-win');
	}

	function closeModal($m) {
		if (!$m.length) { return; }
		$m.removeClass('is-open');
		$('body').removeClass('zhiji-lottery-noscroll');
	}

	/* 开奖动画（九宫格：格子快速轮转高亮，逐渐减速，最终停在中奖格常亮） */
	function spin($m, res) {
		var $res = $m.find('.zhiji-lottery-result');
		var $btn = $m.find('.zhiji-lottery-draw-btn').first();
		var $gbtn = $m.find('.zhiji-grid-btn');
		var $cells = $m.find('.zhiji-grid-cell');
		var real = $cells.filter(function () { return parseInt(this.getAttribute('data-idx'), 10) >= 0; });
		var idxs = real.map(function () { return parseInt(this.getAttribute('data-idx'), 10); }).get();
		if (!idxs.length) { showResult($m, res); return; }
		var target = (typeof res.index !== 'undefined') ? parseInt(res.index, 10) : 0;
		var tPos = idxs.indexOf(target);
		if (tPos < 0) { tPos = 0; }
		$gbtn.addClass('is-drawing');
		if ($btn.length && !$btn.hasClass('zhiji-grid-btn')) { $btn.addClass('is-drawing'); }
		var totalSteps = idxs.length * (5 + Math.floor(Math.random() * 3)) + tPos + 1;
		var step = 0, speed = 70, interval = null;
		function tick() {
			$cells.removeClass('is-active is-win');
			real.eq(step % idxs.length).addClass('is-active');
			step++;
			if (step >= totalSteps) {
				clearInterval(interval);
				$cells.removeClass('is-active');
				real.eq(tPos).addClass('is-active is-win');
				setTimeout(function () {
					$gbtn.removeClass('is-drawing');
					if ($btn.length) { $btn.removeClass('is-drawing'); }
					showResult($m, res);
				}, 700);
			}
		}
		interval = setInterval(function () {
			tick();
			// 减速三段：60% 前快速，85% 前中速，之后慢速
			if (step >= totalSteps * 0.85 && speed < 300) { clearInterval(interval); speed = 300; interval = setInterval(tick, speed); }
			else if (step >= totalSteps * 0.6 && speed < 150) { clearInterval(interval); speed = 150; interval = setInterval(tick, speed); }
		}, speed);
	}

	/* 依据后端返回的最新状态重建按钮区（积分兑换修复核心：免费→兑换→今日已抽完，免刷新） */
	function renderActions($m, st) {
		var nonce = $m.data('nonce') || '';
		var html = '';
		if (!st.done) {
			html += '<a href="javascript:;" class="zhiji-lottery-draw-btn zhiji-btn zhiji-btn-blue" data-extra="0" data-nonce="' + nonce + '">🎲 开始抽奖</a>';
		} else if (st.can_extra) {
			html += '<a href="javascript:;" class="zhiji-lottery-draw-btn zhiji-btn zhiji-btn-yellow" data-extra="1" data-nonce="' + nonce + '">💰 积分兑换（' + st.extra_cost + '/次·剩' + st.extra_left + '次）</a>';
		}
		if (st.can_share) {
			html += '<a href="javascript:;" class="zhiji-lottery-share-btn zhiji-btn zhiji-btn-green" data-nonce="' + nonce + '">🔗 分享得次数（剩' + st.share_left + '次）</a>';
		}
		$m.find('.zhiji-lottery-actions').html(html);
		// 同步更新九宫格中心按钮状态（免费→积分兑换→禁用）
		var gHtml = '';
		if (!st.done) {
			gHtml = '<a href="javascript:;" class="zhiji-grid-btn zhiji-lottery-draw-btn" data-extra="0" data-nonce="' + nonce + '">🎲 开始抽奖<span class="zgb-sub">免费剩 ' + st.free_left + ' 次</span></a>';
		} else if (st.can_extra) {
			gHtml = '<a href="javascript:;" class="zhiji-grid-btn zhiji-lottery-draw-btn" data-extra="1" data-nonce="' + nonce + '">🎯 积分兑换<span class="zgb-sub">' + st.extra_cost + '/次·剩' + st.extra_left + '次</span></a>';
		} else {
			gHtml = '<div class="zhiji-grid-btn is-disabled">今日已抽完</div>';
		}
		$m.find('.zhiji-grid-btn').replaceWith(gHtml);
	}

	function showResult($m, res) {
		var $res = $m.find('.zhiji-lottery-result');
		var $msg = $m.find('.zhiji-lottery-msg');
		$res.html(
			'<div class="zhiji-lottery-result-inner">' +
				'<div class="zhiji-lottery-result-emoji">🎉</div>' +
				'<div class="zhiji-lottery-result-title">' + (res.msg || '') + '</div>' +
				'<div class="zhiji-lottery-result-sub">累计 ' + res.total + ' 次 / 中奖 ' + res.wins + ' 次</div>' +
			'</div>'
		).addClass('show');
		// 动态重建按钮区：免费抽完立即出现积分兑换；兑换后仍可继续兑换（均无需刷新页面）
		if (res.state) {
			renderActions($m, res.state);
			if (res.state.done && res.state.can_extra) {
				$msg.text('今日免费次数已用完 · 可积分兑换再抽').removeClass('is-error');
			} else if (res.state.done) {
				$msg.text('今日已抽完，明天再来吧').removeClass('is-error');
			} else {
				$msg.text('今日免费剩 ' + res.state.free_left + ' 次').removeClass('is-error');
			}
		}
		// 刷新「最近抽奖记录」看板（全局记录，抽奖后无需刷新页面即时更新）
		if (typeof res.log_html !== 'undefined') {
			var $log = $m.find('.zhiji-lottery-log');
			if (res.log_html) {
				if ($log.length) { $log.replaceWith(res.log_html); }
				else { $m.append(res.log_html); }
			} else {
				$log.remove();
			}
		}
		// 中奖庆祝：真中奖（won=1，msg 以「恭喜」开头）触发双礼炮喷彩带（cannons 模式，纯 Canvas 轻量，zhiji.js 提供）
		if (res.won && typeof window.zhiji_confetti === 'function') {
			try { window.zhiji_confetti({ cannons: true, count: 160 }); } catch (e) {}
		}
		// 中奖前端通知：通知中心 lottery_win toast（self_celebrated 标记已放礼炮，通知中心不再重复庆祝；未启用通知中心时静默）
		if (res.won && typeof window.CustomEvent === 'function') {
			try {
				document.dispatchEvent(new CustomEvent('zhiji_event', {
					detail: { event: 'lottery_win', data: { prize_name: res.name || '奖品', self_celebrated: true } }
				}));
			} catch (e) {}
		}
		// 刷新父主题消息角标：中奖已产生站内消息，关闭弹窗后铃铛未读数即时更新（无需手动刷新页面）
		if (res.won && typeof window.zhijiRefreshMsgBadge === 'function') {
			try { window.zhijiRefreshMsgBadge($m.attr('data-nonce') || ''); } catch (e) {}
		}
		// 中奖邮件：特效播完（礼炮约 3.2s）后主动发送，保证送达、不依赖下次访问；与 cron 兜底用 transient 去重
		if (res.mail_key) {
			var mailNonce = $m.attr('data-nonce') || '';
			setTimeout(function () {
				$.ajax({
					url: window.ZHIJI_LOTTERY_AJAX,
					type: 'POST',
					data: { action: 'zhiji_api', api: 'zhiji_lottery_send_mail', nonce: mailNonce, key: res.mail_key },
					dataType: 'json'
				});
			}, 3500);
		}
		// 刷新右侧账户数值（当前余额/当前积分/中奖次数）：抽奖发奖后即时更新，无需刷新页面
		if (typeof res.balance !== 'undefined' && typeof res.points !== 'undefined') {
			var $acc = $m.find('.zhiji-lottery-account .zla-num');
			$acc.filter('[data-zkey="balance"]').text('¥' + Number(res.balance).toFixed(2));
			$acc.filter('[data-zkey="points"]').text(String(Number(res.points)));
			if (typeof res.wins !== 'undefined') {
				$acc.filter('[data-zkey="wins"]').text(String(Number(res.wins)));
			}
		}
		$m.removeData('drawing');
	}

	$(function () {
		// 初始化：绘制静态盘 + 一次性移入 body（脱离 transform 祖先，固定相对视口）
		$('.zhiji-lottery-modal').each(function () {
			var $m = $(this);
			var cv = $m.find('canvas')[0];
			if (cv && !cv._zhiji) { drawWheel(cv); }
			if (!$m.data('zhijiMoved')) {
				$m.appendTo(document.body);
				$m.data('zhijiMoved', 1);
			}
		});

		// 首页转盘触发器：同样绘制 Canvas 转盘（点击沿用 .zhiji-lottery-open-btn 打开弹窗）
		$('.zhiji-lottery-trigger-wheel').each(function () {
			var cv = this;
			if (!cv._zhiji) { drawWheel(cv); }
		});

		// 打开弹窗（防抖）
		$(document).on('click', '.zhiji-lottery-open-btn', function (e) {
			e.preventDefault();
			var id = $(this).attr('data-zhiji-modal');
			var $m = id ? $('#' + id) : $('.zhiji-lottery-modal').first();
			if (!$m.length) { return; }
			openModal($m);
		});

		// 关闭：按钮 / 遮罩 / ESC
		$(document).on('click', '.zhiji-lottery-close', function (e) {
			e.preventDefault();
			closeModal($(this).closest('.zhiji-lottery-modal'));
		});
		$(document).on('click', '.zhiji-lottery-modal', function (e) {
			if (e.target === this) { closeModal($(this)); }
		});
		$(document).on('keydown', function (e) {
			if (e.key === 'Escape' || e.keyCode === 27) {
				var $o = $('.zhiji-lottery-modal.is-open');
				if ($o.length) { closeModal($o); }
			}
		});

	// 抽奖（加载锁 + 防并发 Ajax）
	$(document).on('click', '.zhiji-lottery-draw-btn', function () {
		var $btn = $(this);
		if ($btn.hasClass('is-drawing')) { return; }            // 加载锁
		var $m = $btn.closest('.zhiji-lottery-modal');
		if ($m.data('drawing')) { return; }                    // 二次保险
		$btn.addClass('is-drawing');
		$m.data('drawing', 1);
		// 注意：data-extra 是字符串，"0" 在 JS 里为 truthy，必须用 parseInt 取数值
		// 否则免费抽会被误判为积分兑换，导致免费次数不减、且兑换分支报错使记录不刷新。
		var extra = parseInt($btn.attr('data-extra'), 10) || 0;
		var $msg = $m.find('.zhiji-lottery-msg');
		var $res = $m.find('.zhiji-lottery-result');
		$res.removeClass('show');
		$.ajax({
			url: ZHIJI_LOTTERY_AJAX,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'zhiji_api', api: 'zhiji_lottery_draw',
				nonce: $btn.attr('data-nonce'),
				extra: extra ? 1 : 0
			}
		}).done(function (res) {
				if (!res || res.error) {
					$msg.text(res ? res.msg : '请求失败').addClass('is-error');
					$btn.removeClass('is-drawing');
					$m.removeData('drawing');
					return;
				}
				// 动画期间不提前泄露中奖结果：仅提示「开奖中」，中奖详情由开奖动画结束后 showResult 展示
				$msg.text('🎲 开奖中…').removeClass('is-error');
				spin($m, res);
			}).fail(function () {
				$msg.text('网络异常，请重试').addClass('is-error');
				$btn.removeClass('is-drawing');
				$m.removeData('drawing');
			});
		});

		// 分享得次数
		$(document).on('click', '.zhiji-lottery-share-btn', function (e) {
			e.preventDefault();
			var $btn = $(this);
			if ($btn.hasClass('is-drawing')) { return; }
			$btn.addClass('is-drawing');
			var $m = $btn.closest('.zhiji-lottery-modal');
			$.ajax({
				url: ZHIJI_LOTTERY_AJAX,
				type: 'POST',
				dataType: 'json',
				data: { action: 'zhiji_api', api: 'zhiji_lottery_share', nonce: $btn.attr('data-nonce') }
			}).done(function (r) {
				$m.find('.zhiji-lottery-msg').text(r && r.msg ? r.msg : '分享成功');
				if (!r || r.error) { $btn.removeClass('is-drawing'); return; }
				setTimeout(function () { location.reload(); }, 800);
			}).fail(function () {
				$m.find('.zhiji-lottery-msg').text('网络异常，请重试').addClass('is-error');
				$btn.removeClass('is-drawing');
			});
		});
	});
})(jQuery);

ZHIJI_LOTTERY_JS;
	// jQuery 由父主题在 head 稍后加载，本脚本先于其执行时可能未就绪：轮询等待后再运行。
	// 保留原有外层 IIFE 包装，语义不变
	zhiji_asset_add_js( 'lottery', '(function(){function z_boot() {if(typeof window.jQuery==="undefined"){setTimeout(z_boot,80);return;}' . $lottery_js . '}z_boot();})();' );
}

/**
 * 后台奖品池预设联动 JS：下拉选择预设 → 自动填充奖品池 textarea。
 */
function zhiji_lottery_admin_preset_js() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	$presets = array(
		'lead'     => "谢谢参与|none|0|40\n积分 20|points|20|25\n积分 50|points|50|15\n经验 100|level|100|10\n余额 1 元|balance|1|8\n优惠券 5 元|coupon|5|2\n会员 1 天|vip_day|1|1\n免单券|free|0|1",
		'generous' => "谢谢参与|none|0|14\n积分 50|points|50|25\n积分 100|points|100|20\n经验 200|level|200|15\n余额 2 元|balance|2|15\n优惠券 10 元|coupon|10|7\n会员 3 天|vip_day|3|2\n免单券|free|0|2",
	);
	$json = wp_json_encode( $presets );
	echo '<script>
jQuery(function($){
	var zhijiLotteryPresets = ' . $json . ';
	var $sel = $("select[name*=\"[lottery_prob_preset]\"]");
	var $txt = $("textarea[name*=\"[lottery_prizes]\"]");
	if(!$sel.length || !$txt.length) return;
	$sel.on("change", function(){
		var v = $(this).val();
		if(v === "custom") return;
		if(zhijiLotteryPresets[v]){ $txt.val(zhijiLotteryPresets[v]); }
	});
});
</script>';
}

/** 未登录抽奖统一拒绝响应。 */
function zhiji_lottery_ajax_require_login() {
	wp_send_json( array( 'error' => 1, 'msg' => '请先登录后抽奖' ) );
}

/** AJAX：返回当前用户未读消息计数（父主题角标数据），供前端刷新铃铛角标。 */
function zhiji_lottery_ajax_msg_counts() {
	if ( ! is_user_logged_in() ) {
		wp_send_json( array() );
	}
	check_ajax_referer( 'zhiji_lottery_draw', 'nonce' );
	$uid = get_current_user_id();
	if ( function_exists( 'zibmsg_get_user_new_msg_counts' ) ) {
		wp_send_json( zibmsg_get_user_new_msg_counts( $uid ) );
	}
	wp_send_json( array() );
}

/* ===================== 解析 / 校验 helper ===================== */

/** 解析「日期|次数」meta（兼容旧格式：仅日期）。 */
function zhiji_lottery_parse_day_count( $meta, $today ) {
	if ( is_string( $meta ) && false !== strpos( $meta, '|' ) ) {
		list( $d, $c ) = explode( '|', $meta, 2 );
		return ( $d === $today ) ? (int) $c : 0;
	}
	return ( $meta === $today ) ? 1 : 0;
}

/** 每日总抽次是否未达上限。 */
function zhiji_lottery_check_daily_total( $uid, $today ) {
	$cap = max( 0, (int) zhiji_get_option( 'lottery_daily_total', 0 ) );
	if ( $cap <= 0 ) {
		return true;
	}
	$used = zhiji_lottery_parse_day_count( get_user_meta( $uid, ZHIJI_LOTTERY_META_DAILY_TOTAL, true ), $today );
	return $used < $cap;
}

/** 累加每日总抽次。 */
function zhiji_lottery_inc_daily_total( $uid, $today ) {
	$cap = max( 0, (int) zhiji_get_option( 'lottery_daily_total', 0 ) );
	if ( $cap <= 0 ) {
		return;
	}
	$used = zhiji_lottery_parse_day_count( get_user_meta( $uid, ZHIJI_LOTTERY_META_DAILY_TOTAL, true ), $today );
	update_user_meta( $uid, ZHIJI_LOTTERY_META_DAILY_TOTAL, $today . '|' . ( $used + 1 ) );
}

/**
 * 汇总当前用户抽奖可用状态（服务端渲染与 AJAX 共用，保证一致）。
 *
 * @return array {done,free_left,extra_cost,extra_left,can_extra,share_left,can_share}
 */
function zhiji_lottery_get_user_state( $uid, $today ) {
	$today_used = zhiji_lottery_parse_day_count( get_user_meta( $uid, ZHIJI_LOTTERY_META_TODAY, true ), $today );
	$daily_free = max( 1, (int) zhiji_get_option( 'lottery_daily_free', 1 ) );
	$free_left  = max( 0, $daily_free - $today_used );
	$done       = $free_left <= 0;

	// 积分兑换剩余
	$extra_cost = (int) zhiji_get_option( 'lottery_extra_cost', 0 );
	$extra_max  = (int) zhiji_get_option( 'lottery_extra_max', 5 );
	$extra_left = 0;
	$can_extra  = false;
	if ( $done && $extra_cost > 0  ) {
		$ecnt = zhiji_lottery_parse_day_count( get_user_meta( $uid, ZHIJI_LOTTERY_META_EXTRA_DATE, true ) . '|' . get_user_meta( $uid, ZHIJI_LOTTERY_META_EXTRA_CNT, true ), $today );
		$extra_left = max( 0, $extra_max - $ecnt );
		$can_extra  = $extra_left > 0;
	}

	// 分享得次数剩余
	$share_max  = max( 0, (int) zhiji_get_option( 'lottery_share_daily', 1 ) );
	$share_left = 0;
	$can_share  = false;
	if ( $share_max > 0 ) {
		$scnt = zhiji_lottery_parse_day_count( get_user_meta( $uid, ZHIJI_LOTTERY_META_SHARE_DATE, true ) . '|' . get_user_meta( $uid, ZHIJI_LOTTERY_META_SHARE_CNT, true ), $today );
		$share_left = max( 0, $share_max - $scnt );
		$can_share  = $share_left > 0 && zhiji_lottery_check_daily_total( $uid, $today );
	}

	return array(
		'done'       => $done,
		'free_left'  => $free_left,
		'extra_cost' => $extra_cost,
		'extra_left' => $extra_left,
		'can_extra'  => $can_extra,
		'share_left' => $share_left,
		'can_share'  => $can_share,
	);
}

/* ===================== 奖品池 ===================== */

/**
 * 解析奖品配置（textarea 每行：名称|类型|值|概率）。
 * 类型：none=谢谢参与 / points=积分 / level=经验 / balance=余额（元） / coupon=优惠码 / free=免单券 / vip_day=会员天数 / vip_month=会员月数。
 *
 * @return array[] 每项 {name,type,value,prob}
 */
function zhiji_lottery_get_prizes() {
	$raw = (string) zhiji_get_option( 'lottery_prizes', '谢谢参与|none|0|40' . "\n" . '积分 20|points|20|25' . "\n" . '积分 50|points|50|15' . "\n" . '经验 100|level|100|10' . "\n" . '余额 1 元|balance|1|8' . "\n" . '随机优惠券|coupon|5|2' );
	$prizes = array();
	foreach ( preg_split( '/\r?\n/', $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '|', $line ) );
		if ( count( $parts ) < 2 ) {
			continue;
		}
		$type = in_array( $parts[1], array( 'none', 'points', 'level', 'balance', 'coupon', 'vip_day', 'vip_month', 'free' ), true ) ? $parts[1] : 'none';
		// 优惠码奖品实际为随机立减/折扣面额（与邮箱领取同款），固定显示「随机优惠券」，
		// 避免后台写「优惠券 10 元」之类的固定金额造成误导（格子/记录/消息统一）。
		$name = 'coupon' === $type ? '随机优惠券' : $parts[0];
		$prizes[] = array(
			'name'  => $name,
			'type'  => $type,
			'value' => isset( $parts[2] ) ? max( 0, (float) $parts[2] ) : 0,
			'prob'  => isset( $parts[3] ) ? max( 0, (float) $parts[3] ) : 0,
		);
	}
	return $prizes;
}

/** 概率加权抽取；概率合计不足 100 时「谢谢参与」兜底。 */
function zhiji_lottery_weighted_pick( $prizes ) {
	$roll = wp_rand( 1, 10000 ) / 100;
	$acc  = 0;
	foreach ( $prizes as $i => $p ) {
		$acc += (float) $p['prob'];
		if ( $roll <= $acc ) {
			return $i;
		}
	}
	foreach ( $prizes as $i => $p ) {
		if ( 'none' === $p['type'] ) {
			return $i;
		}
	}
	return count( $prizes ) - 1;
}

/* ===================== 奖品发放（联动优惠码体系） ===================== */

/**
 * 发放奖品。优惠码走 CouponGive 体系（随机立减/折扣 + 个人中心 Tab + 站内通知）。
 *
 * @param int   $uid
 * @param array $prize
 * @return array {msg, coupon_code?}
 */
function zhiji_lottery_grant_prize( $uid, $prize ) {
	$name  = $prize['name'];
	$value = (float) $prize['value'];
	$extra = array();

	switch ( $prize['type'] ) {
		case 'points':
			Zhiji_Adapter::update_user_points( $uid, array( 'value' => (int) $value, 'type' => '抽奖奖品', 'desc' => '大转盘中奖：' . $name ) );
			$msg = '恭喜获得 ' . $name . '！已发放至账户积分。';
			break;

		case 'level':
			Zhiji_Adapter::user_level_integral_add( $uid, (int) $value, 'lottery' );
			$msg = '恭喜获得 ' . $name . '！经验值已到账。';
			break;

		case 'balance':
			Zhiji_Adapter::update_user_balance( $uid, array( 'value' => $value, 'type' => '抽奖奖品', 'desc' => '大转盘中奖：' . $name ) );
			$msg = '恭喜获得 ' . $name . '！已充值至账户余额。';
			break;

		case 'coupon':
			$msg = '很遗憾，' . $name . '，明天再试～';
			if ( class_exists( 'ZibCardPass' ) && function_exists( 'zhiji_coupon_give_create_one' ) ) {
				// 与邮箱领取同款：随机优惠方式（立减/折扣）与随机面值
				$discount = function_exists( 'zhiji_coupon_give_discount_meta' )
					? zhiji_coupon_give_discount_meta()
					: array( 'type' => 'reduce', 'val' => $value );
				// 优惠码名称 = 来源 + 实际优惠（后台「抽奖优惠码名称」可配来源词，金额随实际发放，避免与随机面值不符）
				$discount_text = function_exists( 'zhiji_coupon_give_discount_text' )
					? zhiji_coupon_give_discount_text( $discount )
					: $name;
				$coupon_title = (string) zhiji_get_option( 'lottery_coupon_title', '抽奖中奖' );
				if ( '' !== trim( $discount_text ) ) {
					$coupon_title .= '·' . $discount_text;
				}
				$days = max( 1, (int) zhiji_get_option( 'lottery_coupon_days', 30 ) );
				$expire = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $days * DAY_IN_SECONDS );
				$code = zhiji_coupon_give_create_one(
					array(
						'title'       => $coupon_title,
						'desc'        => '大转盘中奖',
						'discount'    => $discount,
						'reuse'       => 1,
						'used_count'  => 0,
						'expire_time' => $expire,
						'user_id'     => $uid,
						'source'      => 'zhiji_lottery',
					)
				);
				if ( $code ) {
					// 站内通知（个人中心「我的优惠码」Tab 依据 meta.user_id 自动显示）
					if ( function_exists( 'zhiji_coupon_give_notify_user' ) ) {
						zhiji_coupon_give_notify_user( $uid, $code, $discount_text, $expire, 'lottery' );
					}
					// 消息显示实际随机面额（如 立减8.84 / 7.3折），避免与格子「随机优惠券」不一致
					$msg = '🎉 恭喜获得 ' . $name . '（' . $discount_text . '）！优惠码 ' . $code . ' 已发放至「个人中心→我的优惠码」，可在结算时使用。';
					$extra['coupon_code']           = $code;
					$extra['coupon_discount_text']  = $discount_text;
					$extra['coupon_expire']         = $expire;
				}
			}
			break;

		case 'free':
			// 免单券：创建「0 折」优惠码（multiply×0 = 订单全免），走 CouponGive 统一体系
			$msg = '很遗憾，' . $name . '，明天再试～';
			if ( class_exists( 'ZibCardPass' ) && function_exists( 'zhiji_coupon_give_create_one' ) ) {
				$discount_text = '全场免单';
				$coupon_title  = (string) zhiji_get_option( 'lottery_coupon_title', '抽奖中奖' );
				$coupon_title .= '·' . $discount_text;
				$days   = max( 1, (int) zhiji_get_option( 'lottery_coupon_days', 30 ) );
				$expire = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $days * DAY_IN_SECONDS );
				$code   = zhiji_coupon_give_create_one(
					array(
						'title'       => $coupon_title,
						'desc'        => '大转盘中奖：免单券',
						'discount'    => array( 'type' => 'multiply', 'val' => 0 ),
						'reuse'       => 1,
						'used_count'  => 0,
						'expire_time' => $expire,
						'user_id'     => $uid,
						'source'      => 'zhiji_lottery',
					)
				);
				if ( $code ) {
					if ( function_exists( 'zhiji_coupon_give_notify_user' ) ) {
						zhiji_coupon_give_notify_user( $uid, $code, $discount_text, $expire, 'lottery' );
					}
					$msg                         = '🎉 恭喜获得 ' . $name . '！免单券 ' . $code . ' 已发放至「个人中心→我的优惠码」，下单可全额抵扣。';
					$extra['coupon_code']          = $code;
					$extra['coupon_discount_text'] = $discount_text;
					$extra['coupon_expire']        = $expire;
				}
			}
			break;

		case 'vip_day':
		case 'vip_month':
			$msg = '很遗憾，' . $name . '，明天再试～';
			$unit       = ( 'vip_month' === $prize['type'] ) ? 'month' : 'day';
			$vip_result = zhiji_lottery_grant_vip( $uid, (int) $value, $unit, $name );
			if ( $vip_result['ok'] ) {
				$msg = '🎉 恭喜获得 ' . $name . '！' . $vip_result['text'] . '（新到期：' . $vip_result['expire'] . '）';
				$extra['vip_expire'] = $vip_result['expire'];
			} else {
				$msg = '很遗憾，' . $name . '（' . $vip_result['text'] . '）';
			}
			break;

		default:
			$msg = '很遗憾，' . $name . '，明天再试～';
	}

	return array( 'msg' => $msg, 'extra' => $extra );
}

/**
 * 中奖站内通知（经 Adapter 发消息中心系统消息）。
 * 优惠码/免单券奖品已有带码专用通知（zhiji_coupon_give_notify_user），
 * 这里只补 积分/经验/余额/会员 等其余奖品；未中奖不通知。
 * 受后台开关 lottery_notice_enabled 控制（默认开启）。
 *
 * @param int   $uid   用户 ID
 * @param array $prize {name,type,value,prob}
 * @param array $grant grant_prize 返回值 {msg, extra}
 */
function zhiji_lottery_notify_win( $uid, $prize, $grant ) {
	if ( ! $uid || empty( $grant['msg'] ) || ! class_exists( 'ZibMsg' ) ) {
		return;
	}
	if ( ! zhiji_get_option( 'lottery_notice_enabled', 1 ) ) {
		return;
	}
	// 优惠码类已有带码通知，避免重复
	if ( in_array( $prize['type'], array( 'coupon', 'free' ), true ) ) {
		return;
	}
	// 未中奖（msg 为「很遗憾…」）不通知
	if ( false === strpos( $grant['msg'], '恭喜' ) ) {
		return;
	}
	$type_map  = array( 'points' => '积分', 'balance' => '余额', 'vip_day' => '会员', 'vip_month' => '会员', 'level' => '经验值' );
	$type_name = isset( $type_map[ $prize['type'] ] ) ? $type_map[ $prize['type'] ] : '奖励';
	// 奖励数值高亮：奖品名用 <strong> 包裹（wp_kses_post 允许），配合 .msg-content strong 品牌色样式
	$content = str_replace( $prize['name'], '<strong>' . $prize['name'] . '</strong>', $grant['msg'] );
	Zhiji_Adapter::msg_add(
		array(
			'send_user'    => 'admin',
			'receive_user' => $uid,
			'type'         => 'system',
			'title'        => '恭喜抽中' . $type_name,
			'content'      => $content,
			'meta'         => '',
			'other'        => '',
		)
	);
}

/**
 * 发放会员时长（父主题 zibpay 会员体系）。
 * 在现有 vip_exp_date 基础上顺延 N 天/月；已是永久会员不再叠加。
 *
 * @param int    $uid        用户 ID
 * @param int    $num        时长数值
 * @param string $unit       day=天 / month=月
 * @param string $prize_name 奖品名（写入 desc 便于追溯）
 * @return array {ok, text, expire?}
 */
function zhiji_lottery_grant_vip( $uid, $num, $unit, $prize_name ) {
	$level = max( 1, min( 2, (int) zhiji_get_option( 'lottery_vip_level', 1 ) ) );
	$num   = max( 1, (int) $num );
	if ( ! in_array( $unit, array( 'day', 'month' ), true ) ) {
		$unit = 'day';
	}

	$cur = (string) get_user_meta( $uid, 'vip_exp_date', true );
	if ( 'Permanent' === $cur ) {
		return array( 'ok' => false, 'text' => '您已是永久会员，无需再叠加' );
	}

	// 已到期 / 无记录 → 从今天起算；未到期 → 在现有到期时间上顺延
	$base   = ( $cur && strtotime( $cur ) > current_time( 'timestamp' ) ) ? strtotime( $cur ) : current_time( 'timestamp' );
	$new    = date( 'Y-m-d 23:59:59', strtotime( '+' . $num . ' ' . $unit, $base ) );

	// 父主题统一开通会员接口（更新 meta: vip_exp_date / vip_level）
	Zhiji_Adapter::update_user_vip(
		$uid,
		array(
			'vip_level' => $level,
			'exp_date'  => $new,
			'type'      => '抽奖奖品',
			'order_num' => '',
			'desc'      => '大转盘中奖：' . $prize_name,
		)
	);

	return array(
		'ok'     => true,
		'text'   => '会员时长已到账 ' . $num . ( 'month' === $unit ? ' 个月' : ' 天' ),
		'expire' => date_i18n( 'Y年n月j日', strtotime( $new ) ),
	);
}

/**
 * 抽奖中奖后发送邮件通知（后台开关控制，默认开启）。
 * - 优惠码奖品：复用邮箱领取同款「票据」邮件（含优惠码 + 折扣 + 有效期）。
 * - 其余奖品（积分/经验/余额/会员）：发送品牌卡片风格的中奖通知邮件。
 *
 * @param int   $uid   中奖用户 ID
 * @param array $prize {name,type,value,prob}
 * @param array $extra grant 返回的附加信息（coupon_code / coupon_expire 等）
 * @return bool|WP_Error
 */
function zhiji_lottery_send_win_mail( $uid, $prize, $extra = array() ) {
	if ( ! $uid || empty( $prize['type'] ) || 'none' === $prize['type'] ) {
		return false;
	}
	if ( ! zhiji_get_option( 'lottery_mail_enabled', 1 ) ) {
		return false;
	}
	$user = get_userdata( $uid );
	if ( ! $user || ! $user->user_email ) {
		return false;
	}
	$site  = get_bloginfo( 'name' );
	$email = $user->user_email;
	$name  = $user->display_name ? $user->display_name : $user->user_login;

	// 优惠码奖品：走 CouponGive 票据邮件（标题/内容/占位符与邮箱领取一致，用户可在后台自定义）
	if ( in_array( $prize['type'], array( 'coupon', 'free' ), true ) && ! empty( $extra['coupon_code'] ) && function_exists( 'zhiji_coupon_give_send_mail' ) ) {
		$discount_text = ! empty( $extra['coupon_discount_text'] ) ? $extra['coupon_discount_text'] : $prize['name'];
		$expire        = ! empty( $extra['coupon_expire'] ) ? $extra['coupon_expire'] : '';
		return zhiji_coupon_give_send_mail( $email, $extra['coupon_code'], $discount_text, 'claim', $expire, __( '抽奖活动', 'zhiji' ) );
	}

	// 其余奖品：中奖通知邮件
	$subject = (string) zhiji_get_option( 'lottery_mail_title', '' );
	if ( '' === trim( $subject ) ) {
		/* translators: %1$s: 站点名称 %2$s: 奖品名称 */
		$subject = sprintf( __( '【%1$s】恭喜您抽中「%2$s」', 'zhiji' ), $site, $prize['name'] );
	}
	$subject = str_replace( array( '{site}', '{name}', '{prize}', '{value}' ), array( $site, $name, $prize['name'], $prize['value'] ), $subject );

	$custom_body = (string) zhiji_get_option( 'lottery_mail_content', '' );
	if ( '' !== trim( $custom_body ) ) {
		$body = str_replace( array( '{site}', '{name}', '{prize}', '{value}' ), array( $site, $name, $prize['name'], $prize['value'] ), $custom_body );
		$body = nl2br( esc_html( $body ) );
	} elseif ( function_exists( 'zhiji_mail_template_render' ) ) {
		// v1.9.4：统一调用 MailTemplate 邮件模板引擎（品牌票据风格）
		$type_map = array( 'points'=>'积分', 'balance'=>'余额', 'vip_day'=>'会员', 'vip_month'=>'会员', 'level'=>'经验值', 'free'=>'免单券' );
		$type_name = isset( $type_map[$prize['type']] ) ? $type_map[$prize['type']] : '奖励';
		$val_text = $prize['value'];
		if ( 'points' === $prize['type'] ) { $val_text = '+' . (int)$prize['value']; }
		elseif ( 'balance' === $prize['type'] ) { $val_text = '¥' . number_format( (float)$prize['value'], 2 ); }
		elseif ( 'vip_day' === $prize['type'] ) { $val_text = '+' . (int)$prize['value'] . ' 天'; }
		elseif ( 'vip_month' === $prize['type'] ) { $val_text = '+' . (int)$prize['value'] . ' 个月'; }
		elseif ( 'level' === $prize['type'] ) { $val_text = '+' . (int)$prize['value']; }
		$body = zhiji_mail_template_render( array(
			'site'                 => $site,
			'name'                 => $name,
			'headline'             => __( '恭喜您抽中大奖！', 'zhiji' ),
			'subline'              => __( '您在「每日抽奖」中获得了以下奖励，奖励已发放至您的账户：', 'zhiji' ),
			'ticket_left_label'    => __( '奖励类型', 'zhiji' ),
			'ticket_left_content'  => '<div style="font-size:16px;font-weight:800;color:' . zhiji_token_color( 'brand' ) . ';margin-top:8px;line-height:1.3;">' . esc_html( $type_name ) . '</div>',
			'ticket_right_label'   => __( '奖励数值', 'zhiji' ),
			'ticket_right_content' => '<div style="font-size:30px;font-weight:800;color:#111827;line-height:1.2;">' . esc_html( $val_text ) . '</div>',
			'ticket_right_sub'     => __( '已发放至您的账户', 'zhiji' ),
			'rule_line'            => __( '* 奖励已发放至您的账户，可在个人中心查看明细。', 'zhiji' ),
			'btn_text'             => __( '立即前往查看 &#8594;', 'zhiji' ),
			'btn_url'              => home_url( '/' ),
		) );
	} else {
		// 兜底：MailTemplate 未加载时使用旧版内置模板
		$body = zhiji_lottery_win_mail_html_template( $site, $name, $prize, $extra );
	}

	// v1.9.4：统一调用 zhiji_mail_send 发送（自动临时移除父主题 zib_get_mail_content 包装）
	if ( function_exists( 'zhiji_mail_send' ) ) {
		return zhiji_mail_send( $email, $subject, $body );
	}

	// 兜底：MailTemplate 未加载时手动处理父主题包装
	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	$zib_mail_priority = has_filter( 'wp_mail', 'zib_get_mail_content' );
	if ( false !== $zib_mail_priority ) {
		remove_filter( 'wp_mail', 'zib_get_mail_content', $zib_mail_priority );
	}
	$sent = Zhiji_Adapter::mail_raw( $email, $subject, $body, $headers );
	if ( false !== $zib_mail_priority ) {
		add_filter( 'wp_mail', 'zib_get_mail_content', $zib_mail_priority );
	}
	return $sent;
}

/**
 * 中奖邮件延迟发送兜底（WP-Cron）：前端主动发送失败时才由 cron 补发。
 * 通过 transient 去重，避免与前端主动发送重复。
 *
 * @param string $mail_key 唯一发送标识（uid_时间戳_随机数）
 */
function zhiji_lottery_send_win_mail_cron( $mail_key = '' ) {
	if ( ! $mail_key ) {
		return;
	}
	$flag = 'zhiji_lottery_mail_sent_' . md5( $mail_key );
	if ( get_transient( $flag ) ) {
		return; // 前端已发送，跳过
	}
	$info = get_transient( 'zhiji_lottery_mail_' . md5( $mail_key ) );
	if ( ! is_array( $info ) || empty( $info['uid'] ) ) {
		return; // 奖品信息已过期（10 分钟），不再补发
	}
	zhiji_lottery_send_win_mail( (int) $info['uid'], $info['prize'], $info['extra'] );
	set_transient( $flag, 1, 600 );
}
add_action( 'zhiji_lottery_send_win_mail_cron', 'zhiji_lottery_send_win_mail_cron', 10, 1 );

/**
 * 生成「中奖通知」邮件 HTML 模板（品牌卡片风格，全内联样式兼容主流客户端）。
 * 与 CouponGive 票据邮件同一视觉体系：灰底 + 白卡 + Logo + 用户名 + 奖品卡片 + CTA。
 *
 * @param string $site  站点名称
 * @param string $name  收件人昵称
 * @param array  $prize {name,type,value,prob}
 * @param array  $extra 附加信息
 * @return string
 */
function zhiji_lottery_win_mail_html_template( $site, $name, $prize, $extra = array() ) {
	$type_text = array(
		'points'    => '积分',
		'level'     => '经验',
		'balance'   => '余额',
		'coupon'    => '优惠码',
		'vip_day'   => '会员时长',
		'free'      => '免单券',
		'vip_month' => '会员时长',
	);
	$type_label = isset( $type_text[ $prize['type'] ] ) ? $type_text[ $prize['type'] ] : '奖品';
	$value_line = '';
	if ( 'vip_day' === $prize['type'] || 'vip_month' === $prize['type'] ) {
		$value_line = (int) $prize['value'] . ( 'vip_month' === $prize['type'] ? ' 个月' : ' 天' );
	} elseif ( 'balance' === $prize['type'] ) {
		$value_line = '¥' . number_format( (float) $prize['value'], 2 );
	} elseif ( ( 'coupon' === $prize['type'] || 'free' === $prize['type'] ) ) {
		$value_line = ! empty( $extra['coupon_discount_text'] ) ? $extra['coupon_discount_text'] : $prize['name'];
	} else {
		$value_line = $prize['value'];
	}

	$home_url  = esc_url( home_url( '/' ) );
	$center_url = esc_url( home_url( '/user?tab=coupons' ) );
	$mail_brand = zhiji_token_color( 'brand' ); // 邮件品牌色（PHP 注入，邮件客户端不支持 CSS var）

	// 网站 Logo：优先父主题 logo_src，无则回退站点名文字
	$logo_html = '';
	if ( function_exists( '_pz' ) && ( $logo_url = (string) _pz( 'logo_src' ) ) && 0 === strpos( $logo_url, 'http' ) ) {
		$logo_html = '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $site ) . '" style="max-width:200px;max-height:40px;width:auto;height:auto;">';
	}
	if ( '' === $logo_html ) {
		$logo_html = '<div style="font-size:17px;font-weight:700;color:' . $mail_brand . ';letter-spacing:1px;">' . esc_html( $site ) . '</div>';
	}

	$html = <<<'HTML'
<div style="margin:0;padding:0;background:#f3f4f6;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'PingFang SC','Microsoft YaHei','Segoe UI',sans-serif;">
		<tbody><tr>
			<td align="center" style="padding:36px 16px 20px;">
				<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;background:#ffffff;border-radius:16px;box-shadow:0 1px 2px rgba(17,24,39,.04),0 12px 32px rgba(17,24,39,.07);">
					<tbody><tr><td style="padding:38px 36px 0;text-align:center;">
						{logo_html}
					</td></tr>
					<tr><td style="padding:22px 36px 6px;text-align:center;">
						<div style="display:inline-block;padding:5px 14px;border-radius:999px;background:#ecfdf5;color:#059669;font-size:12px;font-weight:600;letter-spacing:1px;">抽奖中奖通知</div>
						<div style="font-size:22px;font-weight:800;color:#111827;line-height:1.5;margin-top:16px;">🎉 恭喜您中奖啦！</div>
						<div style="font-size:15px;font-weight:600;color:#374151;line-height:1.7;margin-top:14px;">{greeting}</div>
						<div style="font-size:14px;color:#6b7280;line-height:1.9;margin-top:4px;">您在「幸运大转盘」抽中了以下奖励，奖励已发放至您的账户：</div>
					</td></tr>

					<!-- 奖品卡片 -->
					<tr><td style="padding:28px 30px 6px;">
						<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-radius:14px;overflow:hidden;border:1px solid #d1d5db;box-shadow:0 3px 14px rgba(17,24,39,.08);">
							<tbody><tr>
								<td width="38%" align="center" style="background:linear-gradient(135deg,#f0fdf4,#ecfdf5);padding:26px 12px;">
									<div style="font-size:12px;color:#059669;letter-spacing:2px;font-weight:600;">{type_label}</div>
									<div style="font-size:30px;font-weight:800;color:#047857;margin-top:10px;line-height:1.2;">{value_line}</div>
								</td>
								<td align="center" style="background:#ffffff;padding:24px 12px;">
									<div style="font-size:10px;color:#9ca3af;letter-spacing:2px;">奖品名称</div>
									<div style="font-size:18px;font-weight:700;color:#111827;margin-top:8px;line-height:1.4;">{prize_name}</div>
								</td>
							</tr></tbody>
						</table>
					</td></tr>

					<!-- 说明 -->
					<tr><td style="padding:20px 36px 4px;">
						<div style="font-size:12px;color:#9ca3af;line-height:1.9;text-align:center;">{extra_line}</div>
					</td></tr>

					<!-- CTA -->
					<tr><td style="padding:26px 36px 46px;text-align:center;">
						<a href="{center_url}" style="display:inline-block;padding:14px 46px;border-radius:999px;background:{btn_bg};color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;">前往个人中心查看 &#8594;</a>
					</td></tr>
				</table>
			</td>
		</tr></tbody>
	</table>

	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'PingFang SC','Microsoft YaHei','Segoe UI',sans-serif;">
		<tbody><tr>
			<td align="center" style="padding:18px 16px 42px;">
				<div style="width:56px;height:2px;background:#e5e7eb;margin:0 auto 16px;">&nbsp;</div>
				<div style="font-size:12px;color:#9ca3af;">本邮件由 {site} 自动发送，请勿直接回复。</div>
				<div style="font-size:12px;color:#d1d5db;margin-top:5px;">&copy; 2026 {site}</div>
			</td>
		</tr></tbody>
	</table>
</div>
HTML;

	$greeting = ( '' === trim( $name ) || '用户' === $name ) ? __( '您好！', 'zhiji' ) : sprintf( __( '%s，您好！', 'zhiji' ), $name );
	$extra_line = '';
	if ( 'vip_day' === $prize['type'] || 'vip_month' === $prize['type'] ) {
		$extra_line = ! empty( $extra['vip_expire'] )
			? sprintf( __( '会员有效期至 %s，届时请及时续费以延续权益。', 'zhiji' ), $extra['vip_expire'] )
			: __( '会员时长已叠加至您的账户。', 'zhiji' );
	} elseif ( ( 'coupon' === $prize['type'] || 'free' === $prize['type'] ) && ! empty( $extra['coupon_expire'] ) ) {
		$extra_line = sprintf( __( '该优惠码有效期至 %s，逾期自动失效。', 'zhiji' ), date_i18n( 'Y年n月j日 H:i', strtotime( $extra['coupon_expire'] ) ) );
	} else {
		$extra_line = __( '奖励已实时到账，可前往「个人中心」查看明细。', 'zhiji' );
	}

	$html = str_replace(
		array( '{logo_html}', '{greeting}', '{type_label}', '{value_line}', '{prize_name}', '{extra_line}', '{home_url}', '{center_url}', '{site}', '{btn_bg}' ),
		array( $logo_html, $greeting, $type_label, $value_line, esc_html( $prize['name'] ), $extra_line, $home_url, $center_url, esc_html( $site ), $mail_brand ),
		$html
	);

	return $html;
}

/* ===================== 抽奖 / 分享记录 ===================== */

/** 写一条全局抽奖记录（最多保留 200 条）。 */
function zhiji_lottery_log_draw( $uid, $prize, $is_extra ) {
	$log = get_option( ZHIJI_LOTTERY_LOG_OPTION, array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	$user = get_userdata( $uid );
	$log[] = array(
		'time'  => current_time( 'mysql' ),
		'uid'   => $uid,
		'user'  => $user ? $user->display_name : 'ID#' . $uid,
		'name'  => $prize['name'],
		'type'  => $prize['type'],
		'value' => $prize['value'],
		'extra' => $is_extra ? 1 : 0,
	);
	if ( count( $log ) > 200 ) {
		$log = array_slice( $log, -200 );
	}
	update_option( ZHIJI_LOTTERY_LOG_OPTION, $log );
}

/** 渲染「最近抽奖记录」看板（短码与 AJAX 响应共用，实时更新）。 */
function zhiji_lottery_render_log() {
	if ( ! (bool) zhiji_get_option( 'lottery_show_stats', 1 ) ) {
		return '';
	}
	$log = get_option( ZHIJI_LOTTERY_LOG_OPTION, array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}
	$log = array_reverse( array_slice( $log, -10 ) );
	if ( empty( $log ) ) {
		return '';
	}
	$rows = '';
	foreach ( $log as $r ) {
		$rows .= '<tr><td class="muted-color">' . esc_html( $r['time'] ) . '</td>'
			. '<td>' . esc_html( $r['user'] ) . '</td>'
			. '<td>' . esc_html( $r['name'] ) . '</td>'
			. '<td class="muted-color">' . ( $r['extra'] ? '兑换' : '免费' ) . '</td></tr>';
	}
	return '<div class="zhiji-lottery-log">'
		. '<div class="zhiji-lottery-log-title">📋 最近抽奖记录</div>'
		. '<table><thead><tr><th>时间</th><th>用户</th><th>奖品</th><th>类型</th></tr></thead>'
		. '<tbody>' . $rows . '</tbody></table></div>';
}

/* ===================== AJAX：抽奖 ===================== */

function zhiji_lottery_ajax_draw() {
	check_ajax_referer( 'zhiji_lottery_draw', 'nonce' );
	$uid     = get_current_user_id();
	$today   = current_time( 'Y-m-d' );
	$is_extra = ! empty( $_POST['extra'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

	// 最低等级校验
	$min_level = max( 0, (int) zhiji_get_option( 'lottery_min_level', 0 ) );
	if ( $min_level > 0  ) {
		if ( (int) Zhiji_Adapter::user_level( $uid ) < $min_level ) {
			wp_send_json( array( 'error' => 1, 'msg' => '需达到 LV' . $min_level . ' 才能参与抽奖' ) );
		}
	}

	// 免费次数
	$daily_free = max( 1, (int) zhiji_get_option( 'lottery_daily_free', 1 ) );
	$today_used = zhiji_lottery_parse_day_count( get_user_meta( $uid, ZHIJI_LOTTERY_META_TODAY, true ), $today );
	$free_left  = max( 0, $daily_free - $today_used );
	if ( $free_left <= 0 && ! $is_extra ) {
		$cost = (int) zhiji_get_option( 'lottery_extra_cost', 0 );
		$msg  = $cost > 0 
			? '今日免费次数已用完，可用积分兑换再抽（' . $cost . ' 积分/次）'
			: '今日已抽过，明天再来吧';
		wp_send_json( array( 'error' => 1, 'msg' => $msg ) );
	}

	// 每日总抽次上限
	if ( ! zhiji_lottery_check_daily_total( $uid, $today ) ) {
		wp_send_json( array( 'error' => 1, 'msg' => '今日总抽奖次数已达上限' ) );
	}

	// 积分兑换额外次数
	if ( $is_extra ) {
		$cost = (int) zhiji_get_option( 'lottery_extra_cost', 0 );
		$max  = (int) zhiji_get_option( 'lottery_extra_max', 5 );
		if ( $cost <= 0 ) {
			wp_send_json( array( 'error' => 1, 'msg' => '未开启积分兑换' ) );
		}
		if ( ! function_exists( 'zibpay_update_user_points' ) ) {
			wp_send_json( array( 'error' => 1, 'msg' => '积分系统未启用' ) );
		}
		$ecnt = zhiji_lottery_parse_day_count( get_user_meta( $uid, ZHIJI_LOTTERY_META_EXTRA_DATE, true ) . '|' . get_user_meta( $uid, ZHIJI_LOTTERY_META_EXTRA_CNT, true ), $today );
		if ( $ecnt >= $max ) {
			wp_send_json( array( 'error' => 1, 'msg' => '今日兑换次数已达上限' ) );
		}
		$points = (int) Zhiji_Adapter::get_user_points( $uid );
		if ( $points < $cost ) {
			wp_send_json( array( 'error' => 1, 'msg' => '积分不足，当前 ' . $points . ' / 需要 ' . $cost ) );
		}
		Zhiji_Adapter::update_user_points( $uid, array( 'value' => - $cost, 'type' => '抽奖兑换', 'desc' => '大转盘积分兑换抽奖次数' ) );
		update_user_meta( $uid, ZHIJI_LOTTERY_META_EXTRA_DATE, $today );
		update_user_meta( $uid, ZHIJI_LOTTERY_META_EXTRA_CNT, $ecnt + 1 );
	}

	$prizes = zhiji_lottery_get_prizes();
	if ( empty( $prizes ) ) {
		wp_send_json( array( 'error' => 1, 'msg' => '奖品未配置' ) );
	}

	$index = zhiji_lottery_weighted_pick( $prizes );
	$prize = $prizes[ $index ];
	$grant = zhiji_lottery_grant_prize( $uid, $prize );
	// 中奖站内通知（积分/经验/余额/会员；优惠码类已自带码通知）
	zhiji_lottery_notify_win( $uid, $prize, $grant );

	// 计数
	if ( ! $is_extra ) {
		update_user_meta( $uid, ZHIJI_LOTTERY_META_TODAY, $today . '|' . ( $today_used + 1 ) );
	}
	update_user_meta( $uid, ZHIJI_LOTTERY_META_TOTAL, (int) get_user_meta( $uid, ZHIJI_LOTTERY_META_TOTAL, true ) + 1 );
	if ( 'none' !== $prize['type'] ) {
		update_user_meta( $uid, ZHIJI_LOTTERY_META_WINS, (int) get_user_meta( $uid, ZHIJI_LOTTERY_META_WINS, true ) + 1 );
	}
	zhiji_lottery_inc_daily_total( $uid, $today );
	zhiji_lottery_log_draw( $uid, $prize, $is_extra );

	// 中奖邮件联动（优惠码走票据模板、其余走品牌卡片；后台开关可关）
	// 保证送达：生成唯一 mail_key 暂存奖品信息，前端特效播完主动 AJAX 发送（不依赖下次访问）；cron 仅作 5 分钟兜底，transient 去重防重复
	if ( 'none' !== $prize['type'] ) {
		$mail_key = $uid . '_' . time() . '_' . wp_rand( 1000, 9999 );
		set_transient( 'zhiji_lottery_mail_' . md5( $mail_key ), array(
			'uid'   => $uid,
			'prize' => $prize,
			'extra' => $grant['extra'],
		), 600 );
		wp_schedule_single_event( time() + 300, 'zhiji_lottery_send_win_mail_cron', array( $mail_key ) );
	}

	// 事件广播（供弹幕 / 实时动态订阅）
	if ( 'none' !== $prize['type'] ) {
		zhiji_event_fire(
			'lottery_win',
			array(
				'uid'   => $uid,
				'name'  => $prize['name'],
				'type'  => $prize['type'],
				'value' => $prize['value'],
				'url'   => home_url( '/' ),
			)
		);
	}

	wp_send_json(
		array(
			'error'   => 0,
			'name'    => $prize['name'],
			'type'    => $prize['type'],
			'msg'     => $grant['msg'],
			'index'   => $index,
			'won'     => ( false !== strpos( $grant['msg'], '恭喜' ) ? 1 : 0 ),
			'mail_key' => isset( $mail_key ) ? $mail_key : '',
			'coupon'  => isset( $grant['extra']['coupon_code'] ) ? $grant['extra']['coupon_code'] : '',
			'balance' => (float) Zhiji_Adapter::get_user_balance( $uid ),
			'points'  => (int) Zhiji_Adapter::get_user_points( $uid ),
			'total'   => (int) get_user_meta( $uid, ZHIJI_LOTTERY_META_TOTAL, true ),
			'wins'    => (int) get_user_meta( $uid, ZHIJI_LOTTERY_META_WINS, true ),
			'state'   => zhiji_lottery_get_user_state( $uid, $today ),
			'log_html' => zhiji_lottery_render_log(),
		)
	);
}

/* ===================== AJAX：中奖邮件即时发送 ===================== */

/**
 * AJAX：中奖邮件即时发送（前端特效播完后触发）
 * - 保证送达：不依赖下次访问触发 WP-Cron（用户看完特效即发，页面关不关不影响本次）；
 * - transient 存奖品信息（10 分钟），发送后置已发标记去重（cron 兜底不会重复发）。
 */
function zhiji_lottery_ajax_send_mail() {
	check_ajax_referer( 'zhiji_lottery_draw', 'nonce' );
	$uid = get_current_user_id();
	$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
	if ( ! $uid || ! $key || 0 !== strpos( $key, (string) $uid . '_' ) ) {
		wp_send_json( array( 'error' => 1, 'msg' => 'invalid' ) );
	}
	$flag = 'zhiji_lottery_mail_sent_' . md5( $key );
	if ( get_transient( $flag ) ) {
		wp_send_json( array( 'error' => 0, 'msg' => 'already-sent' ) );
	}
	$info = get_transient( 'zhiji_lottery_mail_' . md5( $key ) );
	if ( ! is_array( $info ) || (int) $info['uid'] !== (int) $uid ) {
		wp_send_json( array( 'error' => 1, 'msg' => 'expired' ) );
	}
	zhiji_lottery_send_win_mail( $uid, $info['prize'], $info['extra'] );
	set_transient( $flag, 1, 600 );
	wp_send_json( array( 'error' => 0, 'msg' => 'ok' ) );
}

/* ===================== AJAX：分享得次数 ===================== */

function zhiji_lottery_ajax_share() {
	check_ajax_referer( 'zhiji_lottery_draw', 'nonce' );
	$uid   = get_current_user_id();
	$today = current_time( 'Y-m-d' );

	$max = max( 0, (int) zhiji_get_option( 'lottery_share_daily', 1 ) );
	if ( $max <= 0 ) {
		wp_send_json( array( 'error' => 1, 'msg' => '分享得次数已关闭' ) );
	}
	$scnt = zhiji_lottery_parse_day_count( get_user_meta( $uid, ZHIJI_LOTTERY_META_SHARE_DATE, true ) . '|' . get_user_meta( $uid, ZHIJI_LOTTERY_META_SHARE_CNT, true ), $today );
	if ( $scnt >= $max ) {
		wp_send_json( array( 'error' => 1, 'msg' => '今日分享奖励已用完' ) );
	}
	if ( ! zhiji_lottery_check_daily_total( $uid, $today ) ) {
		wp_send_json( array( 'error' => 1, 'msg' => '今日总抽奖次数已达上限' ) );
	}

	// 分享奖励叠加到「免费剩余」：已用次数 -1
	$today_used = zhiji_lottery_parse_day_count( get_user_meta( $uid, ZHIJI_LOTTERY_META_TODAY, true ), $today );
	update_user_meta( $uid, ZHIJI_LOTTERY_META_TODAY, $today . '|' . max( 0, $today_used - 1 ) );
	update_user_meta( $uid, ZHIJI_LOTTERY_META_SHARE_DATE, $today );
	update_user_meta( $uid, ZHIJI_LOTTERY_META_SHARE_CNT, $scnt + 1 );
	zhiji_lottery_inc_daily_total( $uid, $today );

	wp_send_json( array( 'error' => 0, 'msg' => '分享成功，已获得 1 次抽奖机会（今日还可分享 ' . ( $max - $scnt - 1 ) . ' 次）' ) );
}

/* ===================== 前台渲染（短码 / 弹窗 / 悬浮按钮） ===================== */

/** 弹窗是否已在页面输出（短码 / 全局弹窗二选一，避免重复）。 */
function zhiji_lottery_modal_emitted() {
	static $done = false;
	$prev = $done;
	$done = true;
	return $prev;
}

/**
 * 大转盘弹窗 DOM（短码与全局弹窗共用）。
 *
 * @param int    $size     转盘直径 px（160~480，0 取后台 lottery_size）
 * @param string $modal_id 弹窗 DOM id
 * @return string
 */
function zhiji_lottery_modal_html( $size = 0, $modal_id = '' ) {
	$size = (int) $size;
	if ( $size <= 0 ) {
		$size = (int) zhiji_get_option( 'lottery_size', 240 );
	}
	$size = max( 160, min( 480, $size ) );

	$uid   = get_current_user_id();
	$today = current_time( 'Y-m-d' );

	$show_stats = (bool) zhiji_get_option( 'lottery_show_stats', 1 );
	$total      = $show_stats ? (int) get_user_meta( $uid, ZHIJI_LOTTERY_META_TOTAL, true ) : 0;
	$wins       = $show_stats ? (int) get_user_meta( $uid, ZHIJI_LOTTERY_META_WINS, true ) : 0;

	$st         = zhiji_lottery_get_user_state( $uid, $today );
	$done       = $st['done'];
	$extra_left = $st['extra_left'];
	$can_share  = $st['can_share'];

	$prizes = zhiji_lottery_get_prizes();
	if ( count( $prizes ) > 12 ) {
		$prizes = array_slice( $prizes, 0, 12 );
	}
	$n      = max( 1, count( $prizes ) );
	$colors = array( '#ffd9b8', '#ffc9d8', '#bfe0ff', '#c8f0ce', '#ffe9a8', '#d9ccff', '#ffd6e0', '#c9e7ff', '#ffe0c2', '#d3f0d8', '#ffe0ec', '#d6e6ff' );

	if ( ! $modal_id ) {
		$modal_id = 'zhiji-lottery-modal-' . uniqid();
	}
	$canvas_id  = 'zhiji-lottery-canvas-' . uniqid();
	$prize_json = wp_json_encode( array_column( $prizes, 'name' ), JSON_UNESCAPED_UNICODE );
	$color_json = wp_json_encode( array_slice( $colors, 0, $n ) );
	$nonce      = wp_create_nonce( 'zhiji_lottery_draw' );

	$html  = '<div id="' . esc_attr( $modal_id ) . '" class="zhiji-lottery-modal" data-nonce="' . esc_attr( $nonce ) . '" style="--zhiji-lottery-size:' . (int) $size . 'px;">';
	$html .= '<div class="zhiji-lottery-card">';
	$html .= '<a href="javascript:;" class="zhiji-lottery-close" aria-label="关闭">&times;</a>';
	$html .= '<h3 class="zhiji-lottery-title">✨ 幸运大抽奖 ✨</h3>';

	/* ===== 九宫格（3×3：8 个奖品格 + 中心按钮，参考图：彩色格子 + 蓝框容器） ===== */
	/* SVG 图标（内联，跨平台不依赖 emoji 字体，避免部分设备显示方框） */
	$type_icons = array(
		'none'    => '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="#9aa0a6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="3"/><circle cx="8.5" cy="8.5" r="1.1" fill="#9aa0a6" stroke="none"/><circle cx="15.5" cy="8.5" r="1.1" fill="#9aa0a6" stroke="none"/><circle cx="8.5" cy="15.5" r="1.1" fill="#9aa0a6" stroke="none"/><circle cx="15.5" cy="15.5" r="1.1" fill="#9aa0a6" stroke="none"/><circle cx="12" cy="12" r="1.1" fill="#9aa0a6" stroke="none"/></svg>',
		'points'  => '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="#f5a623" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v9M9.6 9.8h4.2a1.6 1.6 0 0 1 0 3.2h-3.6a1.6 1.6 0 0 0 0 3.2h4.2"/></svg>',
		'level'   => '<svg viewBox="0 0 24 24" width="19" height="19" fill="#8e6cf0" stroke="none"><path d="M12 3l2.7 5.5 6.1.9-4.4 4.3 1 6L12 17l-5.4 2.8 1-6L3.2 9.4l6.1-.9z"/></svg>',
		'balance' => '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="#22b573" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7.5A2 2 0 0 1 5 5.5h13a1 1 0 0 1 1 1v1.5"/><path d="M3 7.5v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H5"/><circle cx="16.5" cy="14" r="1.2" fill="#22b573" stroke="none"/></svg>',
		'coupon'  => '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="#ff5a5f" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1.5a2 2 0 0 0 0 4V16a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2.5a2 2 0 0 0 0-4z"/><path d="M14 6v12"/><path d="M9 6v12"/></svg>',
		'free'    => '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="#e8604c" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1.5a2 2 0 0 0 0 4V16a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2.5a2 2 0 0 0 0-4z"/><path d="M14 6v12"/><path d="M9 6v12"/><path d="M11.6 9.2l-2.3 2.6 1.7 1.7 2.8-3.2"/></svg>',
		'vip'     => '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="#4f7dff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l4 6-10 12L2 9z"/><path d="M2 9h20"/><path d="M9 3l-2 6 5 12"/><path d="M15 3l2 6-5 12"/></svg>',
	);
	$grid = '<div class="zhiji-lottery-grid" data-zhiji-prizes="' . esc_attr( $prize_json ) . '">';
	$cell_idx = 0;
	for ( $g = 0; $g < 9; $g++ ) {
		if ( 4 === $g ) {
			// 中心按钮：优先免费抽，其次积分兑换，否则禁用
			if ( ! $done ) {
				$grid .= '<a href="javascript:;" class="zhiji-grid-btn zhiji-lottery-draw-btn" data-extra="0" data-nonce="' . esc_attr( $nonce ) . '">开始抽奖<span class="zgb-sub">免费剩 ' . (int) $st['free_left'] . ' 次</span></a>';
			} elseif ( $extra_left > 0 ) {
				$grid .= '<a href="javascript:;" class="zhiji-grid-btn zhiji-lottery-draw-btn" data-extra="1" data-nonce="' . esc_attr( $nonce ) . '">积分兑换<span class="zgb-sub">' . (int) $st['extra_cost'] . '/次·剩' . (int) $extra_left . '次</span></a>';
			} else {
				$grid .= '<div class="zhiji-grid-btn is-disabled">今日已抽完</div>';
			}
			continue;
		}
		if ( isset( $prizes[ $cell_idx ] ) ) {
			$p  = $prizes[ $cell_idx ];
			$pt = $p['type'];
			// 会员类奖品统一映射到 vip 钻石图标（vip_day/vip_month/vip_year）
			if ( in_array( $pt, array( 'vip_day', 'vip_month', 'vip_year' ), true ) ) {
				$pt = 'vip';
			}
			$icon = isset( $type_icons[ $pt ] ) ? $type_icons[ $pt ] : $type_icons['none'];
			$grid .= '<div class="zhiji-grid-cell" data-idx="' . (int) $cell_idx . '" style="--cg:' . esc_attr( $colors[ $cell_idx % count( $colors ) ] ) . '"><span class="zgc-ic">' . $icon . '</span><span class="zgc-tx">' . esc_html( $p['name'] ) . '</span></div>';
		} else {
			$grid .= '<div class="zhiji-grid-cell is-blank" data-idx="-1"><span class="zgc-ic">' . $type_icons['none'] . '</span><span class="zgc-tx">谢谢参与</span></div>';
		}
		$cell_idx++;
	}
	$grid .= '</div>';

	/* ===== 右栏：账户（余额/积分/中奖次数）+ 最近中奖记录 ===== */
	$balance  = (float) Zhiji_Adapter::get_user_balance( $uid );
	/* 注意：积分必须用 Adapter::get_user_points（points meta，个人中心口径），不能用等级经验接口 */
	$integral = (int) Zhiji_Adapter::get_user_points( $uid );
	$side  = '<div class="zhiji-lottery-side">';
	$side .= '<div class="zhiji-lottery-account">';
	$side .= '<div class="zla-title">我的账户</div>';
	$side .= '<div class="zla-grid">';
	$side .= '<div class="zla-item"><div class="zla-label"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#22b573" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7.5A2 2 0 0 1 5 5.5h13a1 1 0 0 1 1 1v1.5"/><path d="M3 7.5v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H5"/><circle cx="16.5" cy="14" r="1.2" fill="#22b573" stroke="none"/></svg> 当前余额</div><div class="zla-num" data-zkey="balance">¥' . number_format( $balance, 2 ) . '</div></div>';
	$side .= '<div class="zla-item"><div class="zla-label"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#f5a623" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v9M9.6 9.8h4.2a1.6 1.6 0 0 1 0 3.2h-3.6a1.6 1.6 0 0 0 0 3.2h4.2"/></svg> 当前积分</div><div class="zla-num" data-zkey="points">' . $integral . '</div></div>';
	$side .= '</div>';
	$side .= '<div class="zla-item zla-item-full"><div class="zla-label"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#f5a623" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 21h8M12 17.5v3.5M7 4h10v6a5 5 0 0 1-10 0z"/><path d="M7 5.5H4.5v2.5a3 3 0 0 0 3 3M17 5.5h2.5v2.5a3 3 0 0 1-3 3"/></svg> 中奖次数</div><div class="zla-num" data-zkey="wins">' . (int) $wins . '</div></div>';
	$side .= '</div>';
	$side .= zhiji_lottery_render_log();
	$side .= '</div>';

	$html .= '<div class="zhiji-lottery-body">' . $grid . $side . '</div>';

	$msg_txt = $done ? '今日免费次数已用完' : '今日免费剩 ' . $st['free_left'] . ' 次';
	if ( $done && $extra_left > 0 ) {
		$msg_txt .= ' · 可积分兑换再抽';
	}
	$stats_html = $show_stats ? ' · 累计 ' . $total . ' 次 / 中奖 ' . $wins . ' 次' : '';
	$html .= '<div class="zhiji-lottery-msg">' . esc_html( $msg_txt ) . esc_html( $stats_html ) . '</div>';

	$html .= '<div class="zhiji-lottery-result"></div>';

	$html .= '<div class="zhiji-lottery-actions">';
	if ( ! $done ) {
		$html .= '<a href="javascript:;" class="zhiji-lottery-draw-btn zhiji-btn zhiji-btn-blue" data-extra="0" data-nonce="' . esc_attr( $nonce ) . '">🎲 开始抽奖</a>';
	} elseif ( $extra_left > 0 ) {
		$html .= '<a href="javascript:;" class="zhiji-lottery-draw-btn zhiji-btn zhiji-btn-yellow" data-extra="1" data-nonce="' . esc_attr( $nonce ) . '">💰 积分兑换（' . (int) $st['extra_cost'] . '/次·剩' . $extra_left . '次）</a>';
	}
	if ( $can_share ) {
		$html .= '<a href="javascript:;" class="zhiji-lottery-share-btn zhiji-btn zhiji-btn-green" data-nonce="' . esc_attr( $nonce ) . '">🔗 分享得次数（剩' . (int) $st['share_left'] . '次）</a>';
	}
	$html .= '</div>';

	$html .= '</div></div>';
	return $html;
}

/**
 * [zhiji_lottery size="240" trigger="button|wheel"] 短代码。
 */
function zhiji_lottery_shortcode( $atts = array() ) {
	if ( ! is_user_logged_in() ) {
		return '<div class="muted-box padding-10 muted-color">请先 <a href="' . esc_url( wp_login_url() ) . '">登录</a> 后抽奖。</div>';
	}

	$atts = shortcode_atts(
		array(
			'size'    => 0,
			'trigger' => 'button',
		),
		$atts,
		'zhiji_lottery'
	);
	$trigger = in_array( $atts['trigger'], array( 'button', 'wheel' ), true ) ? $atts['trigger'] : 'button';
	$size = (int) $atts['size'];
	if ( $size <= 0 ) {
		$size = (int) zhiji_get_option( 'lottery_size', 240 );
	}
	$size = max( 160, min( 480, $size ) );

	$uid   = get_current_user_id();
	$today = current_time( 'Y-m-d' );
	$show_stats = (bool) zhiji_get_option( 'lottery_show_stats', 1 );
	$total = $show_stats ? (int) get_user_meta( $uid, ZHIJI_LOTTERY_META_TOTAL, true ) : 0;
	$wins  = $show_stats ? (int) get_user_meta( $uid, ZHIJI_LOTTERY_META_WINS, true ) : 0;
	$st    = zhiji_lottery_get_user_state( $uid, $today );
	$done  = $st['done'];

	$modal_id  = 'zhiji-lottery-modal-' . uniqid();
	$prizes    = zhiji_lottery_get_prizes();
	if ( count( $prizes ) > 12 ) {
		$prizes = array_slice( $prizes, 0, 12 );
	}
	$n         = max( 1, count( $prizes ) );
	$colors    = array( '#ffd9b8', '#ffc9d8', '#bfe0ff', '#c8f0ce', '#ffe9a8', '#d9ccff', '#ffd6e0', '#c9e7ff', '#ffe0c2', '#d3f0d8', '#ffe0ec', '#d6e6ff' );
	$prize_json = wp_json_encode( array_column( $prizes, 'name' ), JSON_UNESCAPED_UNICODE );
	$color_json = wp_json_encode( array_slice( $colors, 0, $n ) );

	if ( 'wheel' === $trigger ) {
		$dis = ( $done && $st['extra_left'] <= 0 ) ? ' style="pointer-events:none;opacity:.55;"' : '';
		$cls = 'zhiji-lottery-trigger-wheel zhiji-lottery-open-btn' . ( ( $done && $st['extra_left'] <= 0 ) ? ' is-disabled' : '' );
		$html  = '<div class="zhiji-lottery-trigger-wrap" style="text-align:center;">';
		$html .= '<canvas class="' . esc_attr( $cls ) . '" width="' . (int) $size . '" height="' . (int) $size . '"'
			. ' data-zhiji-modal="' . esc_attr( $modal_id ) . '"'
			. ' data-zhiji-size="' . (int) $size . '"'
			. ' data-zhiji-prizes="' . esc_attr( $prize_json ) . '"'
			. ' data-zhiji-colors="' . esc_attr( $color_json ) . '"' . $dis . '></canvas>';
		$html .= '<div class="zhiji-lottery-trigger-hint">' . ( ( $done && $st['extra_left'] <= 0 ) ? '今日已抽完，明天再来 🌙' : '🎯 点击转盘抽奖' ) . '</div>';
		if ( $show_stats ) {
			$html .= '<div class="zhiji-lottery-stats">累计 ' . $total . ' 次 / 中奖 ' . $wins . ' 次</div>';
		}
		$html .= '</div>';
	} else {
		$btn_text = $done ? ( $st['extra_left'] > 0 ? '抽奖（可兑换）' : '今日已抽完' ) : '开始抽奖';
		$html = '<div class="zhiji-lottery-wrap" style="text-align:center;">';
		if ( $done && $st['extra_left'] <= 0 ) {
			$html .= '<a href="javascript:;" class="zhiji-lottery-open-btn muted-box" style="pointer-events:none;opacity:.55;">' . esc_html( $btn_text ) . '</a>';
		} else {
			$html .= '<a href="javascript:;" class="zhiji-lottery-open-btn zhiji-btn ' . ( $done ? 'zhiji-btn-yellow' : 'zhiji-btn-blue' ) . '" data-zhiji-modal="' . esc_attr( $modal_id ) . '">' . esc_html( $btn_text ) . '</a>';
		}
		if ( $show_stats ) {
			$html .= '<div class="zhiji-lottery-stats">累计 ' . $total . ' 次 / 中奖 ' . $wins . ' 次</div>';
		}
		$html .= '</div>';
	}

	$html .= zhiji_lottery_modal_html( $size, $modal_id );
	return $html;
}

/** 全局弹窗：页面无短码时自动挂载（保证悬浮按钮可弹出）。 */
function zhiji_lottery_global_modal() {
	if ( ! is_user_logged_in() ) {
		return;
	}
	if ( zhiji_lottery_modal_emitted() ) {
		return;
	}
	// 仅输出隐藏弹窗容器；JS 会在 DOMready 移入 body
	echo zhiji_lottery_modal_html( 0, '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 内部已转义
}

/** 融入父主题右侧悬浮按钮栏（zib_float_right 过滤器）。 */
function zhiji_lottery_float_right_btn( $buttons ) {
	// 父主题 zib_float_right 过滤器传入的是「字符串」（浮动按钮 HTML），不是数组。
	// 不能使用 $buttons[] 追加（PHP8 会抛 "[] operator not supported for strings"），改为字符串拼接。
	$modal_id = 'zhiji-lottery-modal-' . uniqid();
	$btn = '<a href="javascript:;" class="float-btn float-btn-sm zhiji-lottery-open-btn" data-zhiji-modal="' . esc_attr( $modal_id ) . '"><i class="fa fa-fw fa-gift" style="font-size:20px;line-height:44px;"></i><span>每日抽奖</span></a>'
		. zhiji_lottery_modal_html( 0, $modal_id );
	return ( is_string( $buttons ) ? $buttons : '' ) . $btn;
}

// 模块初始化
add_action( 'after_setup_theme', 'zhiji_lottery_init' );

/* ===================== 经验来源标签：抽奖（integral_add_options 扩展） ===================== */
add_filter( 'integral_add_options', 'zhiji_lottery_integral_add_options' );
function zhiji_lottery_integral_add_options( $options ) {
	$options['lottery'] = array( __( '每日抽奖', 'zhiji' ), 0, __( '参与每日抽奖获得经验值', 'zhiji' ), __( '抽奖', 'zhiji' ) );
	// 父主题积分来源列表接口会直接读取 $opt['lottery']，后台未保存过该键会触发
	// "Undefined array key" 警告。这里补一个默认值 0，仅在缺键时写一次。
	$opt = (array) _pz( 'user_integral_opt', array() );
	if ( ! array_key_exists( 'lottery', $opt ) && function_exists( '_spz' ) ) {
		$opt['lottery'] = 0;
		_spz( 'user_integral_opt', $opt );
	}
	return $options;
}
