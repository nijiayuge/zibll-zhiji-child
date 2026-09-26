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

/* ============================================================
 * 内联资源服务（2026-09-26 新增，配置统一化探查报告 P2-⑦）
 *
 * 背景：多个业务模块（CouponHighlight / Danmu / Lottery 等）各自用 nowdoc
 *       内联输出 CSS/JS，写法重复且缺少统一的去重与顺序控制。
 *
 * 策略（与既有结论一致，不更改）：
 *   1) **全部在 head 内联输出** —— 本站点实测 wp_footer 输出曾在线上被环境干扰
 *      （脚本丢失 / 500），head 内联最稳；
 *   2) 不依赖任何外部资源文件（自包含）；
 *   3) 按 id 去重：同一 id 重复登记只输出一次；
 *   4) 登记顺序即输出顺序，便于控制依赖关系。
 *
 * 用法：
 *   zhiji_asset_add_css('lottery', $css_string);
 *   zhiji_asset_add_js('lottery', $js_string);
 * ============================================================ */

/**
 * 已登记的 CSS / JS 片段
 *
 * @return array 引用返回：array('css' => [id => code], 'js' => [id => code], 'printed' => bool)
 */
function &zhiji_asset_inline_store()
{
    static $store = array('css' => array(), 'js' => array(), 'printed' => false);
    return $store;
}

/**
 * 登记一段内联 CSS（head 输出，按 id 去重）
 *
 * @param string $id  唯一标识（重复登记将被忽略）
 * @param string $css CSS 代码
 * @return void
 */
function zhiji_asset_add_css($id, $css)
{
    if ('' === (string) $css) {
        return;
    }
    $store = &zhiji_asset_inline_store();
    $id    = (string) $id;
    if (!isset($store['css'][$id])) {
        $store['css'][$id] = (string) $css;
    }
}

/**
 * 登记一段内联 JS（head 输出，按 id 去重）
 *
 * @param string $id 唯一标识（重复登记将被忽略）
 * @param string $js JS 代码
 * @return void
 */
function zhiji_asset_add_js($id, $js)
{
    if ('' === (string) $js) {
        return;
    }
    $store = &zhiji_asset_inline_store();
    $id    = (string) $id;
    if (!isset($store['js'][$id])) {
        $store['js'][$id] = (string) $js;
    }
}

/**
 * 统一输出已登记的内联资源（wp_head 末尾，保证在业务模块登记之后执行）
 *
 * @return void
 */
function zhiji_asset_print_inline()
{
    if (is_admin()) {
        return;
    }
    $store = &zhiji_asset_inline_store();
    if ($store['printed']) {
        return;
    }
    $store['printed'] = true;

    if (!empty($store['css'])) {
        echo "<style id=\"zhiji-inline-css\">\n";
        foreach ($store['css'] as $code) {
            echo $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- 模块自产 CSS，非用户输入
        }
        echo "</style>\n";
    }

    if (!empty($store['js'])) {
        // 注意：**不包裹 IIFE** —— 模块的 JS 可能定义全局变量（window.ZHIJI_XXX）
        // 或依赖外部作用域，包裹会改变语义。此处按登记顺序原样拼接，与改造前行为一致。
        echo "<script id=\"zhiji-inline-js\">\n";
        foreach ($store['js'] as $code) {
            echo $code . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- 模块自产 JS，非用户输入
        }
        echo "</script>\n";
    }
}
add_action('wp_head', 'zhiji_asset_print_inline', 99);

/**
 * 查询某 id 是否已登记（供模块避免重复登记时判断）
 *
 * @param string $id
 * @param string $type css|js
 * @return bool
 */
function zhiji_asset_has($id, $type = 'css')
{
    $store = &zhiji_asset_inline_store();
    $type  = ('js' === $type) ? 'js' : 'css';
    return isset($store[$type][(string) $id]);
}

/* ============================================================
 * 页脚输出调度（2026-09-26，配置统一化探查报告 P3-⑧）
 *
 * 背景：18 处 wp_footer 钩子分散在各模块，输出内容各异（弹窗 / 脚本 / 卡片）。
 *
 * 本服务提供**统一登记入口**：模块把页脚输出回调登记进来，
 * 由本服务在唯一一个 wp_footer 钩子中按优先级依次执行 —— 钩子数 18 → 1，
 * 且输出顺序显式可控。
 *
 * ⚠️ 与「内联资源服务」的区别：
 *   · 内联资源（CSS/JS 片段）→ 走 zhiji_asset_add_css/js（head 输出）
 *   · 页脚**内容**（弹窗 DOM、卡片 HTML、运行期脚本）→ 走本服务（footer 输出）
 *
 * ⚠️ 迁移原则（保守）：存量模块中依赖"执行先后关系"的输出**保持原样**，
 *   仅迁移**无顺序依赖**（原优先级为最后 99，即"所有 DOM/脚本均已就绪"）的输出。
 * ============================================================ */

/**
 * 登记一个页脚输出回调
 *
 * @param string   $id       唯一标识（重复登记忽略）
 * @param callable $callback 输出回调（自行 echo）
 * @param int      $priority 执行顺序（数字小者先执行）
 * @return void
 */
function zhiji_footer_add($id, $callback, $priority = 10)
{
    // 注意：不在登记时做 is_callable 判断 —— 模块常在**函数定义之前**调用本函数
    // （沿用 add_action 的写法），此时函数尚未定义会使检查误判为 false。
    // 可调用性推迟到 zhiji_footer_run() 执行时再校验。
    if (empty($GLOBALS['__zhiji_footer_items']) || !is_array($GLOBALS['__zhiji_footer_items'])) {
        $GLOBALS['__zhiji_footer_items'] = array();
    }
    $GLOBALS['__zhiji_footer_items'][(string) $id] = array(
        'cb'       => $callback,
        'priority' => (int) $priority,
    );
    if (empty($GLOBALS['__zhiji_footer_hooked'])) {
        $GLOBALS['__zhiji_footer_hooked'] = true;
        // 统一钩子挂在 99 —— 与存量「页脚内容型输出」常用的优先级一致，
        // 保证执行时机不早于父主题/其它插件的同类输出（行为最接近迁移前）。
        add_action('wp_footer', 'zhiji_footer_run', 99);
    }
}

/**
 * 统一执行所有已登记的页脚输出（按优先级升序）
 *
 * @return void
 */
function zhiji_footer_run()
{
    if (is_admin()) {
        return;
    }
    $items = isset($GLOBALS['__zhiji_footer_items']) ? (array) $GLOBALS['__zhiji_footer_items'] : array();
    if (!$items) {
        return;
    }
    uasort($items, function ($a, $b) {
        return $a['priority'] - $b['priority'];
    });
    foreach ($items as $item) {
        if (!is_callable($item['cb'])) {
            continue; // 回调不可用（如模块被禁用）时静默跳过
        }
        call_user_func($item['cb']);
    }
}

/**
 * 已登记的页脚输出数量（自查 / 调试用）
 *
 * @return int
 */
function zhiji_footer_count()
{
    return isset($GLOBALS['__zhiji_footer_items']) ? count((array) $GLOBALS['__zhiji_footer_items']) : 0;
}
