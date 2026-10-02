<?php
/**
 * @module  ContractRegistry
 * @desc    契约解析器 —— 业务模块获取平台能力的唯一入口
 * @since   2.0.7
 *
 * 2026-10-02（P2 架构重构）新增。
 *
 * 【解决什么问题】
 * 改造前模块间靠「直接调对方的全局函数」耦合。P0 把能力抽象成接口（contracts/Contracts.php），
 * 但光有接口没用 —— 调用方仍得知道「实现类叫什么、在哪个文件」。
 * 本文件补上这一层：调用方只说「我要 Ledger 能力」，由本文件按 Manifest 找到实现并交付。
 *
 *   改造前：  $x = zhiji_coupon_give_create_one( $meta );      // 硬绑定到某个模块的某个函数
 *   改造后：  $issuer = zhiji_contract('CouponIssuer');
 *            if ($issuer) { $x = $issuer->issue( $args ); }     // 只认能力，不认实现
 *
 * 好处：
 *   ① 换实现不改调用方（可插拔）
 *   ② 能力缺失时返回 null，调用方可优雅降级，而不是 function_exists 到处散落
 *   ③ 依赖关系从「隐式」变成「显式」—— 谁提供什么能力，查 Manifest 即可
 *
 * 【加载与性能】
 * 本文件在 includes/index.php 中于 Manifest 之后、模块之前加载（**必须**：
 * 需要 Manifest 才知道哪个模块提供哪个契约）。
 * 全部为懒加载：构造时不做任何事，只有真正调用 zhiji_contract() 时才解析实现。
 * 前台若无人使用契约（当前 P2 阶段全部调用方都还没切过来），开销为零。
 *
 * 【扩展】第三方可用 zhiji_contract_register() 覆盖某能力的实现，无需改本文件。
 */

defined('ABSPATH') || exit;

/**
 * 内部：已实例化的契约对象缓存（key = 契约名）
 *
 * ⚠️ 按**引用**返回：zhiji_contract_register() 要能就地改写缓存。
 * 普通 `return $c;` 只返回副本，写入会被丢弃（踩过：注册后 zhiji_contract() 仍拿旧值）。
 *
 * @return array<string,object|null>
 */
function &zhiji_contract_cache()
{
    static $c = array();
    return $c;
}

/**
 * 内部：解析并实例化某能力的实现
 *
 * 查找顺序：
 *   1. 已注册的实现（zhiji_contract_register 注册的，第三方优先）
 *   2. Manifest 中 provides 该能力的模块 → 调 zhiji_contract_implementor($module)
 *      由各模块自行返回契约实例（避免本文件硬编码模块名，保持可插拔）
 *   3. 都没有 → null
 *
 * @param string $contract 契约短名（Ledger / Template / Dispatcher / CouponIssuer / Api）
 * @return object|null
 */
function zhiji_contract_resolve($contract)
{
    $contract = (string) $contract;
    if ('' === $contract) {
        return null;
    }

    $cache = &zhiji_contract_cache();
    if (array_key_exists($contract, $cache)) {
        return $cache[$contract];
    }

    $impl = null;

    // ① 第三方注册的实现优先
    $registry = zhiji_contract_registry();
    if (isset($registry[$contract]) && is_object($registry[$contract])) {
        $impl = $registry[$contract];
    } else {
        // ② 走 Manifest：找 provides 该能力的模块
        $module = zhiji_contract_provider($contract);
        if ($module) {
            // 工厂名规则：zhiji_contract_implementor_{模块key}
            // ⚠️ 曾在此写 function_exists('zhiji_contract_implementor') 作前置守卫 ——
            //    那是**不存在**的函数（真实工厂都带模块后缀），守卫恒为 false，
            //    导致所有契约解析静默返回 null，表现为「契约全部不可用」且无任何报错。
            //    故此处直接构造完整工厂名判断，不做多余的前置探测。
            $factory = 'zhiji_contract_implementor_' . str_replace('-', '_', $module);
            if (function_exists($factory)) {
                $obj = call_user_func($factory);
                // 契约接口存在性检查：实现方若返回了不合约的对象，宁可当作未实现也不要放行
                $iface = 'Zhiji_Contract_' . $contract;
                if (is_object($obj) && interface_exists($iface) && $obj instanceof $iface) {
                    $impl = $obj;
                }
            }
        }
    }

    $cache[$contract] = $impl;

    // 解析失败时留下可诊断痕迹：契约层是**静默降级**设计（拿不到实现就返回 null，
    // 调用方走保底分支），若无任何记录，线上出问题时无从查起。
    // 仅在管理员打开调试时记录，前台零开销。
    if (null === $impl) {
        /**
         * 契约解析失败（供排障：哪个能力、谁来提供、工厂函数是否存在）
         *
         * @param string $contract
         * @param string $module
         * @param bool   $factory_exists
         */
        do_action('zhiji_contract_missing', $contract, zhiji_contract_provider($contract), false);
    }

    return $impl;
}

/**
 * 查某能力由哪个模块提供（依据 Manifest 的 provides 声明）
 *
 * @param string $contract 契约短名
 * @return string 模块 key；无人提供返回空串
 */
function zhiji_contract_provider($contract)
{
    $contract = (string) $contract;
    if ('' === $contract || !function_exists('zhiji_manifest')) {
        return '';
    }
    foreach (zhiji_manifest() as $key => $def) {
        if (in_array($contract, (array) $def['provides'], true)) {
            return $key;
        }
    }
    return '';
}

/**
 * 第三方注册表：契约名 => 实现对象
 *
 * ⚠️ 按引用返回，理由同 zhiji_contract_cache()。
 *
 * @return array<string,object>
 */
function &zhiji_contract_registry()
{
    static $r = null;
    if (null === $r) {
        /**
         * 注册自定义契约实现（覆盖 Manifest 声明的默认实现）
         *
         * @param array $r
         */
        $r = (array) apply_filters('zhiji_contracts', array());
    }
    return $r;
}

/**
 * 拿某能力的实现；不可用时返回 null（调用方须处理降级）
 *
 * @param string $contract 契约短名
 * @return object|null
 */
function zhiji_contract($contract)
{
    return zhiji_contract_resolve($contract);
}

/**
 * 注册/替换某能力的实现（第三方扩展用）
 *
 * @param string $contract 契约短名
 * @param object $impl      实现对象（须实现对应接口）
 * @return void
 */
function zhiji_contract_register($contract, $impl)
{
    $r = &zhiji_contract_registry();
    $r[(string) $contract] = $impl;
    // 清掉解析缓存，让新注册立即生效
    $c = &zhiji_contract_cache();
    unset($c[(string) $contract]);
}

/**
 * 某能力是否可用（只需知道有实现，不必取出对象）
 *
 * @param string $contract 契约短名
 * @return bool
 */
function zhiji_contract_available($contract)
{
    return (null !== zhiji_contract($contract));
}
