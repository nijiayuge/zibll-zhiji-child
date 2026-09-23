<?php
/**
 * @module  Assets
 * @desc    子主题统一前端资源加载：核心脚本 zhiji.js
 *          （提供 window.zhiji_confetti 彩带特效 + window.zhijiRefreshMsgBadge 消息角标刷新）
 * @hook    wp_enqueue_scripts(5)
 * @since   2.0.0
 * @migrate 自 v1 `assets/js/zhiji.js` + `inc/Functions/Assets.php`
 *
 * ⚠️ 2026-09-23 补迁：v1 的核心脚本 assets/js/zhiji.js 在 v2 迁移时整份遗漏，
 *    导致以下调用全部静默失败（`typeof === 'function'` 判为 false 而跳过）：
 *      - notify/Channels/Badge.php  通知后刷新铃铛角标
 *      - Lottery.php                抽奖中奖后刷新角标
 *      - CouponGive/CommentFortune/Lottery  领券/锦鲤/中奖的全屏彩带特效
 *    用户反馈「角标消息没有通知 / 未读消息没有提示」即由此引起。
 */

defined('ABSPATH') || exit;

/**
 * 前台加载核心脚本（head 输出）
 *
 * @return void
 */
function zhiji_assets_enqueue()
{
    if (is_admin()) {
        return;
    }

    wp_enqueue_script(
        'zhiji-core',
        zhiji_asset_url('js/zhiji.js'),
        array('jquery'),
        ZHIJI_VERSION,
        false // head 输出：本站点 wp_footer 输出曾出现脚本丢失，head 更稳（同 Danmu/Lottery 模块结论）
    );

    // 通用 AJAX 地址：角标刷新等基础能力不应依赖任何业务模块是否开启
    // （原实现只读 window.ZHIJI_LOTTERY_AJAX，Lottery 关闭时该变量不存在 → 角标刷新失效）
    wp_add_inline_script(
        'zhiji-core',
        'window.ZHIJI_AJAX=' . wp_json_encode(admin_url('admin-ajax.php')) . ';',
        'before'
    );
}
add_action('wp_enqueue_scripts', 'zhiji_assets_enqueue', 5);
