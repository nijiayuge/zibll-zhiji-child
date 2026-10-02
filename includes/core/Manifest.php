<?php
/**
 * @module  Manifest
 * @desc    模块清单与依赖图 —— layer / provides / requires 的唯一数据源
 * @since   2.0.0
 *
 * 2026-10-02（P1 架构重构）新增。
 *
 * 【解决什么问题】
 * 改造前模块之间靠直接调函数耦合，依赖关系只存在于「人的记忆」里：
 *   · 谁依赖谁？→ 只能全量扫代码（本次扫描才发现 6 个公共模块）
 *   · 有没有循环？→ PHP 加载顺序侥幸能跑，`reward_center ⇄ coupon_give` 一直没人发现
 * 本文件把这层隐性关系变成**可查询、可校验、可在后台展示**的显式数据。
 *
 * 【三个字段】
 *   layer     business（业务玩法，可自由开关）| platform（平台能力，被复用）
 *   provides  本模块实现了哪些契约（对应 contracts/Contracts.php 的接口）
 *   requires  本模块依赖哪些平台能力（缺失则不得启用）
 *
 * 【数据来源】
 * 手工维护在此表；`tools/preflight.py` 会校验：
 *   ① 每个 key 都能在 includes/modules/ 找到对应文件
 *   ② provides 引用的契约都真实存在
 *   ③ requires 引用的 key 都真实存在
 *   ④ 依赖图无环
 */

defined('ABSPATH') || exit;

/**
 * 模块清单（layer / provides / requires）
 *
 * requires 依据实测的跨文件**函数调用**关系填写（由 tools/preflight.py 校验）。
 *
 * ⚠️ 勘误（2026-10-02）：`reward_center → coupon_give` 是**单向**依赖 —— RC 调用 CG 的
 * create_one / discount_meta / discount_text，而 CG 并未反向调用 RC。
 * 此前人工探查曾误报为「双向循环依赖」，原因是把 option 前缀匹配也算成了依赖。
 * 依赖图只认函数调用，不认 option 前缀。
 *
 * @return array<string,array{layer:string,provides:array,requires:array}>
 */
function zhiji_manifest()
{
    static $manifest = null;
    if (null !== $manifest) {
        return $manifest;
    }

    $manifest = array(
        'api_gateway'            => array('layer' => 'platform', 'provides' => array('Api'), 'requires' => array()),
        'article_expire'         => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'auto_delete_attachments'  => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'auto_image_alt'         => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'auto_keyword_link'      => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'baidu_seo'              => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'comment_agent'          => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'comment_beautify'       => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'comment_draw'           => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'comment_fortune'        => array('layer' => 'business', 'provides' => array(), 'requires' => array('reward_center', 'reward_notify')),
        'comment_guard'          => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'coupon_give'            => array('layer' => 'platform', 'provides' => array('CouponIssuer'), 'requires' => array('mail_template', 'reward_center')),
        'coupon_highlight'       => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'download_quota'         => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'easter_egg'             => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'email_subscribe'        => array('layer' => 'business', 'provides' => array(), 'requires' => array('mail_template', 'reward_center')),
        'friend_link_apply'      => array('layer' => 'business', 'provides' => array(), 'requires' => array('mail_template')),
        'kanban'                 => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'lottery'                => array('layer' => 'business', 'provides' => array(), 'requires' => array('coupon_give', 'mail_template')),
        'mail_template'          => array('layer' => 'platform', 'provides' => array('Template'), 'requires' => array()),
        'maintenance'            => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'member_guide'           => array('layer' => 'business', 'provides' => array(), 'requires' => array('reward_center')),
        'monitor_404'            => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'ops_console'            => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'page_cache'             => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'password_strength'      => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'post_series'            => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'quiz'                   => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'reading_progress'       => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'reward_center'          => array('layer' => 'platform', 'provides' => array('Ledger'), 'requires' => array('coupon_give')),
        'reward_notify'          => array('layer' => 'platform', 'provides' => array('Dispatcher'), 'requires' => array('mail_template')),
        'security_scanner'       => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'seed_pages'             => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'streak_guard'           => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'time_machine'           => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'transplant_beautify'    => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'tts'                    => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'webp_converter'         => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
        'weiyu'                  => array('layer' => 'business', 'provides' => array(), 'requires' => array()),
    );

    /**
     * 允许扩展：第三方可注册自己的平台能力（不改本文件）
     *
     * @param array $manifest
     */
    $manifest = (array) apply_filters('zhiji_manifest', $manifest);

    return $manifest;
}

/**
 * 取某模块的层（platform / business）；不在清单里则按 business 处理
 *
 * @param string $key 模块 key
 * @return string
 */
function zhiji_manifest_layer($key)
{
    $m = zhiji_manifest();
    return isset($m[$key]['layer']) ? $m[$key]['layer'] : 'business';
}

/**
 * 取某模块依赖的平台能力列表
 *
 * @param string $key 模块 key
 * @return array<int,string>
 */
function zhiji_manifest_requires($key)
{
    $m = zhiji_manifest();
    return isset($m[$key]['requires']) ? (array) $m[$key]['requires'] : array();
}

/**
 * 取某模块实现的契约列表
 *
 * @param string $key 模块 key
 * @return array<int,string>
 */
function zhiji_manifest_provides($key)
{
    $m = zhiji_manifest();
    return isset($m[$key]['provides']) ? (array) $m[$key]['provides'] : array();
}

/**
 * 谁依赖了它（关闭平台能力前的影响面）
 *
 * @param string $key 模块 key
 * @return array<int,string> 依赖方 key 列表
 */
function zhiji_manifest_dependents($key)
{
    $out = array();
    foreach (zhiji_manifest() as $k => $def) {
        if ($k !== $key && in_array($key, zhiji_manifest_requires($k), true)) {
            $out[] = $k;
        }
    }
    return $out;
}

/**
 * 依赖图环检测（DFS 三色标记）
 *
 * 返回所有发现的环，每个环是模块 key 数组。
 * 现状：reward_center ⇄ coupon_give 是一个已知的双向依赖，P2 用 CouponIssuer 解开。
 *
 * @return array<int,array<int,string>>
 */
function zhiji_manifest_cycles()
{
    $m       = zhiji_manifest();
    $state   = array();   // 0=未访问 1=在栈上 2=已完成
    $cycles  = array();
    $path    = array();

    $visit = function ($node) use (&$visit, &$state, &$cycles, &$path, $m) {
        $state[$node] = 1;
        $path[]       = $node;
        foreach (zhiji_manifest_requires($node) as $dep) {
            if (!isset($m[$dep])) {
                continue;   // 依赖了不在清单里的 key：preflight 会另行报错
            }
            if (!isset($state[$dep])) {
                $visit($dep);
            } elseif (1 === $state[$dep]) {
                // 找到环：从 $dep 在栈中的位置起截取
                $idx = array_search($dep, $path, true);
                $cyc = array_slice($path, false === $idx ? 0 : $idx);
                $cyc[] = $dep;   // 闭合
                $sig   = implode('>', $cyc);
                if (!in_array($sig, array_map(function ($c) { return implode('>', $c); }, $cycles), true)) {
                    $cycles[] = $cyc;
                }
            }
        }
        array_pop($path);
        $state[$node] = 2;
    };

    foreach (array_keys($m) as $node) {
        if (!isset($state[$node])) {
            $visit($node);
        }
    }

    return $cycles;
}

/**
 * 校验「待启用集合」是否满足依赖
 *
 * @param array<int,string> $enabled 待启用/已启用的模块 key 集合
 * @return array{ok:bool,missing:array<string,array<int,string>>}
 */
function zhiji_manifest_validate(array $enabled)
{
    $missing = array();
    foreach ($enabled as $key) {
        foreach (zhiji_manifest_requires($key) as $dep) {
            if (!in_array($dep, $enabled, true)) {
                $missing[$key][] = $dep;
            }
        }
    }
    return array('ok' => empty($missing), 'missing' => $missing);
}

/**
 * 校验报告（供后台展示 / preflight 断言复用）
 *
 * @return array
 */
function zhiji_manifest_report()
{
    $manifest = zhiji_manifest();
    $registered = array_keys(Zhiji_Registry::modules());
    $unknown    = array_values(array_diff($registered, array_keys($manifest)));
    $ghost      = array_values(array_diff(array_keys($manifest), $registered));
    $bad_refs   = array();
    foreach ($manifest as $k => $def) {
        foreach ((array) $def['requires'] as $dep) {
            if (!isset($manifest[$dep])) {
                $bad_refs[$k][] = 'requires→' . $dep;
            }
        }
        foreach ((array) $def['provides'] as $p) {
            // 只校验契约接口本身存在（接口在 contracts/Contracts.php，P0 已建）。
            // 实现类由 P2 起陆续加入，此处不校验，避免「声明了但还没实现」被误报为坏引用。
            if (!interface_exists('Zhiji_Contract_' . $p)) {
                $bad_refs[$k][] = 'provides→' . $p;
            }
        }
    }

    return array(
        'total'     => count($manifest),
        'platform'  => count(array_filter($manifest, function ($d) { return 'platform' === $d['layer']; })),
        'business'  => count(array_filter($manifest, function ($d) { return 'business' === $d['layer']; })),
        'unlisted'  => $unknown,   // 已注册但清单里没有
        'ghost'     => $ghost,     // 清单里有但未注册
        'bad_refs'  => $bad_refs,
        'cycles'    => zhiji_manifest_cycles(),
    );
}
