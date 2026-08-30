<?php
/*
 * @Author       : Qinver
 * @Url          : zibll.com
 * @Date         : 2025-07-18 21:54:02
 * @LastEditTime : 2026-04-29 22:38:28
 * @Project      : Zibll子比主题
 * @Description  : 更优雅的Wordpress主题
 * Copyright (c) 2025 by Qinver, All Rights Reserved.
 * @Email        : 770349780@qq.com
 * @Read me      : 感谢您使用子比主题，主题源码有详细的注释，支持二次开发
 * @Remind       : 使用盗版主题会存在各种未知风险。支持正版，从我做起！
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!is_super_admin()) {
    wp_die(__('您不能访问此页面', 'zib_language'), __('权限不足', 'zib_language'));
    exit;
}

$vue_data = [
    '_wpnonce'                    => wp_create_nonce('admin_order_page_ajax_submit'),
    'config'                      => [
        'shop_s'    => (bool) _pz('shop_s'),
        'income_s'  => (bool) _pz('pay_income_s'),
        'rebate_s'  => (bool) _pz('pay_rebate_s'),
        'admin_url' => admin_url(),
    ],
    'after_sale_status_name'      => [
        1 => __('待处理', 'zib_language'),
        2 => __('处理中', 'zib_language'),
        3 => __('处理完成', 'zib_language'),
        4 => __('用户取消', 'zib_language'),
        5 => __('商家驳回', 'zib_language'),
    ],
    'after_sale_type_name'        => [
        'refund'        => __('仅退款', 'zib_language'),
        'refund_return' => __('退货退款', 'zib_language'),
        'replacement'   => __('换货', 'zib_language'),
        'warranty'      => __('保修', 'zib_language'),
        'insured_price' => __('保价', 'zib_language'),
    ],
    'after_sale_progress_name'    => [
        1 => __('等待用户发货', 'zib_language'),
        2 => __('等待商家处理', 'zib_language'),
        3 => __('等待用户收货', 'zib_language'),
        4 => __('处理完成', 'zib_language'),
    ],
    'shipping_status_name'        => [
        0 => __('待发货', 'zib_language'),
        1 => __('待收货', 'zib_language'), //待收货，需要用户确认收货
        2 => __('已完成', 'zib_language'), //已完成
    ],
    'shipping_delivery_type_name' => [
        'invit_code' => __('邀请码', 'zib_language'),
        'card_pass'  => __('卡密', 'zib_language'),
        'express'    => __('快递', 'zib_language'),
        'no_express' => __('无需物流', 'zib_language'),
        'auto'       => __('自动发货', 'zib_language'),
        'fixed'      => __('虚拟商品', 'zib_language'),
        'opts'       => __('虚拟商品', 'zib_language'),
        'manual'     => __('手动发货', 'zib_language'),
    ],
    'withdraw_status_name'        => [
        0 => __('未提现', 'zib_language'),
        1 => __('已提现', 'zib_language'),
        3 => __('提现待处理', 'zib_language'),
    ],
    'order_type_name'             => [
        1  => __('付费阅读', 'zib_language'), //文章，帖子
        2  => __('付费下载', 'zib_language'), //文章
        5  => __('付费图片', 'zib_language'), //文章
        6  => __('付费视频', 'zib_language'), //文章
        4  => __('购买会员', 'zib_language'), //用户
        8  => __('余额充值', 'zib_language'), //用户
        9  => __('购买积分', 'zib_language'), //用户
        10 => __('购买商品', 'zib_language'), //商城，商品
    ],
    'status_name'                 => [
        -2 => __('已退款', 'zib_language'),
        -1 => __('已关闭', 'zib_language'),
        0  => __('待支付', 'zib_language'),
        1  => __('已支付', 'zib_language'),
    ],
    'marks'                       => [
        'pay'    => zibpay_get_pay_mark(),
        'points' => __('积分', 'zib_language'),
    ],
    'colors'                      => ['#ff4747', '#ee5307', '#1e8608', '#1a8a65', '#0c9cc8', '#086ae8', '#3353fd', '#4641e8', '#853bf2', '#e94df7', '#ca2b7d', '#d7354c', '#ff4747', '#8e24ac'],
];
zibpay_admin_page_start();
zibpay_admin_page_vue_data_filter($vue_data);

?>
<style>
    #wpbody-content .notice{
        display: none;
    }
    .loading-mask {
        position: fixed;
        inset: 0;
        background: #fff;
        z-index: 10;
    }
</style>
<div class="flex jc loading-mask shop-page-loading"><div class="loading"></div></div>
<div class="zibpay-shop admin-container" id="zibpay_app">
    <?php require ZIB_ROOT_PATH . '/zibpay/page/template/header.php'; ?>
    <div class="zibpay-shop-content">
        <transition name="slide-down" mode="out-in" tag="div">
            <div v-if="$route.path == '/order'" key="order">
                <?php require_once ZIB_ROOT_PATH . '/zibpay/page/template/order.php'; ?>
            </div>
            <div v-if="$route.path == '/shipping' && config.shop_s" key="shipping">
                <?php require_once ZIB_ROOT_PATH . '/zibpay/page/template/shipping.php'; ?>
            </div>
            <div v-if="$route.path == '/after-sale' && config.shop_s" key="after-sale">
                <?php require_once ZIB_ROOT_PATH . '/zibpay/page/template/after-sale.php'; ?>
            </div>
            <div v-else key="dashboard">
                <!---默认仪表盘-->
                <?php require_once ZIB_ROOT_PATH . '/zibpay/page/template/dashboard.php'; ?>
            </div>
        </transition>
    </div>
    <?php require_once ZIB_ROOT_PATH . '/zibpay/page/template/after-sale-dialog.php'; ?>
    <?php require_once ZIB_ROOT_PATH . '/zibpay/page/template/shipping-dialog.php'; ?>
    <?php require_once ZIB_ROOT_PATH . '/zibpay/page/template/order-dialog.php'; ?>
    <?php require ZIB_ROOT_PATH . '/zibpay/page/template/footer.php'; ?>
</div>
