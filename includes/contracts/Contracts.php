<?php
/**
 * @module  Contracts
 * @desc    平台能力契约层 —— 模块间通信的唯一面（纯接口，零依赖）
 * @since   2.0.0
 *
 * 2026-10-02（P0 架构重构）新增。
 *
 * 【这一层存在的意义】
 * 改造前模块之间靠「直接调对方的全局函数」耦合（实测 11 处）：
 *   mail_template 被 4 个模块调、reward_center 被 3 个调、coupon_give 与 reward_center 还互相调。
 * 直接函数调用无法约束依赖方向，也拦不住循环依赖。本层把这些「能力」抽象成接口：
 *
 *   业务模块 / 平台能力  ──实现──▶  契约（本文件）  ──调用──▶  核心服务（core/ + notify/）
 *            ▲                                                  │
 *            └──────────────── 事件回传（只读通知）◀────────────┘
 *
 * 【硬约束】
 *   1. 本文件**不得** require/include 任何文件、不得查库、不得读 $_GET/$_POST
 *      —— 它会在每个请求里被加载，必须零开销
 *   2. 契约只描述「能做什么」，**不含任何实现**
 *   3. 模块间**只允许**通过「契约」或「事件」通信，禁止跨模块直调函数
 *   4. 新增能力 = 新增接口 + 在平台能力层实现，**核心逻辑不因此改动**（可插拔）
 *
 * 【provides 映射】哪个模块实现哪个契约，由 manifest 声明（见 P1）：
 *   Ledger       ← reward_center     积分 / 成长值账本
 *   Template     ← mail_template     消息模板渲染
 *   Dispatcher   ← reward_notify     通知投递（频控 / 去重 / 日志）
 *   CouponIssuer ← coupon_give       优惠券发放
 *   Api          ← api_gateway       统一 AJAX 网关
 *   Event        ← core/EventBus     事件广播
 */

defined('ABSPATH') || exit;

/**
 * 账本契约：积分 / 成长值的唯一变更入口
 *
 * 实现方保证：原子扣减、幂等（同幂等键重复调用不重复扣）、写流水。
 */
interface Zhiji_Contract_Ledger
{
    /**
     * 发放（增加余额/成长值）
     *
     * @param int    $uid    用户 ID
     * @param string $type   账本类型（points / growth / …）
     * @param int    $amount 数量（正数）
     * @param array  $meta   附加信息（source / ref / expire …）
     * @return array{ok:bool,balance:int,message:string}
     */
    public function grant($uid, $type, $amount, array $meta = array());

    /**
     * 扣减（减少余额，带余额充足性校验）
     *
     * @param int    $uid    用户 ID
     * @param string $type   账本类型
     * @param int    $amount 数量（正数，内部取负）
     * @param array  $meta   附加信息
     * @return array{ok:bool,balance:int,message:string}
     */
    public function spend($uid, $type, $amount, array $meta = array());

    /**
     * 查询余额
     *
     * @param int    $uid  用户 ID
     * @param string $type 账本类型
     * @return int 余额（不存在返回 0）
     */
    public function balance($uid, $type);

    /**
     * 幂等键是否已被占用（用于发奖前置检查）
     *
     * @param string $key 幂等键
     * @return bool
     */
    public function has_key($key);
}

/**
 * 模板契约：消息模板的渲染与版本管理
 */
interface Zhiji_Contract_Template
{
    /**
     * 渲染
     *
     * @param string $template 模板标识（如 reward_mail / welcome）
     * @param array  $vars     变量表
     * @param array  $opts     选项（format => html|text）
     * @return string 渲染结果；模板不存在返回空串
     */
    public function render($template, array $vars = array(), array $opts = array());

    /**
     * 模板是否存在
     *
     * @param string $template 模板标识
     * @return bool
     */
    public function exists($template);

    /**
     * 模板当前版本号
     *
     * @param string $template 模板标识
     * @return string 版本号（无版本返回空串）
     */
    public function version($template);
}

/**
 * 通知投递契约：触发条件、渠道编排、频控、去重
 */
interface Zhiji_Contract_Dispatcher
{
    /**
     * 投递
     *
     * @param array $payload uid / event / channels / vars / dedup_key / throttle
     * @return array{ok:bool,sent:int,skip:int,message:string}
     */
    public function dispatch(array $payload);

    /**
     * 该渠道当前是否可用（开关 / 依赖是否满足）
     *
     * @param string $channel 渠道标识（mail / toast / badge / msg）
     * @return bool
     */
    public function channel_enabled($channel);
}

/**
 * 优惠券发放契约（解耦 reward_center ⇄ coupon_give 的循环依赖用）
 *
 * 现状：reward_center 调 coupon_give 发券，coupon_give 又调 reward_center 的 grant_one()
 *      —— 双向依赖。解环后，coupon_give 通过本契约发券，不再直接调 reward_center。
 */
interface Zhiji_Contract_CouponIssuer
{
    /**
     * 发放一张券
     *
     * @param array $args { user_id, code, title, type, value, expire_days, source }
     * @return array{ok:bool,code:string,message:string}
     */
    public function issue(array $args);

    /**
     * 某用户某来源的领取限制校验
     *
     * @param int    $user_id 用户 ID
     * @param string $source  来源标识
     * @return array{ok:bool,message:string}
     */
    public function check_claimable($user_id, $source = '');
}

/**
 * 接口端点契约（统一 AJAX 网关）
 */
interface Zhiji_Contract_Api
{
    /**
     * 注册端点
     *
     * @param string   $name    端点名
     * @param callable $handler 处理函数
     * @param bool     $login   是否要求登录
     * @param string   $group   分组
     * @return void
     */
    public function register($name, $handler, $login = true, $group = '');

    /**
     * 端点是否存在
     *
     * @param string $name 端点名
     * @return bool
     */
    public function has($name);
}

/**
 * 事件契约：跨模块广播与订阅
 *
 * 广播方不感知订阅方，订阅方不依赖广播方 —— 这是模块间**唯一**允许的反向通道。
 */
interface Zhiji_Contract_Event
{
    /**
     * 广播
     *
     * @param string $event 事件名
     * @param array  $data  事件数据
     * @return void
     */
    public function fire($event, array $data = array());

    /**
     * 订阅
     *
     * @param string   $event    事件名
     * @param callable $callback 回调
     * @param int      $priority 优先级
     * @param int      $args     参数个数
     * @return void
     */
    public function on($event, $callback, $priority = 10, $args = 1);
}
