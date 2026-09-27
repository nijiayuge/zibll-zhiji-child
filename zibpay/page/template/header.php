<?php
/*
 * @Author       : Qinver
 * @Url          : zibll.com
 * @Date         : 2025-07-18 22:12:17
 * @LastEditTime : 2025-08-27 21:09:24
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

$vue_data = array(
    'menu_data' => array(
        'order_id' => '',
    ),
);

zibpay_admin_page_vue_data_filter($vue_data);
?>

<div class="mb10">
    <el-menu
        :default-active="$route.path"
        class="menu-tabs"
        mode="horizontal"
        @select="menuGo">
        <el-menu-item index="/"><?php echo esc_html__('统计', 'zib_language'); ?></el-menu-item>
        <el-menu-item index="/order"><?php echo esc_html__('订单', 'zib_language'); ?></el-menu-item>
        <el-menu-item index="/shipping" v-if="config.shop_s"><?php echo esc_html__('物流', 'zib_language'); ?></el-menu-item>
        <el-menu-item index="/after-sale" v-if="config.shop_s"><?php echo esc_html__('售后', 'zib_language'); ?></el-menu-item>
    </el-menu>

</div>
