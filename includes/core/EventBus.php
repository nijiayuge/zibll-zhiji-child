<?php
/**
 * @module  EventBus
 * @desc    统一事件总线 —— 跨模块联动的唯一广播出口
 * @since   2.0.0
 *
 * 2026-10-02（P0 架构重构）：本文件从 `modules/Lottery.php` 原样迁出。
 *
 * 【为什么必须抽出来】
 * 事件总线是**基础设施**，此前却住在「抽奖大转盘」这个业务模块里（Lottery.php:218），
 * 造成两个问题：
 *   ① 抽奖模块一旦关闭，事件函数随之消失 → 其他模块调用 `zhiji_event_fire()` 直接致命错误
 *   ② 任何模块想发事件就被迫「依赖抽奖」，制造隐式耦合
 *
 * 【分层约定】
 * 依赖方向：业务模块 / 平台能力 → 契约(contracts) → 核心服务(core，本文件)
 * 事件只允许**自上而下**广播；订阅方一律用 `add_action()`，**不允许反向调用发布方**。
 */

defined('ABSPATH') || exit;

/**
 * 统一事件广播（跨模块联动扩展点）
 *
 * 广播两跳，便于既粗粒又细粒订阅：
 *   1. `zhiji_event_fire`           —— 通用入口，监听全部事件
 *   2. `zhiji_event_{$event}`      —— 细分事件，如 zhiji_event_lottery_win
 *
 * 订阅示例（订阅方侧，不需知道发布方是谁）：
 *   add_action( 'zhiji_event_lottery_win', function ( $data ) { … }, 10, 1 );
 *
 * @param string $event 事件名（建议 snake_case，业务域前缀，如 lottery_win / coupon_claimed）
 * @param array  $data  事件数据（约定含 uid / name / type / value / url）
 * @return void
 */
function zhiji_event_fire($event, $data = array())
{
    $event = (string) $event;
    if ('' === $event) {
        return;
    }

    /**
     * 通用事件（所有事件的统一入口）
     *
     * @param string $event
     * @param array  $data
     */
    do_action('zhiji_event_fire', $event, $data);

    /**
     * 细分事件（如 zhiji_event_lottery_win）
     *
     * @param array $data
     */
    do_action('zhiji_event_' . $event, $data);
}

/**
 * 订阅事件的语法糖（可选；等价于 add_action）
 *
 * 存在的意义：让订阅方的意图显式化 —— 明确「我依赖的是事件，而不是某个模块」。
 * 未来事件总线若改为异步/队列，这里是唯一的收敛点。
 *
 * @param string   $event    事件名
 * @param callable $callback 回调
 * @param int      $priority 优先级
 * @param int      $args     传给回调的参数个数
 * @return void
 */
function zhiji_event_on($event, $callback, $priority = 10, $args = 1)
{
    if ('' === (string) $event || !is_callable($callback)) {
        return;
    }
    add_action('zhiji_event_' . $event, $callback, $priority, $args);
}
